<?php

namespace Tests\Feature\Asistente;

use App\Ia\CatalogoTools;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Contrato de seguridad #1: toda tool de lectura opera bajo el TenantScope del request y ninguna
 * acepta tenant_id en su schema. Con 2 tenants sembrados, cada tool solo ve datos del suyo.
 */
class ToolsAislamientoTest extends TestCase
{
    use RefreshDatabase;

    private function activar(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());
    }

    public function test_buscar_clientes_solo_ve_datos_del_tenant_activo(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Cliente::factory()->for($tenantA)->create(['nombre' => 'Cliente-de-A', 'nif' => 'B11111111']);
        Cliente::factory()->for($tenantB)->create(['nombre' => 'Cliente-de-B', 'nif' => 'B22222222']);

        $this->activar($tenantA);
        $resultado = (new \App\Ia\Tools\BuscarClientes)->ejecutar(['texto' => 'Cliente']);

        $nombres = array_column($resultado['clientes'], 'nombre');
        $this->assertContains('Cliente-de-A', $nombres);
        $this->assertNotContains('Cliente-de-B', $nombres);
    }

    public function test_ninguna_tool_acepta_tenant_id_en_su_schema(): void
    {
        foreach (CatalogoTools::todas() as $tool) {
            $props = $tool->schema()['properties'] ?? [];
            $claves = is_array($props) ? array_keys((array) $props) : [];

            $this->assertNotContains('tenant_id', $claves, "La tool {$tool->nombre()} no debe aceptar tenant_id.");
        }
    }
}
