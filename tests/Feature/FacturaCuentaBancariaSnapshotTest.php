<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Cliente;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaCuentaBancariaSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function setUpTenant(): array
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $user, $cliente];
    }

    private function lineas(): array
    {
        return [
            ['concepto' => 'Consultoría', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
        ];
    }

    public function test_transferencia_con_cuenta_compone_el_snapshot(): void
    {
        [$tenant, $user, $cliente] = $this->setUpTenant();
        $banco = Banco::create(['tenant_id' => $tenant->id, 'nombre' => 'Banco Test '.uniqid()]);
        $cuenta = CuentaBancaria::factory()->create([
            'tenant_id' => $tenant->id,
            'banco_id' => $banco->id,
            'iban' => 'ES9121000418450200051332',
            'titular' => 'ACME SL',
        ]);

        $this->loginAs($user);

        $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'cuenta_bancaria_id' => $cuenta->id,
            'lineas' => $this->lineas(),
        ])->assertRedirect();

        $factura = Factura::first();
        $this->assertSame($cuenta->id, $factura->cuenta_bancaria_id);
        $this->assertSame($banco->nombre, $factura->cuenta_bancaria_banco);
        $this->assertSame('ES9121000418450200051332', $factura->cuenta_bancaria_iban);
        $this->assertSame('ACME SL', $factura->cuenta_bancaria_titular);
    }

    public function test_editar_la_cuenta_no_altera_la_factura_ya_creada(): void
    {
        [$tenant, $user, $cliente] = $this->setUpTenant();
        $cuenta = CuentaBancaria::factory()->create([
            'tenant_id' => $tenant->id,
            'iban' => 'ES9121000418450200051332',
            'titular' => 'Titular Original',
        ]);

        $this->loginAs($user);

        $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'cuenta_bancaria_id' => $cuenta->id,
            'lineas' => $this->lineas(),
        ])->assertRedirect();

        $factura = Factura::first();
        $ibanCongelado = $factura->cuenta_bancaria_iban;

        // Editar la cuenta origen no debe cambiar el snapshot de la factura.
        $this->putJson("/cuentas-bancarias/{$cuenta->id}", [
            'banco_id' => $cuenta->banco_id,
            'alias' => $cuenta->alias,
            'iban' => 'ES7921000813610123456789',
            'titular' => 'Titular Cambiado',
        ])->assertOk();

        $factura->refresh();
        $this->assertSame($ibanCongelado, $factura->cuenta_bancaria_iban);
        $this->assertSame('Titular Original', $factura->cuenta_bancaria_titular);
    }

    public function test_forma_pago_distinta_de_transferencia_deja_los_campos_null(): void
    {
        [$tenant, $user, $cliente] = $this->setUpTenant();
        $cuenta = CuentaBancaria::factory()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($user);

        $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'efectivo',
            'cuenta_bancaria_id' => $cuenta->id,
            'lineas' => $this->lineas(),
        ])->assertRedirect();

        $factura = Factura::first();
        $this->assertNull($factura->cuenta_bancaria_id);
        $this->assertNull($factura->cuenta_bancaria_banco);
        $this->assertNull($factura->cuenta_bancaria_iban);
        $this->assertNull($factura->cuenta_bancaria_titular);
    }
}
