<?php

namespace Tests\Feature\Configuracion;

use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Feature 039, FR-014/FR-015: una mesa nueva se crea en una celda libre por defecto, y la celda de
 * una mesa eliminada queda libre para futuros reacomodos.
 */
class PosMesaControllerTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: \App\Models\User} */
    private function tenantConConfiguracion(): array
    {
        $tenant = Tenant::factory()->create();
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        $rol = $this->crearRol($tenant, 'Configuración', ['ver-configuracion']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    public function test_una_mesa_nueva_ocupa_la_celda_que_dejo_libre_una_mesa_eliminada(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();
        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id]);

        $mesaVieja = PosMesa::factory()->create([
            'tenant_id' => $tenant->id,
            'zona_id' => $zona->id,
            'fila' => 0,
            'columna' => 0,
        ]);

        $this->loginAs($user);

        $this->deleteJson("/configuracion/pos/mesas/{$mesaVieja->id}")->assertOk();

        $response = $this->postJson('/configuracion/pos/mesas', [
            'zona_id' => $zona->id,
            'nombre' => 'Mesa nueva',
        ])->assertCreated();

        $this->assertDatabaseHas('pos_mesas', [
            'id' => $response->json('id'),
            'fila' => 0,
            'columna' => 0,
            'forma' => 'cuadrada',
            'tamano' => 'mediana',
        ]);
    }

    public function test_una_mesa_nueva_ocupa_la_primera_celda_libre_de_la_zona(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();
        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id]);

        PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);
        PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 1]);

        $this->loginAs($user);

        $response = $this->postJson('/configuracion/pos/mesas', [
            'zona_id' => $zona->id,
            'nombre' => 'Mesa nueva',
        ])->assertCreated();

        $this->assertDatabaseHas('pos_mesas', [
            'id' => $response->json('id'),
            'fila' => 0,
            'columna' => 2,
        ]);
    }
}
