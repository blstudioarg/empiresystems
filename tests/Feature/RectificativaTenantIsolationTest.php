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

class RectificativaTenantIsolationTest extends TestCase
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

    public function test_no_se_puede_rectificar_una_factura_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $serieOrdinariaB = Serie::factory()->for($tenantB, 'tenant')->create();

        $originalB = $this->crearOriginalEmitida($tenantB, $clienteB, $serieOrdinariaB);

        $this->loginAs($userA);

        $response = $this->post("/facturas/{$originalB->id}/rectificar", [
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Motivo cualquiera.',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('facturas', 1);
    }

    public function test_emitir_una_rectificativa_en_un_tenant_no_altera_la_numeracion_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $serieOrdinariaA = Serie::factory()->for($tenantA, 'tenant')->create();
        $serieRectificativaA = Serie::factory()->rectificativa()->for($tenantA, 'tenant')->create();

        $serieRectificativaB = Serie::factory()->rectificativa()->for($tenantB, 'tenant')->create(['proximo_numero' => 7]);

        $originalA = $this->crearOriginalEmitida($tenantA, $clienteA, $serieOrdinariaA);

        $rectificativaA = Factura::factory()->rectificativa()->create([
            'tenant_id' => $tenantA->id,
            'serie_id' => $serieRectificativaA->id,
            'cliente_id' => $clienteA->id,
            'cliente_nombre' => 'Cliente de prueba',
            'cliente_nif' => '12345678Z',
            'cliente_direccion' => 'Calle Falsa 123',
            'factura_rectificada_id' => $originalA->id,
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Corrección de importe.',
        ]);
        FacturaLinea::factory()->for($rectificativaA)->create(['tenant_id' => $tenantA->id]);

        $this->loginAs($userA);
        $this->post("/facturas/{$rectificativaA->id}/emitir")->assertRedirect();

        $serieRectificativaB->refresh();
        $this->assertEquals(7, $serieRectificativaB->proximo_numero);
    }
}
