<?php

namespace Tests\Feature\Asistente;

use App\Excel\BorradorImportacion;
use App\Ia\Tools\AnalizarMaterialImportable;
use App\Ia\Tools\CorregirFilasImportables;
use App\Ia\Tools\ImportarMaterial;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AlmacenImportaciones;
use App\Support\MaterialImportable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Principio I + IV: el material que una persona aporta al asistente es suyo, no de la empresa.
 *
 * Un token ajeno responde **404 y nunca 403**: un 403 confirmaría que ese token existe, que ya es
 * una filtración (mismo criterio que el historial de la 045 y los documentos de compra de la 044).
 * Y no basta con separar empresas: dos personas del mismo tenant tampoco se ven el material.
 */
class MaterialAislamientoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
    }

    /**
     * Deja un borrador ya listo en el almacén, propiedad de $duenio.
     */
    private function borradorDe(User $duenio, string $token = 'token-ajeno'): BorradorImportacion
    {
        $borrador = new BorradorImportacion(
            token: $token,
            tenantId: (int) $duenio->tenant_id,
            userId: (int) $duenio->id,
            conversacionId: null,
            modulo: 'clientes',
            origen: MaterialImportable::ORIGEN_HOJA,
            filas: [['indice' => 2, 'datos' => ['tipo' => 'particular', 'nombre' => 'Secreto', 'nif' => null], 'leido' => []]],
        );

        app(AlmacenImportaciones::class)->guardarBorrador($borrador);

        return $borrador;
    }

    private function csv(string $nombre = 'clientes.csv'): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'mat_').'.csv';
        file_put_contents($ruta, "Tipo,Nombre,País\nParticular,Juan,ES\n");

        return new UploadedFile($ruta, $nombre, 'text/csv', null, true);
    }

    public function test_no_se_puede_borrar_el_material_de_otra_empresa(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $duenio = $this->usuario($tenantA);
        $intruso = $this->usuario($tenantB);

        $this->borradorDe($duenio);

        $this->loginAs($intruso);

        $this->deleteJson('/asistente/material/token-ajeno')->assertNotFound();
    }

    /**
     * El scope de tenant no separa a dos personas de la misma empresa: hace falta acotar también
     * por persona (FR-004).
     */
    public function test_no_se_puede_borrar_el_material_de_otra_persona_del_mismo_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $duenio = $this->usuario($tenant);
        $companiero = $this->usuario($tenant);

        $this->borradorDe($duenio);

        $this->loginAs($companiero);

        $this->deleteJson('/asistente/material/token-ajeno')->assertNotFound();
    }

    public function test_acumular_material_sobre_un_token_ajeno_responde_404_y_no_403(): void
    {
        $tenant = Tenant::factory()->create();
        $duenio = $this->usuario($tenant);
        $companiero = $this->usuario($tenant);

        $this->borradorDe($duenio);

        $this->loginAs($companiero);

        $respuesta = $this->postJson('/asistente/material', [
            'fichero' => $this->csv(),
            'modulo' => 'clientes',
            'token' => 'token-ajeno',
        ]);

        $respuesta->assertNotFound();
        $this->assertNotSame(403, $respuesta->status(), 'un 403 confirmaría que el token existe');
    }

    public function test_ninguna_tool_alcanza_el_material_de_otra_persona(): void
    {
        $tenant = Tenant::factory()->create();
        $duenio = $this->usuario($tenant);
        $companiero = $this->usuario($tenant);

        $this->borradorDe($duenio);

        $this->loginAs($companiero);

        // Lecturas: devuelven un error explicativo, sin datos del material ajeno.
        foreach ([new AnalizarMaterialImportable, new CorregirFilasImportables] as $tool) {
            $resultado = $tool->ejecutar(['token' => 'token-ajeno', 'correcciones' => [], 'descartar' => []]);

            $this->assertArrayHasKey('error', $resultado, $tool->nombre().' debería negar el acceso');
            $this->assertArrayNotHasKey('leidas', $resultado);
            $this->assertStringNotContainsString('Secreto', json_encode($resultado, JSON_UNESCAPED_UNICODE));
        }
    }

    public function test_no_se_puede_proponer_ni_ejecutar_la_importacion_de_material_ajeno(): void
    {
        $tenant = Tenant::factory()->create();
        $duenio = $this->usuario($tenant);
        $companiero = $this->usuario($tenant);

        $this->borradorDe($duenio);

        $this->loginAs($companiero);

        $tool = new ImportarMaterial;

        try {
            $tool->proponer(['token' => 'token-ajeno']);
            $this->fail('proponer debería negar el material ajeno');
        } catch (ValidationException $e) {
            $this->assertStringNotContainsString('Secreto', $e->getMessage());
        }

        $this->expectException(ValidationException::class);
        $tool->ejecutar(['token' => 'token-ajeno']);
    }

    /**
     * Cambiar de hilo deja el material fuera de juego, igual que invalida una propuesta pendiente.
     */
    public function test_el_material_no_se_arrastra_a_otra_conversacion(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuario($tenant);

        $borrador = new BorradorImportacion(
            token: 'token-de-otro-hilo',
            tenantId: (int) $tenant->id,
            userId: (int) $usuario->id,
            conversacionId: 999,
            modulo: 'clientes',
            origen: MaterialImportable::ORIGEN_HOJA,
            filas: [['indice' => 2, 'datos' => ['tipo' => 'particular', 'nombre' => 'Ana'], 'leido' => []]],
        );
        app(AlmacenImportaciones::class)->guardarBorrador($borrador);

        $this->loginAs($usuario);

        $resultado = (new AnalizarMaterialImportable)->ejecutar(['token' => 'token-de-otro-hilo']);

        $this->assertArrayHasKey('error', $resultado);
    }
}
