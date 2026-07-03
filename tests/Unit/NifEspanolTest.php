<?php

namespace Tests\Unit;

use App\Rules\NifEspanol;
use PHPUnit\Framework\TestCase;

class NifEspanolTest extends TestCase
{
    private function passes(string $value): bool
    {
        $rule = new NifEspanol;
        $failed = false;

        $rule->validate('nif', $value, function () use (&$failed) {
            $failed = true;
        });

        return ! $failed;
    }

    public function test_dni_valido_pasa(): void
    {
        $this->assertTrue($this->passes('12345678Z'));
    }

    public function test_dni_con_letra_de_control_incorrecta_falla(): void
    {
        $this->assertFalse($this->passes('12345678X'));
    }

    public function test_nie_valido_pasa(): void
    {
        $this->assertTrue($this->passes('X1234567L'));
    }

    public function test_nie_con_letra_incorrecta_falla(): void
    {
        $this->assertFalse($this->passes('X1234567A'));
    }

    public function test_cif_valido_con_control_numerico_pasa(): void
    {
        $this->assertTrue($this->passes('B12345674'));
    }

    public function test_cif_con_control_incorrecto_falla(): void
    {
        $this->assertFalse($this->passes('B12345671'));
    }

    public function test_formato_completamente_invalido_falla(): void
    {
        $this->assertFalse($this->passes('ABC123'));
    }

    public function test_valor_vacio_pasa_sin_error(): void
    {
        $this->assertTrue($this->passes(''));
    }
}
