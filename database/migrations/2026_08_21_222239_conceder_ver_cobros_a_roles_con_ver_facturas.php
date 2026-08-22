<?php

use App\Models\Tenant;
use App\Support\ProvisionadorRoles;
use Database\Seeders\PermisosSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migración de datos (feature 043, research D7): las rutas de registro/anulación de pagos se
 * mueven del grupo can:ver-facturas al nuevo can:ver-cobros. Sin esta migración, un rol
 * personalizado ya existente en producción que tuviera ver-facturas (y por tanto podía cobrar
 * hasta ahora) dejaría de poder hacerlo — una regresión silenciosa. Se concede ver-cobros a todo
 * rol personalizado (≠ Administrador/Usuario) de cada tenant que ya tuviera ver-facturas.
 * Los roles base (Administrador/Usuario) ya reciben ver-cobros vía PermisosSeeder/sincronización.
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
                        $rol->givePermissionTo('ver-cobros');
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
