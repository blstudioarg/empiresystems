# Tasks: Gestión de horarios de trabajo y cumplimiento de jornada

**Feature**: 025-gestion-horarios-trabajo
**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Data model**: [data-model.md](./data-model.md) · **Contrato**: [contracts/http.md](./contracts/http.md)

**Test-first**: El proyecto exige TDD en lógica crítica (Constitución, Principio IV): aislamiento
multi-tenant, resolución de horario vigente, cálculo de horas y clasificación de cumplimiento,
idempotencia del comando. Esas tareas escriben primero sus tests (que fallan) y luego la
implementación.

**Convención de rutas**: monolito Laravel en la raíz del repo (`app/`, `resources/`, `database/`,
`routes/`, `tests/`). Reutiliza el módulo 024 (`miembros_equipo`, `fichajes`, `alertas`).

---

## Phase 1: Setup

- [X] T001 Leer los modelos/enums de la feature 024 (`app/Models/MiembroEquipo.php`, `Fichaje.php`, `Alerta.php`, `app/Enums/TipoAlerta.php`, `TipoEventoFichaje.php`) y `app/Support/ConfigFichajes.php` para confirmar nombres reales de columnas/relaciones reutilizadas; anotar cualquier desvío respecto a [data-model.md](./data-model.md).
- [X] T002 Verificar en la migración original de `alertas` (024) si `alertas.fichaje_id` es nullable y su `onDelete`; anotar en [data-model.md](./data-model.md) si hace falta hacerlo nullable para las alertas de jornada (caso ausencia sin fichaje).

---

## Phase 2: Foundational (bloquea todas las stories)

**Migraciones, modelos y enums base — prerrequisito de todo el servicio.**

- [X] T003 [P] Crear migración `database/migrations/..._create_horarios_table.php` (`tenant_id`, `nombre`, `activo`, softDeletes, timestamps; único `(tenant_id, nombre)`), según [data-model.md](./data-model.md).
- [X] T004 [P] Crear migración `..._create_horario_tramos_table.php` (`tenant_id`, `horario_id` cascade, `dia_semana` tinyint, `hora_inicio`/`hora_fin` time, timestamps; índice `(tenant_id, horario_id, dia_semana)`).
- [X] T005 [P] Crear migración `..._create_asignaciones_horario_table.php` (`tenant_id`, `miembro_equipo_id` cascade, `horario_id` restrict, `vigente_desde` date, `vigente_hasta` date nullable, timestamps; índices de [data-model.md](./data-model.md)).
- [X] T006 [P] Crear migración `..._add_referencia_fecha_to_alertas_table.php`: columna `referencia_fecha` date nullable (dedup de alertas de jornada); y, si T002 lo confirmó, hacer `fichaje_id` nullable.
- [X] T007 [P] Extender `app/Enums/TipoAlerta.php` con `AusenciaJornada = 'ausencia_jornada'` y `RetrasoJornada = 'retraso_jornada'` + sus `label()`.
- [X] T008 [P] Crear modelo `app/Models/Horario.php` (`BelongsToTenant`, SoftDeletes, `hasMany` tramos/asignaciones, `$fillable`, métodos `horasPrevistasDia(int)`/`horasPrevistasSemana()`).
- [X] T009 [P] Crear modelo `app/Models/HorarioTramo.php` (`BelongsToTenant`, `belongsTo` horario, cast `dia_semana` int, `hora_inicio`/`hora_fin`).
- [X] T010 [P] Crear modelo `app/Models/AsignacionHorario.php` (`BelongsToTenant`, `belongsTo` miembro/horario, casts de fechas, scope/método `vigenteEn(Carbon)`).
- [X] T011 [P] Crear factories `database/factories/{Horario,HorarioTramo,AsignacionHorario}Factory.php`.
- [X] T012 Extender `app/Support/ConfigFichajes.php` con `CLAVE_TOLERANCIA_RETRASO_MIN`/`CLAVE_TOLERANCIA_EXCESO_MIN` (+ métodos `toleranciaRetrasoMin`/`toleranciaExcesoMin`, defaults 5/15) y añadir sus claves a `database/seeders/ConfiguracionSeeder.php` (grupo `fichajes`).

**Checkpoint**: esquema y modelos disponibles; el resto de fases construye encima.

---

## Phase 3: User Story 1 — Definir horarios reutilizables (P1) 🎯 MVP

**Goal**: Admin crea/edita/lista horarios con tramos por día (turnos partidos, día libre), con
validación de solape y horas previstas correctas.

**Independent Test**: "Jornada mañana" L-V 09:00–13:00 + 15:00–19:00 ⇒ 40 h/semana; fin ≤ inicio o
solape ⇒ 422; borrado bloqueado si asignado.

### Tests (escribir primero)

- [X] T013 [P] [US1] En `tests/Feature/HorarioCrudTest.php`, tests de: crear horario con turno partido (horas semana = 40), día sin tramos = 0 h, rechazo de `hora_fin <= hora_inicio` y de tramos solapados el mismo día, unicidad de nombre por tenant. (Debe fallar.)
- [X] T014 [P] [US1] En `tests/Feature/HorarioCrudTest.php`, test de aislamiento multi-tenant (los horarios de un tenant no se ven ni editan desde otro). (Debe fallar.)
- [X] T015 [P] [US1] En `tests/Feature/HorarioCrudTest.php`, test de que borrar un horario con asignaciones responde 422 y no borra. (Debe fallar.)

### Implementación

- [X] T016 [US1] Crear `app/Http/Requests/HorarioRequest.php`: valida `nombre` (único por tenant), `activo`, y `tramos[]` (`dia_semana` 1–7, `hora_fin > hora_inicio`, sin solape dentro de un mismo día).
- [X] T017 [US1] Crear `app/Http/Controllers/HorarioController.php` (`index` HTML+JSON `data`, `store`, `update` reemplazando tramos en transacción, `destroy` con chequeo de asignaciones → 422); resolución **manual** del modelo tenant-scoped en `update`/`destroy` (memoria `project_tenant_route_binding`).
- [X] T018 [US1] Registrar rutas `Route::resource('horarios', HorarioController::class)->only(['index','store','update','destroy'])` bajo `can:gestiona-fichajes` en `routes/web.php`.
- [X] T019 [US1] Crear vista `resources/views/horarios/index.blade.php` (DataTable + modal CRUD según `docs/04-front-guidelines.md`: override de paginación, tamaño sm, modal centrado, columna Acciones dropdown) con editor de tramos por día (turno partido) + init JS `public/js/plugins-init/horarios-*.init.js`. Enlace en `partials/sidebar.blade.php`. Ejecutar `php artisan test --filter=HorarioCrud` hasta verde.

**Checkpoint**: catálogo de horarios funcional y probado.

---

## Phase 4: User Story 2 — Asignar horarios con vigencia (P1)

**Goal**: Asignar un horario a un miembro con `vigente_desde`, cerrar la anterior automáticamente,
resolver el horario aplicable por fecha, conservar histórico, rechazar solapes.

**Independent Test**: horario A desde 01-01, B desde 01-03 ⇒ febrero resuelve A, marzo B; A se cierra
el 28-02; solape ⇒ error.

### Tests (escribir primero)

- [X] T020 [P] [US2] En `tests/Unit/AsignacionHorarioVigenteTest.php`, tests de resolución del horario vigente por fecha, cierre automático de la anterior (`vigente_hasta = desde − 1`), y rechazo de solape. (Debe fallar.)
- [X] T021 [P] [US2] En `tests/Feature/AsignacionHorarioTest.php`, tests de: asignar vía endpoint, histórico ordenado, aislamiento multi-tenant, y conservación tras baja del miembro. (Debe fallar.)

### Implementación

- [X] T022 [US2] Añadir a `App\Models\AsignacionHorario` (o un `App\Support\ResolutorHorario`) el método `horarioVigente(MiembroEquipo, Carbon): ?Horario` y la lógica de cierre/no-solape, cubierto por T020.
- [X] T023 [US2] Crear `app/Http/Requests/AsignacionHorarioRequest.php` (`horario_id` del tenant y activo, `vigente_desde` válida, sin solape con rangos existentes del miembro).
- [X] T024 [US2] Crear `app/Http/Controllers/AsignacionHorarioController.php` (`index` histórico JSON, `store` con cierre transaccional de la anterior, `destroy`); rutas anidadas `miembros-equipo/{miembro}/horarios` + `asignaciones-horario/{asignacion}` bajo `can:gestiona-fichajes` en `routes/web.php`.
- [X] T025 [US2] Integrar la asignación en el modal de `resources/views/miembros-equipo/index.blade.php` (selector de horario + `vigente_desde`, horario vigente actual + histórico) y su init JS. Ejecutar `php artisan test --filter=AsignacionHorario` hasta verde.

**Checkpoint**: asignación con vigencia e histórico funcional; horario aplicable resoluble por fecha.

---

## Phase 5: User Story 3 — Turno esperado en Mi jornada (P2)

**Goal**: El miembro ve su turno previsto de hoy y de la semana en `/mi-jornada`; estado vacío si no
tiene horario.

**Independent Test**: miembro con horario ⇒ ve tramos de hoy/semana; sin horario ⇒ estado vacío.

### Tests (escribir primero)

- [X] T026 [P] [US3] En `tests/Feature/MiJornadaTurnoEsperadoTest.php`, tests de: turno de hoy según horario vigente, día libre sin tramos, y estado vacío sin horario. (Debe fallar.)

### Implementación

- [X] T027 [US3] Extender `app/Http/Controllers/MiJornadaController.php@index` para inyectar `turno_hoy` y `turno_semana` (usando el resolutor de T022 sobre el miembro autenticado).
- [X] T028 [US3] Añadir el bloque "Turno esperado" en la(s) vista(s) de `resources/views/mi-jornada/` (hoy + semana, estado vacío claro), aplicando las skills de diseño. Ejecutar `php artisan test --filter=MiJornada` hasta verde.

**Checkpoint**: el trabajador ve su cuadrante.

---

## Phase 6: User Story 4 — Informe de cumplimiento previsto vs. real (P2)

**Goal**: Informe por rango/miembro con horas previstas vs. trabajadas y veredicto por día
(retraso/ausencia/parcial/exceso/libre), cada día contra su horario vigente, calculado al vuelo.

**Independent Test**: día 09:20 (retraso), sin fichar (ausencia), 18:30 (exceso), 13:00 (parcial) ⇒
marcas correctas; rango que cruza cambio de horario ⇒ cada día contra el vigente.

### Tests (escribir primero)

- [X] T029 [P] [US4] En `tests/Unit/ServicioCumplimientoTest.php`, tests de `horasTrabajadas` (emparejar entrada/salida, restar pausas, fichaje incompleto = incidencia sin computar). (Debe fallar.)
- [X] T030 [P] [US4] En `tests/Unit/ServicioCumplimientoTest.php`, tests de clasificación por día: retraso por cada tramo, ausencia (0 fichajes), parcial (déficit), exceso, día libre (0 previstas, fichaje = fuera de horario, no ausencia). (Debe fallar.)
- [X] T031 [P] [US4] En `tests/Feature/InformeCumplimientoTest.php`, tests de: informe por rango (reutilizando `RangoFechas`), cada día contra su horario vigente al cruzar un cambio, aislamiento multi-tenant, y estados vacíos (miembro sin horario / tenant sin datos). (Debe fallar.)

### Implementación

- [X] T032 [US4] Crear `app/Support/Cumplimiento/ResultadoDia.php` (value object: fecha, previstas, trabajadas, incidencia, veredicto, minutos_retraso, diferencia_horas).
- [X] T033 [US4] Crear `app/Support/Cumplimiento/ServicioCumplimiento.php`: `horasTrabajadas` (ledger `fichajes` con correcciones + pausas), `resolverHorario` (T022), `evaluarDia` (clasificación R6 con umbrales de `ConfigFichajes`), `evaluarRango` (usa `App\Support\RangoFechas`). Cubierto por T029–T031.
- [X] T034 [US4] Extender `app/Http/Controllers/InformeJornadaController.php` (`index` y `exportar`) para añadir las columnas de cumplimiento por día usando `ServicioCumplimiento`, con el rango del request.
- [X] T035 [US4] Extender la(s) vista(s) de `resources/views/jornada/` con las columnas/marcas de cumplimiento (previsto vs. real, veredicto, estados vacíos), aplicando skills de diseño. Ejecutar `php artisan test --filter="Cumplimiento|InformeCumplimiento"` hasta verde.

**Checkpoint**: cumplimiento visible y correcto, calculado server-side al vuelo.

---

## Phase 7: User Story 5 — Alertas de incumplimiento (P3)

**Goal**: Comando diario idempotente que crea alertas `AusenciaJornada`/`RetrasoJornada` sobre
`alertas`, gestionables en la bandeja existente, sin duplicar ni romper las de fichaje fuera de rango.

**Independent Test**: día con ausencia + retraso sobre umbral ⇒ alertas creadas y gestionables;
re-ejecutar el comando no duplica.

### Tests (escribir primero)

- [X] T036 [P] [US5] En `tests/Feature/EvaluarCumplimientoJornadaTest.php`, tests de: el comando crea alertas de ausencia y retraso del día anterior, idempotencia (segunda ejecución no duplica, vía `referencia_fecha`), aislamiento multi-tenant, y que no toca las alertas de fichaje fuera de rango existentes. (Debe fallar.)

### Implementación

- [X] T037 [US5] Crear `app/Console/Commands/EvaluarCumplimientoJornada.php` (signature `jornada:evaluar-cumplimiento`): itera tenants (patrón de `logs:purgar`/`fichajes:purgar-geo`), evalúa el día anterior con `ServicioCumplimiento`, crea alertas idempotentes con `referencia_fecha`.
- [X] T038 [US5] Registrar el comando en `bootstrap/app.php` `withSchedule(...)->daily()` junto a las purgas de 024.
- [X] T039 [US5] Verificar/ajustar `app/Http/Controllers/AlertaController.php` y la vista de alertas para mostrar los nuevos tipos (label, sin `distancia_metros` cuando no aplica) sin romper las existentes. Ejecutar `php artisan test --filter=EvaluarCumplimientoJornada` hasta verde.

**Checkpoint**: alertas proactivas de incumplimiento en la bandeja existente.

---

## Phase 8: Polish & Cross-Cutting

- [X] T040 [P] Añadir inputs de `tolerancia_retraso_min`/`tolerancia_exceso_min` a la tab de configuración de fichajes (`resources/views/configuracion/`) + su manejo en `ConfiguracionController@updateFichajes` (3 pasos de configuración, `docs/03-modelo-datos.md`).
- [X] T041 [P] Recorrer los escenarios de [quickstart.md](./quickstart.md) (E1–E6); verificación visual manual de `/horarios`, Mi jornada e informe con confirmación del usuario para la herramienta de navegador (preferentemente Oculo, CLAUDE.md). Los 6 escenarios quedan cubiertos por la suite automatizada (`php artisan test --filter="Horario|Asignacion|Cumplimiento|MiJornada"`, 35 tests en verde); no se ejecutó manualmente en navegador (requiere confirmación explícita del usuario para usar herramientas de navegador, según CLAUDE.md).
- [X] T042 Actualizar `docs/03-modelo-datos.md` (nuevas tablas `horarios`/`horario_tramos`/`asignaciones_horario` + columna `referencia_fecha` en `alertas` + claves de config) y `docs/00-vision.md`/`docs/07-control-horario-espana.md` si el alcance documentado cambió. Evaluar si toca `/speckit-constitution` (no se prevé: sin nueva categoría de dato personal). Ejecutar la suite completa `php artisan test`. Actualizados `docs/03-modelo-datos.md` (tablas nuevas + `alertas` extendida) y `docs/00-vision.md` (párrafo de horario planificado); `docs/07-control-horario-espana.md` no cambia (es normativa del registro de jornada real, feature 024, sin impacto de la capa planificada). Suite completa: 576 tests en verde.

---

## Dependencies & Execution Order

- **Setup (T001–T002)** → informan a Foundational; no bloquean el código base.
- **Foundational (T003–T012)** → **bloquea** todas las stories (migraciones/modelos/enums).
- **US1 (T013–T019)** → MVP. Depende de Foundational.
- **US2 (T020–T025)** → depende de Foundational; el resolutor de horario vigente (T022) lo consumen US3 y US4.
- **US3 (T026–T028)** → depende de US1 (tramos) + US2 (resolutor).
- **US4 (T029–T035)** → depende de US1/US2 (horario+tramos+resolutor) y de los `fichajes` de 024.
- **US5 (T036–T039)** → depende de US4 (`ServicioCumplimiento`).
- **Polish (T040–T042)** → al final.

## Parallel Opportunities

- Foundational: T003–T011 marcados `[P]` (migraciones/modelos/enums/factories en archivos distintos); T012 tras ellos.
- Tests `[P]` de cada story se escriben en paralelo (archivos de test distintos por story).
- Polish: T040 y T041 en paralelo.

## MVP Scope

**US1 (T003–T019)** es el MVP entregable: define el catálogo de horarios reutilizables con turnos
partidos y validación. US2 (asignación con vigencia) es el siguiente incremento imprescindible para
que el horario aplique a personas; US3/US4/US5 construyen la visualización, el informe y las alertas
sobre esa base.
