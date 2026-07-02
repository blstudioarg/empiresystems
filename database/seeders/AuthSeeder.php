<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    /**
     * Credenciales de desarrollo. CAMBIAR antes de desplegar a producción.
     */
    public const SUPER_ADMIN_EMAIL = 'admin@empiresystems.es';

    public const SUPER_ADMIN_PASSWORD = 'password';

    public const DEMO_ADMIN_EMAIL = 'demo@empiresystems.es';

    public const DEMO_ADMIN_PASSWORD = 'password';

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
                'activo' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => self::DEMO_ADMIN_EMAIL],
            [
                'name' => 'Admin Demo',
                'password' => Hash::make(self::DEMO_ADMIN_PASSWORD),
                'rol' => UserRole::Admin,
                'tenant_id' => $tenant->id,
                'activo' => true,
            ]
        );
    }
}
