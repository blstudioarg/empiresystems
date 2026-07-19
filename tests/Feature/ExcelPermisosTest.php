<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class ExcelPermisosTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_deniega_exportacion_sin_permiso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $sinPermiso = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Sin clientes', ['ver-dashboard']));

        $this->loginAs($sinPermiso);

        $this->postJson('/exportar/clientes', ['ids' => [$cliente->id]])->assertForbidden();
    }

    public function test_permite_exportacion_con_permiso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $conPermiso = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Con clientes', ['ver-clientes']));

        $this->loginAs($conPermiso);

        $this->postJson('/exportar/clientes', ['ids' => [$cliente->id]])->assertOk();
    }

    /**
     * FR-020: los tres endpoints de importación, no solo el formulario — `confirmar` es el que
     * escribe, así que es el que no puede quedar sin cubrir.
     */
    public function test_deniega_los_tres_endpoints_de_importacion_sin_permiso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();

        $sinPermiso = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Sin clientes 2', ['ver-dashboard']));

        $this->loginAs($sinPermiso);

        $this->get('/importar/clientes')->assertForbidden();

        $fichero = UploadedFile::fake()->createWithContent('clientes.csv', "tipo,nombre,pais\n");
        $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero])->assertForbidden();

        $this->postJson('/importar/clientes/confirmar', ['token' => '9f2c1e40-0000-0000-0000-000000000000'])->assertForbidden();
    }

    public function test_permite_los_tres_endpoints_de_importacion_con_permiso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();

        $conPermiso = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Con clientes 2', ['ver-clientes']));

        $this->loginAs($conPermiso);

        $this->get('/importar/clientes')->assertOk();
    }
}
