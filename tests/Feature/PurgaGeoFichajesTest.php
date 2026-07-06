<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RetencionGeoTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PurgaGeoFichajesTest extends TestCase
{
    use RefreshDatabase;

    public function test_purga_nulifica_columnas_de_geo_pero_conserva_la_fila(): void
    {
        $tenant = Tenant::factory()->create();
        Configuracion::factory()->for($tenant)->create(['clave' => RetencionGeoTenant::CLAVE, 'valor' => '30']);

        $userMiembro = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $userMiembro->id]);

        $antiguo = Fichaje::factory()->for($tenant)->dentro()->create([
            'miembro_equipo_id' => $miembro->id,
            'ocurrido_at' => now()->subDays(40),
        ]);
        $reciente = Fichaje::factory()->for($tenant)->dentro()->create([
            'miembro_equipo_id' => $miembro->id,
            'ocurrido_at' => now()->subDays(10),
        ]);

        Artisan::call('fichajes:purgar-geo');

        $antiguo->refresh();
        $this->assertNull($antiguo->resultado_ubicacion);
        $this->assertNull($antiguo->distancia_metros);
        $this->assertNull($antiguo->precision_metros);
        $this->assertDatabaseHas('fichajes', ['id' => $antiguo->id]);

        $reciente->refresh();
        $this->assertNotNull($reciente->resultado_ubicacion);
        $this->assertNotNull($reciente->distancia_metros);
    }

    public function test_retencion_separada_por_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Configuracion::factory()->for($tenantA)->create(['clave' => RetencionGeoTenant::CLAVE, 'valor' => '10']);
        // TenantB sin config -> usa el default (30 días): un fichaje de hace 20 días NO se purga.

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $miembroA = MiembroEquipo::factory()->for($tenantA)->create(['user_id' => $userA->id]);
        $fichajeA = Fichaje::factory()->for($tenantA)->dentro()->create(['miembro_equipo_id' => $miembroA->id, 'ocurrido_at' => now()->subDays(20)]);

        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $miembroB = MiembroEquipo::factory()->for($tenantB)->create(['user_id' => $userB->id]);
        $fichajeB = Fichaje::factory()->for($tenantB)->dentro()->create(['miembro_equipo_id' => $miembroB->id, 'ocurrido_at' => now()->subDays(20)]);

        Artisan::call('fichajes:purgar-geo');

        $fichajeA->refresh();
        $fichajeB->refresh();
        $this->assertNull($fichajeA->resultado_ubicacion);
        $this->assertNotNull($fichajeB->resultado_ubicacion);
    }
}
