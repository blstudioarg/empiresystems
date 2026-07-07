<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfiguracionAparienciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardar_colores_persiste_las_claves_del_tenant_activo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $response = $this->put('/configuracion/apariencia', [
            'color_primario' => '#112233',
            'color_secundario' => '#445566',
            'color_topbar' => '#778899',
        ]);

        $response->assertRedirect(route('configuracion.show'));

        $this->assertDatabaseHas('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => 'apariencia.color_primario',
            'valor' => '#112233',
        ]);
        $this->assertDatabaseHas('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => 'apariencia.color_secundario',
            'valor' => '#445566',
        ]);
        $this->assertDatabaseHas('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => 'apariencia.color_topbar',
            'valor' => '#778899',
        ]);
    }

    public function test_un_color_con_formato_invalido_devuelve_error_de_validacion_y_no_persiste(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $response = $this->put('/configuracion/apariencia', [
            'color_primario' => 'no-es-un-color',
        ]);

        $response->assertSessionHasErrors('color_primario');

        $this->assertDatabaseMissing('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => 'apariencia.color_primario',
        ]);
    }

    public function test_get_configuracion_sin_autenticar_redirige_a_login(): void
    {
        $response = $this->get('/configuracion');

        $response->assertRedirect('/login');
    }

    public function test_restablecer_borra_las_claves_de_color_del_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        Configuracion::factory()->create([
            'tenant_id' => $tenant->id,
            'clave' => 'apariencia.color_primario',
        ]);

        $this->loginAs($user);

        $response = $this->put('/configuracion/apariencia', [
            'restablecer' => '1',
        ]);

        $response->assertRedirect(route('configuracion.show'));

        $this->assertDatabaseMissing('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => 'apariencia.color_primario',
        ]);
    }

    public function test_subir_logo_valido_guarda_el_fichero_y_setea_logo_path(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $logo = UploadedFile::fake()->image('logo.png', 100, 100)->size(200);

        $response = $this->put('/configuracion/apariencia', [
            'logo' => $logo,
        ]);

        $response->assertRedirect(route('configuracion.show'));

        $tenant->refresh();
        $this->assertNotNull($tenant->logo_path);
        Storage::disk('public')->assertExists($tenant->logo_path);
    }

    public function test_subir_archivo_no_imagen_devuelve_error_y_no_cambia_logo_path(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create(['logo_path' => null]);
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $archivo = UploadedFile::fake()->create('documento.txt', 100);

        $response = $this->put('/configuracion/apariencia', [
            'logo' => $archivo,
        ]);

        $response->assertSessionHasErrors('logo');

        $tenant->refresh();
        $this->assertNull($tenant->logo_path);
    }

    public function test_subir_logo_mini_valido_guarda_el_fichero_y_setea_logo_mini_path(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $logoMini = UploadedFile::fake()->image('logo-mini.png', 40, 40)->size(100);

        $response = $this->put('/configuracion/apariencia', [
            'logo_mini' => $logoMini,
        ]);

        $response->assertRedirect(route('configuracion.show'));

        $tenant->refresh();
        $this->assertNotNull($tenant->logo_mini_path);
        Storage::disk('public')->assertExists($tenant->logo_mini_path);
    }

    public function test_subir_login_logo_valido_guarda_el_fichero_y_setea_login_logo_path(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $loginLogo = UploadedFile::fake()->image('login-logo.png', 100, 100)->size(200);

        $response = $this->put('/configuracion/apariencia', [
            'login_logo' => $loginLogo,
        ]);

        $response->assertRedirect(route('configuracion.show'));

        $tenant->refresh();
        $this->assertNotNull($tenant->login_logo_path);
        Storage::disk('public')->assertExists($tenant->login_logo_path);
    }

    public function test_subir_login_imagen_valida_guarda_el_fichero_y_setea_login_imagen_path(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $loginImagen = UploadedFile::fake()->image('login-imagen.png', 600, 800)->size(500);

        $response = $this->put('/configuracion/apariencia', [
            'login_imagen' => $loginImagen,
        ]);

        $response->assertRedirect(route('configuracion.show'));

        $tenant->refresh();
        $this->assertNotNull($tenant->login_imagen_path);
        Storage::disk('public')->assertExists($tenant->login_imagen_path);
    }

    public function test_restablecer_borra_los_cuatro_logos(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create([
            'logo_path' => 'logos/1/logo.png',
            'logo_mini_path' => 'logos/1/logo-mini.png',
            'login_logo_path' => 'logos/1/login-logo.png',
            'login_imagen_path' => 'logos/1/login-imagen.png',
        ]);
        Storage::disk('public')->put($tenant->logo_path, 'contenido');
        Storage::disk('public')->put($tenant->logo_mini_path, 'contenido');
        Storage::disk('public')->put($tenant->login_logo_path, 'contenido');
        Storage::disk('public')->put($tenant->login_imagen_path, 'contenido');

        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $response = $this->put('/configuracion/apariencia', [
            'restablecer' => '1',
        ]);

        $response->assertRedirect(route('configuracion.show'));

        $tenant->refresh();
        $this->assertNull($tenant->logo_path);
        $this->assertNull($tenant->logo_mini_path);
        $this->assertNull($tenant->login_logo_path);
        $this->assertNull($tenant->login_imagen_path);
    }

    public function test_get_configuracion_autenticado_muestra_navegacion_de_tabs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $response = $this->get('/configuracion');

        $response->assertOk();
        $response->assertSee('Apariencia / Marca');
    }
}
