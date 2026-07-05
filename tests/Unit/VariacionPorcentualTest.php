<?php

namespace Tests\Unit;

use App\Support\VariacionPorcentual;
use PHPUnit\Framework\TestCase;

class VariacionPorcentualTest extends TestCase
{
    public function test_valor_anterior_cero_devuelve_null(): void
    {
        $this->assertNull(VariacionPorcentual::calcular(100, 0));
    }

    public function test_ambos_valores_cero_devuelve_null(): void
    {
        $this->assertNull(VariacionPorcentual::calcular(0, 0));
    }

    public function test_caso_normal_positivo(): void
    {
        $this->assertEqualsWithDelta(50.0, VariacionPorcentual::calcular(150, 100), 0.001);
    }

    public function test_caso_normal_negativo(): void
    {
        $this->assertEqualsWithDelta(-33.33, VariacionPorcentual::calcular(100, 150), 0.01);
    }
}
