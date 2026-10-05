<?php

namespace Tests\Feature\Pos;

use App\Models\CajaSesion;
use App\Models\Factura;
use App\Models\MovimientoStock;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use App\Services\ResumenCaja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Feature 051, US2 (TEST-FIRST): anular un ticket tiene efecto real. Deja de contar en lo vendido y
 * en el efectivo esperado de la caja abierta (un cierre ya hecho no cambia) y devuelve al stock lo
 * que el ticket había descontado, sin tocar la salida original (ledger append-only).
 */
class AnularTicketCajaStockTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    private function montar(array $capacidades = []): CajaSesion
    {
        $this->montarSala($capacidades);
        $this->enTeam($this->tenantPos, fn () => $this->usuarioPos->roles->first()->givePermissionTo(['anular-tickets', 'ver-pos-caja']));
        $id = $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '50'])->assertCreated()->json('sesion.id');

        return CajaSesion::withoutGlobalScopes()->findOrFail($id);
    }

    private function emitir(array $linea): int
    {
        return (int) $this->postJson('/pos', ['lineas' => [$linea]])->assertCreated()->json('id');
    }

    public function test_el_ticket_anulado_deja_de_contar_en_la_caja_abierta(): void
    {
        $sesion = $this->montar();
        $this->emitir(['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 10]); // 11,00
        $anulado = $this->emitir(['concepto' => 'Tarta', 'cantidad' => 1, 'precio_unitario' => 5, 'tipo_impositivo' => 10]); // 5,50

        $this->postJson("/pos/{$anulado}/anular", ['motivo' => 'Error'])->assertOk();

        $r = app(ResumenCaja::class)->calcular($sesion->fresh());
        $this->assertSame(1, $r['num_tickets']);
        $this->assertSame('11.00', $r['total_facturado']);
        $this->assertSame('11.00', $r['efectivo_ventas']);
        $this->assertCount(1, $r['resumen']['anulados']);
    }

    public function test_un_cierre_ya_hecho_no_cambia_al_anular_un_ticket_de_ese_dia(): void
    {
        $sesion = $this->montar();
        $ticket = $this->emitir(['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 10]);
        $this->postJson('/pos/caja/cerrar', ['sesion_id' => $sesion->id, 'efectivo_contado' => '61.00'])->assertOk();
        $cerrada = $sesion->fresh();

        $this->postJson("/pos/{$ticket}/anular", ['motivo' => 'Error'])->assertOk();

        $despues = $sesion->fresh();
        $this->assertSame((string) $cerrada->total_facturado, (string) $despues->total_facturado);
        $this->assertSame((string) $cerrada->efectivo_esperado, (string) $despues->efectivo_esperado);
        $this->assertSame((int) $cerrada->num_tickets, (int) $despues->num_tickets);
    }

    public function test_anular_devuelve_el_stock_sin_tocar_la_salida_original(): void
    {
        $this->montar();
        $articulo = $this->articuloPos(2.00, 10, ['nombre' => 'Agua', 'gestion_stock' => true, 'stock_actual' => 10]);
        $ticket = $this->emitir(['articulo_id' => $articulo->id, 'concepto' => 'Agua', 'cantidad' => 3, 'precio_unitario' => 2, 'tipo_impositivo' => 10]);
        $this->assertSame(7.0, (float) $articulo->fresh()->stock_actual);
        $salida = MovimientoStock::withoutGlobalScopes()->where('factura_id', $ticket)->sole();

        $this->postJson("/pos/{$ticket}/anular", ['motivo' => 'Error'])->assertOk();

        $this->assertSame(10.0, (float) $articulo->fresh()->stock_actual);
        $this->assertEquals($salida->getAttributes(), $salida->fresh()->getAttributes());
        $entrada = MovimientoStock::withoutGlobalScopes()->where('factura_id', $ticket)->where('id', '!=', $salida->id)->sole();
        $this->assertSame('entrada', $entrada->tipo->value);
        $this->assertSame('devolucion', $entrada->origen->value);
        $this->assertSame(3.0, (float) $entrada->cantidad);
    }

    public function test_una_opcion_vinculada_tambien_devuelve_su_stock(): void
    {
        $this->montar(['opciones_activo' => true]);
        $refresco = $this->articuloPos(1.50, 10, ['nombre' => 'Refresco', 'gestion_stock' => true, 'stock_actual' => 20]);
        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id]);
        $opcion = PosOpcion::factory()->create([
            'tenant_id' => $this->tenantPos->id, 'grupo_id' => $grupo->id, 'articulo_vinculado_id' => $refresco->id,
        ]);
        $menu = $this->articuloPos(12.00, 10, ['nombre' => 'Menú']);
        $this->putJson("/articulos/{$menu->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 0, 'orden' => 0]],
        ])->assertOk();
        $cuenta = $this->abrirCuentaCon([['articulo' => $menu, 'cantidad' => 2, 'opciones' => [$opcion->id]]]);
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])->assertCreated();
        $this->assertSame(18.0, (float) $refresco->fresh()->stock_actual);

        $this->postJson('/pos/'.Factura::withoutGlobalScopes()->latest('id')->value('id').'/anular', ['motivo' => 'Error'])->assertOk();

        $this->assertSame(20.0, (float) $refresco->fresh()->stock_actual);
    }
}
