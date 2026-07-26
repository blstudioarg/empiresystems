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
 * con el catálogo completo y el rol "Usuario" con `clavesUsuarioBase()` (feature 033, T005), de
 * modo que un permiso nuevo del rol base (como `ver-informes-comerciales`) llegue automáticamente
 * a los tenants ya existentes sin tocar los permisos que un administrador ya haya quitado a otros
 * roles distintos de estos dos.
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

            $rolAdmin = Role::where('tenant_id', $tenant->getTenantKey())
                ->where('name', ProvisionadorRoles::ROL_ADMINISTRADOR)
                ->first();
            $rolAdmin?->syncPermissions(CatalogoPermisos::claves());

            $rolUsuario = Role::where('tenant_id', $tenant->getTenantKey())
                ->where('name', ProvisionadorRoles::ROL_USUARIO)
                ->first();
            $rolUsuario?->syncPermissions(CatalogoPermisos::clavesUsuarioBase());
        });

        $registrar->setPermissionsTeamId($teamAnterior);
        $registrar->forgetCachedPermissions();
    }
}
