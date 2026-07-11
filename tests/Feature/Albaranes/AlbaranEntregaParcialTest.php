<?php

namespace Tests\Feature\Albaranes;

use App\Models\Albaran;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbaranEntregaParcialTest extends TestCase
{
    use RefreshDatabase;

    public function test_dos_albaranes_consecutivos_respetan_el_tope_pendiente_y_un_tercero_se_rechaza(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $articulo = Articulo::factory()->producto()->create([
            'tenant_id' => $tenant->id,
            'gestion_stock' => true,
            'stock_actual' => 100,
        ]);
        $presupuesto = Presupuesto::factory()->aceptado()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
        ]);
        $linea = PresupuestoLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'presupuesto_id' => $presupuesto->id,
            'articulo_id' => $articulo->id,
            'concepto' => $articulo->nombre,
            'cantidad' => 100,
            'cantidad_entregada' => 0,
            'precio_unitario' => 10,
            'tipo_impositivo' => 21,
        ]);

        $this->loginAs($user);

        $payloadLinea = fn (float $cantidad) => [
            'presupuesto_linea_id' => $linea->id,
            'articulo_id' => $articulo->id,
            'concepto' => $articulo->nombre,
            'cantidad' => $cantidad,
            'precio_unitario' => 10,
            'tipo_impositivo' => 21,
        ];

        $primerAlbaran = $this->post('/albaranes', [
            'presupuesto_id' => $presupuesto->id,
            'lineas' => [$payloadLinea(40)],
        ]);
        $primerAlbaran->assertSessionHasNoErrors();
        $primerAlbaranId = Albaran::where('tenant_id', $tenant->id)->sole()->id;

        $this->put("/albaranes/{$primerAlbaranId}/estado", ['estado' => 'entregado'])->assertRedirect();

        $linea->refresh();
        $this->assertEquals(40.0, (float) $linea->cantidad_entregada);
        $this->assertEquals(60.0, $linea->cantidadPendiente());

        $segundoAlbaran = $this->post('/albaranes', [
            'presupuesto_id' => $presupuesto->id,
            'lineas' => [$payloadLinea(60)],
        ]);
        $segundoAlbaran->assertSessionHasNoErrors();
        $segundoAlbaranId = Albaran::where('tenant_id', $tenant->id)
            ->where('id', '!=', $primerAlbaranId)
            ->sole()->id;

        $this->put("/albaranes/{$segundoAlbaranId}/estado", ['estado' => 'entregado'])->assertRedirect();

        $linea->refresh();
        $this->assertEquals(100.0, (float) $linea->cantidad_entregada);
        $this->assertEquals(0.0, $linea->cantidadPendiente());

        $tercerAlbaran = $this->postJson('/albaranes', [
            'presupuesto_id' => $presupuesto->id,
            'lineas' => [$payloadLinea(1)],
        ]);
        $tercerAlbaran->assertStatus(422);
    }
}
