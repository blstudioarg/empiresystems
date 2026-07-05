<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\CuentaBancaria;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuentaBancariaTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function ibanValido(): string
    {
        return 'ES9121000418450200051332';
    }

    public function test_index_solo_muestra_cuentas_del_tenant_activo(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $cuentaA = CuentaBancaria::factory()->create(['tenant_id' => $tenantA->id]);
        $cuentaB = CuentaBancaria::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->getJson('/cuentas-bancarias');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($cuentaA->id));
        $this->assertFalse($ids->contains($cuentaB->id));
    }

    public function test_store_crea_la_cuenta_bajo_el_tenant_activo(): void
    {
        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $banco = Banco::create(['tenant_id' => $tenantA->id, 'nombre' => 'Banco Test '.uniqid()]);

        $this->loginAs($userA);

        $this->postJson('/cuentas-bancarias', [
            'banco_id' => $banco->id,
            'alias' => 'Principal',
            'iban' => $this->ibanValido(),
            'titular' => 'ACME SL',
        ])->assertCreated();

        $this->assertDatabaseHas('cuentas_bancarias', [
            'tenant_id' => $tenantA->id,
            'alias' => 'Principal',
        ]);
    }

    public function test_no_se_puede_actualizar_cuenta_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $banco = Banco::create(['tenant_id' => $tenantA->id, 'nombre' => 'Banco Test '.uniqid()]);
        $cuentaB = CuentaBancaria::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->putJson("/cuentas-bancarias/{$cuentaB->id}", [
            'banco_id' => $banco->id,
            'alias' => 'Hackeada',
            'iban' => $this->ibanValido(),
            'titular' => 'Intruso',
        ])->assertNotFound();

        $this->assertDatabaseMissing('cuentas_bancarias', ['id' => $cuentaB->id, 'alias' => 'Hackeada']);
    }

    public function test_no_se_puede_desactivar_cuenta_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $cuentaB = CuentaBancaria::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->deleteJson("/cuentas-bancarias/{$cuentaB->id}")->assertNotFound();

        $this->assertDatabaseHas('cuentas_bancarias', ['id' => $cuentaB->id, 'deleted_at' => null]);
    }
}
