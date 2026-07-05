<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\Carpeta;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivoCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_renombrar_cambia_solo_el_nombre_visible_y_el_contenido_no_cambia(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $ruta = "tenants/{$tenant->id}/documentos/original.pdf";
        Storage::disk('documentos')->put($ruta, 'contenido-original');

        $archivo = Archivo::factory()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Antes.pdf',
            'ruta' => $ruta,
        ]);

        $this->loginAs($user);

        $response = $this->put("/archivos/{$archivo->id}", ['nombre' => 'Despues.pdf'], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('archivos', ['id' => $archivo->id, 'nombre' => 'Despues.pdf', 'ruta' => $ruta]);
        $this->assertEquals('contenido-original', Storage::disk('documentos')->get($ruta));
    }

    public function test_mover_un_archivo_cambia_su_carpeta(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $carpetaOrigen = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Origen']);
        $carpetaDestino = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Destino']);
        $archivo = Archivo::factory()->create(['tenant_id' => $tenant->id, 'carpeta_id' => $carpetaOrigen->id]);

        $this->loginAs($user);

        $response = $this->put("/archivos/{$archivo->id}", ['carpeta_id' => $carpetaDestino->id], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('archivos', ['id' => $archivo->id, 'carpeta_id' => $carpetaDestino->id]);
    }

    public function test_preview_sirve_un_pdf_inline(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $ruta = "tenants/{$tenant->id}/documentos/vista.pdf";
        Storage::disk('documentos')->put($ruta, '%PDF-1.4 contenido');

        $archivo = Archivo::factory()->create([
            'tenant_id' => $tenant->id,
            'ruta' => $ruta,
            'extension' => 'pdf',
            'mime' => 'application/pdf',
        ]);

        $this->loginAs($user);

        $response = $this->get("/archivos/{$archivo->id}/preview");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_preview_sirve_una_imagen_inline(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $ruta = "tenants/{$tenant->id}/documentos/foto.png";
        Storage::disk('documentos')->put($ruta, 'contenido-imagen');

        $archivo = Archivo::factory()->create([
            'tenant_id' => $tenant->id,
            'ruta' => $ruta,
            'extension' => 'png',
            'mime' => 'image/png',
        ]);

        $this->loginAs($user);

        $response = $this->get("/archivos/{$archivo->id}/preview");

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_un_tipo_sin_preview_no_se_puede_previsualizar(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $ruta = "tenants/{$tenant->id}/documentos/doc.docx";
        Storage::disk('documentos')->put($ruta, 'contenido-docx');

        $archivo = Archivo::factory()->create([
            'tenant_id' => $tenant->id,
            'ruta' => $ruta,
            'extension' => 'docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);

        $this->loginAs($user);

        $this->get("/archivos/{$archivo->id}/preview")->assertNotFound();
    }

    public function test_borrar_hace_soft_delete_y_elimina_el_fichero_fisico(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $ruta = "tenants/{$tenant->id}/documentos/borrame.pdf";
        Storage::disk('documentos')->put($ruta, 'contenido');

        $archivo = Archivo::factory()->create(['tenant_id' => $tenant->id, 'ruta' => $ruta]);

        $this->loginAs($user);

        $response = $this->delete("/archivos/{$archivo->id}", [], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertSoftDeleted($archivo);
        Storage::disk('documentos')->assertMissing($ruta);
    }
}
