<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Models\Serie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConCajaAbierta;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-009 / SC-003 / Principio II: la precuenta **no es una factura**. Emitir cualquier número de
 * precuentas no crea facturas, ni eventos/registros Verifactu, ni cobros, ni movimientos de stock,
 * no toca la numeración de la serie ni lo cobrado, ni la versión de la cuenta. El siguiente cobro
 * recibe exactamente el número que le tocaba.
 */
class PrecuentaSinEfectoFiscalTest extends TestCase
{
    use ConCajaAbierta, MontaSalaPos, RefreshDatabase;

    public function test_emitir_precuentas_no_tiene_ningun_efecto_fiscal_ni_de_stock(): void
    {
        $this->montarSala();
        $articulo = $this->articuloPos(8.00, 10, ['tipo' => 'producto', 'gestion_stock' => true]);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);
        $proximoAntes = Serie::withoutGlobalScopes()->where('tenant_id', $this->tenantPos->id)->value('proximo_numero');

        foreach (range(1, 3) as $_) {
            $this->emitirPrecuenta($cuenta['id']);
        }

        $this->assertDatabaseCount('pos_precuentas', 3);
        $this->assertDatabaseCount('facturas', 0);
        $this->assertDatabaseCount('factura_eventos', 0);
        $this->assertDatabaseCount('pos_cobros', 0);
        $this->assertSame(0, DB::table('movimientos_stock')->count());
        $this->assertSame($proximoAntes, Serie::withoutGlobalScopes()->where('tenant_id', $this->tenantPos->id)->value('proximo_numero'));

        $modelo = PosCuenta::withoutGlobalScopes()->find($cuenta['id']);
        $this->assertSame($cuenta['version'], (int) $modelo->version);
        $this->assertSame(PosCuenta::ESTADO_ABIERTA, $modelo->estado);
        $this->assertSame(0.0, (float) PosCuentaLinea::withoutGlobalScopes()->where('cuenta_id', $cuenta['id'])->sum('cantidad_saldada'));

        // El cobro posterior se emite como siempre, con el primer número de la serie.
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])->assertCreated();

        $anio = now()->year;
        $this->assertSame(["S-{$anio}-0001"], Factura::query()->pluck('numero_completo')->all());
    }
}
