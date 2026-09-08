<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-017: una cuenta abierta **no es una factura en borrador**. No aparece en el listado de
 * tickets, no reserva número y no genera registro Verifactu.
 *
 * La garantía es estructural (`pos_cuentas` no tiene `numero` ni `serie_id`), pero se comprueba
 * aquí porque es la clase de invariante que se rompe sin querer al añadir "solo un campito".
 */
class CuentaNoEsTicketTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_una_cuenta_abierta_no_aparece_en_el_listado_de_tickets_ni_crea_factura(): void
    {
        $this->montarSala();
        $articulo = $this->articuloPos(12.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 3]]);

        $this->assertSame('abierta', $cuenta['estado']);
        $this->assertGreaterThan(0, (float) $cuenta['pendiente']);

        // Ni una factura, ni un registro Verifactu, ni nada en el listado del POS.
        $this->assertDatabaseCount('facturas', 0);
        $this->assertSame(0, Factura::query()->count());
        $this->assertSame([], $this->getJson('/pos')->assertOk()->json('data'));
    }

    public function test_la_tabla_de_cuentas_no_tiene_columnas_de_numeracion(): void
    {
        $this->montarSala();

        // Garantía estructural de FR-017: si alguien añadiera estas columnas, abrir una cuenta
        // podría empezar a consumir numeración y el resto de tests no lo detectaría.
        $this->assertFalse(Schema::hasColumn('pos_cuentas', 'numero'));
        $this->assertFalse(Schema::hasColumn('pos_cuentas', 'serie_id'));
        $this->assertFalse(Schema::hasColumn('pos_cuentas', 'numero_completo'));
    }
}
