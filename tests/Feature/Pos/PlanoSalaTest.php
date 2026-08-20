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
 * Feature 039 — guardado del plano de sala: aislamiento de tenant (Principio I), bloqueo optimista
 * por zona (D5) y validación de colisión/rango (D4) del endpoint
 * `PUT /pos/sala/zonas/{zona}/plano`.
 */
class PlanoSalaTest extends TestCase
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

    private function mesaPayload(PosMesa $mesa, int $fila, int $columna, string $forma = 'cuadrada'): array
    {
        return [
            'id' => $mesa->id, 'fila' => $fila, 'columna' => $columna,
            'ancho_celdas' => 1, 'alto_celdas' => 1, 'forma' => $forma,
        ];
    }

    public function test_guarda_posiciones_validas_de_una_zona_y_aumenta_version(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa1 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);
        $mesa2 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 1]);
        $mesa3 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 2]);

        $this->loginAs($user);

        $response = $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [
                $this->mesaPayload($mesa1, 2, 3),
                $this->mesaPayload($mesa2, 2, 4),
                $this->mesaPayload($mesa3, 2, 5, 'redonda'),
            ],
        ]);

        $response->assertOk()->assertJson(['version' => 2]);

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa1->id, 'fila' => 2, 'columna' => 3]);
        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa3->id, 'forma' => 'redonda']);
        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'version' => 2]);
    }

    public function test_aislamiento_de_tenant_al_guardar_el_plano_de_una_zona_ajena(): void
    {
        $this->sembrarPermisos();
        [, $userA] = $this->tenantConConfiguracion('Configuración A');
        [$tenantB] = $this->tenantConConfiguracion('Configuración B');

        $zonaB = PosZona::factory()->create(['tenant_id' => $tenantB->id, 'version' => 1]);
        $mesaB = PosMesa::factory()->create(['tenant_id' => $tenantB->id, 'zona_id' => $zonaB->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($userA);

        $this->putJson("/pos/sala/zonas/{$zonaB->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesaB, 1, 1)],
        ])->assertNotFound();

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesaB->id, 'fila' => 0, 'columna' => 0]);
    }

    public function test_rechaza_payload_con_dos_mesas_en_la_misma_celda(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa1 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);
        $mesa2 = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 1]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [
                $this->mesaPayload($mesa1, 3, 3),
                $this->mesaPayload($mesa2, 3, 3),
            ],
        ])->assertStatus(422);

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa1->id, 'fila' => 0, 'columna' => 0]);
        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa2->id, 'fila' => 0, 'columna' => 1]);
        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'version' => 1]);
    }

    public function test_rechaza_fila_o_columna_fuera_de_rango(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesa, 6, 0)],
        ])->assertStatus(422);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesa, 0, 8)],
        ])->assertStatus(422);
    }

    public function test_guardar_el_plano_de_una_zona_no_afecta_a_otra_zona_del_mismo_tenant(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zonaA = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesaA = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zonaA->id, 'fila' => 0, 'columna' => 0]);

        $zonaB = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesaB = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zonaB->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zonaA->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesaA, 4, 4)],
        ])->assertOk();

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesaB->id, 'fila' => 0, 'columna' => 0]);
        $this->assertDatabaseHas('pos_zonas', ['id' => $zonaB->id, 'version' => 1]);
    }

    public function test_409_en_guardado_con_version_desactualizado(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion('Configuración');

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesa, 1, 1)],
        ])->assertOk()->assertJson(['version' => 2]);

        // Segundo guardado con el `version` original (1), ya desactualizado.
        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", [
            'version' => 1,
            'mesas' => [$this->mesaPayload($mesa, 2, 2)],
        ])->assertStatus(409);

        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa->id, 'fila' => 1, 'columna' => 1]);
        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'version' => 2]);
    }
}
