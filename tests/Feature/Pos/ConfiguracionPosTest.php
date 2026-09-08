<?php

namespace Tests\Feature\Pos;

use App\Models\PosCuenta;
use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * FR-006: **no se puede apagar el módulo con cuentas abiertas.** Si se pudiera, quedarían mesas
 * ocupadas con consumo real y sin ninguna pantalla desde la que cobrarlas.
 */
class ConfiguracionPosTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function tenantAdmin(): array
    {
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Admin config', ['ver-configuracion']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    /** @return array<string, mixed> */
    private function payload(array $override = []): array
    {
        return array_merge([
            'hosteleria_activo' => 1,
            'opciones_activo' => 0,
            'cobro_dividido_activo' => 0,
            'suplemento_zona_activo' => 0,
            'mesa_olvidada_min' => 45,
        ], $override);
    }

    public function test_apagar_el_modulo_con_cuentas_abiertas_responde_422_indicando_cuantas_hay(): void
    {
        $this->sembrarPermisos();
        [$tenant, $usuario] = $this->tenantAdmin();

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id]);
        PosCuenta::factory()->count(2)->create(['tenant_id' => $tenant->id, 'mesa_id' => $mesa->id]);

        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', $this->payload(['hosteleria_activo' => 0]))
            ->assertStatus(422)
            ->assertJson(['cuentas_abiertas' => 2])
            ->assertJsonFragment(['message' => 'Hay 2 cuentas abiertas. Ciérralas o anúlalas antes de desactivar el módulo.']);

        // Y sigue encendido: el intento fallido no deja el tenant a medias.
        $this->assertTrue(ConfigPos::hosteleriaActivo($tenant->id));
    }

    public function test_sin_cuentas_abiertas_el_modulo_se_puede_apagar(): void
    {
        $this->sembrarPermisos();
        [$tenant, $usuario] = $this->tenantAdmin();

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);

        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', $this->payload(['hosteleria_activo' => 0]))->assertOk();

        $this->assertFalse(ConfigPos::hosteleriaActivo($tenant->id));
    }

    public function test_una_cuenta_ya_cerrada_no_bloquea_el_apagado(): void
    {
        $this->sembrarPermisos();
        [$tenant, $usuario] = $this->tenantAdmin();

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        PosCuenta::factory()->cerrada()->create(['tenant_id' => $tenant->id]);
        PosCuenta::factory()->anulada()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', $this->payload(['hosteleria_activo' => 0]))->assertOk();
    }

    public function test_el_umbral_de_mesa_olvidada_se_guarda(): void
    {
        $this->sembrarPermisos();
        [$tenant, $usuario] = $this->tenantAdmin();

        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', $this->payload(['mesa_olvidada_min' => 90]))->assertOk();

        $this->assertSame(90, ConfigPos::mesaOlvidadaMin($tenant->id));
    }
}
