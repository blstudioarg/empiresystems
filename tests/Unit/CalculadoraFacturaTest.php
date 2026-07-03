<?php

namespace Tests\Unit;

use App\Enums\RegimenImpositivo;
use App\Services\CalculadoraFactura;
use PHPUnit\Framework\TestCase;

class CalculadoraFacturaTest extends TestCase
{
    private function calculadora(): CalculadoraFactura
    {
        return new CalculadoraFactura;
    }

    public function test_una_linea_21_por_ciento_sin_recargo_ni_irpf(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Iva,
            aplicaRecargo: false,
            irpfPorcentaje: null,
            lineas: [
                ['cantidad' => 2, 'precioUnitario' => 50, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 21],
            ],
        );

        $this->assertEquals(100.0, $resultado->baseTotal);
        $this->assertEquals(21.0, $resultado->cuotaImpuestoTotal);
        $this->assertEquals(0.0, $resultado->cuotaRecargoTotal);
        $this->assertEquals(0.0, $resultado->irpfCuota);
        $this->assertEquals(121.0, $resultado->total);
        $this->assertCount(1, $resultado->impuestos);
        $this->assertEquals('iva', $resultado->impuestos[0]['tipoImpuesto']);
        $this->assertEquals(21.0, $resultado->impuestos[0]['porcentaje']);
    }

    public function test_varias_lineas_con_tipos_distintos_desglosa_por_tipo(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Iva,
            aplicaRecargo: false,
            irpfPorcentaje: null,
            lineas: [
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 21],
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 10],
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 4],
            ],
        );

        $this->assertEquals(300.0, $resultado->baseTotal);
        $this->assertEquals(35.0, $resultado->cuotaImpuestoTotal);
        $this->assertCount(3, $resultado->impuestos);
        $this->assertEquals(335.0, $resultado->total);
    }

    public function test_recargo_de_equivalencia_bajo_iva(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Iva,
            aplicaRecargo: true,
            irpfPorcentaje: null,
            lineas: [
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 21],
            ],
        );

        $this->assertEquals(5.2, $resultado->cuotaRecargoTotal);
        $this->assertEquals(126.2, $resultado->total);
        $this->assertTrue(collect($resultado->impuestos)->contains(fn ($i) => $i['tipoImpuesto'] === 'recargo'));
    }

    public function test_recargo_ausente_bajo_igic(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Igic,
            aplicaRecargo: true,
            irpfPorcentaje: null,
            lineas: [
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 7],
            ],
        );

        $this->assertEquals(0.0, $resultado->cuotaRecargoTotal);
        $this->assertFalse(collect($resultado->impuestos)->contains(fn ($i) => $i['tipoImpuesto'] === 'recargo'));
    }

    public function test_irpf_15_por_ciento_resta_del_total(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Iva,
            aplicaRecargo: false,
            irpfPorcentaje: 15,
            lineas: [
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 21],
            ],
        );

        $this->assertEquals(15.0, $resultado->irpfCuota);
        $this->assertEquals(106.0, $resultado->total);
        $this->assertTrue(collect($resultado->impuestos)->contains(fn ($i) => $i['tipoImpuesto'] === 'irpf'));
    }

    public function test_irpf_null_no_genera_fila(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Iva,
            aplicaRecargo: false,
            irpfPorcentaje: null,
            lineas: [
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 21],
            ],
        );

        $this->assertFalse(collect($resultado->impuestos)->contains(fn ($i) => $i['tipoImpuesto'] === 'irpf'));
    }

    public function test_descuento_100_por_ciento_deja_base_cero(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Iva,
            aplicaRecargo: false,
            irpfPorcentaje: null,
            lineas: [
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => 100, 'tipoImpositivo' => 21],
            ],
        );

        $this->assertEquals(0.0, $resultado->baseTotal);
        $this->assertEquals(0.0, $resultado->cuotaImpuestoTotal);
        $this->assertEquals(0.0, $resultado->total);
    }

    public function test_redondeo_total_cuadra_con_desglose(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Iva,
            aplicaRecargo: true,
            irpfPorcentaje: 15,
            lineas: [
                ['cantidad' => 3, 'precioUnitario' => 33.33, 'descuentoPorcentaje' => 10, 'tipoImpositivo' => 21],
                ['cantidad' => 1, 'precioUnitario' => 19.99, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 10],
            ],
        );

        $sumaImpuestos = array_sum(array_map(fn ($i) => $i['cuota'], array_filter($resultado->impuestos, fn ($i) => $i['tipoImpuesto'] !== 'irpf')));
        $this->assertEqualsWithDelta($resultado->baseTotal + $sumaImpuestos - $resultado->irpfCuota, $resultado->total, 0.001);
    }

    public function test_igic_7_por_ciento_valido_sin_recargo(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Igic,
            aplicaRecargo: false,
            irpfPorcentaje: null,
            lineas: [
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 7],
            ],
        );

        $this->assertEquals(7.0, $resultado->cuotaImpuestoTotal);
        $this->assertEquals(0.0, $resultado->cuotaRecargoTotal);
        $this->assertEquals('igic', $resultado->impuestos[0]['tipoImpuesto']);
    }

    public function test_ipsi_tipo_libre_sin_recargo(): void
    {
        $resultado = $this->calculadora()->calcular(
            regimen: RegimenImpositivo::Ipsi,
            aplicaRecargo: false,
            irpfPorcentaje: null,
            lineas: [
                ['cantidad' => 1, 'precioUnitario' => 100, 'descuentoPorcentaje' => null, 'tipoImpositivo' => 8],
            ],
        );

        $this->assertEquals(8.0, $resultado->cuotaImpuestoTotal);
        $this->assertEquals(0.0, $resultado->cuotaRecargoTotal);
        $this->assertEquals('ipsi', $resultado->impuestos[0]['tipoImpuesto']);
    }
}
