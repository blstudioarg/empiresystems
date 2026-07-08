<?php

namespace Tests\Feature\Presupuestos;

use App\Enums\EstadoFactura;
use App\Enums\EstadoPresupuesto;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresupuestoConversionFacturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_convertir_presupuesto_aceptado_crea_factura_borrador_con_lineas_congeladas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $presupuesto = Presupuesto::factory()->aceptado()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'total' => 121,
        ]);
        PresupuestoLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'presupuesto_id' => $presupuesto->id,
            'concepto' => 'Servicio congelado',
            'base' => 100,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 21,
        ]);

        $this->loginAs($user);

        $response = $this->post("/presupuestos/{$presupuesto->id}/convertir");

        $response->assertRedirect();
        $presupuesto->refresh();
        $this->assertEquals(EstadoPresupuesto::Facturado, $presupuesto->estado);
        $this->assertNotNull($presupuesto->convertido_a_factura_id);

        $factura = $presupuesto->facturaConvertida;
        $this->assertNotNull($factura);
        $this->assertEquals(EstadoFactura::Borrador, $factura->estado);
        $this->assertNull($factura->numero);
        $this->assertEquals(1, $factura->lineas()->count());
        $this->assertEquals('Servicio congelado', $factura->lineas()->first()->concepto);
        $this->assertEquals(121.0, (float) $factura->total);
    }

    public function test_no_permite_convertir_un_presupuesto_ya_facturado_dos_veces(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $presupuesto = Presupuesto::factory()->facturado()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
        ]);

        $this->loginAs($user);

        $response = $this->postJson("/presupuestos/{$presupuesto->id}/convertir");

        $response->assertStatus(422);
    }

    public function test_no_permite_convertir_un_presupuesto_en_borrador(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $presupuesto = Presupuesto::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
        ]);

        $this->loginAs($user);

        $response = $this->postJson("/presupuestos/{$presupuesto->id}/convertir");

        $response->assertStatus(422);
    }
}
