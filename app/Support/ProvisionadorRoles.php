<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea/sincroniza los roles base de un tenant (feature 027, research.md D6). Se usa en tres
 * puntos: alta de tenant (contexto central), migración de datos de tenants existentes y tests.
 *
 * Como el alta corre en contexto central, cada operación fija temporalmente el team de spatie
 * al tenant destino para que los roles y las asignaciones se escriban con el `tenant_id` correcto,
 * restaurando el team anterior al terminar.
 */
class ProvisionadorRoles
{
    public const ROL_ADMINISTRADOR = 'Administrador';

    public const ROL_USUARIO = 'Usuario';

    /**
     * Rol "Administrador" del tenant con TODOS los permisos del catálogo, asignado al admin dado.
     */
    public function provisionarAdministrador(Tenant $tenant, ?User $admin = null): Role
    {
        return $this->enContextoDeTenant($tenant, function () use ($tenant, $admin): Role {
            $rol = Role::firstOrCreate([
                'name' => self::ROL_ADMINISTRADOR,
                'guard_name' => 'web',
                'tenant_id' => $tenant->getTenantKey(),
            ]);

            $rol->syncPermissions(CatalogoPermisos::claves());

            if ($admin) {
                $admin->syncRoles([$rol]);
            }

            return $rol;
        });
    }

    /**
     * Rol "Usuario" base del tenant (permisos RN-07), marcado como rol por defecto.
     */
    public function provisionarUsuarioBase(Tenant $tenant): Role
    {
        return $this->enContextoDeTenant($tenant, function () use ($tenant): Role {
            $rol = Role::firstOrCreate([
                'name' => self::ROL_USUARIO,
                'guard_name' => 'web',
                'tenant_id' => $tenant->getTenantKey(),
            ]);

            $rol->syncPermissions(CatalogoPermisos::clavesUsuarioBase());

            if (! $rol->es_defecto) {
                $rol->forceFill(['es_defecto' => true])->save();
            }

            return $rol;
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function enContextoDeTenant(Tenant $tenant, Closure $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenant->getTenantKey());

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($anterior);
            $registrar->forgetCachedPermissions();
        }
    }
}
