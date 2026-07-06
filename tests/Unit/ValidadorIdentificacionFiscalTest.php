<?php

namespace Tests\Unit;

use App\Support\ValidadorIdentificacionFiscal;
use PHPUnit\Framework\TestCase;

class ValidadorIdentificacionFiscalTest extends TestCase
{
    public function test_nif_persona_fisica_valido(): void
    {
        $this->assertTrue(ValidadorIdentificacionFiscal::esValido('12345678Z'));
    }

    public function test_nif_persona_fisica_con_letra_incorrecta_es_invalido(): void
    {
        $this->assertFalse(ValidadorIdentificacionFiscal::esValido('12345678X'));
    }

    public function test_nie_valido(): void
    {
        $this->assertTrue(ValidadorIdentificacionFiscal::esValido('X1234567L'));
        $this->assertTrue(ValidadorIdentificacionFiscal::esValido('Y1234567X'));
        $this->assertTrue(ValidadorIdentificacionFiscal::esValido('Z1234567R'));
    }

    public function test_nie_con_letra_incorrecta_es_invalido(): void
    {
        $this->assertFalse(ValidadorIdentificacionFiscal::esValido('X1234567A'));
    }

    public function test_cif_con_control_numerico_valido(): void
    {
        $this->assertTrue(ValidadorIdentificacionFiscal::esValido('B12345674'));
    }

    public function test_cif_con_control_numerico_incorrecto_es_invalido(): void
    {
        $this->assertFalse(ValidadorIdentificacionFiscal::esValido('B12345671'));
    }

    public function test_cif_que_exige_control_alfabetico(): void
    {
        // Letra P (entidades religiosas) exige control alfabético.
        $this->assertTrue(ValidadorIdentificacionFiscal::esValido('P1234567D'));
    }

    public function test_cif_con_letra_organizativa_invalida(): void
    {
        $this->assertFalse(ValidadorIdentificacionFiscal::esValido('I12345674'));
    }

    public function test_formato_completamente_invalido(): void
    {
        $this->assertFalse(ValidadorIdentificacionFiscal::esValido('ABC123'));
    }

    public function test_valor_vacio_o_null_es_invalido(): void
    {
        $this->assertFalse(ValidadorIdentificacionFiscal::esValido(''));
        $this->assertFalse(ValidadorIdentificacionFiscal::esValido(null));
    }

    public function test_minusculas_y_espacios_se_normalizan(): void
    {
        $this->assertTrue(ValidadorIdentificacionFiscal::esValido(' 12345678z '));
    }
}
