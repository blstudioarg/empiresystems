<?php

use App\Models\Tenant;
use App\Support\ProvisionadorRoles;
use Database\Seeders\PermisosSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migración de datos (doc 09, Cambio 5): "Fichar" y "Mi jornada" pasan de secciones universales
 * (sin permiso) a permisos gateables (ver-fichar, ver-mi-jornada). Como antes el acceso era de
 * TODOS los usuarios, se conceden a TODOS los roles existentes para no quitarle el acceso a nadie
 * (no solo a los que tenían algún permiso concreto). Roles base vía PermisosSeeder (no excluidos →
 * el rol Usuario base también los recibe); roles personalizados en el bucle.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PermisosSeeder)->run();

        $registrar = app(PermissionRegistrar::class);
        $nuevos = ['ver-fichar', 'ver-mi-jornada'];

        Tenant::all()->each(function (Tenant $tenant) use ($registrar, $nuevos): void {
            $registrar->setPermissionsTeamId($tenant->getTenantKey());
            $registrar->forgetCachedPermissions();

            Role::where('tenant_id', $tenant->getTenantKey())
                ->whereNotIn('name', [ProvisionadorRoles::ROL_ADMINISTRADOR, ProvisionadorRoles::ROL_USUARIO])
                ->get()
                ->each(function (Role $rol) use ($nuevos): void {
                    $rol->givePermissionTo($nuevos);
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
