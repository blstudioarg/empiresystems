<?php

namespace Tests\Feature\Asistente;

use App\Ia\ConocimientoAsistente;
use App\Ia\ConversacionAsistente;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AsistenteIa;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Propuestas en lote: varias escrituras del mismo turno se presentan en UNA tarjeta y se confirman
 * una sola vez.
 *
 * Antes, pedir "creá 10 clientes" creaba exactamente uno: la primera escritura se proponía y las
 * otras nueve chocaban con el guard de propuesta pendiente, y confirmar no reanudaba al modelo. El
 * usuario veía al asistente prometer diez altas y entregar una.
 *
 * La garantía de D4 no se relaja: sigue sin escribirse nada sin confirmación explícita, y el usuario
 * ve la lista completa antes de confirmar.
 */
class PropuestaEnLoteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->getTenantKey());
        IaTenant::guardarApiKey('sk-de-prueba', $this->tenant->id);
        $this->usuario = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($this->usuario);
        tenancy()->initialize($this->tenant);
    }

    /**
     * Asistente que pide `crear_cliente` tantas veces como nombres se le pasen, en un solo turno.
     *
     * @param  array<int, string>  $nombres
     */
    private function asistenteQuePide(array $nombres): AsistenteIa
    {
        $toolCalls = [];
        foreach (array_values($nombres) as $i => $nombre) {
            $toolCalls[] = (object) [
                'index' => $i,
                'id' => 'call_'.$i,
                'function' => (object) [
                    'name' => 'crear_cliente',
                    'arguments' => json_encode(['tipo' => 'particular', 'nombre' => $nombre]),
                ],
            ];
        }

        return new class(app(ConversacionAsistente::class), app(ConocimientoAsistente::class), $toolCalls) extends AsistenteIa
        {
            /** @param array<int, object> $toolCalls */
            public function __construct(
                ConversacionAsistente $conversacion,
                ConocimientoAsistente $conocimiento,
                private readonly array $toolCalls,
            ) {
                parent::__construct($conversacion, $conocimiento);
            }

            protected function abrirStream(array $params): iterable
            {
                return [(object) ['choices' => [(object) [
                    'delta' => (object) ['content' => null, 'toolCalls' => $this->toolCalls],
                    'finishReason' => 'tool_calls',
                ]]]];
            }
        };
    }

    public function test_diez_escrituras_del_mismo_turno_se_presentan_en_una_sola_tarjeta(): void
    {
        $nombres = array_map(fn (int $i) => "Cliente de Prueba {$i}", range(1, 10));

        $acciones = [];
        $this->asistenteQuePide($nombres)->responder($this->usuario, 'Creá 10 clientes de prueba', [
            'accionPendiente' => function (array $a) use (&$acciones) {
                $acciones[] = $a;
            },
        ]);

        // Una sola tarjeta...
        $this->assertCount(1, $acciones, 'Se presentó más de una tarjeta para el mismo turno.');
        // ...que lista las diez.
        $this->assertCount(10, $acciones[0]['resumenes']);
        // Y nada se ha escrito todavía: sigue haciendo falta confirmar (D4).
        $this->assertSame(0, Cliente::count());
    }

    public function test_la_propuesta_lleva_los_campos_completos_para_poder_revisarlos(): void
    {
        $acciones = [];
        $this->asistenteQuePide(['Acme', 'Beta'])->responder($this->usuario, 'Creá dos clientes', [
            'accionPendiente' => function (array $a) use (&$acciones) {
                $acciones[] = $a;
            },
        ]);

        $detalle = $acciones[0]['detalle'];

        // El resumen de una línea esconde lo que no cabe en él: sin los campos completos, confirmar
        // diez altas con la misma razón social y distinto NIF es firmar en blanco.
        $this->assertCount(2, $detalle);
        $etiquetas = array_column($detalle[0]['campos'], 'etiqueta');
        $this->assertContains('Nombre', $etiquetas);
        $this->assertContains('NIF/CIF', $etiquetas);

        $valores = array_column($detalle[0]['campos'], 'valor', 'etiqueta');
        $this->assertSame('Acme', $valores['Nombre']);
        // El NIF no lo dio el modelo: se ve el hueco, no se omite la columna.
        $this->assertSame('—', $valores['NIF/CIF']);
    }

    public function test_confirmar_una_vez_ejecuta_todo_el_lote(): void
    {
        $nombres = array_map(fn (int $i) => "Cliente de Prueba {$i}", range(1, 10));

        $acciones = [];
        $this->asistenteQuePide($nombres)->responder($this->usuario, 'Creá 10 clientes', [
            'accionPendiente' => function (array $a) use (&$acciones) {
                $acciones[] = $a;
            },
        ]);

        $this->postJson("/asistente/accion/{$acciones[0]['id']}/confirmar")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(10, Cliente::count());
    }

    public function test_una_accion_invalida_no_tumba_el_resto_del_lote(): void
    {
        // La del medio lleva un tipo que no existe: `TipoCliente::from()` revienta al ejecutarla.
        // Es el fallo realista en confirmación —los datos ya pasaron por `proponer()` al proponerse,
        // así que lo que puede romperse aquí es algo que cambió después o un dato imposible—.
        $conversacion = app(ConversacionAsistente::class);
        $conversacion->agregar('user', 'Creá clientes');

        $id = $conversacion->proponerAcciones([
            ['tool' => 'crear_cliente', 'parametros' => ['tipo' => 'particular', 'nombre' => 'Uno'], 'resumen' => 'Crear Uno', 'url' => null],
            ['tool' => 'crear_cliente', 'parametros' => ['tipo' => 'inexistente', 'nombre' => 'Rota'], 'resumen' => 'Crear Rota', 'url' => null],
            ['tool' => 'crear_cliente', 'parametros' => ['tipo' => 'particular', 'nombre' => 'Tres'], 'resumen' => 'Crear Tres', 'url' => null],
        ]);

        $respuesta = $this->postJson("/asistente/accion/{$id}/confirmar")->assertOk();

        // Las válidas entran; la rechazada se informa, no se silencia (criterio de los importadores).
        $this->assertSame(2, Cliente::count());
        $this->assertCount(1, $respuesta->json('rechazadas'));
        $this->assertStringContainsString('Rota', $respuesta->json('rechazadas.0'));
    }

    public function test_si_falla_todo_el_lote_responde_422_con_los_motivos(): void
    {
        $conversacion = app(ConversacionAsistente::class);
        $conversacion->agregar('user', 'Creá clientes');

        $id = $conversacion->proponerAcciones([
            ['tool' => 'crear_cliente', 'parametros' => ['tipo' => 'inexistente', 'nombre' => 'Rota 1'], 'resumen' => 'Crear Rota 1', 'url' => null],
            ['tool' => 'crear_cliente', 'parametros' => ['tipo' => 'inexistente', 'nombre' => 'Rota 2'], 'resumen' => 'Crear Rota 2', 'url' => null],
        ]);

        $this->postJson("/asistente/accion/{$id}/confirmar")
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(0, Cliente::count());
    }

    public function test_el_lote_tiene_un_tope_para_seguir_siendo_revisable(): void
    {
        $nombres = array_map(fn (int $i) => "Cliente {$i}", range(1, 30));

        $acciones = [];
        $this->asistenteQuePide($nombres)->responder($this->usuario, 'Creá 30 clientes', [
            'accionPendiente' => function (array $a) use (&$acciones) {
                $acciones[] = $a;
            },
        ]);

        // Una lista de 30 deja de ser revisable de un vistazo: se corta en 20 y el modelo recibe el
        // motivo para poder explicárselo al usuario.
        $this->assertCount(1, $acciones);
        $this->assertLessThanOrEqual(20, count($acciones[0]['resumenes']));
    }
}
