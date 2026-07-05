<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\Carpeta;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivosBusquedaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_archivos_y_carpetas_por_nombre_en_cualquier_nivel(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $raiz = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Proveedores', 'parent_id' => null]);
        $sub = Carpeta::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contratos 2026', 'parent_id' => $raiz->id]);
        Archivo::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Contrato firmado.pdf', 'carpeta_id' => $sub->id]);
        Archivo::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Factura enero.pdf', 'carpeta_id' => null]);

        $this->loginAs($user);

        $response = $this->getJson('/archivos?q=contrat');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Contratos 2026']);
        $response->assertJsonFragment(['nombre' => 'Contrato firmado.pdf']);
        $response->assertJsonMissing(['nombre' => 'Factura enero.pdf']);
    }

    public function test_la_busqueda_no_incluye_resultados_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        Archivo::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Presupuesto obra.pdf']);
        Archivo::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Presupuesto secreto.pdf']);
        Carpeta::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Presupuestos B']);

        $this->loginAs($userA);

        $response = $this->getJson('/archivos?q=presupuesto');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Presupuesto obra.pdf']);
        $response->assertJsonMissing(['nombre' => 'Presupuesto secreto.pdf']);
        $response->assertJsonMissing(['nombre' => 'Presupuestos B']);
    }

    public function test_busqueda_sin_coincidencias_devuelve_listas_vacias(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        Archivo::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Factura.pdf']);

        $this->loginAs($user);

        $response = $this->getJson('/archivos?q=inexistente');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonCount(0, 'carpetas');
    }
}
