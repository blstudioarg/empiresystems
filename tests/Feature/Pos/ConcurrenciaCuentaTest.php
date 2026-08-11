<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-024 (research.md D7): dos camareros sobre la misma mesa desde tablets distintas.
 *
 * El bloqueo es **optimista**: quien guarda con una versión obsoleta recibe 409 y **no pisa** los
 * cambios del otro. Un bloqueo pesimista exigiría liberar el bloqueo cuando alguien cierra la
 * tablet o se queda sin batería, un problema de expiración que no queremos.
 */
class ConcurrenciaCuentaTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_guardar_con_una_version_obsoleta_responde_409_y_no_pisa_los_cambios_del_otro(): void
    {
        $this->montarSala();
        $cafe = $this->articuloPos(2.00, 10, ['nombre' => 'Café']);
        $tapa = $this->articuloPos(5.00, 10, ['nombre' => 'Tapa']);

        // Camarero A abre la cuenta y añade un café. Los dos dispositivos cargan la misma versión.
        $cuenta = $this->abrirCuentaCon([['articulo' => $cafe]]);
        $versionCargadaPorAmbos = $cuenta['version'];

        // Camarero A añade una tapa: la versión avanza.
        $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $versionCargadaPorAmbos,
            'mesa_id' => $this->mesasPos[1]->id,
            'lineas' => [
                ['articulo_id' => $cafe->id, 'concepto' => 'Café', 'cantidad' => 1, 'tipo_impositivo' => 10],
                ['articulo_id' => $tapa->id, 'concepto' => 'Tapa', 'cantidad' => 1, 'tipo_impositivo' => 10],
            ],
        ])->assertOk();

        // Camarero B guarda con la versión que había cargado: 409, con el estado actual adjunto.
        $respuesta = $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $versionCargadaPorAmbos,
            'mesa_id' => $this->mesasPos[1]->id,
            'lineas' => [
                ['articulo_id' => $cafe->id, 'concepto' => 'Café', 'cantidad' => 4, 'tipo_impositivo' => 10],
            ],
        ])->assertStatus(409);

        // El trabajo de A sigue intacto: dos líneas, no la única que mandaba B.
        $this->assertCount(2, $respuesta->json('cuenta.lineas'));
        $this->assertEqualsWithDelta(1, $respuesta->json('cuenta.lineas.0.cantidad'), 0.001);

        $actual = $this->getJson("/pos/cuentas/{$cuenta['id']}")->assertOk()->json();
        $this->assertCount(2, $actual['lineas']);
    }

    public function test_reintentar_con_la_version_devuelta_por_el_409_sí_guarda(): void
    {
        $this->montarSala();
        $cafe = $this->articuloPos(2.00, 10, ['nombre' => 'Café']);

        $cuenta = $this->abrirCuentaCon([['articulo' => $cafe]]);

        $conflicto = $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => 999,
            'mesa_id' => $this->mesasPos[1]->id,
            'lineas' => [],
        ])->assertStatus(409)->json();

        $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $conflicto['cuenta']['version'],
            'mesa_id' => $this->mesasPos[1]->id,
            'lineas' => [
                ['articulo_id' => $cafe->id, 'concepto' => 'Café', 'cantidad' => 2, 'tipo_impositivo' => 10],
            ],
        ])->assertOk();
    }

    public function test_cobrar_con_version_obsoleta_tambien_responde_409_y_no_emite_nada(): void
    {
        $this->montarSala();
        $cafe = $this->articuloPos(2.00, 10, ['nombre' => 'Café']);

        $cuenta = $this->abrirCuentaCon([['articulo' => $cafe]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version'] - 1])
            ->assertStatus(409);

        $this->assertDatabaseCount('facturas', 0);
    }
}
