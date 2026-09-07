<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * SC-001: la Sala se pinta en menos de 2 s con 20 cuentas abiertas. La única forma de garantizar
 * eso de verdad es que el número de consultas **no crezca con el número de mesas** — de ahí que
 * el test cuente queries en vez de cronometrar (un cronómetro es frágil entre máquinas; el
 * conteo de queries no).
 */
class SalaSinNMasUnoTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_el_numero_de_consultas_no_crece_con_el_numero_de_mesas_ocupadas(): void
    {
        $this->montarSala(mesas: 20);
        $articulo = $this->articuloPos(5.00, 10);

        foreach (range(1, 20) as $i) {
            $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]], $this->mesasPos[$i]);
        }

        DB::enableQueryLog();
        $this->getJson('/pos/sala')->assertOk();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Zonas + mesas + cuentas abiertas + sus líneas: un puñado fijo de consultas, no una por
        // mesa. Un límite generoso (10) deja margen sin dejar pasar una regresión N+1 real.
        $this->assertLessThanOrEqual(10, $consultas, "La Sala hizo {$consultas} consultas con 20 mesas ocupadas: sospecha de N+1.");
    }
}
