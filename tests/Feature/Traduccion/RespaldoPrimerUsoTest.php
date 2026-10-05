<?php

namespace Tests\Feature\Traduccion;

use App\Models\Traduccion;
use App\Support\ConfigPos;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * US3-2 / FR-012 / FR-013 / SC-003 / SC-005: un texto sin traducción guardada se ve en español la
 * primera vez y se registra (y traduce) **después** de enviar la respuesta, nunca dentro de ella.
 * Si la API falla, el POS sigue igual y el texto queda pendiente.
 */
class RespaldoPrimerUsoTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    private bool $respuestaEnviada = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['traduccion.deepl.api_key' => 'clave-de-prueba', 'traduccion.deepl.api_url' => 'https://deepl.test']);
        Event::listen(RequestHandled::class, fn () => $this->respuestaEnviada = true);
    }

    private function montar(string $idioma): void
    {
        $this->montarSala();
        ConfigPos::guardar($this->tenantPos->id, ['idioma' => $idioma]);
    }

    public function test_un_texto_sin_traduccion_se_ve_en_espanol_y_se_traduce_despues_de_la_respuesta(): void
    {
        $this->montar('zh');
        $llamadasAntesDeResponder = 0;

        Http::fake([
            'deepl.test/v2/glossaries' => Http::response(['glossaries' => []]),
            'deepl.test/v2/translate' => function (Request $r) use (&$llamadasAntesDeResponder) {
                if (! $this->respuestaEnviada) {
                    $llamadasAntesDeResponder++;
                }

                return Http::response(['translations' => array_map(fn ($t) => ['text' => '译'.$t], $r['text'])]);
            },
        ]);

        $this->respuestaEnviada = false;
        $this->get('/pos')->assertOk()->assertSee('Tickets emitidos');

        $this->assertSame(0, $llamadasAntesDeResponder, 'La traducción no puede ocurrir dentro de la respuesta.');
        $fila = Traduccion::query()->where('texto', 'Tickets emitidos')->sole();
        $this->assertSame(Traduccion::ESTADO_TRADUCIDA, $fila->estado);
        $this->assertSame('译Tickets emitidos', $fila->traduccion);

        // La siguiente carga ya sale traducida, sin volver a llamar a la API por ese texto.
        $this->get('/pos')->assertOk()->assertSee('译Tickets emitidos');
    }

    public function test_si_la_api_falla_el_pos_responde_igual_y_el_texto_queda_pendiente(): void
    {
        $this->montar('zh');
        Http::fake([
            'deepl.test/*' => Http::response('caído', 503),
        ]);

        $this->get('/pos')->assertOk()->assertSee('Tickets emitidos');

        $fila = Traduccion::query()->where('texto', 'Tickets emitidos')->sole();
        $this->assertSame(Traduccion::ESTADO_PENDIENTE, $fila->estado);
        $this->assertSame(1, $fila->intentos);
    }

    public function test_con_el_pos_en_espanol_no_se_registra_nada(): void
    {
        $this->montar('es');
        Http::fake();

        $this->get('/pos')->assertOk();

        $this->assertSame(0, Traduccion::query()->count());
        Http::assertNothingSent();
    }
}
