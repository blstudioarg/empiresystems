<?php

namespace Tests\Feature;

use App\Models\PosMesa;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Support\ConfigPos;
use Database\Seeders\DemoPosHosteleriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garantías del seeder de demo del módulo de hostelería: activa el módulo, siembra zonas/mesas/
 * opciones y es idempotente. Mismo contrato que {@see DemoPruebaPosSeederTest}.
 */
class DemoPosHosteleriaSeederTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConDominioPruebapos(): Tenant
    {
        $tenant = Tenant::factory()->create(['nombre_comercial' => 'Prueba POS']);
        $tenant->domains()->create(['domain' => 'pruebapos.gestionley.com']);

        return $tenant;
    }

    public function test_activa_el_modulo_y_siembra_zonas_mesas_y_opciones(): void
    {
        $tenant = $this->tenantConDominioPruebapos();

        $this->seed(DemoPosHosteleriaSeeder::class);

        $this->assertTrue(ConfigPos::hosteleriaActivo($tenant->id));
        $this->assertTrue(ConfigPos::opcionesActivo($tenant->id));

        $this->assertSame(3, PosZona::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(6, PosMesa::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(2, PosOpcionGrupo::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, PosOpcion::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_no_toca_una_zona_preexistente_con_el_mismo_nombre(): void
    {
        $tenant = $this->tenantConDominioPruebapos();

        $zonaReal = PosZona::create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Comedor',
            'suplemento_porcentaje' => 99,
            'orden' => 5,
        ]);

        $this->seed(DemoPosHosteleriaSeeder::class);

        $zonaReal->refresh();
        $this->assertSame('99.00', (string) $zonaReal->suplemento_porcentaje);
    }

    public function test_es_idempotente_al_correrlo_dos_veces(): void
    {
        $tenant = $this->tenantConDominioPruebapos();

        $this->seed(DemoPosHosteleriaSeeder::class);
        $this->seed(DemoPosHosteleriaSeeder::class);

        $this->assertSame(3, PosZona::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(6, PosMesa::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, PosOpcion::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_no_hace_nada_si_no_existe_el_tenant(): void
    {
        $this->seed(DemoPosHosteleriaSeeder::class);

        $this->assertSame(0, PosZona::withoutGlobalScopes()->count());
    }
}
