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

class FacturaEmisionIgicTest extends TestCase
{
    use RefreshDatabase;

    private function crearFacturaBorrador(Tenant $tenant, User $user, array $lineas): Factura
    {
        Serie::factory()->create(['tenant_id' => $tenant->id, 'codigo' => 'F', 'formato' => '{serie}-{anio}-{numero:0000}']);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => '12345678Z',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);

        $this->loginAs($user);

        $response = $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => $lineas,
        ]);

        $response->assertRedirect(route('facturas.index'));

        return Factura::where('tenant_id', $tenant->id)->latest('id')->first();
    }

    public function test_emitir_factura_igic_calcula_base_cuota_y_total_correctos(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'igic']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $factura = $this->crearFacturaBorrador($tenant, $user, [[
            'concepto' => 'Servicio de prueba',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'tipo_impositivo' => 7,
        ]]);

        $this->assertNotNull($factura);
        $this->assertEquals(100, (float) $factura->base_total);
        $this->assertEquals(107, (float) $factura->total);

        $impuesto = FacturaImpuesto::where('factura_id', $factura->id)->first();
        $this->assertNotNull($impuesto);
        $this->assertEquals('igic', $impuesto->tipo_impuesto->value);
        $this->assertEquals(7, (float) $impuesto->porcentaje);
        $this->assertEquals(7, (float) $impuesto->cuota);
    }

    public function test_regimen_congelado_en_factura_es_el_del_tenant_activo_y_no_de_otro_tenant(): void
    {
        $tenantIgic = Tenant::factory()->create(['regimen_impositivo' => 'igic']);
        $userIgic = User::factory()->create(['tenant_id' => $tenantIgic->id, 'password' => bcrypt('secret123')]);

        $tenantIva = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        Serie::factory()->create(['tenant_id' => $tenantIva->id, 'codigo' => 'F', 'formato' => '{serie}-{anio}-{numero:0000}']);

        $factura = $this->crearFacturaBorrador($tenantIgic, $userIgic, [[
            'concepto' => 'Servicio de prueba',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'tipo_impositivo' => 7,
        ]]);

        $this->assertEquals('igic', $factura->regimen_impositivo->value);
        $this->assertNotEquals($tenantIva->regimen_impositivo->value, $factura->regimen_impositivo->value);
    }

    public function test_emitir_factura_ipsi_admite_tipo_libre_y_sin_recargo(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'ipsi']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $factura = $this->crearFacturaBorrador($tenant, $user, [[
            'concepto' => 'Servicio de prueba',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'tipo_impositivo' => 4,
        ]]);

        $this->assertNotNull($factura);
        $impuesto = FacturaImpuesto::where('factura_id', $factura->id)->first();
        $this->assertEquals('ipsi', $impuesto->tipo_impuesto->value);
        $this->assertEquals(4, (float) $impuesto->porcentaje);

        $recargo = FacturaImpuesto::where('factura_id', $factura->id)->where('tipo_impuesto', 'recargo')->first();
        $this->assertNull($recargo);
    }
}
