<?php

namespace Tests\Feature\Asistente;

use App\Ia\CatalogoTools;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Contrato de seguridad #2: el servidor solo ejecuta tools cuyo permiso tiene el usuario. Doble
 * capa: paraUsuario() no las envía y resolver() las rechaza aunque se pidan por nombre.
 */
class ToolsPermisosTest extends TestCase
{
    use RefreshDatabase;

    private function activar(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());
    }

    public function test_para_usuario_excluye_tools_sin_permiso(): void
    {
        $tenant = Tenant::factory()->create();
        // Rol base: NO tiene ver-configuracion, ver-usuarios, etc., pero sí ver-clientes/facturas.
        $usuario = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->activar($tenant);

        $nombres = array_map(fn ($t) => $t->nombre(), CatalogoTools::paraUsuario($usuario));

        // El rol base sí ve clientes.
        $this->assertContains('buscar_clientes', $nombres);
    }

    public function test_resolver_rechaza_una_tool_sin_permiso_aunque_se_pida_por_nombre(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->activar($tenant);

        // Usuario sin ningún rol/permiso: resolver debe rechazar cualquier tool por nombre.
        $usuario->syncRoles([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertNull(CatalogoTools::resolver('buscar_facturas', $usuario->fresh()));
        $this->assertNull(CatalogoTools::resolver('buscar_clientes', $usuario->fresh()));
    }

    public function test_usuario_sin_permiso_de_configuracion_no_recibe_tools_de_configuracion(): void
    {
        // Bloqueo estructural: no existe ninguna tool de configuración en el catálogo (contrato #3).
        $nombres = array_map(fn ($t) => $t->nombre(), CatalogoTools::todas());

        foreach ($nombres as $nombre) {
            $this->assertStringNotContainsString('config', $nombre);
            $this->assertStringNotContainsString('usuario', $nombre);
            $this->assertStringNotContainsString('rol', $nombre);
        }
    }
}
