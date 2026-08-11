<?php

namespace Tests\Feature\Pos;

use App\Models\PosMesa;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * FR-007 (edge case del spec): apagar el módulo o una capacidad **oculta, no borra**. Zonas,
 * mesas, grupos y opciones siguen ahí y reaparecen intactos al reactivar.
 *
 * Sin este test, "desactivar" podría implementarse alguna vez como un borrado y nadie se
 * enteraría hasta que un cliente perdiera la configuración de su sala entera.
 */
class CapacidadDesactivadaConservaDatosTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_apagar_el_modulo_no_borra_zonas_mesas_grupos_ni_opciones(): void
    {
        $this->sembrarPermisos();

        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Admin', ['ver-configuracion', 'ver-pos-sala']));

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true, 'opciones_activo' => true]);

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Comedor', 'suplemento_porcentaje' => 7.5]);
        PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'nombre' => 'Mesa 4']);
        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Punto de cocción']);
        PosOpcion::factory()->create(['tenant_id' => $tenant->id, 'grupo_id' => $grupo->id, 'nombre' => 'Al punto']);

        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', [
            'hosteleria_activo' => 0,
            'opciones_activo' => 0,
            'cobro_dividido_activo' => 0,
            'suplemento_zona_activo' => 0,
            'mesa_olvidada_min' => 45,
        ])->assertOk();

        // Los datos siguen en base de datos, sin soft-delete ni nada parecido.
        $this->assertDatabaseHas('pos_zonas', ['tenant_id' => $tenant->id, 'nombre' => 'Comedor', 'deleted_at' => null]);
        $this->assertDatabaseHas('pos_mesas', ['tenant_id' => $tenant->id, 'nombre' => 'Mesa 4', 'deleted_at' => null]);
        $this->assertDatabaseHas('pos_opcion_grupos', ['tenant_id' => $tenant->id, 'nombre' => 'Punto de cocción', 'deleted_at' => null]);
        $this->assertDatabaseHas('pos_opciones', ['tenant_id' => $tenant->id, 'nombre' => 'Al punto', 'deleted_at' => null]);

        // Y al reactivar, la sala vuelve tal cual estaba, incluido el suplemento de la zona.
        $this->putJson('/configuracion/pos', [
            'hosteleria_activo' => 1,
            'opciones_activo' => 1,
            'cobro_dividido_activo' => 0,
            'suplemento_zona_activo' => 1,
            'mesa_olvidada_min' => 45,
        ])->assertOk();

        $json = $this->getJson('/pos/sala')->assertOk()->json();

        $this->assertSame('Comedor', $json['zonas'][0]['nombre']);
        $this->assertSame('7.50', $json['zonas'][0]['suplemento']);
        $this->assertSame('Mesa 4', $json['mesas'][0]['nombre']);
        $this->assertSame('libre', $json['mesas'][0]['estado']);
    }

    public function test_apagar_solo_la_capacidad_de_opciones_conserva_el_recetario(): void
    {
        $this->sembrarPermisos();

        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Admin', ['ver-configuracion', 'ver-pos-opciones']));

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true, 'opciones_activo' => true]);
        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Guarnición']);
        PosOpcion::factory()->create(['tenant_id' => $tenant->id, 'grupo_id' => $grupo->id, 'nombre' => 'Patatas']);

        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', [
            'hosteleria_activo' => 1,
            'opciones_activo' => 0,
            'cobro_dividido_activo' => 0,
            'suplemento_zona_activo' => 0,
            'mesa_olvidada_min' => 45,
        ])->assertOk();

        // La pantalla deja de existir…
        $this->get('/pos/opciones')->assertNotFound();

        // …pero los datos no se han tocado.
        $this->assertDatabaseHas('pos_opciones', ['tenant_id' => $tenant->id, 'nombre' => 'Patatas', 'deleted_at' => null]);
    }
}
