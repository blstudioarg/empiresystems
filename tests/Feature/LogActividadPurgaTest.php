<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Support\RetencionLogsTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogActividadPurgaTest extends TestCase
{
    use RefreshDatabase;

    public function test_purga_elimina_filas_mas_antiguas_que_la_retencion_por_defecto(): void
    {
        $tenant = Tenant::factory()->create();

        $vieja = LogActividad::factory()->for($tenant)->create([
            'ocurrido_at' => now()->subDays(RetencionLogsTenant::DEFAULT_RETENCION_DIAS + 1),
        ]);
        $reciente = LogActividad::factory()->for($tenant)->create([
            'ocurrido_at' => now()->subDays(10),
        ]);

        $this->artisan('logs:purgar')->assertExitCode(0);

        $this->assertFalse(LogActividad::withoutGlobalScopes()->whereKey($vieja->id)->exists());
        $this->assertTrue(LogActividad::withoutGlobalScopes()->whereKey($reciente->id)->exists());
    }

    public function test_purga_respeta_la_retencion_configurada_por_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        Configuracion::create([
            'tenant_id' => $tenant->id,
            'clave' => RetencionLogsTenant::CLAVE_RETENCION_DIAS,
            'valor' => '30',
            'tipo' => 'integer',
            'grupo' => 'seguridad',
        ]);

        $fueraDePlazoCorto = LogActividad::factory()->for($tenant)->create(['ocurrido_at' => now()->subDays(31)]);
        $dentroDePlazoCorto = LogActividad::factory()->for($tenant)->create(['ocurrido_at' => now()->subDays(29)]);

        $this->artisan('logs:purgar')->assertExitCode(0);

        $this->assertFalse(LogActividad::withoutGlobalScopes()->whereKey($fueraDePlazoCorto->id)->exists());
        $this->assertTrue(LogActividad::withoutGlobalScopes()->whereKey($dentroDePlazoCorto->id)->exists());
    }

    public function test_purga_no_mezcla_retenciones_entre_tenants(): void
    {
        $tenantCorto = Tenant::factory()->create();
        $tenantLargo = Tenant::factory()->create();

        Configuracion::create([
            'tenant_id' => $tenantCorto->id,
            'clave' => RetencionLogsTenant::CLAVE_RETENCION_DIAS,
            'valor' => '30',
            'tipo' => 'integer',
            'grupo' => 'seguridad',
        ]);

        $filaTenantCorto = LogActividad::factory()->for($tenantCorto)->create(['ocurrido_at' => now()->subDays(60)]);
        $filaTenantLargo = LogActividad::factory()->for($tenantLargo)->create(['ocurrido_at' => now()->subDays(60)]);

        $this->artisan('logs:purgar')->assertExitCode(0);

        $this->assertFalse(LogActividad::withoutGlobalScopes()->whereKey($filaTenantCorto->id)->exists());
        $this->assertTrue(LogActividad::withoutGlobalScopes()->whereKey($filaTenantLargo->id)->exists());
    }

    public function test_purga_procesa_por_lotes_sin_dejar_filas_antiguas(): void
    {
        $tenant = Tenant::factory()->create();

        LogActividad::factory()->for($tenant)->count(600)->create([
            'ocurrido_at' => now()->subDays(RetencionLogsTenant::DEFAULT_RETENCION_DIAS + 1),
        ]);

        $this->artisan('logs:purgar')->assertExitCode(0);

        $this->assertSame(0, LogActividad::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
