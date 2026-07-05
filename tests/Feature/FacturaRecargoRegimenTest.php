<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaImpuesto;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaRecargoRegimenTest extends TestCase
{
    use RefreshDatabase;

    private function crearFacturaConClienteEnRecargo(Tenant $tenant): Factura
    {
        Serie::factory()->create(['tenant_id' => $tenant->id, 'codigo' => 'F', 'formato' => '{serie}-{anio}-{numero:0000}']);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => '12345678Z',
            'nombre' => 'Cliente en recargo',
            'direccion' => 'Calle Falsa 123',
            'aplica_recargo_equivalencia' => true,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $tipoImpositivo = $tenant->regimen_impositivo->value === 'iva' ? 21 : 7;

        $response = $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [[
                'concepto' => 'Servicio de prueba',
                'cantidad' => 1,
                'precio_unitario' => 100,
                'tipo_impositivo' => $tipoImpositivo,
            ]],
        ]);

        $response->assertRedirect(route('facturas.index'));

        return Factura::where('tenant_id', $tenant->id)->latest('id')->first();
    }

    public function test_tenant_igic_con_cliente_en_recargo_no_genera_recargo(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'igic']);

        $factura = $this->crearFacturaConClienteEnRecargo($tenant);

        $recargo = FacturaImpuesto::where('factura_id', $factura->id)->where('tipo_impuesto', 'recargo')->first();
        $this->assertNull($recargo);
    }

    public function test_tenant_iva_con_cliente_en_recargo_si_genera_recargo(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);

        $factura = $this->crearFacturaConClienteEnRecargo($tenant);

        $recargo = FacturaImpuesto::where('factura_id', $factura->id)->where('tipo_impuesto', 'recargo')->first();
        $this->assertNotNull($recargo);
        $this->assertEquals(5.2, (float) $recargo->porcentaje);
    }
}
