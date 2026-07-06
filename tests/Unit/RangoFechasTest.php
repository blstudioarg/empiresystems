<?php

namespace Tests\Unit;

use App\Enums\PresetRango;
use App\Support\RangoFechas;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RangoFechasTest extends TestCase
{
    private function hoy(): Carbon
    {
        return Carbon::create(2026, 8, 20, 10, 30);
    }

    public function test_mes_en_curso_va_desde_el_inicio_del_mes_hasta_hoy(): void
    {
        $rango = RangoFechas::mesEnCurso($this->hoy());

        $this->assertSame('2026-08-01', $rango->desde->toDateString());
        $this->assertSame('2026-08-20', $rango->hasta->toDateString());
        $this->assertSame(PresetRango::Mes, $rango->preset);
    }

    public function test_trimestre_en_curso_va_desde_el_inicio_del_trimestre_hasta_hoy(): void
    {
        $rango = RangoFechas::trimestreEnCurso($this->hoy());

        $this->assertSame('2026-07-01', $rango->desde->toDateString());
        $this->assertSame('2026-08-20', $rango->hasta->toDateString());
        $this->assertSame(PresetRango::Trimestre, $rango->preset);
    }

    public function test_anio_en_curso_va_desde_el_inicio_del_anio_hasta_hoy(): void
    {
        $rango = RangoFechas::anioEnCurso($this->hoy());

        $this->assertSame('2026-01-01', $rango->desde->toDateString());
        $this->assertSame('2026-08-20', $rango->hasta->toDateString());
        $this->assertSame(PresetRango::Anio, $rango->preset);
    }

    public function test_personalizado_usa_las_fechas_dadas(): void
    {
        $rango = RangoFechas::personalizado(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-10'));

        $this->assertSame('2026-01-01', $rango->desde->toDateString());
        $this->assertSame('2026-01-10', $rango->hasta->toDateString());
        $this->assertSame(PresetRango::Personalizado, $rango->preset);
    }

    public function test_desde_peticion_mapea_preset_conocido(): void
    {
        $rango = RangoFechas::desdePeticion(['preset' => 'anio'], $this->hoy());

        $this->assertSame(PresetRango::Anio, $rango->preset);
        $this->assertSame('2026-01-01', $rango->desde->toDateString());
        $this->assertSame('2026-08-20', $rango->hasta->toDateString());
    }

    public function test_desde_peticion_personalizado_valido(): void
    {
        $rango = RangoFechas::desdePeticion([
            'preset' => 'personalizado',
            'desde' => '2026-02-01',
            'hasta' => '2026-02-15',
        ], $this->hoy());

        $this->assertSame(PresetRango::Personalizado, $rango->preset);
        $this->assertSame('2026-02-01', $rango->desde->toDateString());
        $this->assertSame('2026-02-15', $rango->hasta->toDateString());
    }

    public function test_desde_peticion_sin_filtros_cae_a_mes_en_curso(): void
    {
        $rango = RangoFechas::desdePeticion([], $this->hoy());

        $this->assertSame(PresetRango::Mes, $rango->preset);
        $this->assertSame('2026-08-01', $rango->desde->toDateString());
        $this->assertSame('2026-08-20', $rango->hasta->toDateString());
    }

    public function test_desde_peticion_preset_desconocido_cae_a_mes_en_curso(): void
    {
        $rango = RangoFechas::desdePeticion(['preset' => 'quincena'], $this->hoy());

        $this->assertSame(PresetRango::Mes, $rango->preset);
    }

    public function test_desde_peticion_personalizado_con_hasta_anterior_a_desde_cae_a_mes_en_curso(): void
    {
        $rango = RangoFechas::desdePeticion([
            'preset' => 'personalizado',
            'desde' => '2026-05-01',
            'hasta' => '2026-04-01',
        ], $this->hoy());

        $this->assertSame(PresetRango::Mes, $rango->preset);
        $this->assertSame('2026-08-01', $rango->desde->toDateString());
    }

    public function test_desde_peticion_personalizado_con_fechas_invalidas_cae_a_mes_en_curso(): void
    {
        $rango = RangoFechas::desdePeticion([
            'preset' => 'personalizado',
            'desde' => 'no-es-una-fecha',
            'hasta' => '2026-04-01',
        ], $this->hoy());

        $this->assertSame(PresetRango::Mes, $rango->preset);
    }

    public function test_anterior_devuelve_periodo_de_igual_duracion_terminando_el_dia_antes(): void
    {
        $rango = RangoFechas::personalizado(Carbon::parse('2026-03-11'), Carbon::parse('2026-03-20'));
        $anterior = $rango->anterior();

        $this->assertSame('2026-03-10', $anterior->hasta->toDateString());
        $this->assertSame('2026-03-01', $anterior->desde->toDateString());
        $this->assertSame($rango->dias(), $anterior->dias());
        $this->assertSame(PresetRango::Personalizado, $anterior->preset);
    }

    public function test_dias_cuenta_de_forma_inclusiva(): void
    {
        $rango = RangoFechas::personalizado(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-10'));

        $this->assertSame(10, $rango->dias());
    }

    public function test_granularidad_es_dia_hasta_62_dias(): void
    {
        $rango = RangoFechas::personalizado(Carbon::parse('2026-01-01'), Carbon::parse('2026-03-03'));

        $this->assertSame(62, $rango->dias());
        $this->assertSame('dia', $rango->granularidad());
    }

    public function test_granularidad_es_mes_a_partir_de_63_dias(): void
    {
        $rango = RangoFechas::personalizado(Carbon::parse('2026-01-01'), Carbon::parse('2026-03-04'));

        $this->assertSame(63, $rango->dias());
        $this->assertSame('mes', $rango->granularidad());
    }

    public function test_contiene_evalua_pertenencia_al_rango(): void
    {
        $rango = RangoFechas::personalizado(Carbon::parse('2026-01-05'), Carbon::parse('2026-01-15'));

        $this->assertTrue($rango->contiene(Carbon::parse('2026-01-05')));
        $this->assertTrue($rango->contiene(Carbon::parse('2026-01-15')));
        $this->assertTrue($rango->contiene(Carbon::parse('2026-01-10')));
        $this->assertFalse($rango->contiene(Carbon::parse('2026-01-04')));
        $this->assertFalse($rango->contiene(Carbon::parse('2026-01-16')));
    }
}
