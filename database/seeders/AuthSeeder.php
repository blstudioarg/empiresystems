<?php

namespace Database\Seeders;

use App\Enums\RegimenImpositivo;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ProvisionadorRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Domain;

class AuthSeeder extends Seeder
{
    /**
     * Credenciales de desarrollo. CAMBIAR antes de desplegar a producción.
     */
    public const SUPER_ADMIN_EMAIL = 'admin@empiresystems.es';

    public const SUPER_ADMIN_PASSWORD = 'password';

    public const DEMO_ADMIN_EMAIL = 'demo@empiresystems.es';

    public const DEMO_ADMIN_PASSWORD = 'password';

    public const DEMO_TENANT_DOMAIN = 'demo.test';

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => self::SUPER_ADMIN_EMAIL],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(self::SUPER_ADMIN_PASSWORD),
                'rol' => UserRole::SuperAdmin,
                'tenant_id' => null,
                'activo' => true,
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['nombre_comercial' => 'Empresa Demo SL'],
            [
                'regimen_impositivo' => RegimenImpositivo::Iva,
                'activo' => true,
            ]
        );

        Domain::firstOrCreate(
            ['domain' => self::DEMO_TENANT_DOMAIN],
            ['tenant_id' => $tenant->id]
        );

        $adminDemo = User::firstOrCreate(
            ['email' => self::DEMO_ADMIN_EMAIL],
            [
                'name' => 'Admin Demo',
                'password' => Hash::make(self::DEMO_ADMIN_PASSWORD),
                'rol' => UserRole::Admin,
                'tenant_id' => $tenant->id,
                'activo' => true,
            ]
        );

        // El enum `rol` no otorga permisos por sí solo: las rutas gatean por spatie (`can:...`).
        // Sin esto el tenant queda sin roles, porque la migración que los provisiona solo recorre
        // los tenants existentes en el momento de correr, y en una instalación limpia este seeder
        // va después.
        $provisionador = new ProvisionadorRoles;
        $provisionador->provisionarAdministrador($tenant, $adminDemo);
        $provisionador->provisionarUsuarioBase($tenant);
    }
}
