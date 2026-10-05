<?php

namespace Tests\Feature\Pos;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use App\Models\PosZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConCajaAbierta;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * SC-002 / research D2: el total de la precuenta coincide **al céntimo** con el del ticket que se
 * emite después al cobrar la cuenta entera. Se fuerza el caso difícil (dos tipos impositivos,
 * opción con suplemento, zona con suplemento) y se repite con recargo de equivalencia y bajo IGIC:
 * nada asume IVA (Principio II).
 */
class PrecuentaTotalIgualTicketTest extends TestCase
{
    use ConCajaAbierta, MontaSalaPos, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributosTenant
     * @return array<string, mixed> payload de la cuenta
     */
    private function cuentaComplicada(array $atributosTenant = [], float $tipoBajo = 10, float $tipoAlto = 21): array
    {
        $this->montarSala(['opciones_activo' => true, 'suplemento_zona_activo' => true], atributosTenant: $atributosTenant);
        PosZona::query()->find($this->zonaPos->id)->update(['suplemento_porcentaje' => 10]);

        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id]);
        $opcion = PosOpcion::factory()->create([
            'tenant_id' => $this->tenantPos->id, 'grupo_id' => $grupo->id,
            'nombre' => 'Extra queso', 'precio_defecto' => 1.35,
        ]);

        $bebida = $this->articuloPos(1.95, $tipoBajo, ['nombre' => 'Caña']);
        $plato = $this->articuloPos(12.45, $tipoAlto, ['nombre' => 'Hamburguesa']);

        $this->putJson("/articulos/{$plato->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 1.35, 'orden' => 0]],
        ])->assertOk();

        return $this->abrirCuentaCon([
            ['articulo' => $bebida, 'cantidad' => 3],
            ['articulo' => $plato, 'cantidad' => 2, 'opciones' => [$opcion->id]],
        ]);
    }

    private function assertPrecuentaIgualTicket(int $cuentaId): void
    {
        $precuenta = $this->emitirPrecuenta($cuentaId)['precuenta'];

        $version = $this->getJson("/pos/cuentas/{$cuentaId}")->json('version');
        $this->postJson("/pos/cuentas/{$cuentaId}/cobrar", ['version' => $version])->assertCreated();

        $factura = Factura::query()->latest('id')->firstOrFail();

        $this->assertSame(
            number_format((float) $factura->total, 2, '.', ''),
            $precuenta['total'],
            'El total de la precuenta no coincide al céntimo con el ticket emitido.',
        );
    }

    public function test_tipos_mixtos_opciones_y_suplemento_de_zona(): void
    {
        $cuenta = $this->cuentaComplicada();

        $this->assertPrecuentaIgualTicket($cuenta['id']);
    }

    public function test_con_receptor_en_recargo_de_equivalencia(): void
    {
        $cuenta = $this->cuentaComplicada();

        $cliente = Cliente::factory()->create([
            'tenant_id' => $this->tenantPos->id,
            'nif' => 'B12345674',
            'aplica_recargo_equivalencia' => true,
        ]);

        $lineas = array_map(fn (array $l) => [
            'id' => $l['id'],
            'articulo_id' => $l['articulo_id'],
            'concepto' => $l['concepto'],
            'cantidad' => $l['cantidad'],
            'tipo_impositivo' => $l['tipo_impositivo'],
            'opciones' => array_map(fn ($o) => ['opcion_id' => $o['opcion_id']], $l['opciones']),
        ], $cuenta['lineas']);

        $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $cuenta['version'],
            'mesa_id' => $cuenta['mesa_id'],
            'lineas' => $lineas,
            'receptor' => [
                'cliente_id' => $cliente->id,
                'cliente_nif' => 'B12345674',
                'cliente_nombre' => 'Bar Cliente SL',
            ],
        ])->assertOk();

        $this->assertPrecuentaIgualTicket($cuenta['id']);

        // Sanidad del propio test: el recargo se aplicó de verdad en el ticket.
        $this->assertGreaterThan(0, (float) Factura::query()->latest('id')->first()->cuota_recargo_total);
    }

    public function test_con_tenant_en_regimen_igic(): void
    {
        $cuenta = $this->cuentaComplicada(['regimen_impositivo' => 'igic'], tipoBajo: 3, tipoAlto: 7);

        $this->assertPrecuentaIgualTicket($cuenta['id']);
        $this->assertSame('igic', Factura::query()->latest('id')->first()->regimen_impositivo->value);
    }
}
