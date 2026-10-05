<?php

namespace Tests\Feature\Traduccion;

use App\Models\Tenant;
use App\Models\Traduccion;
use App\Models\TraduccionCorreccion;
use App\Traduccion\ExtractorClaves;
use App\Traduccion\TraductorDeepl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * US3 (FR-010…FR-014, SC-004/SC-009) y US2 (FR-015, SC-006): el comando del deploy extrae los
 * textos, los traduce por lotes con el glosario, es idempotente y nunca falla por la API.
 * Ningún test llama a DeepL: todo con `Http::fake()`.
 */
class SincronizarTraduccionesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{glossary_id: string, name: string}> */
    private array $glosariosRemotos = [];

    /** Prefijo con el que «traduce» el DeepL simulado (Http::fake acumula, no reemplaza). */
    private string $prefijo = '译';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'traduccion.deepl.api_key' => 'clave-de-prueba',
            'traduccion.deepl.api_url' => 'https://deepl.test',
            'traduccion.ambitos.prueba' => [
                'rutas' => ['tests/Fixtures/traduccion'],
                'claves_extra' => ['Clave extra'],
            ],
            'traduccion.glosario.zh' => ['Precuenta' => '预结单', 'Mesa' => '餐桌'],
        ]);
    }

    /** DeepL simulado: traduce anteponiendo «译», conservando las etiquetas tal cual. */
    private function fakeDeepl(?callable $traducir = null): void
    {
        Http::fake([
            'deepl.test/v2/glossaries/*' => Http::response(null, 204),
            'deepl.test/v2/glossaries' => fn (Request $r) => $r->method() === 'GET'
                ? Http::response(['glossaries' => $this->glosariosRemotos])
                : Http::response(['glossary_id' => 'g-nuevo', 'name' => $r['name']]),
            'deepl.test/v2/translate' => $traducir ?? fn (Request $r) => Http::response([
                'translations' => array_map(fn (string $t) => ['text' => $this->prefijo.$t], $r['text']),
            ]),
        ]);
    }

    private function sincronizar(array $opciones = []): PendingCommand
    {
        return $this->artisan('traducciones:sincronizar', array_merge(['--ambito' => 'prueba'], $opciones));
    }

    /** @return list<Request> */
    private function llamadasTraducir(): array
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/v2/translate'))
            ->map(fn (array $par) => $par[0])->values()->all();
    }

    public function test_el_extractor_encuentra_las_claves_de_blade_php_js_y_extra(): void
    {
        $claves = app(ExtractorClaves::class)->extraer('prueba');

        foreach ([
            'Hola mundo', 'Texto con comillas dobles', "Con 'comilla' simple", 'Ayuda con <strong>negrita</strong>',
            'Mesa :mesa ocupada', "Mensaje \"doble\" y 'simple'", 'Desde JS', 'Doble en JS', 'Clave extra',
        ] as $esperada) {
            $this->assertContains($esperada, $claves);
        }

        $this->assertNotContains('$variable', $claves);
    }

    public function test_solo_extraer_registra_pendientes_sin_llamar_a_la_api(): void
    {
        Http::fake();

        $this->sincronizar(['--solo-extraer' => true])->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(9, Traduccion::query()->pendientes()->count());
        $this->assertTrue(Traduccion::query()->where('texto', 'Ayuda con <strong>negrita</strong>')->sole()->es_html);
    }

    public function test_traduce_las_pendientes_y_conserva_las_variables(): void
    {
        $this->fakeDeepl();

        $this->sincronizar()->assertSuccessful();

        $this->assertSame(0, Traduccion::query()->where('estado', '!=', Traduccion::ESTADO_TRADUCIDA)->count());
        $this->assertSame('译Mesa :mesa ocupada', Traduccion::query()->where('texto', 'Mesa :mesa ocupada')->value('traduccion'));
        $texto = "Mensaje \"doble\" y 'simple'";
        $this->assertSame('译'.$texto, Traduccion::query()->where('texto', $texto)->value('traduccion'));
    }

    public function test_un_texto_con_html_se_envia_con_tag_handling_html_y_el_resto_con_xml(): void
    {
        $this->fakeDeepl();

        $this->sincronizar()->assertSuccessful();

        $porModo = collect($this->llamadasTraducir())->keyBy(fn (Request $r) => $r['tag_handling']);
        $this->assertSame(['Ayuda con <strong>negrita</strong>'], $porModo['html']['text']);
        $this->assertContains('Mesa <x>:mesa</x> ocupada', $porModo['xml']['text']);
        $this->assertSame(['x', 'k'], $porModo['xml']['ignore_tags']);
    }

    public function test_traduce_en_lotes_de_como_mucho_cincuenta(): void
    {
        config(['traduccion.ambitos.prueba.claves_extra' => array_map(fn ($i) => "Texto número {$i}", range(1, 120))]);
        $this->fakeDeepl();

        $this->sincronizar()->assertSuccessful();

        $lotes = array_map(fn (Request $r) => count($r['text']), $this->llamadasTraducir());
        $this->assertLessThanOrEqual(50, max($lotes));
        $this->assertSame(128, array_sum($lotes));
    }

    public function test_una_segunda_ejecucion_sin_textos_nuevos_no_llama_a_la_api(): void
    {
        $this->fakeDeepl();
        $this->sincronizar()->assertSuccessful();

        Http::fake();
        $this->sincronizar()->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_cupo_agotado_no_falla_el_deploy_y_deja_los_textos_pendientes(): void
    {
        $this->fakeDeepl(fn () => Http::response(['message' => 'Quota exceeded'], 456));

        $this->sincronizar()->expectsOutputToContain('cupo de caracteres agotado')->assertSuccessful();

        $fila = Traduccion::query()->where('texto', 'Hola mundo')->sole();
        $this->assertSame(Traduccion::ESTADO_PENDIENTE, $fila->estado);
        $this->assertSame(1, $fila->intentos);
        $this->assertStringContainsString('456', $fila->ultimo_error);
    }

    public function test_error_del_servicio_o_timeout_no_falla_el_deploy(): void
    {
        $this->fakeDeepl(fn () => Http::response('error', 500));
        $this->sincronizar()->assertSuccessful();

        $this->fakeDeepl(fn () => throw new ConnectionException('timeout'));
        $this->sincronizar()->assertSuccessful();

        $this->assertSame(0, Traduccion::query()->traducidas()->count());
        $this->assertSame(2, Traduccion::query()->where('texto', 'Hola mundo')->value('intentos'));
    }

    public function test_una_traduccion_que_pierde_una_variable_queda_en_error(): void
    {
        $this->fakeDeepl(fn (Request $r) => Http::response([
            'translations' => array_map(fn (string $t) => ['text' => str_replace('<x>:mesa</x>', '', '译'.$t)], $r['text']),
        ]));

        $this->sincronizar()->assertSuccessful();

        $fila = Traduccion::query()->where('texto', 'Mesa :mesa ocupada')->sole();
        $this->assertSame(Traduccion::ESTADO_ERROR, $fila->estado);
        $this->assertNull($fila->traduccion);
    }

    public function test_sin_clave_de_la_api_falla_si_se_pide_traducir(): void
    {
        config(['traduccion.deepl.api_key' => '']);
        Http::fake();

        $this->sincronizar()->assertFailed();
        $this->sincronizar(['--solo-extraer' => true])->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_la_correccion_de_un_tenant_no_se_modifica(): void
    {
        $tenant = Tenant::factory()->create();
        $correccion = TraduccionCorreccion::factory()->create([
            'tenant_id' => $tenant->id, 'texto' => 'Hola mundo', 'traduccion' => '你好，世界',
        ]);
        $this->fakeDeepl();

        $this->sincronizar()->assertSuccessful();

        $this->assertSame('你好，世界', $correccion->fresh()->traduccion);
        $this->assertSame('译Hola mundo', Traduccion::query()->where('texto', 'Hola mundo')->value('traduccion'));
    }

    // ── Glosario (US2) ───────────────────────────────────────────────────────────────────

    public function test_crea_el_glosario_versionado_y_lo_usa_en_cada_traduccion(): void
    {
        $this->fakeDeepl();

        $this->sincronizar()->assertSuccessful();

        $creacion = Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v2/glossaries'))->sole()[0];
        $this->assertMatchesRegularExpression('/^empire-pos-es-zh-[0-9a-f]{8}$/', $creacion['name']);
        $this->assertSame('es', $creacion['source_lang']);
        $this->assertSame('zh', $creacion['target_lang']);
        $this->assertStringContainsString("Precuenta\t预结单", $creacion['entries']);
        $this->assertStringContainsString("precuenta\t预结单", $creacion['entries']);

        foreach ($this->llamadasTraducir() as $llamada) {
            $this->assertSame('g-nuevo', $llamada['glossary_id']);
        }
    }

    public function test_reutiliza_el_glosario_vigente_y_borra_los_antiguos_del_prefijo(): void
    {
        $nombre = TraductorDeepl::nombreGlosario('zh', TraductorDeepl::conVariantes(config('traduccion.glosario.zh')));
        $this->glosariosRemotos = [
            ['glossary_id' => 'g-vigente', 'name' => $nombre],
            ['glossary_id' => 'g-viejo', 'name' => 'empire-pos-es-zh-00000000'],
            ['glossary_id' => 'g-ajeno', 'name' => 'otro-glosario'],
        ];
        $this->fakeDeepl();

        $this->sincronizar()->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v2/glossaries'));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/v2/glossaries/g-viejo'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/g-ajeno'));
        $this->assertSame('g-vigente', $this->llamadasTraducir()[0]['glossary_id']);
    }

    public function test_un_texto_en_mayusculas_se_envia_en_minusculas_y_respeta_las_siglas(): void
    {
        config(['traduccion.ambitos.prueba' => ['rutas' => [], 'claves_extra' => ['TOTAL FACTURADO', 'IVA INCLUIDO']]]);
        $this->fakeDeepl();

        $this->sincronizar()->assertSuccessful();

        $enviados = collect($this->llamadasTraducir())->flatMap(fn (Request $r) => $r['text'])->all();
        $this->assertContains('Total facturado', $enviados);
        $this->assertContains('<k>IVA</k> INCLUIDO', $enviados);
    }

    public function test_retraducir_vuelve_a_traducir_lo_automatico_sin_tocar_correcciones(): void
    {
        $this->fakeDeepl();
        $this->sincronizar()->assertSuccessful();
        $tenant = Tenant::factory()->create();
        TraduccionCorreccion::factory()->create(['tenant_id' => $tenant->id, 'texto' => 'Hola mundo', 'traduccion' => '你好']);

        $this->prefijo = '新';
        $this->sincronizar(['--retraducir' => true])->assertSuccessful();

        $this->assertSame('新Hola mundo', Traduccion::query()->where('texto', 'Hola mundo')->value('traduccion'));
        $this->assertSame('你好', TraduccionCorreccion::query()->withoutGlobalScopes()->sole()->traduccion);
    }

    public function test_cambiar_una_entrada_del_glosario_cambia_su_nombre(): void
    {
        $antes = TraductorDeepl::nombreGlosario('zh', ['Precuenta' => '预结单']);
        $despues = TraductorDeepl::nombreGlosario('zh', ['Precuenta' => '预结账单']);

        $this->assertNotSame($antes, $despues);
    }

    public function test_un_texto_que_es_exactamente_un_termino_se_traduce_sin_llamar_a_la_api(): void
    {
        config(['traduccion.ambitos.prueba' => ['rutas' => [], 'claves_extra' => ['Precuenta', 'mesa']]]);
        $this->fakeDeepl();

        $this->sincronizar()->assertSuccessful();

        $this->assertSame('预结单', Traduccion::query()->where('texto', 'Precuenta')->value('traduccion'));
        $this->assertSame('餐桌', Traduccion::query()->where('texto', 'mesa')->value('traduccion'));
        $this->assertSame([], $this->llamadasTraducir());
    }
}
