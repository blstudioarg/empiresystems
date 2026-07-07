<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\CatalogoPermisos;
use App\Support\ProvisionadorRoles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Siembra el catálogo global de permisos (feature 027, RN-04). Idempotente: re-ejecutarlo no
 * duplica ni borra asignaciones. Tras sembrar, sincroniza el rol "Administrador" de cada tenant
 * con el catálogo completo, de modo que un permiso nuevo llegue automáticamente solo a ese rol
 * (los demás roles lo reciben opt-in por tenant).
 */
class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogoPermisos::claves() as $clave) {
            Permission::firstOrCreate(['name' => $clave, 'guard_name' => 'web']);
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $teamAnterior = $registrar->getPermissionsTeamId();

        Tenant::all()->each(function (Tenant $tenant) use ($registrar): void {
            $registrar->setPermissionsTeamId($tenant->getTenantKey());

            $rol = Role::where('tenant_id', $tenant->getTenantKey())
                ->where('name', ProvisionadorRoles::ROL_ADMINISTRADOR)
                ->first();

            $rol?->syncPermissions(CatalogoPermisos::claves());
        });

        $registrar->setPermissionsTeamId($teamAnterior);
        $registrar->forgetCachedPermissions();
    }
}
