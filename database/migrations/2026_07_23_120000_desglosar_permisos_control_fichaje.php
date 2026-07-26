<?php

use App\Models\Tenant;
use App\Support\ProvisionadorRoles;
use Database\Seeders\PermisosSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migración de datos (doc 09, Cambio 1): desglosa "Control de fichaje" (antes el único permiso
 * ver-jornada) en un permiso por subvista. Los roles PERSONALIZADOS que tenían ver-jornada
 * conservan el acceso al bloque completo recibiendo también ver-calendario/ver-miembros/
 * ver-horarios/ver-alertas. Los roles base (Administrador → catálogo completo; Usuario → base) los
 * resincroniza PermisosSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PermisosSeeder)->run();

        $registrar = app(PermissionRegistrar::class);
        $nuevos = ['ver-calendario', 'ver-miembros', 'ver-horarios', 'ver-alertas'];

        Tenant::all()->each(function (Tenant $tenant) use ($registrar, $nuevos): void {
            $registrar->setPermissionsTeamId($tenant->getTenantKey());
            $registrar->forgetCachedPermissions();

            Role::where('tenant_id', $tenant->getTenantKey())
                ->whereNotIn('name', [ProvisionadorRoles::ROL_ADMINISTRADOR, ProvisionadorRoles::ROL_USUARIO])
                ->get()
                ->each(function (Role $rol) use ($nuevos): void {
                    if ($rol->hasPermissionTo('ver-jornada')) {
                        $rol->givePermissionTo($nuevos);
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
