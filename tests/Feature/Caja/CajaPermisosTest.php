<?php

namespace Tests\Feature\Caja;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaCajaPos;
use Tests\TestCase;

/** US5 + FR-022 — histórico de cierres y permiso propio de la caja. */
class CajaPermisosTest extends TestCase
{
    use MontaCajaPos, RefreshDatabase;

    public function test_el_historico_lista_solo_cierres_del_tenant_mas_recientes_primero(): void
    {
        $this->montarCaja();
        $primera = $this->abrirCajaHttp('0');
        $this->venderHttp(10);
        $this->cerrarHttp($primera->id, ['efectivo_contado' => '12.10'])->assertOk();

        $this->travel(1)->hours();
        $segunda = $this->abrirCajaHttp('0');
        $this->cerrarHttp($segunda->id, ['efectivo_contado' => '1.50'])->assertOk(); // sobra 1,50
        $this->abrirCajaHttp('0'); // abierta: no sale en el histórico

        $this->getJson('/pos/caja/cierres')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $segunda->id)
            ->assertJsonPath('data.0.estado', 'sobra')
            ->assertJsonPath('data.1.id', $primera->id)
            ->assertJsonPath('data.1.estado', 'cuadra')
            ->assertJsonPath('data.1.total_facturado', '12.10')
            ->assertJsonPath('data.1.num_tickets', 1)
            ->assertJsonStructure(['data' => [['informe_url_ticket', 'informe_url_a4', 'cerrada_por', 'abierta_por']], 'resumen_mes']);
    }

    public function test_las_pantallas_de_caja_se_renderizan_en_todos_sus_estados(): void
    {
        $this->montarCaja();

        // Cerrada, sin cierres previos.
        $this->get('/pos/caja')->assertOk()->assertSee('id="caja-cerrada"', false)->assertSee('data-bandeja', false);

        // Abierta, con ventas y un movimiento.
        $sesion = $this->abrirCajaHttp('100');
        $this->venderHttp(10);
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '5', 'motivo' => 'Hielo'])->assertCreated();
        $this->get('/pos/caja')->assertOk()->assertSee('"abierta":true', false);

        // El TPV muestra el chip de caja abierta.
        $this->get('/pos/crear')->assertOk()->assertSee('id="pos-caja-chip"', false)->assertSee('Caja abierta');

        // Cerrada con un cierre previo, histórico y configuración.
        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '107.10'])->assertOk();
        $this->get('/pos/caja')->assertOk()->assertSee('"ultimo_cierre":{', false);
        $this->get('/pos/caja/cierres')->assertOk()->assertSee('id="cierres-table"', false);
        $this->get('/pos/crear')->assertOk()->assertSee('id="posCajaAperturaModal"', false);
        $this->get('/configuracion')->assertOk()->assertSee('id="pos_caja_umbral_descuadre"', false);
    }

    public function test_sin_ver_pos_caja_todas_las_rutas_de_gestion_responden_403(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Solo listado', ['ver-pos']);
        $this->loginAs($this->usuarioConRol($tenant, $rol));

        $this->get('/pos/caja')->assertForbidden();
        $this->getJson('/pos/caja/cierres')->assertForbidden();
        $this->postJson('/pos/caja/movimientos', [])->assertForbidden();
        $this->postJson('/pos/caja/cerrar', [])->assertForbidden();
        $this->get('/pos/caja/sesiones/1/informe')->assertForbidden();
    }

    public function test_la_entrada_caja_del_menu_aparece_con_el_permiso(): void
    {
        $this->montarCaja();

        $this->get('/pos/caja')->assertOk()->assertSee('href="'.route('pos.caja').'"', false);
    }

    // Método aparte (no en el anterior): con SESSION_DRIVER=array el registro de permisos se
    // comparte entre usuarios dentro de un mismo método de test.
    public function test_la_entrada_caja_del_menu_no_aparece_sin_el_permiso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Solo listado', ['ver-pos']);
        $this->loginAs($this->usuarioConRol($tenant, $rol));

        $this->get('/pos')->assertOk()->assertDontSee('pos/caja"', false);
    }
}
