<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\Carpeta;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CarpetaCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_una_carpeta_en_la_raiz(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $response = $this->post('/archivos/carpetas', ['nombre' => 'Contratos'], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertDatabaseHas('carpetas', ['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => null]);
    }

    public function test_el_listado_de_archivos_index_expone_parent_id_y_urls_de_las_carpetas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $carpeta = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => null]);

        $this->loginAs($user);

        $response = $this->getJson('/archivos');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $carpeta->id,
            'nombre' => 'Contratos',
            'parent_id' => null,
            'update_url' => route('carpetas.update', $carpeta),
            'delete_url' => route('carpetas.destroy', $carpeta),
        ]);
    }

    public function test_crea_una_subcarpeta_dentro_de_otra(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $padre = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos']);

        $this->loginAs($user);

        $response = $this->post('/archivos/carpetas', [
            'nombre' => '2026',
            'parent_id' => $padre->id,
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertDatabaseHas('carpetas', ['tenant_id' => $tenant->id, 'nombre' => '2026', 'parent_id' => $padre->id]);
    }

    public function test_rechaza_nombre_duplicado_en_el_mismo_nivel(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => null]);

        $this->loginAs($user);

        $response = $this->post('/archivos/carpetas', ['nombre' => 'Contratos'], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('carpetas', 1);
    }

    public function test_permite_el_mismo_nombre_en_niveles_distintos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $padre = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Proveedores', 'parent_id' => null]);
        Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => '2026', 'parent_id' => null]);

        $this->loginAs($user);

        $response = $this->post('/archivos/carpetas', [
            'nombre' => '2026',
            'parent_id' => $padre->id,
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertDatabaseCount('carpetas', 3);
    }

    public function test_renombrar_respeta_la_unicidad_por_nivel(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => null]);
        $carpeta = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Proveedores', 'parent_id' => null]);

        $this->loginAs($user);

        $response = $this->put("/archivos/carpetas/{$carpeta->id}", ['nombre' => 'Contratos'], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'nombre' => 'Proveedores']);
    }

    public function test_borrar_una_carpeta_borra_en_cascada_subcarpetas_archivos_y_ficheros(): void
    {
        Storage::fake('documentos');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $raiz = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => null]);
        $sub = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => '2026', 'parent_id' => $raiz->id]);

        $rutaRaiz = "tenants/{$tenant->id}/documentos/raiz.pdf";
        $rutaSub = "tenants/{$tenant->id}/documentos/sub.pdf";
        Storage::disk('documentos')->put($rutaRaiz, 'a');
        Storage::disk('documentos')->put($rutaSub, 'b');

        $archivoRaiz = Archivo::factory()->create(['tenant_id' => $tenant->id, 'carpeta_id' => $raiz->id, 'ruta' => $rutaRaiz]);
        $archivoSub = Archivo::factory()->create(['tenant_id' => $tenant->id, 'carpeta_id' => $sub->id, 'ruta' => $rutaSub]);

        $this->loginAs($user);

        $response = $this->delete("/archivos/carpetas/{$raiz->id}", [], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertSoftDeleted($raiz);
        $this->assertSoftDeleted($sub);
        $this->assertSoftDeleted($archivoRaiz);
        $this->assertSoftDeleted($archivoSub);
        Storage::disk('documentos')->assertMissing($rutaRaiz);
        Storage::disk('documentos')->assertMissing($rutaSub);
    }

    public function test_mover_una_carpeta_a_otra_carpeta_cambia_su_padre(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $origen = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Origen', 'parent_id' => null]);
        $destino = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Destino', 'parent_id' => null]);
        $carpeta = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => $origen->id]);

        $this->loginAs($user);

        $response = $this->put("/archivos/carpetas/{$carpeta->id}", ['parent_id' => $destino->id], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'parent_id' => $destino->id]);
    }

    public function test_mover_una_carpeta_a_la_raiz(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $origen = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Origen', 'parent_id' => null]);
        $carpeta = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => $origen->id]);

        $this->loginAs($user);

        $response = $this->put("/archivos/carpetas/{$carpeta->id}", ['parent_id' => null], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'parent_id' => null]);
    }

    public function test_no_se_puede_mover_una_carpeta_dentro_de_si_misma(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $carpeta = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => null]);

        $this->loginAs($user);

        $response = $this->put("/archivos/carpetas/{$carpeta->id}", ['parent_id' => $carpeta->id], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'parent_id' => null]);
    }

    public function test_no_se_puede_mover_una_carpeta_dentro_de_una_subcarpeta_propia(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $carpeta = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => null]);
        $sub = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => '2026', 'parent_id' => $carpeta->id]);
        $subSub = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Q1', 'parent_id' => $sub->id]);

        $this->loginAs($user);

        $response = $this->put("/archivos/carpetas/{$carpeta->id}", ['parent_id' => $subSub->id], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'parent_id' => null]);
    }

    public function test_mover_respeta_la_unicidad_de_nombre_en_el_nivel_destino(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $destino = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Destino', 'parent_id' => null]);
        Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => $destino->id]);
        $carpeta = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos', 'parent_id' => null]);

        $this->loginAs($user);

        $response = $this->put("/archivos/carpetas/{$carpeta->id}", ['parent_id' => $destino->id], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'parent_id' => null]);
    }

    public function test_no_se_puede_borrar_una_carpeta_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $carpetaB = Carpeta::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->delete("/archivos/carpetas/{$carpetaB->id}")->assertNotFound();
        $this->assertNotSoftDeleted($carpetaB);
    }
}
