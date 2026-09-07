<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-052 (research.md D5): una opción con artículo vinculado descuenta stock **sin** generar
 * línea propia en el documento. Si el artículo vinculado no gestiona stock, no se registra
 * movimiento — no es un error, es el mismo criterio que ya aplica el resto del sistema.
 */
class StockOpcionVinculadaTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_vender_la_opcion_descuenta_stock_del_articulo_vinculado_sin_linea_propia(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $refresco = $this->articuloPos(1.50, 10, [
            'nombre' => 'Refresco',
            'gestion_stock' => true,
            'stock_actual' => 20,
        ]);

        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id, 'nombre' => 'Bebida incluida']);
        $opcion = PosOpcion::factory()->create([
            'tenant_id' => $this->tenantPos->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Refresco incluido',
            'articulo_vinculado_id' => $refresco->id,
        ]);

        $menu = $this->articuloPos(12.00, 10, ['nombre' => 'Menú del día']);
        $this->putJson("/articulos/{$menu->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 0, 'orden' => 0]],
        ])->assertOk();

        $cuenta = $this->abrirCuentaCon([
            ['articulo' => $menu, 'cantidad' => 2, 'opciones' => [$opcion->id]],
        ]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])->assertCreated();

        // Se movió el stock del refresco (2 unidades, una por cada menú vendido)…
        $this->assertSame(18.0, (float) $refresco->fresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_stock', ['articulo_id' => $refresco->id, 'cantidad' => 2]);

        // …pero el refresco NO aparece como línea propia de la factura.
        $factura = Factura::first();
        $this->assertFalse($factura->lineas->contains('articulo_id', $refresco->id));
        $this->assertCount(1, $factura->lineas);
    }

    public function test_si_el_articulo_vinculado_no_gestiona_stock_no_se_registra_movimiento(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $servilletas = $this->articuloPos(0.10, 10, ['nombre' => 'Servilletas', 'gestion_stock' => false]);
        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id]);
        $opcion = PosOpcion::factory()->create([
            'tenant_id' => $this->tenantPos->id,
            'grupo_id' => $grupo->id,
            'articulo_vinculado_id' => $servilletas->id,
        ]);

        $menu = $this->articuloPos(12.00, 10);
        $this->putJson("/articulos/{$menu->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 0, 'orden' => 0]],
        ])->assertOk();

        $cuenta = $this->abrirCuentaCon([['articulo' => $menu, 'opciones' => [$opcion->id]]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])->assertCreated();

        $this->assertDatabaseCount('movimientos_stock', 0);
    }
}
