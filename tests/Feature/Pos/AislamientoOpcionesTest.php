<?php

namespace Tests\Feature\Pos;

use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Principio I: ni grupos ni opciones del tenant A son accesibles desde el B, tampoco por id
 * directo.
 */
class AislamientoOpcionesTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_el_listado_de_grupos_y_opciones_no_muestra_los_de_otro_tenant(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $otroTenant = Tenant::factory()->create();
        $grupoAjeno = PosOpcionGrupo::factory()->create(['tenant_id' => $otroTenant->id, 'nombre' => 'Ajeno']);
        PosOpcion::factory()->create(['tenant_id' => $otroTenant->id, 'grupo_id' => $grupoAjeno->id, 'nombre' => 'Opción ajena']);

        $grupoPropio = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id, 'nombre' => 'Propio']);
        PosOpcion::factory()->create(['tenant_id' => $this->tenantPos->id, 'grupo_id' => $grupoPropio->id, 'nombre' => 'Opción propia']);

        $grupos = $this->getJson('/pos/opciones/grupos')->assertOk()->json('data');
        $this->assertSame(['Propio'], array_column($grupos, 'nombre'));

        $opciones = $this->getJson('/pos/opciones')->assertOk()->json('data');
        $this->assertSame(['Opción propia'], array_column($opciones, 'nombre'));
    }

    public function test_un_grupo_o_una_opcion_de_otro_tenant_no_son_accesibles_por_id_directo(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $otroTenant = Tenant::factory()->create();
        $grupoAjeno = PosOpcionGrupo::factory()->create(['tenant_id' => $otroTenant->id]);
        $opcionAjena = PosOpcion::factory()->create(['tenant_id' => $otroTenant->id, 'grupo_id' => $grupoAjeno->id]);

        $this->putJson("/pos/opciones/grupos/{$grupoAjeno->id}", ['nombre' => 'Hackeado'])->assertNotFound();
        $this->deleteJson("/pos/opciones/grupos/{$grupoAjeno->id}")->assertNotFound();
        $this->putJson("/pos/opciones/{$opcionAjena->id}", ['grupo_id' => $grupoAjeno->id, 'nombre' => 'Hackeada'])->assertNotFound();
        $this->deleteJson("/pos/opciones/{$opcionAjena->id}")->assertNotFound();

        $this->assertDatabaseHas('pos_opcion_grupos', ['id' => $grupoAjeno->id, 'nombre' => $grupoAjeno->nombre]);
    }
}
