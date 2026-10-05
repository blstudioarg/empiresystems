<?php

namespace Tests\Feature\Pos;

use App\Models\Articulo;
use App\Models\PosCuenta;
use App\Models\PosMesa;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use App\Models\PosZona;
use App\Services\PrecuentaCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConCajaAbierta;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Research D4 / FR-016 / FR-017: "vigente" se decide por la huella del consumo, no por la versión.
 * Lo que cambia el importe (líneas, opciones, unir, suplemento de zona) la desactualiza; lo que no
 * (cobro parcial, notas, comensales, receptor, recrear líneas idénticas) no.
 */
class PrecuentaVigenciaTest extends TestCase
{
    use ConCajaAbierta, MontaSalaPos, RefreshDatabase;

    private function estado(int $cuentaId): string
    {
        tenancy()->initialize($this->tenantPos);

        $cuenta = PosCuenta::query()->with('lineas.opciones', 'mesa.zona')->findOrFail($cuentaId);
        $ultima = $cuenta->precuentas()->reorder()->latest('id')->first();

        return app(PrecuentaCuenta::class)->estado($cuenta, $ultima);
    }

    /** @param  array<string, mixed>|callable(array): array<string, mixed>  $cambios */
    private function guardar(array $cuenta, array|callable $cambios = []): array
    {
        $actual = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json();

        $lineas = array_map(fn (array $l) => [
            'id' => $l['id'],
            'articulo_id' => $l['articulo_id'],
            'concepto' => $l['concepto'],
            'cantidad' => $l['cantidad'],
            'tipo_impositivo' => $l['tipo_impositivo'],
            'opciones' => array_map(fn ($o) => ['opcion_id' => $o['opcion_id']], $l['opciones']),
        ], $actual['lineas']);

        return $this->putJson("/pos/cuentas/{$cuenta['id']}", array_merge([
            'version' => $actual['version'],
            'mesa_id' => $actual['mesa_id'],
            'lineas' => $lineas,
        ], is_callable($cambios) ? $cambios($lineas) : $cambios))->assertOk()->json();
    }

    private function cuentaConPrecuenta(array $capacidades = []): array
    {
        $this->montarSala(array_merge(['cobro_dividido_activo' => true], $capacidades));
        $cana = $this->articuloPos(2.00, 10, ['nombre' => 'Caña']);
        $tapa = $this->articuloPos(4.50, 10, ['nombre' => 'Tapa']);

        $cuenta = $this->abrirCuentaCon([
            ['articulo' => $cana, 'cantidad' => 2],
            ['articulo' => $tapa, 'cantidad' => 1],
        ]);

        $this->emitirPrecuenta($cuenta['id']);

        return $cuenta;
    }

    public function test_sin_precuentas_el_estado_es_ninguna_y_tras_emitir_vigente(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10)]]);

        $this->assertSame('ninguna', $this->estado($cuenta['id']));

        $this->emitirPrecuenta($cuenta['id']);

        $this->assertSame('vigente', $this->estado($cuenta['id']));
    }

    public function test_cambiar_la_cantidad_la_desactualiza(): void
    {
        $cuenta = $this->cuentaConPrecuenta();

        $this->guardar($cuenta, fn (array $lineas) => ['lineas' => array_replace($lineas, [0 => array_merge($lineas[0], ['cantidad' => 3])])]);

        $this->assertSame('desactualizada', $this->estado($cuenta['id']));
    }

    public function test_cambiar_el_precio_del_articulo_y_reguardar_la_desactualiza(): void
    {
        $cuenta = $this->cuentaConPrecuenta();

        // El precio se relee del catálogo al guardar: subirlo cambia el importe de la línea.
        tenancy()->initialize($this->tenantPos);
        Articulo::query()->where('nombre', 'Caña')->update(['precio' => 2.20]);

        $this->guardar($cuenta);

        $this->assertSame('desactualizada', $this->estado($cuenta['id']));
    }

    public function test_anadir_o_quitar_una_linea_la_desactualiza(): void
    {
        $cuenta = $this->cuentaConPrecuenta();
        $cafe = $this->articuloPos(1.50, 10, ['nombre' => 'Café']);

        $this->guardar($cuenta, fn (array $lineas) => ['lineas' => array_merge($lineas, [[
            'articulo_id' => $cafe->id, 'concepto' => 'Café', 'cantidad' => 1, 'tipo_impositivo' => 10, 'opciones' => [],
        ]])]);
        $this->assertSame('desactualizada', $this->estado($cuenta['id']));

        // Nueva precuenta (vigente) y después quitar una línea.
        $this->emitirPrecuenta($cuenta['id']);
        $this->assertSame('vigente', $this->estado($cuenta['id']));

        $this->guardar($cuenta, fn (array $lineas) => ['lineas' => array_slice($lineas, 1)]);
        $this->assertSame('desactualizada', $this->estado($cuenta['id']));
    }

    public function test_cambiar_las_opciones_la_desactualiza(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id]);
        $opcion = PosOpcion::factory()->create([
            'tenant_id' => $this->tenantPos->id, 'grupo_id' => $grupo->id, 'nombre' => 'Extra', 'precio_defecto' => 1,
        ]);
        $plato = $this->articuloPos(10.00, 10, ['nombre' => 'Plato']);
        $this->putJson("/articulos/{$plato->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 1, 'orden' => 0]],
        ])->assertOk();

        $cuenta = $this->abrirCuentaCon([['articulo' => $plato]]);
        $this->emitirPrecuenta($cuenta['id']);

        $this->guardar($cuenta, fn (array $lineas) => ['lineas' => [array_merge($lineas[0], ['opciones' => [['opcion_id' => $opcion->id]]])]]);

        $this->assertSame('desactualizada', $this->estado($cuenta['id']));
    }

    public function test_unir_otra_cuenta_la_desactualiza(): void
    {
        $cuenta = $this->cuentaConPrecuenta();
        $otra = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10)]], $this->mesasPos[2]);

        $this->postJson("/pos/cuentas/{$otra['id']}/unir", ['cuenta_destino_id' => $cuenta['id']])->assertOk();

        $this->assertSame('desactualizada', $this->estado($cuenta['id']));
    }

    public function test_transferir_a_una_zona_con_otro_suplemento_la_desactualiza(): void
    {
        $cuenta = $this->cuentaConPrecuenta(['suplemento_zona_activo' => true]);

        $terraza = PosZona::factory()->create(['tenant_id' => $this->tenantPos->id, 'nombre' => 'Terraza', 'suplemento_porcentaje' => 10]);
        $mesaTerraza = PosMesa::factory()->create(['tenant_id' => $this->tenantPos->id, 'zona_id' => $terraza->id, 'nombre' => 'T1']);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/transferir", ['mesa_id' => $mesaTerraza->id])->assertOk();

        $this->assertSame('desactualizada', $this->estado($cuenta['id']));
    }

    public function test_transferir_a_una_mesa_de_la_misma_zona_no_la_desactualiza(): void
    {
        $cuenta = $this->cuentaConPrecuenta(['suplemento_zona_activo' => true]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/transferir", ['mesa_id' => $this->mesasPos[2]->id])->assertOk();

        $this->assertSame('vigente', $this->estado($cuenta['id']));
    }

    public function test_un_cobro_parcial_no_la_desactualiza(): void
    {
        $cuenta = $this->cuentaConPrecuenta();
        $actual = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json();

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $actual['version'],
            'lineas' => [['cuenta_linea_id' => $actual['lineas'][0]['id'], 'cantidad' => 1]],
        ])->assertCreated();

        $this->assertSame('vigente', $this->estado($cuenta['id']));
    }

    public function test_notas_comensales_y_receptor_no_la_desactualizan(): void
    {
        $cuenta = $this->cuentaConPrecuenta();

        $this->guardar($cuenta, [
            'notas' => 'Sin prisa',
            'comensales' => 4,
            'receptor' => ['cliente_nif' => 'B12345674', 'cliente_nombre' => 'Empresa SL'],
        ]);

        $this->assertSame('vigente', $this->estado($cuenta['id']));
    }

    public function test_recrear_las_lineas_con_ids_nuevos_y_mismo_contenido_no_la_desactualiza(): void
    {
        $cuenta = $this->cuentaConPrecuenta();

        // Sin `id`: el guardado borra las líneas y las crea de nuevo, en otro orden.
        $this->guardar($cuenta, fn (array $lineas) => ['lineas' => array_reverse(array_map(function (array $l) {
            unset($l['id']);

            return $l;
        }, $lineas))]);

        $this->assertSame('vigente', $this->estado($cuenta['id']));
    }
}
