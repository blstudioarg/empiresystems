<?php

namespace Tests\Feature\Leads;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportadorLeadsTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $filas): string
    {
        $lineas = ['nombre,empresa,email,telefono'];
        foreach ($filas as $fila) {
            $lineas[] = implode(',', $fila);
        }

        return implode("\n", $lineas);
    }

    public function test_importacion_crea_validas_y_reporta_invalidas_y_duplicadas_sin_abortar(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'email' => 'dup@example.com', 'telefono' => null]);

        $contenido = $this->csv([
            ['Ana Gomez', '', 'ana@example.com', ''],
            ['Luis Ruiz', '', '', '600111222'],
            ['Sin Contacto', '', '', ''],
            ['Duplicado', '', 'dup@example.com', ''],
        ]);

        $fichero = UploadedFile::fake()->createWithContent('leads.csv', $contenido);

        $this->loginAs($user);

        $response = $this->post('/leads/importar', ['fichero' => $fichero]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leads', 3);
        $this->assertDatabaseHas('leads', ['tenant_id' => $tenant->id, 'email' => 'ana@example.com']);
        $this->assertDatabaseHas('leads', ['tenant_id' => $tenant->id, 'telefono' => '600111222']);
    }

    public function test_importacion_no_filtra_entre_tenants_en_la_deteccion_de_duplicados(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        Lead::factory()->create(['tenant_id' => $tenantB->id, 'email' => 'compartido@example.com', 'telefono' => null]);

        $contenido = $this->csv([
            ['Nuevo Contacto', '', 'compartido@example.com', ''],
        ]);

        $fichero = UploadedFile::fake()->createWithContent('leads.csv', $contenido);

        $this->loginAs($userA);

        $response = $this->post('/leads/importar', ['fichero' => $fichero]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leads', ['tenant_id' => $tenantA->id, 'email' => 'compartido@example.com']);
    }
}
