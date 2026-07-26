<?php

use App\Models\Tenant;
use App\Support\ProvisionadorRoles;
use Database\Seeders\PermisosSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migración de datos (doc 09, Cambio 3): "Crear ticket" (POS) y "Nueva campaña" (Marketing) pasan a
 * permisos propios (ver-pos-crear, ver-campanas-crear). Roles personalizados con ver-pos reciben
 * ver-pos-crear; con ver-campanas reciben ver-campanas-crear. Roles base los resincroniza
 * PermisosSeeder (ninguno de los dos está excluido → el rol Usuario base también los recibe).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PermisosSeeder)->run();

        $registrar = app(PermissionRegistrar::class);

        Tenant::all()->each(function (Tenant $tenant) use ($registrar): void {
            $registrar->setPermissionsTeamId($tenant->getTenantKey());
            $registrar->forgetCachedPermissions();

            Role::where('tenant_id', $tenant->getTenantKey())
                ->whereNotIn('name', [ProvisionadorRoles::ROL_ADMINISTRADOR, ProvisionadorRoles::ROL_USUARIO])
                ->get()
                ->each(function (Role $rol): void {
                    if ($rol->hasPermissionTo('ver-pos')) {
                        $rol->givePermissionTo('ver-pos-crear');
                    }
                    if ($rol->hasPermissionTo('ver-campanas')) {
                        $rol->givePermissionTo('ver-campanas-crear');
                    }
                });

            $registrar->setPermissionsTeamId(null);
            $registrar->forgetCachedPermissions();
        });
    }

    public function down(): void
    {
        // Migración de datos: no reversible.
    }
};
