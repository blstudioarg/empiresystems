<?php

namespace Tests\Feature\Traduccion;

use App\Models\Articulo;
use App\Models\PosCuenta;
use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigPos;
use App\Support\MenuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DiccionarioFalso;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * US1 / FR-004…FR-009 / SC-001 / SC-002: con el POS en chino, todo texto propio de las pantallas
 * del POS sale traducido (aquí, con el diccionario falso `⟦…⟧`); los datos del negocio no; fuera
 * del POS todo sigue en español; con el POS en español nada cambia.
 */
class PosEnChinoTest extends TestCase
{
    use DiccionarioFalso, MontaSalaPos, RefreshDatabase;

    /** Pantallas del POS y una marca que tiene que aparecer traducida en cada una. */
    private const PANTALLAS = [
        '/pos' => 'Facturas simplificadas',
        '/pos/crear' => 'Cobrar',
        '/pos/sala' => 'Total de mesas',
        '/pos/caja' => 'La caja está cerrada',
        '/pos/caja/cierres' => 'Historial de cierres de caja',
        '/pos/opciones' => 'Grupos de opciones',
    ];

    private function montar(string $idioma = 'zh'): void
    {
        $this->montarSala(['opciones_activo' => true]);
        $this->enTeam($this->tenantPos, fn () => $this->usuarioPos->roles->first()->givePermissionTo('ver-pos-caja'));
        ConfigPos::guardar($this->tenantPos->id, ['idioma' => $idioma]);
        Articulo::factory()->create(['tenant_id' => $this->tenantPos->id, 'nombre' => '宫保鸡丁']);
        $this->cargarDiccionarioFalso();
    }

    /**
     * Textos visibles (nodos de texto y `title`/`placeholder`/`aria-label`) de la parte de la
     * página que pinta el POS: desde el contenido hasta los modales globales (confirmación y
     * ayuda). Quedan fuera la cabecera, el menú (solo el grupo POS se traduce, FR-009) y el
     * asistente IA (fuera de alcance).
     *
     * @return list<string>
     */
    private function textosSinTraducir(string $html, array $datosDelNegocio): array
    {
        $inicio = strpos($html, '>', strpos($html, 'class="content-body"')) + 1;
        $fin = strrpos(substr($html, 0, strpos($html, 'vendor/global/global.min.js')), '<script');
        $zona = substr($html, $inicio, $fin - $inicio);
        $zona = preg_replace('#<(script|style)\b.*?</\1>#si', '', $zona);
        // Un bloque traducido entero (guías de ayuda, con su HTML dentro) cuenta como traducido.
        $zona = preg_replace('/⟦.*?⟧/su', '', $zona);

        preg_match_all('/(?:title|placeholder|aria-label)="([^"]*)"/u', $zona, $atributos);
        $textos = array_merge($atributos[1], preg_split('/<[^>]+>/', $zona));

        return array_values(array_filter(array_map(fn ($t) => trim(html_entity_decode($t)), $textos), function (string $t) use ($datosDelNegocio) {
            if (! preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]{2,}/u', $t) || str_contains($t, '⟦')) {
                return false;
            }

            foreach ($datosDelNegocio as $dato) {
                if ($dato !== '' && str_contains($t, $dato)) {
                    return false;
                }
            }

            // Siglas y marcas que no se traducen nunca (FR-015).
            return ! in_array($t, ['IVA', 'IGIC', 'IPSI', 'NIF', 'TPV', 'POS'], true);
        }));
    }

    public function test_las_pantallas_del_pos_salen_traducidas_y_no_queda_texto_espanol_propio(): void
    {
        $this->montar();

        $datos = array_merge(
            Articulo::withoutGlobalScopes()->pluck('nombre')->all(),
            PosZona::withoutGlobalScopes()->pluck('nombre')->all(),
            PosMesa::withoutGlobalScopes()->pluck('nombre')->all(),
            User::query()->pluck('name')->all(),
            Tenant::query()->pluck('razon_social')->all(),
            Tenant::query()->pluck('nombre_comercial')->filter()->all(),
        );

        foreach (self::PANTALLAS as $url => $marca) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString(self::marcar($marca), $html, $url);
            $this->assertStringContainsString('window.posI18n', $html, $url);
            $this->assertSame([], $this->textosSinTraducir($html, $datos), "Textos sin traducir en {$url}");
        }
    }

    public function test_los_datos_del_negocio_no_se_traducen(): void
    {
        $this->montar();

        $this->get('/pos/crear')->assertOk()
            ->assertSee('宫保鸡丁')
            ->assertDontSee(self::marcar('宫保鸡丁'), false);
        $this->get('/pos/sala')->assertOk()->assertDontSee(self::marcar('Comedor'), false);
    }

    public function test_la_guia_de_ayuda_del_pos_sale_traducida(): void
    {
        $this->montar();

        $this->get('/pos')->assertOk()
            ->assertSee(self::marcar('Facturas simplificadas (POS)'), false)
            ->assertSee('⟦Acá ves los <strong>tickets</strong>', false);
    }

    public function test_el_cartel_de_modulo_inactivo_sale_traducido(): void
    {
        $this->montar();
        ConfigPos::guardar($this->tenantPos->id, ['hosteleria_activo' => false]);

        $this->get('/pos/sala')->assertStatus(403)
            ->assertSee(self::marcar('El módulo de hostelería está desactivado'), false)
            ->assertSee(self::marcar('El módulo de hostelería del POS está desactivado. Actívalo en Configuración → POS.'), false);
    }

    public function test_cobrar_sin_caja_devuelve_el_mensaje_traducido(): void
    {
        $this->montar();

        $this->postJson('/pos', ['lineas' => [['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => 1.5, 'tipo_impositivo' => 10]]])
            ->assertStatus(409)
            ->assertJsonPath('message', self::marcar('La caja está cerrada. Ábrela para poder cobrar.'));
    }

    public function test_guardar_una_cuenta_anulada_devuelve_el_mensaje_traducido(): void
    {
        $this->montar();
        $cuenta = PosCuenta::factory()->anulada()->create(['tenant_id' => $this->tenantPos->id, 'mesa_id' => $this->mesasPos[1]->id]);

        $this->putJson("/pos/cuentas/{$cuenta->id}", ['version' => $cuenta->version, 'lineas' => []])
            ->assertStatus(422)
            ->assertJsonPath('message', self::marcar('Esta cuenta ya no está abierta.'));
    }

    public function test_con_el_pos_en_espanol_los_mensajes_y_pantallas_no_cambian(): void
    {
        $this->montar('es');

        $this->postJson('/pos', ['lineas' => [['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => 1.5, 'tipo_impositivo' => 10]]])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La caja está cerrada. Ábrela para poder cobrar.');

        foreach (array_keys(self::PANTALLAS) as $url) {
            $this->get($url)->assertOk()->assertDontSee('⟦', false)->assertDontSee('window.posI18n', false);
        }
    }

    public function test_fuera_del_pos_la_app_sigue_en_espanol_salvo_el_grupo_pos_del_menu_y_la_ayuda(): void
    {
        $this->montar();

        $html = $this->get('/perfil')->assertOk()->getContent();

        // Fuera del POS: sin diccionario JS y el contenido en español.
        $this->assertStringNotContainsString('window.posI18n', $html);
        // Menú: el grupo POS y sus entradas, traducidos; el resto, no.
        $this->assertStringContainsString(self::marcar('Facturas simplificadas'), $html);
        $this->assertStringContainsString(self::marcar('Crear ticket'), $html);
        $this->assertStringContainsString(self::marcar('Ayuda de esta pantalla'), $html);
        $this->assertStringContainsString('<title>Mi perfil', $html);
        $this->assertStringNotContainsString('⟦Mi perfil', $html);
    }

    public function test_una_etiqueta_del_menu_personalizada_por_el_tenant_se_muestra_tal_cual(): void
    {
        $this->montar();
        MenuTenant::guardar($this->tenantPos->id, ['pos-crear' => 'Comandar'], []);

        $html = $this->get('/perfil')->assertOk()->getContent();

        $this->assertStringContainsString('Comandar', $html);
        $this->assertStringNotContainsString(self::marcar('Comandar'), $html);
    }
}
