<?php

namespace Tests\Unit;

use App\Models\Serie;
use App\Models\Tenant;
use App\Services\NumeradorFacturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumeradorFacturasTest extends TestCase
{
    use RefreshDatabase;

    public function test_llamadas_secuenciales_generan_correlativo_sin_huecos(): void
    {
        $tenant = Tenant::factory()->create();
        $serie = Serie::where('tenant_id', $tenant->id)->first()
            ?? Serie::factory()->for($tenant, 'tenant')->create();

        $numerador = new NumeradorFacturas;

        $primero = $numerador->siguienteNumero($serie);
        $segundo = $numerador->siguienteNumero($serie);
        $tercero = $numerador->siguienteNumero($serie);

        $this->assertEquals(1, $primero['numero']);
        $this->assertEquals(2, $segundo['numero']);
        $this->assertEquals(3, $tercero['numero']);
    }

    public function test_formato_se_aplica_al_numero_completo(): void
    {
        $tenant = Tenant::factory()->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create([
            'codigo' => 'F',
            'ejercicio' => 2026,
            'proximo_numero' => 1,
            'formato' => '{serie}-{anio}-{numero:0000}',
        ]);

        $numerador = new NumeradorFacturas;
        $resultado = $numerador->siguienteNumero($serie);

        $this->assertEquals('F-2026-0001', $resultado['numeroCompleto']);
    }

    public function test_concurrencia_no_duplica_ni_salta_numeros(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('lockForUpdate solo es significativo bajo MySQL/MariaDB.');
        }

        $tenant = Tenant::factory()->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create(['proximo_numero' => 1]);

        $numerador = new NumeradorFacturas;
        $numeros = [];

        $procesos = [];
        for ($i = 0; $i < 5; $i++) {
            $procesos[] = function () use ($numerador, $serie, &$numeros) {
                $numeros[] = $numerador->siguienteNumero(Serie::find($serie->id))['numero'];
            };
        }
        foreach ($procesos as $proceso) {
            $proceso();
        }

        sort($numeros);
        $this->assertEquals([1, 2, 3, 4, 5], $numeros);
    }
}
