<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivoSubidaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `UploadedFile::fake()` (Illuminate\Http\Testing\File) sobreescribe getMimeType() para
     * devolver el tipo derivado del NOMBRE del archivo, no de su contenido real — inútil para
     * probar que el backend detecta un ejecutable renombrado a .pdf. Construimos un
     * `Illuminate\Http\UploadedFile` real (sin el wrapper de test) sobre un fichero físico con
     * contenido real, para que `getMimeType()` haga la detección real vía fileinfo, igual que en
     * producción.
     */
    private function archivoConContenidoReal(string $nombreVisible, string $contenido): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'archivo-test-');
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombreVisible, null, null, true);
    }

    private function pdfValido(int $bytesRelleno = 1024): string
    {
        return "%PDF-1.4\n%".str_repeat('A', $bytesRelleno)."\n%%EOF";
    }

    private function ejecutableRenombrado(): string
    {
        // Cabecera DOS/PE ("MZ") de un ejecutable real, nunca detectada como PDF/imagen/ofimática.
        return "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00\xB8\x00\x00\x00\x00\x00\x00\x00".str_repeat("\x00", 200);
    }

    public function test_acepta_un_pdf_de_la_lista_blanca(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $archivo = $this->archivoConContenidoReal('contrato.pdf', $this->pdfValido());

        $response = $this->post('/archivos', ['archivo' => $archivo], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertDatabaseHas('archivos', ['tenant_id' => $tenant->id, 'nombre' => 'contrato.pdf']);
    }

    public function test_rechaza_un_tipo_fuera_de_la_lista_blanca(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        // Zip genérico: no está en la lista blanca (no es docx/xlsx/pptx real).
        $archivo = $this->archivoConContenidoReal('paquete.zip', "PK\x03\x04".str_repeat("\x00", 100));

        $response = $this->post('/archivos', ['archivo' => $archivo], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('archivos', 0);
        Storage::disk('documentos')->assertDirectoryEmpty("tenants/{$tenant->id}/documentos");
    }

    public function test_rechaza_un_ejecutable_renombrado_a_pdf_por_mime_real(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $archivo = $this->archivoConContenidoReal('factura.pdf', $this->ejecutableRenombrado());

        $response = $this->post('/archivos', ['archivo' => $archivo], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('archivos', 0);
        Storage::disk('documentos')->assertDirectoryEmpty("tenants/{$tenant->id}/documentos");
    }

    public function test_rechaza_un_archivo_que_supera_el_limite_configurado(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        Configuracion::create([
            'tenant_id' => $tenant->id,
            'clave' => 'archivos.limite_mb',
            'valor' => '1',
            'tipo' => 'integer',
            'grupo' => 'archivos',
        ]);

        $this->loginAs($user);

        $archivo = $this->archivoConContenidoReal('grande.pdf', $this->pdfValido(2 * 1024 * 1024));

        $response = $this->post('/archivos', ['archivo' => $archivo], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('archivos', 0);
        Storage::disk('documentos')->assertDirectoryEmpty("tenants/{$tenant->id}/documentos");
    }
}
