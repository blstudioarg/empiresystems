<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuentaBancariaIbanValidationTest extends TestCase
{
    use RefreshDatabase;

    private function login(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        return $tenant;
    }

    public function test_iban_valido_se_acepta_y_se_normaliza(): void
    {
        $tenant = $this->login();
        $banco = Banco::create(['tenant_id' => $tenant->id, 'nombre' => 'Banco Test '.uniqid()]);

        $this->postJson('/cuentas-bancarias', [
            'banco_id' => $banco->id,
            'alias' => 'Principal',
            'iban' => 'es91 2100 0418 4502 0005 1332',
            'titular' => 'ACME SL',
        ])->assertCreated();

        $this->assertDatabaseHas('cuentas_bancarias', [
            'iban' => 'ES9121000418450200051332',
        ]);
    }

    public function test_iban_invalido_devuelve_422_en_el_campo_iban(): void
    {
        $tenant = $this->login();
        $banco = Banco::create(['tenant_id' => $tenant->id, 'nombre' => 'Banco Test '.uniqid()]);

        $this->postJson('/cuentas-bancarias', [
            'banco_id' => $banco->id,
            'alias' => 'Principal',
            'iban' => 'ES0000000000000000000000',
            'titular' => 'ACME SL',
        ])->assertStatus(422)->assertJsonValidationErrors('iban');
    }
}
