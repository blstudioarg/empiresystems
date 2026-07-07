<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ProvisionadorRoles;
use Database\Seeders\PermisosSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migración de datos (feature 027, FR-008): siembra el catálogo de permisos y provisiona los
 * roles "Administrador" y "Usuario" en cada tenant ya existente, asignando a cada usuario según
 * su enum `rol` actual (admin → Administrador, usuario → Usuario). Usuarios centrales (sin
 * tenant_id) se omiten. Garantiza equivalencia de accesos tras la migración (SC-005).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PermisosSeeder)->run();

        $provisionador = new ProvisionadorRoles;
        $registrar = app(PermissionRegistrar::class);

        Tenant::all()->each(function (Tenant $tenant) use ($provisionador, $registrar): void {
            $rolAdmin = $provisionador->provisionarAdministrador($tenant);
            $rolUsuario = $provisionador->provisionarUsuarioBase($tenant);

            $registrar->setPermissionsTeamId($tenant->getTenantKey());

            User::where('tenant_id', $tenant->id)->each(function (User $user) use ($rolAdmin, $rolUsuario): void {
                $rol = $user->rol === UserRole::Admin ? $rolAdmin : $rolUsuario;
                $user->assignRole($rol);
            });

            $registrar->setPermissionsTeamId(null);
            $registrar->forgetCachedPermissions();
        });
    }

    public function down(): void
    {
        // Migración de datos: no reversible (no se recrea el estado previo a spatie).
    }
};
