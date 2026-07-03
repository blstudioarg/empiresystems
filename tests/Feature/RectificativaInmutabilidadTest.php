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

class RectificativaInmutabilidadTest extends TestCase
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

    private function crearRectificativaEmitida(Tenant $tenant, Cliente $cliente, Serie $serieRectificativa, Factura $original): Factura
    {
        $rectificativa = Factura::factory()->rectificativa()->emitida()->create([
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

        $original->update(['estado' => 'rectificada']);

        return $rectificativa;
    }

    public function test_una_rectificativa_emitida_no_se_puede_editar_ni_borrar_ni_reemitir(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        $serieRectificativa = Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);
        $rectificativa = $this->crearRectificativaEmitida($tenant, $cliente, $serieRectificativa, $original);

        $this->loginAs($user);

        $this->get("/facturas/{$rectificativa->id}/editar")->assertForbidden();
        $this->delete("/facturas/{$rectificativa->id}")->assertForbidden();

        $reemitir = $this->post("/facturas/{$rectificativa->id}/emitir");
        $reemitir->assertRedirect();
        $reemitir->assertSessionHas('error', 'Solo se pueden emitir facturas en borrador.');
    }

    public function test_una_original_rectificada_no_se_puede_editar_ni_borrar_ni_volver_a_rectificar(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        $serieRectificativa = Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();

        $original = $this->crearOriginalEmitida($tenant, $cliente, $serieOrdinaria);
        $this->crearRectificativaEmitida($tenant, $cliente, $serieRectificativa, $original);
        $original->refresh();

        $this->loginAs($user);

        $this->get("/facturas/{$original->id}/editar")->assertForbidden();
        $this->delete("/facturas/{$original->id}")->assertForbidden();

        $reRectificar = $this->post("/facturas/{$original->id}/rectificar", [
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Otro motivo.',
        ]);
        $reRectificar->assertRedirect();
        $reRectificar->assertSessionHas('error', 'Esta factura ya fue rectificada.');
    }
}
