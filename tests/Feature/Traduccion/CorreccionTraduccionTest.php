<?php

namespace Tests\Feature\Traduccion;

use App\Models\Tenant;
use App\Models\Traduccion;
use App\Models\TraduccionCorreccion;
use App\Models\User;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * US4 / FR-016…FR-018 contra contracts/traducciones.md: listar, corregir y restaurar las
 * traducciones del POS del tenant desde Configuración → POS.
 */
class CorreccionTraduccionTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private function montar(string $idioma = 'zh', array $permisos = ['ver-configuracion']): void
    {
        $this->sembrarPermisos();
        $this->tenant = Tenant::factory()->create();
        ConfigPos::guardar($this->tenant->id, ['idioma' => $idioma]);
        $this->admin = $this->usuarioConRol($this->tenant, $this->crearRol($this->tenant, 'Admin', $permisos));

        Traduccion::factory()->traducida('收款')->create(['texto' => 'Cobrar']);
        Traduccion::factory()->traducida(':mesa 号餐桌')->create(['texto' => 'Mesa :mesa']);
        Traduccion::factory()->traducida('<strong>注意</strong>：说明')->create(['texto' => '<strong>Ojo</strong>: explicación', 'es_html' => true]);
        Traduccion::factory()->create(['texto' => 'Texto nuevo']);
        Traduccion::factory()->traducida('其他')->create(['texto' => 'De otro ámbito', 'ambito' => 'facturas']);

        $this->loginAs($this->admin);
    }

    private function url(string $texto): string
    {
        return '/configuracion/pos/traducciones/'.Traduccion::hashDe($texto);
    }

    public function test_el_listado_trae_los_textos_del_pos_con_su_origen(): void
    {
        $this->montar();

        $filas = collect($this->getJson('/configuracion/pos/traducciones')->assertOk()->assertJsonPath('idioma', 'zh')->json('data'))
            ->keyBy('texto');

        $this->assertSame(['automatica', '收款'], [$filas['Cobrar']['origen'], $filas['Cobrar']['traduccion']]);
        $this->assertSame('pendiente', $filas['Texto nuevo']['origen']);
        $this->assertTrue($filas['<strong>Ojo</strong>: explicación']['es_html']);
        $this->assertArrayNotHasKey('De otro ámbito', $filas->all());
    }

    public function test_con_el_pos_en_espanol_el_listado_esta_vacio(): void
    {
        $this->montar('es');

        $this->getJson('/configuracion/pos/traducciones')->assertOk()->assertExactJson(['idioma' => 'es', 'data' => []]);
    }

    public function test_corregir_crea_la_correccion_del_tenant_y_responde_la_fila(): void
    {
        $this->montar();

        $this->putJson($this->url('Cobrar'), ['traduccion' => '结账'])
            ->assertOk()
            ->assertJsonPath('origen', 'corregida')
            ->assertJsonPath('traduccion', '结账')
            ->assertJsonPath('corregida_por', $this->admin->name);

        $correccion = TraduccionCorreccion::query()->withoutGlobalScopes()->sole();
        $this->assertSame($this->tenant->id, (int) $correccion->tenant_id);
        $this->assertSame($this->admin->id, (int) $correccion->corregida_por);

        // Volver a corregir actualiza la misma fila.
        $this->putJson($this->url('Cobrar'), ['traduccion' => '买单'])->assertOk();
        $this->assertSame('买单', TraduccionCorreccion::query()->withoutGlobalScopes()->sole()->traduccion);
    }

    public function test_una_correccion_que_pierde_una_variable_responde_422(): void
    {
        $this->montar();

        $this->putJson($this->url('Mesa :mesa'), ['traduccion' => '餐桌'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('traduccion');

        $this->assertSame(0, TraduccionCorreccion::query()->withoutGlobalScopes()->count());
    }

    public function test_el_html_no_permitido_se_sanea(): void
    {
        $this->montar();

        $this->putJson($this->url('<strong>Ojo</strong>: explicación'), [
            'traduccion' => '<strong onclick="x()">注意</strong><script>alert(1)</script>：<em>说明</em>',
        ])->assertOk();

        $this->assertSame('<strong>注意</strong>alert(1)：<em>说明</em>', TraduccionCorreccion::query()->withoutGlobalScopes()->sole()->traduccion);

        // En un texto sin HTML no se admite ninguna etiqueta: los JS lo pintan como HTML.
        $this->putJson($this->url('Cobrar'), ['traduccion' => '<img src=x onerror=alert(1)>结账'])->assertOk();
        $this->assertSame('结账', TraduccionCorreccion::query()->withoutGlobalScopes()->where('texto', 'Cobrar')->sole()->traduccion);
    }

    public function test_un_hash_inexistente_responde_404(): void
    {
        $this->montar();

        $this->putJson($this->url('No existe'), ['traduccion' => '无'])->assertNotFound();
        $this->deleteJson($this->url('No existe'))->assertNotFound();
    }

    public function test_restaurar_borra_la_correccion_y_vuelve_la_automatica(): void
    {
        $this->montar();
        $this->putJson($this->url('Cobrar'), ['traduccion' => '结账'])->assertOk();

        $this->deleteJson($this->url('Cobrar'))
            ->assertOk()
            ->assertJsonPath('origen', 'automatica')
            ->assertJsonPath('traduccion', '收款');

        $this->assertSame(0, TraduccionCorreccion::query()->withoutGlobalScopes()->count());
        $this->deleteJson($this->url('Cobrar'))->assertNotFound();
    }

    public function test_sin_permiso_de_configuracion_responde_403(): void
    {
        $this->montar('zh', ['ver-pos']);

        $this->getJson('/configuracion/pos/traducciones')->assertForbidden();
        $this->putJson($this->url('Cobrar'), ['traduccion' => '结账'])->assertForbidden();
        $this->deleteJson($this->url('Cobrar'))->assertForbidden();
    }

    public function test_la_correccion_se_ve_en_el_pos(): void
    {
        $this->montar('zh', ['ver-configuracion', 'ver-pos-crear']);
        $this->putJson($this->url('Cobrar'), ['traduccion' => '结账'])->assertOk();

        $html = $this->get('/pos/crear')->assertOk()->getContent();

        $this->assertStringContainsString('<span>结账</span>', $html);
        // Y en el diccionario de los JS (`@json` escapa el chino como \uXXXX).
        $this->assertStringContainsString(substr(json_encode(['Cobrar' => '结账']), 1, -1), $html);
    }

    public function test_la_correccion_sobrevive_a_la_sincronizacion(): void
    {
        $this->montar();
        $this->putJson($this->url('Cobrar'), ['traduccion' => '结账'])->assertOk();
        config(['traduccion.deepl.api_key' => 'clave-de-prueba', 'traduccion.deepl.api_url' => 'https://deepl.test']);
        Http::fake([
            'deepl.test/v2/glossaries' => Http::response(['glossaries' => []]),
            'deepl.test/*' => Http::response(['translations' => [['text' => '译']], 'glossary_id' => 'g']),
        ]);

        $this->artisan('traducciones:sincronizar', ['--solo-extraer' => true])->assertSuccessful();
        $this->artisan('traducciones:sincronizar')->assertSuccessful();

        $this->assertSame('结账', TraduccionCorreccion::query()->withoutGlobalScopes()->sole()->traduccion);
    }
}
