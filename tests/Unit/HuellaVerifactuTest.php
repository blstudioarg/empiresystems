<?php

namespace Tests\Unit;

use App\Support\HuellaVerifactu;
use PHPUnit\Framework\TestCase;

/**
 * Vectores de prueba OFICIALES de la AEAT (docs/02-facturacion-espana.md §1.1), tomados
 * literalmente de "Detalle de las especificaciones técnicas para generación de la huella o hash
 * de los registros de facturación", v0.1.2 (27/08/2024). No son valores inventados por el
 * proyecto: si esta librería deja de coincidir con ellos, el algoritmo está mal implementado.
 */
class HuellaVerifactuTest extends TestCase
{
    public function test_caso_1_primer_registro_de_alta_sin_huella_anterior(): void
    {
        $huella = HuellaVerifactu::alta([
            'idEmisorFactura' => '89890001K',
            'numSerieFactura' => '12345678/G33',
            'fechaExpedicionFactura' => '01-01-2024',
            'tipoFactura' => 'F1',
            'cuotaTotal' => '12.35',
            'importeTotal' => '123.45',
            'fechaHoraHusoGenRegistro' => '2024-01-01T19:20:30+01:00',
        ], huellaAnterior: '');

        $this->assertSame(
            '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60',
            $huella,
        );
    }

    public function test_caso_2_segundo_registro_de_alta_encadenado(): void
    {
        $huella = HuellaVerifactu::alta([
            'idEmisorFactura' => '89890001K',
            'numSerieFactura' => '12345679/G34',
            'fechaExpedicionFactura' => '01-01-2024',
            'tipoFactura' => 'F1',
            'cuotaTotal' => '12.35',
            'importeTotal' => '123.45',
            'fechaHoraHusoGenRegistro' => '2024-01-01T19:20:35+01:00',
        ], huellaAnterior: '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60');

        $this->assertSame(
            'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97',
            $huella,
        );
    }

    public function test_caso_3_anulacion_encadenada(): void
    {
        $huella = HuellaVerifactu::anulacion([
            'idEmisorFacturaAnulada' => '89890001K',
            'numSerieFacturaAnulada' => '12345679/G34',
            'fechaExpedicionFacturaAnulada' => '01-01-2024',
            'fechaHoraHusoGenRegistro' => '2024-01-01T19:20:40+01:00',
        ], huellaAnterior: 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97');

        $this->assertSame(
            '177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68',
            $huella,
        );
    }
}
