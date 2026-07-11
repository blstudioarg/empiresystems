<?php

namespace Tests\Feature\Albaranes;

use App\Models\Albaran;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbaranDirectoClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_y_entregar_un_albaran_sin_presupuesto_mueve_stock_igual_que_uno_derivado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $articulo = Articulo::factory()->producto()->create([
            'tenant_id' => $tenant->id,
            'gestion_stock' => true,
            'stock_actual' => 50,
        ]);

        $this->loginAs($user);

        $response = $this->post('/albaranes', [
            'cliente_id' => $cliente->id,
            'lineas' => [[
                'articulo_id' => $articulo->id,
                'concepto' => $articulo->nombre,
                'cantidad' => 5,
                'precio_unitario' => 20,
                'tipo_impositivo' => 21,
            ]],
        ]);
        $response->assertSessionHasNoErrors();

        $albaran = Albaran::where('tenant_id', $tenant->id)->sole();
        $this->assertNull($albaran->presupuesto_id);
        $this->assertEquals($cliente->id, $albaran->cliente_id);

        $this->put("/albaranes/{$albaran->id}/estado", ['estado' => 'entregado'])->assertRedirect();

        $articulo->refresh();
        $this->assertEquals(45.0, (float) $articulo->stock_actual);
    }
}
