<?php

namespace Tests\Feature;

use App\Enums\EstadoCobro;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoSaldoEstadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_estado_y_saldo_derivados_de_los_pagos_vigentes(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'total' => 100.00,
        ]);

        $this->assertSame(EstadoCobro::Pendiente, $factura->estadoCobro());
        $this->assertSame(100.0, $factura->saldoPendiente());

        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id, 'importe' => 30.00]);
        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id, 'importe' => 20.00]);

        $factura->refresh();
        $this->assertSame(50.0, $factura->saldoPendiente());
        $this->assertSame(EstadoCobro::Parcial, $factura->estadoCobro());
        $this->assertCount(2, $factura->pagos);
    }

    public function test_saldo_exacto_sin_redondeo_con_cuotas_decimales(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'total' => 100.00,
        ]);

        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id, 'importe' => 33.33]);
        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id, 'importe' => 33.33]);
        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id, 'importe' => 33.34]);

        $factura->refresh();
        $this->assertSame(0.0, $factura->saldoPendiente());
        $this->assertSame(EstadoCobro::Cobrada, $factura->estadoCobro());
    }

    public function test_listado_de_facturas_incluye_estado_de_cobro_y_saldo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'total' => 100.00,
        ]);
        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id, 'importe' => 40.00]);

        $this->loginAs($user);

        $response = $this->getJson('/facturas');

        $response->assertOk();
        $fila = collect($response->json('data'))->firstWhere('id', $factura->id);
        $this->assertSame('parcial', $fila['estado_cobro']);
        $this->assertSame('60.00', $fila['saldo_pendiente']);
        $this->assertSame('40.00', $fila['monto_cobrado']);
    }
}
