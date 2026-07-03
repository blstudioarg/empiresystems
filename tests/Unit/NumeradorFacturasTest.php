<?php

namespace Tests\Unit;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\NumeradorFacturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumeradorFacturasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La fuente de verdad es MAX(numero) de las facturas ya emitidas de la serie en el año
     * (research.md § D1): el numerador no avanza solo, hace falta persistir cada factura emitida
     * con su número entre llamadas para que la siguiente vea el máximo real.
     */
    private function marcarEmitida(Factura $factura, int $numero, ?string $numeroCompleto = null): void
    {
        $factura->update([
            'estado' => 'emitida',
            'numero' => $numero,
            'numero_completo' => $numeroCompleto ?? "F-{$factura->fecha_expedicion->year}-".str_pad((string) $numero, 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function test_llamadas_secuenciales_generan_correlativo_sin_huecos(): void
    {
        $tenant = Tenant::factory()->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $hoy = now();

        $numerador = new NumeradorFacturas;

        $primero = $numerador->siguienteNumero($serie, $hoy);
        $this->assertEquals(1, $primero['numero']);
        $this->marcarEmitida(Factura::factory()->for($tenant, 'tenant')->for($serie, 'serie')->for($cliente, 'cliente')->create(), $primero['numero']);

        $segundo = $numerador->siguienteNumero($serie, $hoy);
        $this->assertEquals(2, $segundo['numero']);
        $this->marcarEmitida(Factura::factory()->for($tenant, 'tenant')->for($serie, 'serie')->for($cliente, 'cliente')->create(), $segundo['numero']);

        $tercero = $numerador->siguienteNumero($serie, $hoy);
        $this->assertEquals(3, $tercero['numero']);
    }

    public function test_formato_se_aplica_al_numero_completo(): void
    {
        $tenant = Tenant::factory()->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create([
            'codigo' => 'F',
            'formato' => '{serie}-{anio}-{numero:0000}',
        ]);

        $numerador = new NumeradorFacturas;
        $resultado = $numerador->siguienteNumero($serie, \DateTime::createFromFormat('Y-m-d', '2026-01-15'));

        $this->assertEquals('F-2026-0001', $resultado['numeroCompleto']);
    }

    public function test_el_numero_se_reinicia_a_1_en_un_nuevo_anio(): void
    {
        $tenant = Tenant::factory()->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();

        $this->marcarEmitida(
            Factura::factory()->for($tenant, 'tenant')->for($serie, 'serie')->for($cliente, 'cliente')
                ->create(['fecha_expedicion' => '2025-12-30']),
            5,
        );

        $numerador = new NumeradorFacturas;
        $resultado = $numerador->siguienteNumero($serie, \DateTime::createFromFormat('Y-m-d', '2026-01-05'));

        $this->assertEquals(1, $resultado['numero']);
    }
}
