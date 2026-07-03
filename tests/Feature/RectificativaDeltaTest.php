<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaImpuesto;
use App\Models\FacturaLinea;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RectificativaDeltaTest extends TestCase
{
    use RefreshDatabase;

    private function crearOriginalEmitida(Tenant $tenant, Cliente $cliente, Serie $serieOrdinaria): Factura
    {
        $original = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serieOrdinaria->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => 'Cliente de prueba',
            'cliente_nif' => '12345678Z',
            'cliente_direccion' => 'Calle Falsa 123',
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'cuota_recargo_total' => 0,
            'irpf_cuota' => 0,
            'total' => 121,
        ]);

        FacturaLinea::factory()->for($original)->create([
            'tenant_id' => $tenant->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'base' => 100,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 21,
        ]);

        FacturaImpuesto::factory()->for($original)->create([
            'tenant_id' => $tenant->id,
            'tipo_impuesto' => 'iva',
            'porcentaje' => 21,
            'base_imponible' => 100,
            'cuota' => 21,
        ]);

        return $original;
    }

    private function payloadLinea(float $cantidad, float $precioUnitario, float $tipoImpositivo = 21): array
    {
        return [
            'cliente_id' => null,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Consultoría corregida', 'cantidad' => $cantidad, 'precio_unitario' => $precioUnitario, 'tipo_impositivo' => $tipoImpositivo],
            ],
        ];
    }

    public function test_modalidad_sustitucion_persiste_los_totales_corregidos(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);

        $this->loginAs($user);

        $this->post("/facturas/{$original->id}/rectificar", [
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Precio incorrecto.',
        ]);

        $rectificativa = Factura::where('factura_rectificada_id', $original->id)->first();

        $payload = $this->payloadLinea(1, 150);
        $payload['cliente_id'] = $cliente->id;

        $response = $this->put("/facturas/{$rectificativa->id}", $payload);
        $response->assertRedirect(route('facturas.index'));

        $rectificativa->refresh();
        $this->assertEquals(150, $rectificativa->base_total);
        $this->assertEquals(31.5, $rectificativa->cuota_impuesto_total);
        $this->assertEquals(181.5, $rectificativa->total);
        $this->assertEquals('rectificativa', $rectificativa->tipo->value);
        $this->assertTrue($rectificativa->es_rectificativa);
    }

    public function test_modalidad_diferencias_persiste_el_delta_incluyendo_negativos(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);

        $this->loginAs($user);

        $this->post("/facturas/{$original->id}/rectificar", [
            'tipo_rectificacion' => 'diferencias',
            'motivo_rectificacion' => 'El importe correcto es menor.',
        ]);

        $rectificativa = Factura::where('factura_rectificada_id', $original->id)->first();

        $payload = $this->payloadLinea(1, 60);
        $payload['cliente_id'] = $cliente->id;

        $this->put("/facturas/{$rectificativa->id}", $payload)->assertRedirect(route('facturas.index'));

        $rectificativa->refresh();
        // Corregido: base 60, cuota 12.6, total 72.6. Original: base 100, cuota 21, total 121.
        $this->assertEquals(-40, $rectificativa->base_total);
        $this->assertEquals(-8.4, $rectificativa->cuota_impuesto_total);
        $this->assertEquals(-48.4, $rectificativa->total);
    }

    public function test_delta_cero_es_emitible_cuando_solo_corrige_un_dato_del_receptor(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);

        $this->loginAs($user);

        $this->post("/facturas/{$original->id}/rectificar", [
            'tipo_rectificacion' => 'diferencias',
            'motivo_rectificacion' => 'Corrección del domicilio del cliente.',
        ]);

        $rectificativa = Factura::where('factura_rectificada_id', $original->id)->first();

        $payload = $this->payloadLinea(1, 100);
        $payload['cliente_id'] = $cliente->id;
        $payload['cliente_nombre'] = 'Cliente de prueba';
        $payload['cliente_nif'] = '12345678Z';
        $payload['cliente_direccion'] = 'Nueva dirección 42';

        $this->put("/facturas/{$rectificativa->id}", $payload)->assertRedirect(route('facturas.index'));

        $rectificativa->refresh();
        $this->assertEquals(0, $rectificativa->base_total);
        $this->assertEquals(0, $rectificativa->total);

        $response = $this->post("/facturas/{$rectificativa->id}/emitir");
        $response->assertRedirect(route('facturas.index'));

        $rectificativa->refresh();
        $this->assertEquals('emitida', $rectificativa->estado->value);
    }
}
