<?php

namespace Tests\Feature\Pos;

use App\Models\PosCuenta;
use App\Models\PosPrecuenta;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\ConCajaAbierta;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Contrato de `POST /pos/cuentas/{id}/precuentas` (contracts/precuenta.md) y del bloque
 * `precuenta` del payload de la cuenta: emisión (US1), aviso de desactualizada (US3) y
 * reimpresión (US4).
 */
class PrecuentaEmisionTest extends TestCase
{
    use ConCajaAbierta, MontaSalaPos, RefreshDatabase;

    /** @return array<string, mixed> */
    private function cuenta(array $capacidades = []): array
    {
        $this->montarSala(array_merge(['cobro_dividido_activo' => true], $capacidades));

        return $this->abrirCuentaCon([
            ['articulo' => $this->articuloPos(2.00, 10, ['nombre' => 'Caña']), 'cantidad' => 3],
            ['articulo' => $this->articuloPos(6.00, 10, ['nombre' => 'Tapa']), 'cantidad' => 1],
        ]);
    }

    /** @return list<array<string, mixed>> líneas del payload en formato de guardado */
    private function lineasGuardables(array $cuenta): array
    {
        return array_map(fn (array $l) => [
            'id' => $l['id'],
            'articulo_id' => $l['articulo_id'],
            'concepto' => $l['concepto'],
            'cantidad' => $l['cantidad'],
            'tipo_impositivo' => $l['tipo_impositivo'],
            'opciones' => [],
        ], $cuenta['lineas']);
    }

    // ── US1: emisión ─────────────────────────────────────────────────────

    public function test_emite_una_precuenta_y_la_cuenta_queda_vigente(): void
    {
        $cuenta = $this->cuenta();

        $respuesta = $this->postJson("/pos/cuentas/{$cuenta['id']}/precuentas", ['version' => $cuenta['version']])
            ->assertCreated()
            ->json();

        // 3×2,00 + 6,00 = 12,00 € + 10% = 13,20 €.
        $this->assertSame('13.20', $respuesta['precuenta']['total']);
        $this->assertFalse($respuesta['precuenta']['reimpresion']);
        $this->assertSame("/pos/precuentas/{$respuesta['precuenta']['id']}/pdf", $respuesta['precuenta']['pdf_url']);
        $this->assertSame('vigente', $respuesta['cuenta']['precuenta']['estado']);
        $this->assertSame('13.20', $respuesta['cuenta']['precuenta']['ultima']['total']);
        $this->assertNull($respuesta['cuenta']['precuenta']['total_actual']);

        $fila = PosPrecuenta::query()->firstOrFail();
        $this->assertSame('Mesa 1', $fila->mesa_nombre);
        $this->assertSame('Comedor', $fila->zona_nombre);
        $this->assertSame($this->usuarioPos->id, $fila->usuario_id);
        $this->assertSame($cuenta['version'], $fila->cuenta_version);
        $this->assertCount(2, $fila->lineas);
    }

    public function test_una_cuenta_sin_precuentas_lleva_el_estado_ninguna(): void
    {
        $cuenta = $this->cuenta();

        $this->assertSame('ninguna', $cuenta['precuenta']['estado']);
        $this->assertNull($cuenta['precuenta']['ultima']);
    }

    public function test_con_version_vieja_responde_409_y_no_inserta_nada(): void
    {
        $cuenta = $this->cuenta();

        $this->postJson("/pos/cuentas/{$cuenta['id']}/precuentas", ['version' => $cuenta['version'] - 1])
            ->assertStatus(409)
            ->assertJsonPath('cuenta.id', $cuenta['id']);

        $this->assertDatabaseCount('pos_precuentas', 0);
    }

    public function test_version_ausente_es_un_error_de_validacion(): void
    {
        $cuenta = $this->cuenta();

        $this->postJson("/pos/cuentas/{$cuenta['id']}/precuentas", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');
    }

    public function test_una_cuenta_anulada_no_admite_precuenta(): void
    {
        $cuenta = $this->cuenta();
        $this->postJson("/pos/cuentas/{$cuenta['id']}/anular")->assertOk();

        $this->postJson("/pos/cuentas/{$cuenta['id']}/precuentas", ['version' => $cuenta['version'] + 1])
            ->assertStatus(422);

        $this->assertDatabaseCount('pos_precuentas', 0);
    }

    public function test_una_cuenta_cobrada_entera_no_admite_precuenta(): void
    {
        $cuenta = $this->cuenta();
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])->assertCreated();

        $version = (int) PosCuenta::query()->find($cuenta['id'])->version;

        $this->postJson("/pos/cuentas/{$cuenta['id']}/precuentas", ['version' => $version])->assertStatus(422);
        $this->assertDatabaseCount('pos_precuentas', 0);
    }

    public function test_una_cuenta_sin_nada_pendiente_es_rechazada(): void
    {
        $this->montarSala();
        $cuenta = $this->postJson('/pos/cuentas', ['mesa_id' => $this->mesasPos[1]->id])->assertCreated()->json();

        $this->postJson("/pos/cuentas/{$cuenta['id']}/precuentas", ['version' => $cuenta['version']])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No hay nada pendiente para la precuenta.');
    }

    public function test_tras_un_cobro_parcial_solo_incluye_lo_pendiente(): void
    {
        $cuenta = $this->cuenta();
        $cana = collect($cuenta['lineas'])->firstWhere('concepto', 'Caña');

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $cana['id'], 'cantidad' => 2]],
        ])->assertCreated();

        $respuesta = $this->emitirPrecuenta($cuenta['id']);

        // Queda 1 caña (2,00) + 1 tapa (6,00) = 8,00 € + 10% = 8,80 € = pendiente de la mesa.
        $this->assertSame('8.80', $respuesta['precuenta']['total']);
        $this->assertSame($respuesta['cuenta']['pendiente'], $respuesta['precuenta']['total']);

        $lineaCana = collect(PosPrecuenta::query()->firstOrFail()->lineas)->firstWhere('concepto', 'Caña');
        $this->assertSame('1.00', $lineaCana['cantidad']);
    }

    public function test_una_cuenta_sin_mesa_admite_precuenta_y_la_registra_sin_mesa(): void
    {
        $cuenta = $this->cuenta();

        $this->postJson("/pos/cuentas/{$cuenta['id']}/transferir", ['mesa_id' => null])->assertOk();
        $this->emitirPrecuenta($cuenta['id']);

        $fila = PosPrecuenta::query()->firstOrFail();
        $this->assertNull($fila->mesa_id);
        $this->assertNull($fila->mesa_nombre);
    }

    public function test_el_registro_es_append_only(): void
    {
        $cuenta = $this->cuenta();
        $this->emitirPrecuenta($cuenta['id']);
        $fila = PosPrecuenta::query()->firstOrFail();

        try {
            $fila->update(['total' => 1]);
            $this->fail('Una precuenta no debería poder modificarse.');
        } catch (LogicException) {
        }

        try {
            $fila->delete();
            $this->fail('Una precuenta no debería poder borrarse.');
        } catch (LogicException) {
        }

        $this->assertSame('13.20', PosPrecuenta::query()->firstOrFail()->total);
    }

    public function test_anular_la_cuenta_conserva_sus_precuentas(): void
    {
        $cuenta = $this->cuenta();
        $this->emitirPrecuenta($cuenta['id']);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/anular")->assertOk();

        // SC-007: para una cuenta anulada se puede saber si llegó a tener precuenta.
        $this->assertSame(PosCuenta::ESTADO_ANULADA, PosCuenta::query()->find($cuenta['id'])->estado);
        $this->assertSame(1, PosPrecuenta::query()->where('cuenta_id', $cuenta['id'])->count());
    }

    public function test_con_el_modulo_apagado_la_ruta_no_es_accesible(): void
    {
        $cuenta = $this->cuenta();
        ConfigPos::guardar($this->tenantPos->id, ['hosteleria_activo' => false]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/precuentas", ['version' => $cuenta['version']])->assertForbidden();
        $this->assertDatabaseCount('pos_precuentas', 0);
    }

    // ── US3: desactualizada ──────────────────────────────────────────────

    public function test_anadir_una_linea_tras_la_precuenta_la_deja_desactualizada_con_ambos_totales(): void
    {
        $cuenta = $this->cuenta();
        $this->emitirPrecuenta($cuenta['id']);
        $actual = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json();
        $cafe = $this->articuloPos(1.50, 10, ['nombre' => 'Café']);

        $respuesta = $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $actual['version'],
            'mesa_id' => $actual['mesa_id'],
            'lineas' => array_merge($this->lineasGuardables($actual), [[
                'articulo_id' => $cafe->id, 'concepto' => 'Café', 'cantidad' => 1, 'tipo_impositivo' => 10, 'opciones' => [],
            ]]),
        ])->assertOk()->json();

        $this->assertSame('desactualizada', $respuesta['precuenta']['estado']);
        $this->assertSame('13.20', $respuesta['precuenta']['ultima']['total']);
        // 13,20 + 1,50 + 10% = 14,85 € (lo que emitiría el cobro).
        $this->assertSame('14.85', $respuesta['precuenta']['total_actual']);
        $this->assertSame($respuesta['pendiente'], $respuesta['precuenta']['total_actual']);
    }

    public function test_cambiar_solo_las_notas_la_mantiene_vigente(): void
    {
        $cuenta = $this->cuenta();
        $this->emitirPrecuenta($cuenta['id']);
        $actual = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json();

        $respuesta = $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $actual['version'],
            'mesa_id' => $actual['mesa_id'],
            'notas' => 'Cliente habitual',
            'lineas' => $this->lineasGuardables($actual),
        ])->assertOk()->json();

        $this->assertSame('vigente', $respuesta['precuenta']['estado']);
        $this->assertNull($respuesta['precuenta']['total_actual']);
    }

    // ── US4: reimpresión ─────────────────────────────────────────────────

    public function test_dos_emisiones_seguidas_la_segunda_es_reimpresion_y_quedan_ambas(): void
    {
        $cuenta = $this->cuenta();

        $primera = $this->emitirPrecuenta($cuenta['id']);
        $segunda = $this->emitirPrecuenta($cuenta['id']);

        $this->assertFalse($primera['precuenta']['reimpresion']);
        $this->assertTrue($segunda['precuenta']['reimpresion']);
        $this->assertDatabaseCount('pos_precuentas', 2);
    }

    public function test_tras_cambiar_una_linea_la_nueva_no_es_reimpresion(): void
    {
        $cuenta = $this->cuenta();
        $this->emitirPrecuenta($cuenta['id']);
        $actual = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json();
        $lineas = $this->lineasGuardables($actual);
        $lineas[0]['cantidad'] = 4;

        $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $actual['version'],
            'mesa_id' => $actual['mesa_id'],
            'lineas' => $lineas,
        ])->assertOk();

        $this->assertFalse($this->emitirPrecuenta($cuenta['id'])['precuenta']['reimpresion']);
    }

    public function test_tras_un_cobro_parcial_la_nueva_no_es_reimpresion(): void
    {
        $cuenta = $this->cuenta();
        $this->emitirPrecuenta($cuenta['id']);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $cuenta['lineas'][0]['id'], 'cantidad' => 1]],
        ])->assertCreated();

        $this->assertFalse($this->emitirPrecuenta($cuenta['id'])['precuenta']['reimpresion']);
    }
}
