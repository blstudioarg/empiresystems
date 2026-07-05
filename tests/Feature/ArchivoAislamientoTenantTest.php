<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivoAislamientoTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_de_un_tenant_no_incluye_archivos_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        Archivo::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'De A.pdf']);
        Archivo::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'De B.pdf']);

        $this->loginAs($userA);

        $response = $this->getJson('/archivos');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'De A.pdf']);
        $response->assertJsonMissing(['nombre' => 'De B.pdf']);
    }

    public function test_no_se_puede_descargar_un_archivo_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $archivoB = Archivo::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->get("/archivos/{$archivoB->id}/descargar")->assertNotFound();
    }

    public function test_no_se_puede_previsualizar_un_archivo_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $archivoB = Archivo::factory()->create(['tenant_id' => $tenantB->id, 'extension' => 'pdf', 'mime' => 'application/pdf']);

        $this->loginAs($userA);

        $this->get("/archivos/{$archivoB->id}/preview")->assertNotFound();
    }

    public function test_no_se_puede_renombrar_ni_mover_un_archivo_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $archivoB = Archivo::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Original.pdf']);

        $this->loginAs($userA);

        $this->put("/archivos/{$archivoB->id}", ['nombre' => 'Hackeado.pdf'])->assertNotFound();
        $this->assertDatabaseHas('archivos', ['id' => $archivoB->id, 'nombre' => 'Original.pdf']);
    }

    public function test_no_se_puede_borrar_un_archivo_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $archivoB = Archivo::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->delete("/archivos/{$archivoB->id}")->assertNotFound();
        $this->assertNotSoftDeleted($archivoB);
    }
}
