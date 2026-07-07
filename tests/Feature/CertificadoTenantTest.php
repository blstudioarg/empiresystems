<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificadoTenantTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'test1234';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
    }

    private function contenidoFixture(string $nombre): string
    {
        return file_get_contents(base_path("tests/Fixtures/facturae/{$nombre}"));
    }

    private function subirCertificado(string $nombre, string $password): \Illuminate\Testing\TestResponse
    {
        $archivo = UploadedFile::fake()->createWithContent($nombre, $this->contenidoFixture($nombre));

        return $this->patch('/configuracion/certificado', [
            'certificado' => $archivo,
            'password' => $password,
        ]);
    }

    public function test_subida_valida_guarda_archivo_y_password_cifrada_y_muestra_titular_caducidad(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $response = $this->subirCertificado('certificado.p12', self::PASSWORD);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => 'certificado.archivo_path',
        ]);

        $passwordGuardada = \App\Models\Configuracion::query()
            ->where('tenant_id', $tenant->id)
            ->where('clave', 'certificado.password')
            ->value('valor');

        $this->assertNotSame(self::PASSWORD, $passwordGuardada);
        $this->assertSame(self::PASSWORD, \Illuminate\Support\Facades\Crypt::decryptString($passwordGuardada));

        $show = $this->get('/configuracion');
        $show->assertOk();
        $show->assertSee('Empresa Demo Test SL');
    }

    public function test_password_incorrecta_se_rechaza_y_no_guarda_nada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $response = $this->subirCertificado('certificado.p12', 'password-incorrecta');

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => 'certificado.archivo_path',
        ]);
    }

    public function test_certificado_caducado_se_rechaza(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $response = $this->subirCertificado('certificado-caducado.p12', self::PASSWORD);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => 'certificado.archivo_path',
        ]);
    }

    public function test_aislamiento_tenant_a_no_ve_ni_usa_el_certificado_de_b(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userB = User::factory()->admin()->create(['tenant_id' => $tenantB->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userB);
        $this->subirCertificado('certificado.p12', self::PASSWORD);

        $this->assertDatabaseHas('configuraciones', [
            'tenant_id' => $tenantB->id,
            'clave' => 'certificado.archivo_path',
        ]);
        $this->assertDatabaseMissing('configuraciones', [
            'tenant_id' => $tenantA->id,
            'clave' => 'certificado.archivo_path',
        ]);

        $userA = User::factory()->admin()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userA);

        $show = $this->get('/configuracion');
        $show->assertOk();
        $show->assertDontSee('Empresa Demo Test SL');
    }
}
