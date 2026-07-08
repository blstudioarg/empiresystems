<?php

namespace Tests\Feature\Presupuestos;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Presupuesto;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PresupuestoCalculoIgualFacturaTest extends TestCase
{
    use RefreshDatabase;

    private function lineas(): array
    {
        return [
            ['concepto' => 'Servicio A', 'cantidad' => 2, 'precio_unitario' => 50, 'tipo_impositivo' => 21],
            ['concepto' => 'Servicio B', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 10],
        ];
    }

    #[DataProvider('regimenes')]
    public function test_totales_de_presupuesto_igualan_a_los_de_una_factura_equivalente(string $regimen): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => $regimen]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'aplica_recargo_equivalencia' => false]);

        $this->loginAs($user);

        $respuestaPresupuesto = $this->post('/presupuestos', [
            'cliente_id' => $cliente->id,
            'fecha_emision' => now()->toDateString(),
            'lineas' => $this->lineas(),
        ]);
        $respuestaPresupuesto->assertSessionHasNoErrors();

        $respuestaFactura = $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => $this->lineas(),
        ]);
        $respuestaFactura->assertSessionHasNoErrors();

        $presupuesto = Presupuesto::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $factura = Factura::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $this->assertEquals((float) $factura->base_total, (float) $presupuesto->base_total);
        $this->assertEquals((float) $factura->cuota_impuesto_total, (float) $presupuesto->cuota_impuesto_total);
        $this->assertEquals((float) $factura->cuota_recargo_total, (float) $presupuesto->cuota_recargo_total);
        $this->assertEquals((float) $factura->total, (float) $presupuesto->total);
    }

    public static function regimenes(): array
    {
        return [
            'IVA' => ['iva'],
            'IGIC' => ['igic'],
            'IPSI' => ['ipsi'],
        ];
    }
}
