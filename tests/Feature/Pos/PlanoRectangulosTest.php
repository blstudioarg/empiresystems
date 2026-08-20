<?php

namespace Tests\Feature\Pos;

use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Feature 040 — invariantes de geometría del plano (G1/G2/G3 de data-model.md) verificados en el
 * servidor, que es la barrera real (Principio III: el cliente nunca es la única barrera).
 *
 * El caso que más importa es el último: tras un 422 **ningún** dato cambió (FR-013/SC-004). Un
 * guardado parcial dejaría el plano en un estado que la propia validación rechazaría al recargarlo.
 */
class PlanoRectangulosTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: \App\Models\User} */
    private function tenantConConfiguracion(string $nombreRol): array
    {
        $tenant = Tenant::factory()->create();
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        $rol = $this->crearRol($tenant, $nombreRol, ['ver-configuracion', 'ver-pos-sala']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    /** @return array<string, mixed> */
    private function mesaPayload(PosMesa $mesa, int $fila, int $columna, int $ancho = 1, int $alto = 1, string $forma = 'cuadrada'): array
    {
        return [
            'id' => $mesa->id,
            'fila' => $fila,
            'columna' => $columna,
            'ancho_celdas' => $ancho,
            'alto_celdas' => $alto,
            'forma' => $forma,
        ];
    }

    public function test_guarda_una_mesa_de_dos_por_una(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesa, 2, 3, 2, 1, 'cuadrada')],
        ])->assertOk()->assertJson(['version' => 2]);

        $this->assertDatabaseHas('pos_mesas', [
            'id' => $mesa->id, 'fila' => 2, 'columna' => 3, 'ancho_celdas' => 2, 'alto_celdas' => 1,
        ]);
    }

    public function test_rechaza_una_ocupacion_menor_que_una_celda(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesa, 1, 1, 0, 1)],
        ])->assertStatus(422);

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa->id, 'fila' => 0, 'columna' => 0, 'ancho_celdas' => 1]);
    }

    public function test_rechaza_un_rectangulo_que_se_sale_de_la_rejilla(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        // columna + ancho > 8: la celda de origen es válida, el rectángulo no.
        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesa, 0, 7, 2, 1)],
        ])->assertStatus(422);

        // fila + alto > 6.
        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesa, 5, 0, 1, 2)],
        ])->assertStatus(422);

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa->id, 'fila' => 0, 'columna' => 0]);
        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'version' => 1]);
    }

    public function test_rechaza_dos_rectangulos_que_se_solapan_sin_compartir_origen(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa1 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);
        $mesa2 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 1]);

        $this->loginAs($user);

        // Orígenes distintos (1,1) y (1,2), pero la primera ocupa 2 celdas de ancho: se pisan.
        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [
                $this->mesaPayload($mesa1, 1, 1, 2, 1),
                $this->mesaPayload($mesa2, 1, 2, 1, 1),
            ],
        ])->assertStatus(422);
    }

    public function test_un_422_no_aplica_ningun_cambio_parcial(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa1 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);
        $mesa2 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 1]);

        $this->loginAs($user);

        // La primera mesa del payload es válida; la segunda se sale de la rejilla. Ninguna de las
        // dos debe quedar escrita.
        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [
                $this->mesaPayload($mesa1, 4, 4, 2, 2),
                $this->mesaPayload($mesa2, 0, 7, 3, 1),
            ],
        ])->assertStatus(422);

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa1->id, 'fila' => 0, 'columna' => 0, 'ancho_celdas' => 1, 'alto_celdas' => 1]);
        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa2->id, 'fila' => 0, 'columna' => 1, 'ancho_celdas' => 1, 'alto_celdas' => 1]);
        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'version' => 1]);
    }
}
