<?php

namespace Tests\Feature;

use App\Enums\TipoEventoFichaje;
use App\Enums\UserRole;
use App\Enums\VeredictoCumplimiento;
use App\Models\AsignacionHorario;
use App\Models\Fichaje;
use App\Models\Horario;
use App\Models\HorarioTramo;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Cumplimiento\ServicioCumplimiento;
use App\Support\RangoFechas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioEventosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Congela "hoy" para que la regla D4 (sin veredicto en fechas >= hoy) sea determinista:
        // 2026-08-03..07 son pasado, 2026-08-20 es hoy y 2026-08-25 futuro.
        Carbon::setTestNow('2026-08-20 12:00:00');
    }

    private function crearAdmin(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
    }

    /**
     * Miembro con horario L-V 09:00-17:00 vigente desde 2026-01-01.
     */
    private function crearMiembroConHorario(Tenant $tenant): MiembroEquipo
    {
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        foreach (range(1, 5) as $dia) {
            HorarioTramo::factory()->for($tenant)->create([
                'horario_id' => $horario->id,
                'dia_semana' => $dia,
                'hora_inicio' => '09:00:00',
                'hora_fin' => '17:00:00',
            ]);
        }
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);

        return $miembro;
    }

    private function ficha(Tenant $tenant, MiembroEquipo $miembro, TipoEventoFichaje $tipo, Carbon $momento): Fichaje
    {
        return Fichaje::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'tipo' => $tipo,
            'ocurrido_at' => $momento,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>> eventos del feed filtrados por tipo
     */
    private function eventosDeTipo(array $eventos, string $tipo): array
    {
        return array_values(array_filter($eventos, fn (array $e) => ($e['extendedProps']['tipo'] ?? null) === $tipo));
    }

    // ------------------------------------------------------------------ US1

    public function test_feed_modo_miembro_emite_veredicto_por_dia_coherente_con_el_informe(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConHorario($tenant);

        // Lunes 03: retraso. Martes 04: ausencia. Miércoles 05: parcial. Sábado 08: libre.
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-03 09:20'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Salida, Carbon::parse('2026-08-03 17:00'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-05 09:00'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Salida, Carbon::parse('2026-08-05 13:00'));

        $this->loginAs($admin);

        $response = $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-09&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        $veredictos = collect($this->eventosDeTipo($response->json(), 'veredicto_dia'))
            ->keyBy(fn (array $e) => substr($e['start'], 0, 10));

        $this->assertSame('retraso', $veredictos['2026-08-03']['extendedProps']['veredicto']);
        $this->assertSame('ausencia', $veredictos['2026-08-04']['extendedProps']['veredicto']);
        $this->assertSame('parcial', $veredictos['2026-08-05']['extendedProps']['veredicto']);
        $this->assertSame('libre', $veredictos['2026-08-08']['extendedProps']['veredicto']);

        $this->assertSame(['cal-veredicto-retraso'], $veredictos['2026-08-03']['classNames']);
        $this->assertSame(8.0, (float) $veredictos['2026-08-03']['extendedProps']['horas_previstas']);
        $this->assertSame(4.0, (float) $veredictos['2026-08-05']['extendedProps']['horas_trabajadas']);
        $this->assertGreaterThan(0, $veredictos['2026-08-03']['extendedProps']['minutos_retraso']);

        // SC-001: mismo veredicto que el informe de cumplimiento para el mismo rango.
        $rango = RangoFechas::personalizado(Carbon::parse('2026-08-03'), Carbon::parse('2026-08-08'));
        $informe = app(ServicioCumplimiento::class)->evaluarRango($miembro, $rango);
        foreach ($informe as $dia) {
            $this->assertSame(
                $dia->veredicto->value,
                $veredictos[$dia->fecha->toDateString()]['extendedProps']['veredicto'],
                'Veredicto del calendario difiere del informe para '.$dia->fecha->toDateString(),
            );
        }
    }

    public function test_fichaje_incompleto_marca_incidencia_en_el_dia(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConHorario($tenant);

        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-03 09:00'));

        $this->loginAs($admin);

        $response = $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-04&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        $veredicto = $this->eventosDeTipo($response->json(), 'veredicto_dia')[0];
        $this->assertTrue($veredicto['extendedProps']['incidencia']);
    }

    public function test_fechas_desde_hoy_no_llevan_veredicto(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConHorario($tenant);

        $this->loginAs($admin);

        // Rango que cruza "hoy" (2026-08-20): 18-19 pasado, 20-24 hoy/futuro.
        $response = $this->getJson('/calendario/eventos?start=2026-08-18&end=2026-08-25&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        $fechas = array_map(
            fn (array $e) => substr($e['start'], 0, 10),
            $this->eventosDeTipo($response->json(), 'veredicto_dia'),
        );
        $this->assertNotEmpty($fechas);
        foreach ($fechas as $fecha) {
            $this->assertLessThan('2026-08-20', $fecha, "Un día >= hoy ($fecha) lleva veredicto");
        }
    }

    public function test_start_y_end_son_requeridos(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $this->loginAs($admin);

        $this->getJson('/calendario/eventos')->assertStatus(422);
        $this->getJson('/calendario/eventos?start=2026-08-01')->assertStatus(422);
    }

    public function test_rango_mayor_a_62_dias_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $this->loginAs($admin);

        $this->getJson('/calendario/eventos?start=2026-01-01&end=2026-03-15')->assertStatus(422);
        $this->getJson('/calendario/eventos?start=2026-08-10&end=2026-08-01')->assertStatus(422);
    }

    public function test_miembro_de_otro_tenant_devuelve_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = $this->crearAdmin($tenantA);
        $miembroB = $this->crearMiembroConHorario($tenantB);

        $this->loginAs($adminA);

        $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-09&miembro_equipo_id='.$miembroB->id)
            ->assertNotFound();
    }

    public function test_usuario_sin_permiso_recibe_403_en_vista_y_feed(): void
    {
        $tenant = Tenant::factory()->create();
        $empleado = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Usuario, 'password' => bcrypt('secret123')]);

        $this->loginAs($empleado);

        $this->get('/calendario')->assertForbidden();
        $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-09')->assertForbidden();
    }

    public function test_el_contenido_del_feed_no_mezcla_datos_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = $this->crearAdmin($tenantA);
        $miembroA = $this->crearMiembroConHorario($tenantA);
        $miembroB = $this->crearMiembroConHorario($tenantB);

        // Solo B ficha el lunes 03: para A ese día debe seguir siendo ausencia.
        $this->ficha($tenantB, $miembroB, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-03 09:00'));
        $this->ficha($tenantB, $miembroB, TipoEventoFichaje::Salida, Carbon::parse('2026-08-03 17:00'));

        $this->loginAs($adminA);

        $response = $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-04&miembro_equipo_id='.$miembroA->id);

        $response->assertOk();
        $veredicto = $this->eventosDeTipo($response->json(), 'veredicto_dia')[0];
        $this->assertSame(VeredictoCumplimiento::Ausencia->value, $veredicto['extendedProps']['veredicto']);
        $this->assertSame(0.0, (float) $veredicto['extendedProps']['horas_trabajadas']);
    }

    // ------------------------------------------------------------------ US2

    /**
     * Miembro con turno partido L-V 09:00-13:00 / 15:00-19:00.
     */
    private function crearMiembroConTurnoPartido(Tenant $tenant): MiembroEquipo
    {
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        foreach (range(1, 5) as $dia) {
            HorarioTramo::factory()->for($tenant)->create(['horario_id' => $horario->id, 'dia_semana' => $dia, 'hora_inicio' => '09:00:00', 'hora_fin' => '13:00:00']);
            HorarioTramo::factory()->for($tenant)->create(['horario_id' => $horario->id, 'dia_semana' => $dia, 'hora_inicio' => '15:00:00', 'hora_fin' => '19:00:00']);
        }
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);

        return $miembro;
    }

    public function test_turno_partido_emite_dos_eventos_previstos_incluso_en_futuro(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConTurnoPartido($tenant);

        $this->loginAs($admin);

        // Lunes 24 de agosto de 2026: futuro (hoy congelado = 2026-08-20).
        $response = $this->getJson('/calendario/eventos?start=2026-08-24&end=2026-08-25&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        $previstos = $this->eventosDeTipo($response->json(), 'previsto');
        $this->assertCount(2, $previstos);
        $this->assertSame('2026-08-24T09:00:00', $previstos[0]['start']);
        $this->assertSame('2026-08-24T13:00:00', $previstos[0]['end']);
        $this->assertSame('2026-08-24T15:00:00', $previstos[1]['start']);
        $this->assertSame('2026-08-24T19:00:00', $previstos[1]['end']);
        $this->assertSame(['cal-previsto'], $previstos[0]['classNames']);
    }

    public function test_cambio_de_horario_a_mitad_de_rango_cada_dia_usa_su_horario_vigente(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);

        $horarioA = Horario::factory()->for($tenant)->create();
        HorarioTramo::factory()->for($tenant)->create(['horario_id' => $horarioA->id, 'dia_semana' => 1, 'hora_inicio' => '09:00:00', 'hora_fin' => '13:00:00']);
        $horarioB = Horario::factory()->for($tenant)->create();
        HorarioTramo::factory()->for($tenant)->create(['horario_id' => $horarioB->id, 'dia_semana' => 1, 'hora_inicio' => '10:00:00', 'hora_fin' => '18:00:00']);

        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id, 'horario_id' => $horarioA->id,
            'vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-08-04',
        ]);
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id, 'horario_id' => $horarioB->id,
            'vigente_desde' => '2026-08-05', 'vigente_hasta' => null,
        ]);

        $this->loginAs($admin);

        // Lunes 03 (horario A) y lunes 10 (horario B).
        $response = $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-11&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        $previstos = collect($this->eventosDeTipo($response->json(), 'previsto'))->keyBy(fn (array $e) => substr($e['start'], 0, 10));
        $this->assertCount(2, $previstos);
        $this->assertSame('2026-08-03T09:00:00', $previstos['2026-08-03']['start']);
        $this->assertSame('2026-08-10T10:00:00', $previstos['2026-08-10']['start']);
    }

    public function test_intervalos_reales_excluyen_pausas_y_el_incompleto_no_dibuja_intervalo(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConTurnoPartido($tenant);

        // Lunes 03: 09:20-13:00 y 15:00-19:30 (dos intervalos). Martes 04: entrada sin salida.
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-03 09:20'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::InicioPausa, Carbon::parse('2026-08-03 13:00'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::FinPausa, Carbon::parse('2026-08-03 15:00'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Salida, Carbon::parse('2026-08-03 19:30'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-04 09:00'));

        $this->loginAs($admin);

        $response = $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-05&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        $reales = $this->eventosDeTipo($response->json(), 'real');

        // Solo los 2 intervalos del lunes: el martes (incompleto) no dibuja intervalo real...
        $this->assertCount(2, $reales);
        $this->assertSame(['cal-real'], $reales[0]['classNames']);
        $this->assertStringStartsWith('2026-08-03', $reales[0]['start']);
        $this->assertStringStartsWith('2026-08-03', $reales[1]['start']);

        // ...pero sí marca de incidencia en su veredicto.
        $veredictos = collect($this->eventosDeTipo($response->json(), 'veredicto_dia'))->keyBy(fn (array $e) => substr($e['start'], 0, 10));
        $this->assertTrue($veredictos['2026-08-04']['extendedProps']['incidencia']);
    }

    public function test_fichajes_en_dia_libre_son_visibles_como_intervalo_real(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConTurnoPartido($tenant);

        // Sábado 08: sin tramos previstos, pero fichó.
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-08 10:00'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Salida, Carbon::parse('2026-08-08 12:00'));

        $this->loginAs($admin);

        $response = $this->getJson('/calendario/eventos?start=2026-08-08&end=2026-08-09&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        $this->assertCount(0, $this->eventosDeTipo($response->json(), 'previsto'));
        $reales = $this->eventosDeTipo($response->json(), 'real');
        $this->assertCount(1, $reales);
    }

    // ------------------------------------------------------------------ US3

    public function test_modo_equipo_agrega_recuentos_y_miembros_afectados_por_dia(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembroA = $this->crearMiembroConHorario($tenant);
        $miembroB = $this->crearMiembroConHorario($tenant);

        // Lunes 03: A con retraso, B ausente. Martes 04: solo B con incidencia (entrada sin salida).
        $this->ficha($tenant, $miembroA, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-03 09:20'));
        $this->ficha($tenant, $miembroA, TipoEventoFichaje::Salida, Carbon::parse('2026-08-03 17:00'));
        $this->ficha($tenant, $miembroA, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-04 09:00'));
        $this->ficha($tenant, $miembroA, TipoEventoFichaje::Salida, Carbon::parse('2026-08-04 17:00'));
        $this->ficha($tenant, $miembroB, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-04 09:00'));

        $this->loginAs($admin);

        $response = $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-05');

        $response->assertOk();
        $resumen = collect($this->eventosDeTipo($response->json(), 'resumen_equipo'))->keyBy(fn (array $e) => substr($e['start'], 0, 10));

        $lunes = $resumen['2026-08-03']['extendedProps'];
        $this->assertSame(1, $lunes['ausencias']);
        $this->assertSame(1, $lunes['retrasos']);
        $this->assertCount(2, $lunes['miembros']);

        $martes = $resumen['2026-08-04']['extendedProps'];
        $this->assertSame(1, $martes['incidencias']);
        $this->assertCount(1, $martes['miembros']);
        $this->assertSame($miembroB->id, $martes['miembros'][0]['id']);
    }

    public function test_modo_equipo_dias_sin_incumplimientos_no_emiten_evento_ni_los_futuros(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConHorario($tenant);

        // Miércoles 05: día cumplido → sin evento de resumen.
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-05 09:00'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Salida, Carbon::parse('2026-08-05 17:00'));

        $this->loginAs($admin);

        // Rango que cruza "hoy": 05 (cumplido), sábado 08 (libre), y 20-24 (hoy/futuro con ausencias "aparentes").
        $response = $this->getJson('/calendario/eventos?start=2026-08-05&end=2026-08-25');

        $response->assertOk();
        $fechas = array_map(
            fn (array $e) => substr($e['start'], 0, 10),
            $this->eventosDeTipo($response->json(), 'resumen_equipo'),
        );
        $this->assertNotContains('2026-08-05', $fechas);
        $this->assertNotContains('2026-08-08', $fechas);
        foreach ($fechas as $fecha) {
            $this->assertLessThan('2026-08-20', $fecha, "Resumen de equipo en fecha >= hoy ($fecha)");
        }
    }

    public function test_modo_equipo_solo_evalua_miembros_activos(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $inactivo = $this->crearMiembroConHorario($tenant);
        $inactivo->update(['activo' => false]);

        $this->loginAs($admin);

        // El inactivo estaría "ausente" el lunes 03, pero no debe evaluarse.
        $response = $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-04');

        $response->assertOk();
        $this->assertCount(0, $this->eventosDeTipo($response->json(), 'resumen_equipo'));
    }

    public function test_modo_equipo_no_mezcla_miembros_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = $this->crearAdmin($tenantA);
        $this->crearMiembroConHorario($tenantB); // ausente el lunes 03 en B

        $this->loginAs($adminA);

        $response = $this->getJson('/calendario/eventos?start=2026-08-03&end=2026-08-04');

        $response->assertOk();
        $this->assertCount(0, $this->eventosDeTipo($response->json(), 'resumen_equipo'));
    }

    // ------------------------------------------------------------------ US4

    public function test_el_detalle_de_fichajes_viaja_en_extended_props_y_una_correccion_recalcula_el_veredicto(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConHorario($tenant);

        // Lunes 03: entrada 09:20 (retraso) + salida 17:00.
        $entrada = $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-03 09:20'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Salida, Carbon::parse('2026-08-03 17:00'));

        $this->loginAs($admin);

        $url = '/calendario/eventos?start=2026-08-03&end=2026-08-04&miembro_equipo_id='.$miembro->id;

        $response = $this->getJson($url);
        $response->assertOk();
        $veredicto = $this->eventosDeTipo($response->json(), 'veredicto_dia')[0];
        $this->assertSame('retraso', $veredicto['extendedProps']['veredicto']);

        $fichajes = $veredicto['extendedProps']['fichajes'];
        $this->assertCount(2, $fichajes);
        $this->assertSame($entrada->id, $fichajes[0]['id']);
        $this->assertSame('entrada', $fichajes[0]['tipo']);
        $this->assertArrayHasKey('hora', $fichajes[0]);
        $this->assertArrayHasKey('resultado_ubicacion', $fichajes[0]);
        $this->assertFalse($fichajes[0]['es_correccion']);
        $this->assertNotNull($fichajes[0]['corregir_url']);

        // Corrección vía el endpoint existente de 024: la entrada real fue a las 09:00 UTC.
        // El endpoint interpreta `ocurrido_at` en la zona del tenant, así que se envía la
        // representación local de ese instante (mismo contrato que el modal de /jornada).
        $ocurridoLocal = Carbon::parse('2026-08-03 09:00', 'UTC')
            ->setTimezone(\App\Support\ConfigTenant::zonaHoraria($tenant->id))
            ->format('Y-m-d\TH:i');
        $this->post('/fichajes/'.$entrada->id.'/corregir', [
            'tipo' => 'entrada',
            'ocurrido_at' => $ocurridoLocal,
            'motivo' => 'Olvidó fichar al llegar',
        ])->assertSessionHas('success');

        // El feed re-consultado refleja el nuevo veredicto (cálculo al vuelo, sin caché) y
        // señala la corrección en el detalle; el original sigue en el ledger.
        $response = $this->getJson($url);
        $veredicto = $this->eventosDeTipo($response->json(), 'veredicto_dia')[0];
        $this->assertSame('cumplido', $veredicto['extendedProps']['veredicto']);

        $fichajes = collect($veredicto['extendedProps']['fichajes']);
        $this->assertCount(3, $fichajes);
        $correccion = $fichajes->firstWhere('es_correccion', true);
        $this->assertNotNull($correccion);
        $this->assertSame($entrada->id, $correccion['corrige_fichaje_id']);
        $this->assertNull($correccion['corregir_url']);
    }

    // ------------------------------------------------------------------ Panel de métricas (resumen)

    public function test_resumen_modo_miembro_agrega_kpis(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConHorario($tenant);

        // Lunes 03: cumplido (09:00-17:00). Martes 04: retraso. Miércoles 05: ausencia (sin fichar).
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-03 09:00'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Salida, Carbon::parse('2026-08-03 17:00'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-04 09:25'));
        $this->ficha($tenant, $miembro, TipoEventoFichaje::Salida, Carbon::parse('2026-08-04 17:00'));

        $this->loginAs($admin);

        $response = $this->getJson('/calendario/resumen?start=2026-08-03&end=2026-08-06&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        $data = $response->json();

        $this->assertSame('miembro', $data['modo']);
        // 3 días laborables (L, M, X), 1 cumplido → 33%.
        $this->assertSame(3, $data['kpis']['dias_laborables']);
        $this->assertSame(1, $data['kpis']['dias_cumplidos']);
        $this->assertSame(33.0, (float) $data['kpis']['cumplimiento_pct']);
        $this->assertSame(1, $data['kpis']['dias_retraso']);
        $this->assertSame(1, $data['kpis']['ausencias']);
        $this->assertSame(24.0, (float) $data['kpis']['horas_previstas']);
    }

    public function test_resumen_solo_considera_dias_pasados(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembro = $this->crearMiembroConHorario($tenant);

        $this->loginAs($admin);

        // Rango 18-25: solo 18-19 son pasado (hoy congelado = 2026-08-20).
        $response = $this->getJson('/calendario/resumen?start=2026-08-18&end=2026-08-25&miembro_equipo_id='.$miembro->id);

        $response->assertOk();
        // Martes 18 y miércoles 19 laborables, ambos ausencia (no fichó); nada desde hoy.
        $this->assertSame(2, $response->json('kpis.dias_laborables'));
        $this->assertSame(2, $response->json('kpis.ausencias'));
    }

    public function test_resumen_modo_equipo_suma_todos_los_miembros_activos(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $miembroA = $this->crearMiembroConHorario($tenant);
        $this->crearMiembroConHorario($tenant); // B: no ficha → ausencias

        // A cumple el lunes 03.
        $this->ficha($tenant, $miembroA, TipoEventoFichaje::Entrada, Carbon::parse('2026-08-03 09:00'));
        $this->ficha($tenant, $miembroA, TipoEventoFichaje::Salida, Carbon::parse('2026-08-03 17:00'));

        $this->loginAs($admin);

        $response = $this->getJson('/calendario/resumen?start=2026-08-03&end=2026-08-04');

        $response->assertOk();
        $this->assertSame('equipo', $response->json('modo'));
        // 2 miembros × 1 día laborable = 2; A cumplido, B ausente.
        $this->assertSame(2, $response->json('kpis.dias_laborables'));
        $this->assertSame(1, $response->json('kpis.dias_cumplidos'));
        $this->assertSame(1, $response->json('kpis.ausencias'));
    }

    public function test_resumen_requiere_permiso_y_valida_rango(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $this->loginAs($admin);

        $this->getJson('/calendario/resumen')->assertStatus(422);
        $this->getJson('/calendario/resumen?start=2026-01-01&end=2026-03-15')->assertStatus(422);
    }
}
