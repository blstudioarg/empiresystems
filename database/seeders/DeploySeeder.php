<?php

namespace Database\Seeders;

use App\Enums\EstadoUsuario;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\CatalogoPermisos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Seed mínimo e idempotente para un despliegue nuevo (Railway u otro hosting), pensado para
 * correr desde el entrypoint del contenedor tras las migraciones.
 *
 * Crea SOLO lo imprescindible para poder entrar por primera vez:
 *   - Un super admin (sin tenant) que resuelve por el dominio central.
 *   - El catálogo global de permisos (feature 027), para que al crear tenants desde el panel
 *     el ProvisionadorRoles tenga permisos que sincronizar.
 *
 * NO crea un tenant demo: en un hosting real el tenant se resuelve por Host contra la tabla
 * `domains`, y un dominio de ejemplo no sería alcanzable. El super admin crea los tenants reales
 * desde el panel (flujo que ya provisiona roles correctamente).
 *
 * Las credenciales del super admin salen de env (ADMIN_EMAIL / ADMIN_PASSWORD) para no hardcodear
 * secretos; si no se definen, usa valores de arranque que DEBEN cambiarse tras el primer login.
 */
class DeploySeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@empiresystems.es')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'rol' => UserRole::SuperAdmin,
                'tenant_id' => null,
                'activo' => true,
                'estado' => EstadoUsuario::Aprobado,
            ]
        );

        // Catálogo global de permisos (idempotente: no duplica ni borra).
        foreach (CatalogoPermisos::claves() as $clave) {
            Permission::firstOrCreate(['name' => $clave, 'guard_name' => 'web']);
        }
    }
}
