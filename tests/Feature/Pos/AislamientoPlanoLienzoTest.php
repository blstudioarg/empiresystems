<?php

namespace Tests\Feature\Pos;

use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Feature 042 — Principio I aplicado al lienzo de la zona.
 *
 * El lienzo es un dato de negocio nuevo (medidas + recorte de la sala) y por tanto tiene que estar
 * tan aislado como la zona que lo contiene: ni se lee en el payload de otro tenant, ni se escribe
 * por identificador directo. El "tampoco por id" es la parte que importa: es exactamente lo que un
 * route binding implícito rompería sin que ningún test de listado se enterase.
 */
class AislamientoPlanoLienzoTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function tenantConConfiguracion(string $nombreRol): array
    {
        $tenant = Tenant::factory()->create();
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        $rol = $this->crearRol($tenant, $nombreRol, ['ver-configuracion', 'ver-pos-sala']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    public function test_el_payload_de_la_sala_no_expone_el_lienzo_de_una_zona_de_otro_tenant(): void
    {
        $this->sembrarPermisos();
        [$tenantA, $userA] = $this->tenantConConfiguracion('Configuración A');
        [$tenantB] = $this->tenantConConfiguracion('Configuración B');

        PosZona::factory()->create([
            'tenant_id' => $tenantA->id, 'nombre' => 'Comedor A',
            'columnas' => 8, 'filas' => 6, 'celdas_inactivas' => [],
        ]);

        PosZona::factory()->create([
            'tenant_id' => $tenantB->id, 'nombre' => 'Terraza B',
            'columnas' => 20, 'filas' => 16, 'celdas_inactivas' => ['0-19', '1-19'],
        ]);

        $this->loginAs($userA);
        $zonas = $this->getJson('/pos/sala')->assertOk()->json('zonas');

        $this->assertSame(['Comedor A'], array_column($zonas, 'nombre'));
        $this->assertSame([8], array_column($zonas, 'columnas'));
        $this->assertSame([6], array_column($zonas, 'filas'));
        $this->assertSame([[]], array_column($zonas, 'celdas_inactivas'));
    }

    public function test_no_se_puede_guardar_el_lienzo_de_una_zona_de_otro_tenant_por_id_directo(): void
    {
        $this->sembrarPermisos();
        [, $userA] = $this->tenantConConfiguracion('Configuración A');
        [$tenantB] = $this->tenantConConfiguracion('Configuración B');

        $zonaB = PosZona::factory()->create([
            'tenant_id' => $tenantB->id, 'version' => 1,
            'columnas' => 8, 'filas' => 6, 'celdas_inactivas' => [],
        ]);
        $mesaB = PosMesa::factory()->create([
            'tenant_id' => $tenantB->id, 'zona_id' => $zonaB->id, 'fila' => 0, 'columna' => 0,
        ]);

        $this->loginAs($userA);

        $respuesta = $this->putJson("/pos/sala/zonas/{$zonaB->id}/plano", [
            'version' => 1,
            'columnas' => 24,
            'filas' => 24,
            'celdas_inactivas' => ['5-5'],
            'mesas' => [[
                'id' => $mesaB->id, 'fila' => 0, 'columna' => 0,
                'ancho_celdas' => 1, 'alto_celdas' => 1, 'forma' => 'cuadrada',
            ]],
        ]);

        $this->assertContains($respuesta->status(), [403, 404], 'El lienzo de otro tenant nunca debe poder guardarse.');

        // Ni una sola escritura: ni las medidas, ni el recorte, ni el bump de `version`.
        $this->assertDatabaseHas('pos_zonas', [
            'id' => $zonaB->id, 'columnas' => 8, 'filas' => 6, 'version' => 1,
        ]);
        $this->assertSame([], $zonaB->fresh()->celdasInactivas());
    }
}
