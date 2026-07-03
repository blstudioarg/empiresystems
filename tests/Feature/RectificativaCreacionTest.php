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

class RectificativaCreacionTest extends TestCase
{
    use RefreshDatabase;

    private function crearOriginalEmitida(Tenant $tenant, Cliente $cliente): Factura
    {
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();

        $original = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serieOrdinaria->id,
            'cliente_id' => $cliente->id,
        ]);

        FacturaLinea::factory()->for($original)->create(['tenant_id' => $tenant->id]);
        FacturaImpuesto::factory()->for($original)->create(['tenant_id' => $tenant->id]);

        return $original;
    }

    public function test_rectificar_una_emitida_crea_borrador_vinculado_con_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original = $this->crearOriginalEmitida($tenant, $cliente);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$original->id}/rectificar", [
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Error en el tipo impositivo aplicado.',
        ]);

        $rectificativa = Factura::where('factura_rectificada_id', $original->id)->first();

        $response->assertRedirect(route('facturas.edit', $rectificativa));
        $this->assertNotNull($rectificativa);
        $this->assertEquals('rectificativa', $rectificativa->tipo->value);
        $this->assertTrue($rectificativa->es_rectificativa);
        $this->assertEquals('sustitucion', $rectificativa->tipo_rectificacion->value);
        $this->assertEquals('Error en el tipo impositivo aplicado.', $rectificativa->motivo_rectificacion);
        $this->assertEquals('borrador', $rectificativa->estado->value);
        $this->assertNull($rectificativa->numero);
        $this->assertEquals($original->cliente_nif, $rectificativa->cliente_nif);
        $this->assertEquals($original->regimen_impositivo->value, $rectificativa->regimen_impositivo->value);
        $this->assertEquals($original->aplica_recargo, $rectificativa->aplica_recargo);
        $this->assertCount(1, $rectificativa->lineas);

        $original->refresh();
        $this->assertEquals('emitida', $original->estado->value);
    }

    public function test_no_se_puede_rectificar_una_factura_en_borrador(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $borrador = Factura::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$borrador->id}/rectificar", [
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Motivo cualquiera.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Solo se pueden rectificar facturas emitidas.');
        $this->assertDatabaseCount('facturas', 1);
    }

    public function test_no_se_puede_rectificar_una_factura_ya_rectificada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $rectificada = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => 'rectificada',
        ]);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$rectificada->id}/rectificar", [
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Motivo cualquiera.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Esta factura ya fue rectificada.');
        $this->assertDatabaseCount('facturas', 1);
    }
}
