<?php

namespace Database\Seeders;

use App\Enums\EstadoUsuario;
use App\Enums\RegimenImpositivo;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ProvisionadorRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Garantiza que el acceso personal de desarrollo (superadmin + tenant "Empire Demo" + su admin)
 * siempre exista. Las credenciales reales NO viven en este archivo (se commitea a git): salen de
 * `config('acceso_personal.*')`, que a su vez lee de variables de entorno (`.env`, no versionado)
 * — ver `ACCESOS.local.md` (tampoco versionado) para los valores documentados.
 *
 * Idempotente por diseño: usa `firstOrCreate` con la clave única de cada modelo, así que volver a
 * correrlo NO pisa la contraseña si el usuario ya existe (p. ej. si se cambió a mano desde la app)
 * — solo recrea lo que falte tras un `migrate:fresh` u otra pérdida de datos.
 *
 * Uso: `php artisan db:seed --class=AccesoPersonalSeeder`.
 */
class AccesoPersonalSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminEmail = config('acceso_personal.superadmin_email');
        $superAdminPassword = config('acceso_personal.superadmin_password');

        $tenantNif = config('acceso_personal.tenant_nif');
        $tenantNombreComercial = config('acceso_personal.tenant_nombre');
        $tenantDomain = config('acceso_personal.tenant_domain');
        $tenantAdminEmail = config('acceso_personal.tenant_admin_email');
        $tenantAdminPassword = config('acceso_personal.tenant_admin_password');

        if (! $superAdminPassword || ! $tenantAdminPassword) {
            $this->command?->warn(
                'AccesoPersonalSeeder: faltan ACCESO_PERSONAL_SUPERADMIN_PASSWORD / '
                .'ACCESO_PERSONAL_TENANT_ADMIN_PASSWORD en .env — no se crea/actualiza ningún usuario.'
            );

            return;
        }

        // El catálogo de permisos debe existir antes de sincronizar roles (PermissionDoesNotExist
        // si no); idempotente, seguro de re-correr.
        $this->call(PermisosSeeder::class);

        User::firstOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($superAdminPassword),
                'rol' => UserRole::SuperAdmin,
                'tenant_id' => null,
                'activo' => true,
                'estado' => EstadoUsuario::Aprobado,
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['nif' => $tenantNif],
            [
                'nombre_comercial' => $tenantNombreComercial,
                'razon_social' => $tenantNombreComercial,
                'regimen_impositivo' => RegimenImpositivo::Iva,
                'email' => $tenantAdminEmail,
                'activo' => true,
            ]
        );

        Domain::firstOrCreate(
            ['domain' => $tenantDomain],
            ['tenant_id' => $tenant->id]
        );

        $admin = User::firstOrCreate(
            ['email' => $tenantAdminEmail],
            [
                'name' => 'Administrador',
                'password' => Hash::make($tenantAdminPassword),
                'rol' => UserRole::Admin,
                'tenant_id' => $tenant->id,
                'estado' => EstadoUsuario::Aprobado,
                'activo' => true,
            ]
        );

        // Idempotente y beneficioso re-correrlo: sincroniza el rol Administrador con el catálogo
        // de permisos completo (p. ej. si se agregó un permiso nuevo, como `ver-albaranes`).
        $provisionador = app(ProvisionadorRoles::class);
        $provisionador->provisionarAdministrador($tenant, $admin);
        $provisionador->provisionarUsuarioBase($tenant);
    }
}
