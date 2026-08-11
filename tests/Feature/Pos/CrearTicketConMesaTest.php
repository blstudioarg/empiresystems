<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-059/FR-060: la vista de crear ticket, con el módulo activo, expone el contexto de mesa
 * (chip) y precarga la cuenta abierta cuando se llega con `?cuenta=`.
 */
class CrearTicketConMesaTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_get_crear_con_mesa_preseleccionada_no_crea_cuenta(): void
    {
        $this->montarSala();

        $respuesta = $this->get('/pos/crear?mesa='.$this->mesasPos[1]->id)->assertOk();

        $respuesta->assertViewHas('mesaPreseleccionada', fn ($mesa) => $mesa->id === $this->mesasPos[1]->id);
        $respuesta->assertViewHas('cuentaPrecargada', null);
        $respuesta->assertSee('id="pos-mesa-chip"', false);

        $this->assertDatabaseCount('pos_cuentas', 0);
    }

    public function test_get_crear_con_cuenta_precarga_sus_lineas(): void
    {
        $this->montarSala();
        $articulo = $this->articuloPos(8.50, 10, ['nombre' => 'Bocadillo']);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);

        $respuesta = $this->get('/pos/crear?cuenta='.$cuenta['id'])->assertOk();

        $respuesta->assertViewHas('cuentaPrecargada', fn ($c) => $c->id === $cuenta['id']);
        $respuesta->assertSee('Bocadillo');
    }

    public function test_una_cuenta_de_otro_tenant_no_se_precarga_por_query_string(): void
    {
        $this->montarSala();
        $otroTenant = \App\Models\Tenant::factory()->create();
        $cuentaAjena = \App\Models\PosCuenta::factory()->create(['tenant_id' => $otroTenant->id]);

        $respuesta = $this->get('/pos/crear?cuenta='.$cuentaAjena->id)->assertOk();

        $respuesta->assertViewHas('cuentaPrecargada', null);
    }
}
