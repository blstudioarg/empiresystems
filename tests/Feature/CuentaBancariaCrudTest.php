<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\CuentaBancaria;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuentaBancariaCrudTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'ES9121000418450200051332';

    private const IBAN_ALT = 'ES7921000813610123456789';

    private function login(Tenant $tenant): void
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);
    }

    public function test_update_cambia_los_datos_de_la_cuenta(): void
    {
        $tenant = Tenant::factory()->create();
        $this->login($tenant);
        $banco = Banco::create(['tenant_id' => $tenant->id, 'nombre' => 'Banco Nuevo '.uniqid()]);
        $cuenta = CuentaBancaria::factory()->create(['tenant_id' => $tenant->id]);

        $this->putJson("/cuentas-bancarias/{$cuenta->id}", [
            'banco_id' => $banco->id,
            'alias' => 'Editada',
            'iban' => self::IBAN_ALT,
            'titular' => 'Nuevo Titular',
        ])->assertOk();

        $this->assertDatabaseHas('cuentas_bancarias', [
            'id' => $cuenta->id,
            'alias' => 'Editada',
            'banco_id' => $banco->id,
            'iban' => self::IBAN_ALT,
            'titular' => 'Nuevo Titular',
        ]);
    }

    public function test_destroy_desactiva_y_hace_soft_delete(): void
    {
        $tenant = Tenant::factory()->create();
        $this->login($tenant);
        $cuenta = CuentaBancaria::factory()->create(['tenant_id' => $tenant->id]);

        $this->deleteJson("/cuentas-bancarias/{$cuenta->id}")->assertOk();

        $cuenta = CuentaBancaria::withTrashed()->find($cuenta->id);
        $this->assertFalse($cuenta->activa);
        $this->assertNotNull($cuenta->deleted_at);
    }

    public function test_restore_reactiva_la_cuenta(): void
    {
        $tenant = Tenant::factory()->create();
        $this->login($tenant);
        $cuenta = CuentaBancaria::factory()->inactiva()->create(['tenant_id' => $tenant->id]);
        $cuenta->delete();

        $this->post("/cuentas-bancarias/{$cuenta->id}/restaurar")->assertRedirect();

        $cuenta = CuentaBancaria::find($cuenta->id);
        $this->assertNotNull($cuenta);
        $this->assertTrue($cuenta->activa);
        $this->assertNull($cuenta->deleted_at);
    }

    public function test_no_se_puede_reactivar_cuenta_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $cuentaB = CuentaBancaria::factory()->inactiva()->create(['tenant_id' => $tenantB->id]);
        $cuentaB->delete();

        $this->login($tenantA);

        $this->post("/cuentas-bancarias/{$cuentaB->id}/restaurar")->assertNotFound();

        // Consulta directa a la BD (sin scope de tenant) para confirmar que sigue de baja.
        $fila = \DB::table('cuentas_bancarias')->where('id', $cuentaB->id)->first();
        $this->assertNotNull($fila->deleted_at);
        $this->assertSame(0, (int) $fila->activa);
    }
}
