<?php

namespace Tests\Feature\Pos;

use App\Models\PosCuenta;
use App\Models\PosPrecuenta;
use App\Models\Tenant;
use App\Services\PrecuentaCuenta;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConCajaAbierta;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * US2 / FR-015..FR-021 / research D5: el estado "precuenta pedida" lo decide el servidor, prevalece
 * sobre "olvidada", sobrevive al cobro parcial, viaja con la cuenta al transferir y desaparece al
 * cobrar entera o al desactualizarse. Sin N+1.
 */
class PrecuentaSalaTest extends TestCase
{
    use ConCajaAbierta, MontaSalaPos, RefreshDatabase;

    /** @return array<string, mixed> */
    private function mesa(int $numero): array
    {
        return collect($this->getJson('/pos/sala')->assertOk()->json('mesas'))
            ->firstWhere('id', $this->mesasPos[$numero]->id);
    }

    public function test_una_mesa_con_precuenta_vigente_aparece_como_precuenta_pedida_aunque_este_olvidada(): void
    {
        $this->montarSala(['mesa_olvidada_min' => 30]);
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(4.00, 10)]]);

        PosCuenta::query()->whereKey($cuenta['id'])->update(['abierta_en' => now()->subMinutes(90)]);
        $this->emitirPrecuenta($cuenta['id']);

        $mesa = $this->mesa(1);

        $this->assertSame('ocupada', $mesa['estado']);
        $this->assertTrue($mesa['precuenta_pedida']);
        $this->assertSame(0, $mesa['precuenta_hace_min']);
        $this->assertFalse($mesa['olvidada'], 'La precuenta prevalece sobre "olvidada" (FR-018).');
    }

    public function test_las_mesas_libres_llevan_los_campos_a_falso_y_null(): void
    {
        $this->montarSala();

        $mesa = $this->mesa(2);

        $this->assertFalse($mesa['precuenta_pedida']);
        $this->assertNull($mesa['precuenta_hace_min']);
    }

    public function test_una_precuenta_desactualizada_devuelve_la_mesa_a_ocupada(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(4.00, 10)]]);
        $this->emitirPrecuenta($cuenta['id']);

        $actual = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json();
        $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $actual['version'],
            'mesa_id' => $actual['mesa_id'],
            'lineas' => [array_merge(
                array_intersect_key($actual['lineas'][0], array_flip(['id', 'articulo_id', 'concepto', 'tipo_impositivo'])),
                ['cantidad' => 2, 'opciones' => []],
            )],
        ])->assertOk();

        $mesa = $this->mesa(1);

        $this->assertFalse($mesa['precuenta_pedida']);
        $this->assertNull($mesa['precuenta_hace_min']);
    }

    public function test_el_minuto_de_la_precuenta_sale_de_la_ultima_emision(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(4.00, 10)]]);
        $this->emitirPrecuenta($cuenta['id']);

        // La fila es append-only: se envejece por SQL directo, solo para el test.
        DB::table('pos_precuentas')->update(['emitida_en' => now()->subMinutes(12)]);

        $this->assertSame(12, $this->mesa(1)['precuenta_hace_min']);
    }

    public function test_tras_un_cobro_parcial_sigue_en_precuenta_con_el_pendiente_actualizado(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(4.00, 10), 'cantidad' => 2]]);
        $this->emitirPrecuenta($cuenta['id']);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $cuenta['lineas'][0]['id'], 'cantidad' => 1]],
        ])->assertCreated();

        $mesa = $this->mesa(1);

        $this->assertTrue($mesa['precuenta_pedida']);
        $this->assertSame('4.40', $mesa['pendiente']);
    }

    public function test_al_cobrarla_entera_la_mesa_queda_libre(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(4.00, 10)]]);
        $this->emitirPrecuenta($cuenta['id']);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])->assertCreated();

        $mesa = $this->mesa(1);
        $this->assertSame('libre', $mesa['estado']);
        $this->assertFalse($mesa['precuenta_pedida']);
    }

    public function test_al_transferir_el_estado_viaja_a_la_mesa_destino(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(4.00, 10)]]);
        $this->emitirPrecuenta($cuenta['id']);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/transferir", ['mesa_id' => $this->mesasPos[2]->id])->assertOk();

        $this->assertFalse($this->mesa(1)['precuenta_pedida']);
        $this->assertTrue($this->mesa(2)['precuenta_pedida']);
    }

    public function test_el_numero_de_consultas_no_crece_con_las_mesas_con_precuenta(): void
    {
        $this->montarSala(mesas: 12);
        $articulo = $this->articuloPos(5.00, 10);

        $contar = function (): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $mesas = $this->getJson('/pos/sala')->assertOk()->json('mesas');
            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$consultas, count(array_filter($mesas, fn ($m) => $m['precuenta_pedida']))];
        };

        foreach (range(1, 2) as $i) {
            $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]], $this->mesasPos[$i]);
            $this->emitirPrecuenta($cuenta['id']);
        }
        [$conDos, $pedidas] = $contar();
        $this->assertSame(2, $pedidas);

        foreach (range(3, 12) as $i) {
            $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]], $this->mesasPos[$i]);
            $this->emitirPrecuenta($cuenta['id']);
        }
        [$conDoce, $pedidas] = $contar();
        $this->assertSame(12, $pedidas);

        // Lo que se afirma es que NO CRECE: las mismas consultas con 2 que con 12 mesas.
        $this->assertSame($conDos, $conDoce, "La Sala pasó de {$conDos} a {$conDoce} consultas al pasar de 2 a 12 mesas con precuenta: N+1.");
    }

    public function test_la_sala_de_un_tenant_no_refleja_precuentas_de_otro(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(4.00, 10)]]);

        // Una precuenta de OTRO tenant que apunta (por error) a la misma cuenta y con su misma
        // huella —es decir, que la daría por vigente— no debe contar: el scope de tenant manda.
        tenancy()->initialize($this->tenantPos);
        $huella = app(PrecuentaCuenta::class)->huellaConsumo(PosCuenta::query()->findOrFail($cuenta['id']));

        $otro = Tenant::factory()->create();
        ConfigPos::guardar($otro->id, ['hosteleria_activo' => true]);
        PosPrecuenta::factory()->create([
            'tenant_id' => $otro->id,
            'cuenta_id' => $cuenta['id'],
            'huella_consumo' => $huella,
        ]);

        $this->assertFalse($this->mesa(1)['precuenta_pedida']);
    }
}
