<?php

namespace Tests\Concerns;

use App\Enums\EstadoUsuario;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Helpers para tests de la feature 027: siembra del catálogo y creación de roles/usuarios de
 * tenant fijando el team de spatie correcto (particionado por tenant_id).
 */
trait GestionaRolesDeTenant
{
    protected function sembrarPermisos(): void
    {
        $this->seed(PermisosSeeder::class);
    }

    /**
     * @param  list<string>  $permisos
     */
    protected function crearRol(Tenant $tenant, string $nombre, array $permisos, bool $esDefecto = false): Role
    {
        return $this->enTeam($tenant, function () use ($tenant, $nombre, $permisos, $esDefecto): Role {
            $rol = Role::firstOrCreate([
                'name' => $nombre,
                'guard_name' => 'web',
                'tenant_id' => $tenant->getTenantKey(),
            ]);
            $rol->syncPermissions($permisos);

            if ($esDefecto) {
                $rol->forceFill(['es_defecto' => true])->save();
            }

            return $rol;
        });
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function usuarioConRol(Tenant $tenant, ?Role $rol, array $attrs = []): User
    {
        // make()->save() en vez de create(): evita el hook afterCreating del factory que
        // auto-provisiona el rol "Usuario" base (SC-005) — estos tests controlan explícitamente
        // qué roles existen en el tenant y cuántos, así que ese efecto secundario rompería los
        // conteos esperados.
        $user = User::factory()->make(array_merge([
            'tenant_id' => $tenant->id,
            'rol' => UserRole::Usuario,
            'estado' => EstadoUsuario::Aprobado,
            'activo' => true,
            'password' => bcrypt('secret123'),
        ], $attrs));
        $user->save();

        if ($rol) {
            $this->enTeam($tenant, fn () => $user->syncRoles([$rol]));
        }

        return $user;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    protected function enTeam(Tenant $tenant, \Closure $callback): mixed
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
