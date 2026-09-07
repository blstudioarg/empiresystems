<?php

namespace Tests\Unit;

use App\Services\AsistenteIa;
use Tests\TestCase;

/**
 * Campos que viajan al detalle de una propuesta.
 *
 * Se arman en servidor y no en el JS para que el criterio —qué se rotula cómo, qué pasa con lo
 * vacío— sea uno solo y se pueda fijar con tests.
 */
class CamposLegiblesTest extends TestCase
{
    public function test_rotula_las_claves_conocidas_en_castellano(): void
    {
        $campos = AsistenteIa::camposLegibles([
            'razon_social' => 'Prueba S.L.',
            'nif' => 'A12345678',
        ]);

        $this->assertSame('Razón social', $campos[0]['etiqueta']);
        $this->assertSame('NIF/CIF', $campos[1]['etiqueta']);
    }

    public function test_una_clave_desconocida_se_humaniza_en_vez_de_omitirse(): void
    {
        $campos = AsistenteIa::camposLegibles(['codigo_postal' => '28001']);

        // Omitir un campo que no reconocemos sería esconderle al usuario algo que se va a escribir.
        $this->assertSame('Codigo postal', $campos[0]['etiqueta']);
        $this->assertSame('28001', $campos[0]['valor']);
    }

    public function test_los_campos_vacios_se_muestran_marcados_y_no_se_esconden(): void
    {
        $campos = AsistenteIa::camposLegibles([
            'nombre' => 'Acme',
            'nif' => null,
            'email' => '',
        ]);

        // Que falte el NIF es justamente lo que el usuario necesita ver antes de confirmar.
        $this->assertCount(3, $campos);
        $this->assertSame('—', $campos[1]['valor']);
        $this->assertSame('—', $campos[2]['valor']);
    }

    public function test_un_valor_compuesto_se_serializa_en_vez_de_romper(): void
    {
        $campos = AsistenteIa::camposLegibles(['lineas' => [['articulo' => 'X', 'cantidad' => 2]]]);

        $this->assertStringContainsString('articulo', $campos[0]['valor']);
    }
}
