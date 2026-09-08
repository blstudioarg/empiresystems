<?php

namespace Tests\Feature\Asistente;

use App\Enums\TipoCliente;
use App\Ia\Tools\AnalizarMaterialImportable;
use App\Ia\Tools\CorregirFilasImportables;
use App\Ia\Tools\ImportarMaterial;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * Flujo completo de la importación conversacional (quickstart, escenarios 1 y 2) y códigos del
 * contrato §1.
 */
class ImportacionConversacionalTest extends TestCase
{
    use RefreshDatabase;

    private function cabecera(): array
    {
        return ['Tipo', 'Nombre', 'Razón social', 'NIF', 'Dirección', 'CP', 'Ciudad', 'Provincia', 'País', 'Email', 'Teléfono', 'Recargo de equivalencia', 'Notas'];
    }

    private function particular(string $nombre): array
    {
        return ['Particular', $nombre, '', '', '', '', '', '', 'ES', '', '', 'No', ''];
    }

    /** Empresa sin NIF: inválida hasta que alguien aporte el dato. */
    private function empresaSinNif(string $nombre): array
    {
        return ['Empresa', $nombre, $nombre.' SL', '', '', '', '', '', 'ES', '', '', 'No', ''];
    }

    /**
     * @param  array<int, array<int, mixed>>  $filas
     */
    private function xlsx(array $filas, string $nombre = 'clientes.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();

        foreach ($filas as $f => $fila) {
            foreach (array_values($fila) as $c => $valor) {
                $hoja->setCellValueByColumnAndRow($c + 1, $f + 1, $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx_').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($ruta);

        return new UploadedFile($ruta, $nombre, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function usuario(?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create();

        return User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
    }

    /** Diez clientes: siete válidos y tres empresas sin NIF (SC-003). */
    private function ficheroDeDiez(): UploadedFile
    {
        $filas = [$this->cabecera()];

        for ($i = 1; $i <= 7; $i++) {
            $filas[] = $this->particular("Cliente Válido {$i}");
        }

        foreach (['Acme', 'Beta', 'Ceta'] as $nombre) {
            $filas[] = $this->empresaSinNif($nombre);
        }

        return $this->xlsx($filas);
    }

    private function subir(UploadedFile $fichero, string $modulo = 'clientes'): string
    {
        $respuesta = $this->postJson('/asistente/material', ['fichero' => $fichero, 'modulo' => $modulo]);
        $respuesta->assertOk();

        return $respuesta->json('token');
    }

    // --- Contrato §1 --------------------------------------------------------

    public function test_subir_material_devuelve_el_token_el_modulo_y_el_origen(): void
    {
        $this->loginAs($this->usuario());

        $respuesta = $this->postJson('/asistente/material', [
            'fichero' => $this->xlsx([$this->cabecera(), $this->particular('Juan')]),
            'modulo' => 'clientes',
        ]);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('modulo', 'clientes');
        $respuesta->assertJsonPath('origen', 'hoja');
        $respuesta->assertJsonPath('nombre', 'clientes.xlsx');
        $this->assertNotEmpty($respuesta->json('token'));
    }

    /**
     * FR-010: facturas no implementan el contrato importable, así que ni siquiera existen para esta
     * ruta. No hay lista negra que mantener.
     */
    public function test_un_modulo_no_importable_responde_404(): void
    {
        $this->loginAs($this->usuario());

        $this->postJson('/asistente/material', [
            'fichero' => $this->xlsx([$this->cabecera(), $this->particular('Juan')]),
            'modulo' => 'facturas',
        ])->assertNotFound();
    }

    public function test_sin_permiso_sobre_el_modulo_responde_403(): void
    {
        $usuario = $this->usuario();
        $this->loginAs($usuario);

        // Sin roles: autenticado y del tenant, pero sin permiso sobre clientes.
        $usuario->syncRoles([]);

        $this->postJson('/asistente/material', [
            'fichero' => $this->xlsx([$this->cabecera(), $this->particular('Juan')]),
            'modulo' => 'clientes',
        ])->assertForbidden();
    }

    public function test_un_tipo_no_admitido_se_rechaza_con_explicacion_y_no_con_un_500(): void
    {
        $this->loginAs($this->usuario());

        $ruta = tempnam(sys_get_temp_dir(), 'raro_').'.exe';
        file_put_contents($ruta, 'contenido');

        $respuesta = $this->postJson('/asistente/material', [
            'fichero' => new UploadedFile($ruta, 'virus.exe', 'application/octet-stream', null, true),
            'modulo' => 'clientes',
        ]);

        $respuesta->assertStatus(422);
        $this->assertStringContainsString('.exe', (string) $respuesta->json('mensaje'));
    }

    public function test_un_fichero_de_mas_de_5mb_se_rechaza(): void
    {
        $this->loginAs($this->usuario());

        $this->postJson('/asistente/material', [
            'fichero' => UploadedFile::fake()->create('enorme.xlsx', 6 * 1024),
            'modulo' => 'clientes',
        ])->assertStatus(422);
    }

    public function test_descartar_el_material_lo_borra_del_almacen(): void
    {
        $this->loginAs($this->usuario());

        $token = $this->subir($this->xlsx([$this->cabecera(), $this->particular('Juan')]));

        $this->deleteJson('/asistente/material/'.$token)->assertOk()->assertJsonPath('ok', true);

        // Y ya no vale para nada más.
        $this->assertArrayHasKey('error', (new AnalizarMaterialImportable)->ejecutar(['token' => $token]));
    }

    // --- Análisis y corrección (US1 + US2) ----------------------------------

    /**
     * SC-003: de 10 registros con 3 datos obligatorios ausentes, el asistente identifica exactamente
     * esos 3 y los nombra.
     */
    public function test_el_analisis_cuenta_lo_leido_lo_valido_y_nombra_lo_que_falla(): void
    {
        $this->loginAs($this->usuario());

        $token = $this->subir($this->ficheroDeDiez());

        $analisis = (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);

        $this->assertSame(10, $analisis['leidas']);
        $this->assertSame(7, $analisis['validas']);
        $this->assertCount(3, $analisis['rechazadas']);

        $this->assertSame(
            ['Acme', 'Beta', 'Ceta'],
            array_column($analisis['rechazadas'], 'identificador'),
            'el asistente tiene que poder nombrarlos, no leer números de fila',
        );
        $this->assertSame('nif', $analisis['rechazadas'][0]['campo']);
    }

    /**
     * SC-002: nada se escribe hasta la confirmación, por muchas vueltas que dé la conversación.
     */
    public function test_analizar_y_corregir_no_escriben_ni_un_registro(): void
    {
        $this->loginAs($this->usuario());

        $antes = Cliente::count();
        $token = $this->subir($this->ficheroDeDiez());

        $analisis = (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);
        $indices = array_column($analisis['rechazadas'], 'indice');

        (new CorregirFilasImportables)->ejecutar([
            'token' => $token,
            'correcciones' => [['indice' => $indices[0], 'campo' => 'nif', 'valor' => 'B12345674']],
        ]);
        (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);
        (new CorregirFilasImportables)->ejecutar(['token' => $token, 'descartar' => [$indices[1]]]);
        (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);

        $this->assertSame($antes, Cliente::count(), 'ninguna vuelta de la conversación puede escribir');
    }

    /**
     * SC-003, SC-004 y SC-008 de una sola pasada: corregir uno, descartar dos, quedan 8 válidos, y
     * confirmar importa 8.
     */
    public function test_corregir_uno_y_descartar_dos_deja_ocho_validos_y_confirmar_importa_ocho(): void
    {
        $this->loginAs($this->usuario());

        $token = $this->subir($this->ficheroDeDiez());

        $analisis = (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);
        $indices = array_column($analisis['rechazadas'], 'indice');

        $tras = (new CorregirFilasImportables)->ejecutar([
            'token' => $token,
            'correcciones' => [['indice' => $indices[0], 'campo' => 'nif', 'valor' => 'B12345674']],
            'descartar' => [$indices[1], $indices[2]],
        ]);

        $this->assertSame(8, $tras['validas']);
        $this->assertSame(2, $tras['descartadas']);
        $this->assertSame([], $tras['rechazadas']);

        $propuesta = (new ImportarMaterial)->proponer(['token' => $token]);

        $this->assertSame('Importar 8 clientes', $propuesta['resumen']);
        $this->assertCount(8, $propuesta['detalle'], 'la tabla de detalle enseña todos los registros (FR-017)');
        $this->assertStringContainsString('8 válidas', $propuesta['analisis']);

        // La propuesta lleva solo el token: el borrador vigente es la fuente de verdad (FR-015).
        $this->assertSame(['token' => $token], $propuesta['parametros']);

        $resultado = (new ImportarMaterial)->ejecutar($propuesta['parametros']);

        $this->assertSame(8, Cliente::count());
        $this->assertStringContainsString('8 clientes', $resultado['descripcion']);
        $this->assertSame([], $resultado['rechazadas']);
        $this->assertNotNull(Cliente::where('nif', 'B12345674')->first());
    }

    /**
     * FR-018: importar las válidas y reportar las rechazadas, sin abortar el lote. Se fuerza el
     * caso creando el choque **entre** el análisis y la confirmación (FR-019).
     */
    public function test_al_confirmar_se_revalida_contra_la_base_de_datos_y_se_reportan_las_rechazadas(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuario($tenant);
        $this->loginAs($usuario);

        $filas = [
            $this->cabecera(),
            ['Empresa', 'Acme', 'Acme SL', 'B12345674', '', '', '', '', 'ES', '', '', 'No', ''],
            $this->particular('Juan'),
        ];

        $token = $this->subir($this->xlsx($filas));

        $analisis = (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);
        $this->assertSame(2, $analisis['validas']);

        $propuesta = (new ImportarMaterial)->proponer(['token' => $token]);

        // Otra persona del tenant crea el mismo NIF justo antes de confirmar.
        Cliente::create([
            'tenant_id' => $tenant->id,
            'tipo' => TipoCliente::Empresa,
            'nombre' => 'Acme', 'razon_social' => 'Acme SL', 'nif' => 'B12345674', 'pais' => 'ES',
        ]);

        $resultado = (new ImportarMaterial)->ejecutar($propuesta['parametros']);

        $this->assertSame(2, Cliente::count(), 'el que ya existía más el que sí valía: la fila repetida no entra');
        $this->assertCount(1, $resultado['rechazadas']);
        $this->assertStringContainsString('Acme', $resultado['rechazadas'][0], 'se nombra el registro, no la fila');
        $this->assertStringContainsString('NIF', $resultado['rechazadas'][0]);
    }

    /**
     * FR-015: se importa siempre lo último acordado. Si tras proponer la persona sigue corrigiendo,
     * la confirmación recoge esos cambios y no la foto del momento de proponer.
     */
    public function test_la_confirmacion_relee_el_borrador_y_no_una_copia_del_analisis(): void
    {
        $this->loginAs($this->usuario());

        $token = $this->subir($this->xlsx([
            $this->cabecera(),
            $this->particular('Uno'),
            $this->particular('Dos'),
        ]));

        (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);
        $propuesta = (new ImportarMaterial)->proponer(['token' => $token]);
        $this->assertSame('Importar 2 clientes', $propuesta['resumen']);

        // Después de proponer, la persona se arrepiente de uno.
        $analisis = (new CorregirFilasImportables)->ejecutar(['token' => $token, 'descartar' => [1]]);
        $this->assertSame(1, $analisis['validas']);

        (new ImportarMaterial)->ejecutar($propuesta['parametros']);

        $this->assertSame(1, Cliente::count());
        $this->assertNotNull(Cliente::where('nombre', 'Uno')->first());
    }

    public function test_al_importar_se_borra_el_material_y_queda_registrado_en_el_historial(): void
    {
        $usuario = $this->usuario();
        $this->loginAs($usuario);

        $token = $this->subir($this->xlsx([$this->cabecera(), $this->particular('Juan')]));

        (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);
        $resultado = (new ImportarMaterial)->ejecutar(['token' => $token]);

        // Nada del material sobrevive a la importación (FR-023).
        $this->assertSame(
            [],
            array_values(array_filter(
                Storage::disk('local')->files('importaciones'),
                fn (string $ruta) => str_contains($ruta, $token),
            )),
        );

        // Y la descripción que va al historial de actividad dice módulo y cantidad (FR-022).
        $this->assertStringContainsString('1 clientes', $resultado['descripcion']);
        $this->assertNotNull($resultado['entidad_tipo']);
    }

    /**
     * US3, escenario 4: dos ficheros en la misma importación. Los índices continúan en vez de
     * reiniciarse, o una corrección dictada antes apuntaría a otra fila.
     */
    public function test_se_puede_acumular_mas_material_en_la_misma_importacion(): void
    {
        $this->loginAs($this->usuario());

        $token = $this->subir($this->xlsx([$this->cabecera(), $this->particular('Uno')], 'primero.xlsx'));
        $this->assertSame(1, (new AnalizarMaterialImportable)->ejecutar(['token' => $token])['leidas']);

        $segundo = $this->postJson('/asistente/material', [
            'fichero' => $this->xlsx([$this->cabecera(), $this->particular('Dos')], 'segundo.xlsx'),
            'modulo' => 'clientes',
            'token' => $token,
        ]);

        $segundo->assertOk();
        $segundo->assertJsonPath('token', $token, 'acumular no abre otra importación');

        $analisis = (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);

        $this->assertSame(2, $analisis['leidas']);
        $this->assertSame(2, $analisis['validas']);
    }

    /**
     * Edge case del spec: sin clave de IA, las hojas se siguen analizando y los documentos se
     * rechazan con una explicación, no con un error técnico.
     */
    public function test_sin_clave_de_ia_las_hojas_funcionan_y_los_documentos_se_explican(): void
    {
        $this->loginAs($this->usuario());

        // La hoja no toca al proveedor: funciona igual.
        $hoja = $this->subir($this->xlsx([$this->cabecera(), $this->particular('Juan')]));
        $this->assertSame(1, (new AnalizarMaterialImportable)->ejecutar(['token' => $hoja])['validas']);

        $ruta = tempnam(sys_get_temp_dir(), 'doc_').'.txt';
        file_put_contents($ruta, 'Listado de clientes');
        $documento = $this->subir(new UploadedFile($ruta, 'listado.txt', 'text/plain', null, true));

        $resultado = (new AnalizarMaterialImportable)->ejecutar(['token' => $documento]);

        $this->assertArrayHasKey('error', $resultado);
        $this->assertMatchesRegularExpression('/clave|asistente/i', $resultado['error']);
    }

    /**
     * Contrato §4: una corrección sobre un índice inexistente falla explícitamente, no se ignora.
     */
    public function test_corregir_una_fila_que_no_existe_devuelve_un_error_y_no_finge_haberlo_hecho(): void
    {
        $this->loginAs($this->usuario());

        $token = $this->subir($this->xlsx([$this->cabecera(), $this->particular('Juan')]));
        (new AnalizarMaterialImportable)->ejecutar(['token' => $token]);

        $resultado = (new CorregirFilasImportables)->ejecutar([
            'token' => $token,
            'correcciones' => [['indice' => 999, 'campo' => 'nif', 'valor' => 'B12345674']],
        ]);

        $this->assertArrayHasKey('error', $resultado);
        $this->assertStringContainsString('999', $resultado['error']);
    }
}
