<?php

namespace Tests\Feature\Albaranes;

use App\Models\Albaran;
use App\Models\AlbaranLinea;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EntregadorAlbaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbaranAnulacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_anular_un_albaran_entregado_revierte_el_stock_y_repone_la_cantidad_entregada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $articulo = Articulo::factory()->producto()->create([
            'tenant_id' => $tenant->id,
            'gestion_stock' => true,
            'stock_actual' => 100,
        ]);
        $presupuesto = Presupuesto::factory()->aceptado()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);
        $linea = PresupuestoLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'presupuesto_id' => $presupuesto->id,
            'articulo_id' => $articulo->id,
            'cantidad' => 40,
            'cantidad_entregada' => 0,
        ]);

        $albaran = Albaran::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'presupuesto_id' => $presupuesto->id,
        ]);
        AlbaranLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'albaran_id' => $albaran->id,
            'presupuesto_linea_id' => $linea->id,
            'articulo_id' => $articulo->id,
            'cantidad' => 40,
        ]);

        app(EntregadorAlbaran::class)->entregar($albaran);
        $articulo->refresh();
        $this->assertEquals(60.0, (float) $articulo->stock_actual);
        $this->assertEquals(40.0, (float) $linea->refresh()->cantidad_entregada);

        $this->loginAs($user);

        $this->put("/albaranes/{$albaran->id}/estado", ['estado' => 'anulado'])->assertRedirect();

        $articulo->refresh();
        $this->assertEquals(100.0, (float) $articulo->stock_actual);
        $this->assertEquals(0.0, (float) $linea->refresh()->cantidad_entregada);
        $this->assertEquals('anulado', $albaran->refresh()->estado->value);
    }

    public function test_anular_un_albaran_ya_facturado_se_rechaza(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $albaran = Albaran::factory()->facturado()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);

        $response = $this->putJson("/albaranes/{$albaran->id}/estado", ['estado' => 'anulado']);

        $response->assertStatus(422);
    }
}
