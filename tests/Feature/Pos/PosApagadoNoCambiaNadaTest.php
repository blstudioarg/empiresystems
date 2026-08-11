<?php

namespace Tests\Feature\Pos;

use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SC-012 y FR-021: **con el módulo apagado, esta feature no existe.** El POS de venta directa
 * sigue exactamente igual que antes, y `POST /pos` recorre literalmente el mismo código.
 *
 * Es el test que protege a los tenants que no quieren hostelería: si algún día alguien "aprovecha"
 * para colar lógica de mesas en el camino de venta directa, esto se pone rojo.
 */
class PosApagadoNoCambiaNadaTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConTicket(): User
    {
        $tenant = Tenant::factory()->create();
        Serie::factory()->simplificada()->for($tenant, 'tenant')->create();

        return User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
    }

    public function test_la_pantalla_de_crear_ticket_no_expone_contexto_de_mesa_ni_opciones(): void
    {
        $user = $this->tenantConTicket();

        $this->loginAs($user);

        $respuesta = $this->get('/pos/crear')->assertOk();

        $respuesta->assertViewHas('hosteleriaActiva', false);
        $respuesta->assertViewHas('opcionesActivas', false);
        $respuesta->assertViewHas('cuentaPrecargada', null);
        $respuesta->assertViewHas('mesaPreseleccionada', null);

        // Marcadores de HTML, no texto: la guía de ayuda de la pantalla también se renderiza y
        // puede mencionar mesas sin que la interfaz las exponga.
        $respuesta->assertDontSee('id="pos-mesa-chip"', false);
        $respuesta->assertDontSee('id="posOpcionesModal"', false);
    }

    public function test_emitir_un_ticket_de_venta_directa_sigue_funcionando_igual(): void
    {
        $user = $this->tenantConTicket();

        $this->loginAs($user);

        $respuesta = $this->postJson('/pos', [
            'lineas' => [
                ['concepto' => 'Café', 'cantidad' => 2, 'precio_unitario' => 1.50, 'tipo_impositivo' => 10],
            ],
        ])->assertCreated();

        $anio = now()->year;
        $this->assertSame("S-{$anio}-0001", $respuesta->json('numero_completo'));

        // Ni una sola fila del módulo: la venta directa no crea cuenta ni cobro.
        $this->assertDatabaseCount('pos_cuentas', 0);
        $this->assertDatabaseCount('pos_cobros', 0);
    }

    public function test_con_el_modulo_apagado_ningun_articulo_declara_tener_opciones(): void
    {
        $user = $this->tenantConTicket();
        \App\Models\Articulo::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->loginAs($user);

        $articulos = $this->get('/pos/crear')->assertOk()->viewData('articulos');

        foreach ($articulos as $articulo) {
            $this->assertFalse((bool) $articulo->tiene_opciones);
        }
    }
}
