<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use App\Models\PosCobro;
use App\Models\PosMesa;
use App\Models\PosZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-051: se aplica el valor **vigente al cobrar** y el de la zona **donde se cobra**, incluso si
 * la cuenta se transfirió desde otra zona. El suplemento de zona NUNCA se congela al añadir la
 * línea — solo al cobrar (data-model.md, `pos_cuenta_lineas`).
 */
class SuplementoZonaVigenciaTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_se_aplica_el_valor_vigente_de_la_zona_al_momento_de_cobrar(): void
    {
        $this->montarSala(['suplemento_zona_activo' => true]);

        $zona = PosZona::query()->find($this->zonaPos->id);
        $zona->update(['suplemento_porcentaje' => 5]);

        $articulo = $this->articuloPos(10.00, 10);
        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo]]);

        // El suplemento sube DESPUÉS de comandar, ANTES de cobrar: debe aplicarse el nuevo valor.
        $zona->update(['suplemento_porcentaje' => 15]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])
            ->assertCreated();

        // 10 € × 1.15 = 11,50 € + 10% = 12,65 €.
        $this->assertEqualsWithDelta(12.65, (float) Factura::first()->total, 0.01);
        $this->assertSame('15.00', (string) PosCobro::first()->zona_suplemento_aplicado);
    }

    public function test_al_transferir_a_otra_zona_se_aplica_el_suplemento_de_la_zona_de_destino(): void
    {
        $this->montarSala(['suplemento_zona_activo' => true], mesas: 1);

        $zonaOrigen = PosZona::query()->find($this->zonaPos->id);
        $zonaOrigen->update(['suplemento_porcentaje' => 0]);

        $zonaDestino = PosZona::factory()->create(['tenant_id' => $this->tenantPos->id, 'nombre' => 'Terraza', 'suplemento_porcentaje' => 20]);
        $mesaDestino = PosMesa::factory()->create(['tenant_id' => $this->tenantPos->id, 'zona_id' => $zonaDestino->id, 'nombre' => 'T1']);

        $articulo = $this->articuloPos(10.00, 10);
        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/transferir", ['mesa_id' => $mesaDestino->id])->assertOk();

        $version = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('version');
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $version])->assertCreated();

        // Se cobra con el suplemento de la Terraza (20%), no el de la zona de origen (0%).
        $this->assertEqualsWithDelta(13.20, (float) Factura::first()->total, 0.01);
    }
}
