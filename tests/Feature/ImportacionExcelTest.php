<?php

namespace Tests\Feature;

use App\Excel\Definiciones\DefinicionClientes;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ImportacionExcelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Construye un .xlsx real (no un fake genérico) para que el importador lo pueda leer.
     *
     * @param  array<int, array<int, mixed>>  $filas  Fila 0 = cabecera.
     */
    private function xlsx(array $filas, string $nombre = 'import.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($filas as $filaIndice => $fila) {
            foreach (array_values($fila) as $colIndice => $valor) {
                $sheet->setCellValueByColumnAndRow($colIndice + 1, $filaIndice + 1, $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx_test_').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($ruta);

        return new UploadedFile($ruta, $nombre, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function cabeceraClientes(): array
    {
        return ['Tipo', 'Nombre', 'Razón social', 'NIF', 'Dirección', 'CP', 'Ciudad', 'Provincia', 'País', 'Email', 'Teléfono', 'Recargo de equivalencia', 'Notas'];
    }

    private function filaClienteValida(string $nombre = 'Juan Pérez'): array
    {
        return ['Particular', $nombre, '', '', '', '', '', '', 'ES', '', '', 'No', ''];
    }

    public function test_fuerza_el_tenant_activo_ignorando_la_columna_del_fichero(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($userA);

        $cabecera = [...$this->cabeceraClientes(), 'tenant_id'];
        $fila = [...$this->filaClienteValida(), (string) $tenantB->id];

        $fichero = $this->xlsx([$cabecera, $fila]);

        $preview = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);
        $preview->assertOk();
        $token = $preview->json('token');

        $this->post('/importar/clientes/confirmar', ['token' => $token])->assertRedirect();

        $cliente = Cliente::where('nombre', 'Juan Pérez')->first();
        $this->assertNotNull($cliente);
        $this->assertEquals($tenantA->id, $cliente->tenant_id);
    }

    public function test_la_previsualizacion_no_escribe_nada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $antes = Cliente::count();

        $fichero = $this->xlsx([$this->cabeceraClientes(), $this->filaClienteValida()]);
        $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero])->assertOk();

        $this->assertEquals($antes, Cliente::count());
    }

    public function test_importa_las_validas_y_reporta_las_rechazadas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $filas = [
            $this->cabeceraClientes(),
            $this->filaClienteValida('Cliente Válido Uno'),
            $this->filaClienteValida('Cliente Válido Dos'),
            ['Particular', '', '', '', '', '', '', '', 'ES', '', '', 'No', ''], // sin nombre
            ['Empresa', 'Empresa Mal NIF', 'Empresa Mal NIF SL', 'XXX-NO-VALIDO', '', '', '', '', 'ES', '', '', 'No', ''],
        ];

        $fichero = $this->xlsx($filas);

        $preview = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);
        $preview->assertOk();
        $preview->assertJsonPath('validas', 2);
        $this->assertCount(2, $preview->json('rechazadas'));

        $token = $preview->json('token');
        $this->post('/importar/clientes/confirmar', ['token' => $token])->assertRedirect();

        $this->assertEquals(2, Cliente::count());
    }

    /**
     * El modal de importación (excel-importar-modal.init.js) hace todo el flujo por AJAX: cuando
     * la petición pide JSON, `confirmar` no debe redirigir — debe responder el resumen igual que
     * `previsualizar`.
     */
    public function test_confirmar_responde_json_cuando_la_peticion_lo_pide(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $filas = [
            $this->cabeceraClientes(),
            $this->filaClienteValida('Cliente Válido'),
            ['Particular', '', '', '', '', '', '', '', 'ES', '', '', 'No', ''], // sin nombre
        ];

        $fichero = $this->xlsx($filas);

        $preview = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);
        $preview->assertOk();
        $token = $preview->json('token');

        $confirmar = $this->postJson('/importar/clientes/confirmar', ['token' => $token]);

        $confirmar->assertOk();
        $confirmar->assertJsonPath('importados', 1);
        $this->assertCount(1, $confirmar->json('rechazadas'));
        $this->assertNotNull($confirmar->json('rechazos_url'));
    }

    public function test_no_existen_rutas_de_importacion_para_facturas_y_albaranes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->get('/importar/facturas')->assertNotFound();
        $this->get('/importar/albaranes')->assertNotFound();
    }

    public function test_pdf_renombrado_a_xlsx_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $ruta = tempnam(sys_get_temp_dir(), 'pdf_test_').'.xlsx';
        file_put_contents($ruta, "%PDF-1.4\n%âãÏÓ\nfalso pdf, no es un xlsx real");
        $fichero = new UploadedFile($ruta, 'facturas.xlsx', 'application/pdf', null, true);

        $response = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);

        $response->assertStatus(422);
        $this->assertEquals(0, Cliente::count());
    }

    public function test_fichero_vacio_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $ruta = tempnam(sys_get_temp_dir(), 'empty_test_').'.xlsx';
        file_put_contents($ruta, '');
        $fichero = new UploadedFile($ruta, 'vacio.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero])->assertStatus(422);
    }

    public function test_mas_de_2000_filas_devuelve_422_mencionando_el_limite(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $filas = [$this->cabeceraClientes()];
        for ($i = 0; $i < 2001; $i++) {
            $filas[] = $this->filaClienteValida("Cliente {$i}");
        }

        $fichero = $this->xlsx($filas);

        $response = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => $response->json('message')]);
        $this->assertStringContainsString('2.000', $response->json('message'));
    }

    public function test_cabeceras_desconocidas_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $fichero = $this->xlsx([
            ['Columna X', 'Columna Y'],
            ['a', 'b'],
        ]);

        $response = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);

        $response->assertStatus(422);
        $this->assertEquals(0, Cliente::count());
    }

    public function test_confirmar_con_token_caducado_no_importa_nada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $fichero = $this->xlsx([$this->cabeceraClientes(), $this->filaClienteValida()]);
        $preview = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);
        $preview->assertOk();
        $token = $preview->json('token');

        // Simula la caducidad/borrado del fichero de previsualización.
        foreach (Storage::disk('local')->files('importaciones') as $ruta) {
            Storage::disk('local')->delete($ruta);
        }

        $antes = Cliente::count();

        $response = $this->post('/importar/clientes/confirmar', ['token' => $token]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('token');
        $this->assertEquals($antes, Cliente::count());
    }

    /**
     * SC-007: exportación, plantilla e importación comparten una única definición (D7). Construye
     * la fila exactamente como la escribiría `ExportacionModulo` (mismas columnas, mismo orden) y
     * comprueba que, tras editar NIF y nombre, se reimporta sin rechazos de formato.
     */
    public function test_un_fichero_exportado_se_puede_reimportar(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $cliente = Cliente::factory()->empresa()->create(['tenant_id' => $tenant->id, 'nombre' => 'Original SL']);

        $definicion = new DefinicionClientes;
        $cabecera = array_map(fn ($c) => $c->etiqueta, $definicion->columnas());
        $fila = array_map(fn ($c) => ($c->exportar)($cliente), $definicion->columnas());

        // Cambia NIF y nombre y reimporta el fichero como si fuera el que se acaba de exportar.
        $fila[array_search('NIF', $cabecera, true)] = 'B10000008';
        $fila[array_search('Nombre', $cabecera, true)] = 'Reimportado SL';

        $fichero = $this->xlsx([$cabecera, $fila]);

        $preview = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);

        $preview->assertOk();
        $preview->assertJsonPath('validas', 1);
        $this->assertCount(0, $preview->json('rechazadas'));
    }

    /**
     * Acentos, eñes y comillas sobreviven al viaje de ida y vuelta export → import.
     */
    public function test_acentos_enes_y_comillas_sobreviven_el_viaje_de_ida_y_vuelta(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $nombre = 'Peña "El Ñandú" S.L.';

        $fichero = $this->xlsx([
            $this->cabeceraClientes(),
            ['Empresa', $nombre, $nombre, 'B10000008', '', '', '', '', 'ES', '', '', 'No', 'Notas con "comillas" y ñ'],
        ]);

        $preview = $this->postJson('/importar/clientes/previsualizar', ['fichero' => $fichero]);
        $preview->assertOk();
        $preview->assertJsonPath('validas', 1);

        $token = $preview->json('token');
        $this->post('/importar/clientes/confirmar', ['token' => $token])->assertRedirect();

        $cliente = Cliente::where('nif', 'B10000008')->first();
        $this->assertNotNull($cliente);
        $this->assertSame($nombre, $cliente->nombre);
        $this->assertSame('Notas con "comillas" y ñ', $cliente->notas);
    }
}
