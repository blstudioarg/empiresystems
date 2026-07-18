<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AccesoPersonalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccesoPersonalSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'acceso_personal.superadmin_email' => 'superadmin-test@example.com',
            'acceso_personal.superadmin_password' => 'test-super-pass-123',
            'acceso_personal.tenant_nif' => 'B00000001',
            'acceso_personal.tenant_nombre' => 'Tenant Test Seeder',
            'acceso_personal.tenant_domain' => 'acceso-test.localhost',
            'acceso_personal.tenant_admin_email' => 'admin-test@example.com',
            'acceso_personal.tenant_admin_password' => 'test-admin-pass-123',
        ]);
    }

    public function test_crea_superadmin_tenant_y_admin_con_permisos_sincronizados(): void
    {
        $this->seed(AccesoPersonalSeeder::class);

        $super = User::where('email', 'superadmin-test@example.com')->first();
        $this->assertNotNull($super);
        $this->assertTrue($super->isSuperAdmin());
        $this->assertTrue(Hash::check('test-super-pass-123', $super->password));

        $tenant = Tenant::withoutGlobalScopes()->where('nif', 'B00000001')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('acceso-test.localhost', $tenant->domains()->first()->domain);

        $admin = User::where('email', 'admin-test@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals($tenant->id, $admin->tenant_id);
        $this->assertTrue(Hash::check('test-admin-pass-123', $admin->password));

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $this->assertTrue($admin->hasPermissionTo('ver-albaranes'));
    }

    public function test_es_idempotente_y_no_pisa_la_contrasena_ya_existente(): void
    {
        $this->seed(AccesoPersonalSeeder::class);

        $admin = User::where('email', 'admin-test@example.com')->first();
        $admin->forceFill(['password' => Hash::make('otra-contrasena-distinta')])->save();

        $this->seed(AccesoPersonalSeeder::class);

        $this->assertEquals(1, User::where('email', 'superadmin-test@example.com')->count());
        $this->assertEquals(1, User::where('email', 'admin-test@example.com')->count());
        $this->assertEquals(1, Tenant::withoutGlobalScopes()->where('nif', 'B00000001')->count());

        $admin->refresh();
        $this->assertTrue(Hash::check('otra-contrasena-distinta', $admin->password));
    }

    public function test_sin_contrasenas_configuradas_no_crea_nada(): void
    {
        config([
            'acceso_personal.superadmin_password' => null,
            'acceso_personal.tenant_admin_password' => null,
        ]);

        $this->seed(AccesoPersonalSeeder::class);

        $this->assertEquals(0, User::where('email', 'superadmin-test@example.com')->count());
    }
}
