<?php

namespace Tests\Feature\Caja;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaCajaPos;
use Tests\TestCase;

/** Informe Z en PDF, 80 mm y A4 (FR-015, FR-016). Solo existe para sesiones cerradas. */
class CajaInformeTest extends TestCase
{
    use MontaCajaPos, RefreshDatabase;

    public function test_el_informe_de_una_sesion_cerrada_se_sirve_en_80mm_y_a4(): void
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('50');
        $this->venderHttp(10);
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '5', 'motivo' => 'Hielo'])->assertCreated();
        // Esperado 50 + 12,10 − 5 = 57,10 = 50 + 5 + 2 + 0,10
        $this->cerrarHttp($sesion->id, ['conteo' => ['5000' => 1, '500' => 1, '200' => 1, '10' => 1]])->assertOk()
            ->assertJsonPath('resultado.estado', 'cuadra');

        $ticket = $this->get("/pos/caja/sesiones/{$sesion->id}/informe?formato=ticket");
        $ticket->assertOk();
        $this->assertStringContainsString('application/pdf', $ticket->headers->get('Content-Type'));

        $a4 = $this->get("/pos/caja/sesiones/{$sesion->id}/informe?formato=a4");
        $a4->assertOk();
        $this->assertStringContainsString('application/pdf', $a4->headers->get('Content-Type'));
    }

    public function test_una_sesion_abierta_no_tiene_informe_z(): void
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('0');

        $this->get("/pos/caja/sesiones/{$sesion->id}/informe")->assertNotFound();
    }
}
