<?php

namespace Tests\Feature\Pos;

use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Principio I + FR-054: ni zona, ni mesa, ni cuenta del tenant A son accesibles desde el B,
 * **tampoco por identificador directo**. El "tampoco por id" es la parte que importa: es lo que
 * un route binding implícito rompería sin que ningún test de listado se enterase.
 */
class AislamientoMesasTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: \App\Models\User} */
    private function tenantConSala(string $nombreRol): array
    {
        $tenant = Tenant::factory()->create();
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        $rol = $this->crearRol($tenant, $nombreRol, ['ver-pos-sala', 'ver-pos-crear']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    public function test_la_sala_de_un_tenant_no_muestra_zonas_ni_mesas_de_otro(): void
    {
        $this->sembrarPermisos();
        [$tenantA, $userA] = $this->tenantConSala('Sala A');
        [$tenantB] = $this->tenantConSala('Sala B');

        $zonaA = PosZona::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Comedor A']);
        PosMesa::factory()->create(['tenant_id' => $tenantA->id, 'zona_id' => $zonaA->id, 'nombre' => 'Mesa A1']);

        $zonaB = PosZona::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Comedor B']);
        PosMesa::factory()->create(['tenant_id' => $tenantB->id, 'zona_id' => $zonaB->id, 'nombre' => 'Mesa B1']);

        $this->loginAs($userA);
        $json = $this->getJson('/pos/sala')->assertOk()->json();

        $this->assertSame(['Comedor A'], array_column($json['zonas'], 'nombre'));
        $this->assertSame(['Mesa A1'], array_column($json['mesas'], 'nombre'));
    }

    public function test_una_cuenta_de_otro_tenant_no_es_accesible_por_id_directo(): void
    {
        $this->sembrarPermisos();
        [$tenantA, $userA] = $this->tenantConSala('Sala A');
        [$tenantB] = $this->tenantConSala('Sala B');

        $cuentaB = PosCuenta::factory()->create(['tenant_id' => $tenantB->id]);
        PosCuentaLinea::factory()->create(['tenant_id' => $tenantB->id, 'cuenta_id' => $cuentaB->id]);

        $this->loginAs($userA);

        $this->getJson("/pos/cuentas/{$cuentaB->id}")->assertNotFound();
        $this->putJson("/pos/cuentas/{$cuentaB->id}", ['version' => 1, 'lineas' => []])->assertNotFound();
        $this->postJson("/pos/cuentas/{$cuentaB->id}/anular")->assertNotFound();
        $this->postJson("/pos/cuentas/{$cuentaB->id}/cobrar", ['version' => 1, 'pagos' => []])->assertNotFound();
    }

    public function test_no_se_puede_abrir_una_cuenta_sobre_una_mesa_de_otro_tenant(): void
    {
        $this->sembrarPermisos();
        [, $userA] = $this->tenantConSala('Sala A');
        [$tenantB] = $this->tenantConSala('Sala B');

        $zonaB = PosZona::factory()->create(['tenant_id' => $tenantB->id]);
        $mesaB = PosMesa::factory()->create(['tenant_id' => $tenantB->id, 'zona_id' => $zonaB->id]);

        $this->loginAs($userA);

        $this->postJson('/pos/cuentas', ['mesa_id' => $mesaB->id])->assertStatus(422);
        $this->assertDatabaseCount('pos_cuentas', 0);
    }

    /**
     * Feature 040 — la geometría de las mesas (`ancho_celdas`/`alto_celdas`) no se escapa del
     * tenant: ni se lee desde la sala del otro, ni se puede redimensionar por id directo.
     */
    public function test_no_se_puede_redimensionar_ni_leer_la_geometria_de_una_mesa_de_otro_tenant(): void
    {
        $this->sembrarPermisos();
        [$tenantA] = $this->tenantConSala('Sala A');
        [$tenantB] = $this->tenantConSala('Sala B');

        // El editor de plano exige además `ver-configuracion`: sin él la respuesta sería un 403 que
        // taparía lo que este test quiere comprobar (que la zona ajena ni siquiera existe para A).
        $rolA = $this->crearRol($tenantA, 'Encargado A', ['ver-configuracion', 'ver-pos-sala', 'ver-pos-crear']);
        $userA = $this->usuarioConRol($tenantA, $rolA);

        $zonaB = PosZona::factory()->create(['tenant_id' => $tenantB->id, 'version' => 1]);
        $mesaB = PosMesa::factory()->create([
            'tenant_id' => $tenantB->id, 'zona_id' => $zonaB->id,
            'fila' => 0, 'columna' => 0, 'ancho_celdas' => 1, 'alto_celdas' => 1,
        ]);

        $this->loginAs($userA);

        $this->putJson("/pos/sala/zonas/{$zonaB->id}/plano", [
            'version' => 1,
            'mesas' => [[
                'id' => $mesaB->id, 'fila' => 0, 'columna' => 0,
                'ancho_celdas' => 3, 'alto_celdas' => 2, 'forma' => 'barra',
            ]],
        ])->assertNotFound();

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesaB->id, 'ancho_celdas' => 1, 'alto_celdas' => 1]);

        // Y la sala del tenant A no expone ninguna mesa del B, con geometría o sin ella.
        $mesas = $this->getJson('/pos/sala')->assertOk()->json('mesas');
        $this->assertSame([], array_values(array_filter($mesas, fn ($m) => $m['id'] === $mesaB->id)));
    }
}
