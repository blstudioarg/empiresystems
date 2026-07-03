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

class RectificativaEmisionTest extends TestCase
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
        ]);

        FacturaLinea::factory()->for($original)->create(['tenant_id' => $tenant->id]);
        FacturaImpuesto::factory()->for($original)->create(['tenant_id' => $tenant->id]);

        return $original;
    }

    private function crearRectificativaBorrador(Tenant $tenant, Cliente $cliente, Serie $serieRectificativa, Factura $original): Factura
    {
        $rectificativa = Factura::factory()->rectificativa()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serieRectificativa->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => 'Cliente de prueba',
            'cliente_nif' => '12345678Z',
            'cliente_direccion' => 'Calle Falsa 123',
            'factura_rectificada_id' => $original->id,
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Corrección de importe.',
        ]);

        FacturaLinea::factory()->for($rectificativa)->create(['tenant_id' => $tenant->id]);

        return $rectificativa;
    }

    public function test_emitir_rectificativa_asigna_numero_de_serie_rectificativa_y_marca_la_original(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        $serieRectificativa = Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);
        $rectificativa = $this->crearRectificativaBorrador($tenant, $cliente, $serieRectificativa, $original);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$rectificativa->id}/emitir");

        $response->assertRedirect(route('facturas.index'));

        $rectificativa->refresh();
        $original->refresh();

        $this->assertEquals('emitida', $rectificativa->estado->value);
        $this->assertStringStartsWith('R-'.now()->year.'-', $rectificativa->numero_completo);
        $this->assertEquals('rectificada', $original->estado->value);
        $this->assertDatabaseHas('factura_eventos', ['factura_id' => $rectificativa->id, 'tipo_evento' => 'emitida']);
        $this->assertDatabaseHas('factura_eventos', ['factura_id' => $original->id, 'tipo_evento' => 'rectificada']);
    }

    public function test_correlativos_de_la_serie_rectificativa_sin_huecos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        $serieRectificativa = Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original1 = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);
        $original2 = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);

        $rectificativa1 = $this->crearRectificativaBorrador($tenant, $cliente, $serieRectificativa, $original1);
        $rectificativa2 = $this->crearRectificativaBorrador($tenant, $cliente, $serieRectificativa, $original2);

        $this->loginAs($user);

        $this->post("/facturas/{$rectificativa1->id}/emitir")->assertRedirect();
        $this->post("/facturas/{$rectificativa2->id}/emitir")->assertRedirect();

        $rectificativa1->refresh();
        $rectificativa2->refresh();

        $this->assertEquals('R-'.now()->year.'-0001', $rectificativa1->numero_completo);
        $this->assertEquals('R-'.now()->year.'-0002', $rectificativa2->numero_completo);
    }

    public function test_emitir_una_rectificativa_no_altera_el_contador_de_la_serie_ordinaria(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create(['proximo_numero' => 5]);
        $serieRectificativa = Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);
        $rectificativa = $this->crearRectificativaBorrador($tenant, $cliente, $serieRectificativa, $original);

        $this->loginAs($user);

        $this->post("/facturas/{$rectificativa->id}/emitir")->assertRedirect();

        $serieOrdinaria->refresh();
        $this->assertEquals(5, $serieOrdinaria->proximo_numero);
    }

    public function test_reinicio_anual_de_la_serie_rectificativa(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        $serieRectificativa = Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        // Rectificativa ya emitida el año anterior en esta serie.
        Factura::factory()->rectificativa()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serieRectificativa->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => 'Cliente de prueba',
            'cliente_nif' => '12345678Z',
            'cliente_direccion' => 'Calle Falsa 123',
            'estado' => 'emitida',
            'numero' => 5,
            'numero_completo' => 'R-'.(now()->year - 1).'-0005',
            'fecha_expedicion' => now()->subYear()->toDateString(),
        ]);

        $originalEsteAnio = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);
        $rectificativaEsteAnio = $this->crearRectificativaBorrador($tenant, $cliente, $serieRectificativa, $originalEsteAnio);

        $this->loginAs($user);
        $this->post("/facturas/{$rectificativaEsteAnio->id}/emitir")->assertRedirect();

        $rectificativaEsteAnio->refresh();
        $this->assertEquals('R-'.now()->year.'-0001', $rectificativaEsteAnio->numero_completo);
    }
}
