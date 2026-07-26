<?php

use App\Models\Tenant;
use App\Support\ProvisionadorRoles;
use Database\Seeders\PermisosSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migración de datos (doc 09, Cambio 2): "Crear factura" pasa a ser un permiso propio
 * (ver-facturas-crear). Los roles personalizados que ya tenían ver-facturas conservan la capacidad
 * de crear recibiendo también ver-facturas-crear. Roles base los resincroniza PermisosSeeder
 * (ver-facturas-crear no está excluido → el rol Usuario base también lo recibe).
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
                    if ($rol->hasPermissionTo('ver-facturas')) {
                        $rol->givePermissionTo('ver-facturas-crear');
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
