<?php

namespace Tests\Unit;

use App\Support\RangoFechas;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RangoFechasEjercicioAnteriorTest extends TestCase
{
    public function test_29_de_febrero_comparado_contra_anio_no_bisiesto_cae_en_28(): void
    {
        // 2028 es bisiesto; 2027 no lo es.
        $rango = RangoFechas::personalizado(Carbon::parse('2028-02-29'), Carbon::parse('2028-02-29'));

        $anterior = $rango->mismoPeriodoEjercicioAnterior();

        $this->assertSame('2027-02-28', $anterior->desde->toDateString());
        $this->assertSame('2027-02-28', $anterior->hasta->toDateString());
    }

    public function test_mes_completo_mantiene_su_longitud_natural_en_el_anio_comparado(): void
    {
        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $anterior = $rango->mismoPeriodoEjercicioAnterior();

        $this->assertSame('2025-06-01', $anterior->desde->toDateString());
        $this->assertSame('2025-06-30', $anterior->hasta->toDateString());
    }

    public function test_no_modifica_el_metodo_anterior_existente(): void
    {
        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-11'), Carbon::parse('2026-06-20'));

        $anteriorInmediato = $rango->anterior();

        $this->assertSame('2026-06-01', $anteriorInmediato->desde->toDateString());
        $this->assertSame('2026-06-10', $anteriorInmediato->hasta->toDateString());
    }
}
