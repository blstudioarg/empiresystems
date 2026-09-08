<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompraDocumentoSubidaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function tenantConIa(): Tenant
    {
        $tenant = Tenant::factory()->create();
        IaTenant::guardarApiKey('sk-de-prueba', $tenant->id);

        return $tenant;
    }

    private function admin(Tenant $tenant): User
    {
        return User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
    }

    private function pdf(string $nombre = 'factura.pdf', int $kb = 20): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, $kb, 'application/pdf');
    }

    public function test_subir_devuelve_un_token_por_documento_valido(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs($this->admin($tenant));

        $respuesta = $this->postJson('/compras/documentos', [
            'archivos' => [$this->pdf('factura-marzo.pdf'), $this->pdf('albaran.pdf')],
        ]);

        $respuesta->assertOk();
        $respuesta->assertJsonCount(2, 'documentos');
        $respuesta->assertJsonPath('documentos.0.archivo_nombre', 'factura-marzo.pdf');
        $respuesta->assertJsonPath('documentos.0.formato', 'pdf');
        $this->assertNotEmpty($respuesta->json('documentos.0.token'));
    }

    public function test_rechaza_un_tipo_de_archivo_no_admitido(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs($this->admin($tenant));

        $respuesta = $this->postJson('/compras/documentos', [
            'archivos' => [UploadedFile::fake()->create('hoja.xlsx', 10)],
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('archivos.0');
    }

    public function test_rechaza_un_archivo_que_supera_el_tamano_maximo(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs($this->admin($tenant));
        config(['compras.documentos.max_mb' => 1]);

        $respuesta = $this->postJson('/compras/documentos', [
            'archivos' => [$this->pdf('gordo.pdf', 2048)],
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('archivos.0');
    }

    public function test_rechaza_un_lote_con_mas_ficheros_del_maximo(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs($this->admin($tenant));
        config(['compras.documentos.max_ficheros' => 2]);

        $respuesta = $this->postJson('/compras/documentos', [
            'archivos' => [$this->pdf('a.pdf'), $this->pdf('b.pdf'), $this->pdf('c.pdf')],
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('archivos');
    }

    /**
     * FR-004: el PDF largo se rechaza por nombre y motivo, sin invalidar los demás del lote.
     */
    public function test_un_pdf_con_demasiadas_paginas_se_rechaza_pero_el_resto_del_lote_sigue(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs($this->admin($tenant));
        config(['compras.documentos.max_paginas_pdf' => 2]);

        $largo = UploadedFile::fake()->createWithContent(
            'catalogo.pdf',
            "%PDF-1.4\n<< /Type /Pages /Count 40 >>\n",
        );

        $respuesta = $this->postJson('/compras/documentos', [
            'archivos' => [$largo, UploadedFile::fake()->createWithContent('ok.pdf', "%PDF-1.4\n/Type /Page\n")],
        ]);

        $respuesta->assertOk();
        $respuesta->assertJsonCount(1, 'documentos');
        $respuesta->assertJsonCount(1, 'rechazados');
        $respuesta->assertJsonPath('rechazados.0.archivo_nombre', 'catalogo.pdf');
        $this->assertStringContainsString('páginas', $respuesta->json('rechazados.0.motivo'));
    }

    public function test_un_lote_enteramente_rechazado_responde_422(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs($this->admin($tenant));
        config(['compras.documentos.max_paginas_pdf' => 2]);

        $respuesta = $this->postJson('/compras/documentos', [
            'archivos' => [UploadedFile::fake()->createWithContent('catalogo.pdf', "%PDF-1.4\n<< /Type /Pages /Count 40 >>\n")],
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonCount(0, 'documentos');
        $respuesta->assertJsonCount(1, 'rechazados');
    }

    public function test_sin_clave_de_ia_responde_422_con_codigo_propio(): void
    {
        $tenant = Tenant::factory()->create(); // sin guardarApiKey
        $this->loginAs($this->admin($tenant));

        $respuesta = $this->postJson('/compras/documentos', ['archivos' => [$this->pdf()]]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonPath('codigo', 'ia_no_configurada');
    }

    public function test_sin_permiso_ver_compras_responde_403(): void
    {
        $tenant = $this->tenantConIa();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);
        $user->syncRoles([]);

        $respuesta = $this->postJson('/compras/documentos', ['archivos' => [$this->pdf()]]);

        $respuesta->assertStatus(403);
    }

    /**
     * FR-012: descartar borra el temporal, es idempotente y no toca la base de datos.
     */
    public function test_descartar_borra_el_temporal_y_es_idempotente(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs($this->admin($tenant));

        $token = $this->postJson('/compras/documentos', ['archivos' => [$this->pdf()]])->json('documentos.0.token');

        $this->deleteJson("/compras/documentos/{$token}")->assertOk();

        // Segundo descarte del mismo token: también 200, no un 404.
        $this->deleteJson("/compras/documentos/{$token}")->assertOk();

        $this->assertDatabaseCount('compras', 0);
    }
}
