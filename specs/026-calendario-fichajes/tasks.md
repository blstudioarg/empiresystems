# Tasks: Calendario de fichajes y horarios

**Feature**: 026-calendario-fichajes
**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Data model**: [data-model.md](./data-model.md) · **Contrato**: [contracts/http.md](./contracts/http.md)

**Test-first**: El proyecto exige TDD en lógica crítica (Constitución, Principio IV): aislamiento
multi-tenant y autorización del feed, coherencia veredicto-informe, regla "sin veredicto en fechas
futuras", agregado de equipo e intervalos reales. Esas tareas escriben primero sus tests (que
fallan) y luego la implementación. La capa visual JS sigue flujo flexible.

**Convención de rutas**: monolito Laravel en la raíz del repo. **Sin migraciones ni tablas nuevas**:
todo es proyección de lectura sobre 024/025 (FR-016).

---

## Phase 1: Setup

- [X] T001 Vendorizar FullCalendar 5.11.0 (D1): copiar `template/Laravel-NexaDash-v1.0-28_May_2025/package/public/vendor/fullcalendar-5.11.0/lib/{main.min.js,main.min.css}` y `lib/locales/es.js` a `public/vendor/fullcalendar/`; incluir `LICENSE.txt`. Verificar que el UMD no requiere otras libs (patrón CLAUDE.md de dependencias UMD).
- [X] T002 Releer `app/Support/Cumplimiento/ServicioCumplimiento.php`, `app/Services/InformeJornada.php` (firma de `eventosEfectivos`), `app/Support/ResolutorHorario.php` y `app/Enums/VeredictoCumplimiento.php` para confirmar firmas reales; anotar desvíos respecto a [data-model.md](./data-model.md).

---

## Phase 2: Foundational (bloquea todas las stories)

**Punto de extensión único de 025 + esqueleto de rutas/controller.**

- [X] T003 [P] En `tests/Unit/ServicioCumplimientoTest.php`, tests del nuevo método público `intervalosDia(MiembroEquipo, Carbon): array` (D5): segmentos Entrada→Salida, pausa parte el intervalo en dos, entrada sin salida no genera intervalo, correcciones aplicadas. (Deben fallar.)
- [X] T004 Implementar `intervalosDia()` en `app/Support/Cumplimiento/ServicioCumplimiento.php` reutilizando `InformeJornada::eventosEfectivos()` + el privado `intervalosTrabajo()` (exponer sin duplicar lógica). `php artisan test --filter=ServicioCumplimiento` en verde.
- [X] T005 [P] Añadir a `app/Enums/VeredictoCumplimiento.php` el método `clase(): string` → `cal-veredicto-{value}` (mapa único backend, D6).
- [X] T006 Crear `app/Http/Controllers/CalendarioController.php` con `index()` (vista) y `eventos()` (stub) y registrar rutas `GET /calendario` + `GET /calendario/eventos` dentro del grupo `can:gestiona-fichajes` de `routes/web.php` (nombres `calendario.index`/`calendario.eventos`, [contracts/http.md](./contracts/http.md)).

**Checkpoint**: intervalos expuestos y rutas montadas; las stories construyen encima.

---

## Phase 3: User Story 1 — Calendario mensual de cumplimiento por miembro (P1) 🎯 MVP

**Goal**: Mes con veredicto por color/etiqueta por día pasado, coherente con el informe; días
futuros solo previsión; navegación por rango.

**Independent Test**: mes sembrado con retraso/ausencia/exceso/parcial/libre/incidencia ⇒ cada día
pinta su veredicto idéntico al informe; futuro sin veredicto; rango >62 días ⇒ 422.

### Tests (escribir primero)

- [X] T007 [P] [US1] En `tests/Feature/CalendarioEventosTest.php`, tests del feed en modo miembro: eventos `veredicto_dia` con veredicto/horas/incidencia correctos y **coherentes con `ServicioCumplimiento::evaluarRango`** para el mismo rango (SC-001); día libre = `libre`; fichaje incompleto = incidencia. (Deben fallar.)
- [X] T008 [P] [US1] En `tests/Feature/CalendarioEventosTest.php`, tests de reglas del feed: fechas ≥ hoy sin `veredicto_dia` (D4), `start`/`end` requeridos, rango > 62 días ⇒ 422, `miembro_equipo_id` de otro tenant ⇒ 404 (resolución manual), usuario sin `gestiona-fichajes` ⇒ 403 en `/calendario` y `/calendario/eventos`, y aislamiento multi-tenant del contenido (≥2 tenants). (Deben fallar.)

### Implementación

- [X] T009 [US1] Implementar `CalendarioController@eventos` modo miembro (parte 1): validación de query params ([contracts/http.md](./contracts/http.md)), resolución manual del miembro tenant-scoped, y emisión de `veredicto_dia` por día pasado vía `ServicioCumplimiento::evaluarRango` con `classNames` de `VeredictoCumplimiento::clase()` y `extendedProps` de [data-model.md](./data-model.md) (incluido `fichajes[]` del día para el modal de US4). `php artisan test --filter=CalendarioEventos` parcial en verde (T007–T008).
- [X] T010 [US1] Crear `resources/views/calendario/index.blade.php` (contenedor `#calendar.app-fullcalendar`, selector de miembro activo, leyenda de veredictos, estados vacíos) cargando CSS del plugin en `@stack('styles')` **antes** de `style.css` y JS en `@push('scripts')`. Consultar skills de diseño (`frontend-design`/`ui-ux-pro-max`, CLAUDE.md) para leyenda y paleta de veredictos.
- [X] T011 [US1] Crear `public/js/plugins-init/calendario.init.js`: init FullCalendar (locale `es`, `dayGridMonth` inicial, toolbar mes/semana/día, `events` → `calendario.eventos` con `miembro_equipo_id` del filtro), sin `setTimeout` (D1). Añadir clases `cal-veredicto-*`/`cal-previsto`/`cal-real` a `public/css/app-overrides.css` (D6, light+dark).
- [X] T012 [US1] Añadir entrada "Calendario" al bloque admin del dropdown de fichajes en `resources/views/partials/sidebar.blade.php` (junto a Jornada/Horarios/Alertas).

**Checkpoint**: mes funcional y probado — MVP entregable.

---

## Phase 4: User Story 2 — Semana/día: previsto vs. real (P1)

**Goal**: timeGrid con tramos previstos (incluido futuro) e intervalos reales superpuestos,
distinguibles; incidencia sin inventar hora de fin.

**Independent Test**: turno partido 09–13/15–19 con fichajes 09:20–13:00 y 15:00–19:30 ⇒ 2 bloques
`previsto` + 2 `real` en sus horas; entrada sin salida ⇒ sin intervalo real + marca de incidencia.

### Tests (escribir primero)

- [X] T013 [P] [US2] En `tests/Feature/CalendarioEventosTest.php`, tests de eventos `previsto` (tramos del horario vigente cada día, turno partido = 2 eventos, cambio de horario a mitad de rango ⇒ cada día su horario, futuro incluido) y `real` (intervalos de `intervalosDia`, pausas excluidas, incompleto sin intervalo, fichajes en día libre visibles). (Deben fallar.)

### Implementación

- [X] T014 [US2] Extender `CalendarioController@eventos` modo miembro (parte 2): emitir `previsto` (vía `ResolutorHorario` + tramos por `dayOfWeekIso`, anclados a cada fecha) y `real` (vía `intervalosDia`, T004). `php artisan test --filter=CalendarioEventos` en verde.
- [X] T015 [US2] Ajustar `calendario.init.js` + `app-overrides.css` para las vistas timeGrid: `previsto` como bloque de fondo/borde y `real` sólido superpuesto (distinguibles, accesibles), badge de incidencia en el día. Verificación con skill `emil-design-eng` para el pulido de superposición.

**Checkpoint**: previsto vs. real visible por horas.

---

## Phase 5: User Story 3 — Filtro por miembro y vista de equipo (P2)

**Goal**: cambiar de miembro recarga el feed; sin miembro = resumen agregado por día con drill-down
a miembros afectados.

**Independent Test**: 2 miembros con incumplimientos en días distintos ⇒ vista equipo muestra
recuentos por día; clic en un día lista los afectados y salta al calendario individual (≤2
interacciones).

### Tests (escribir primero)

- [X] T016 [P] [US3] En `tests/Feature/CalendarioEventosTest.php`, tests del modo equipo (sin `miembro_equipo_id`): `resumen_equipo` por día pasado con recuentos `{ausencias, retrasos, incidencias}` y `miembros[]` correctos, solo miembros activos evaluados, días sin incumplimientos sin evento, fechas ≥ hoy sin resumen, aislamiento multi-tenant. (Deben fallar.)

### Implementación

- [X] T017 [US3] Implementar el modo equipo en `CalendarioController@eventos` (D3: iterar miembros activos × días con `evaluarDia`, agregar recuentos + lista de afectados). `php artisan test --filter=CalendarioEventos` en verde.
- [X] T018 [US3] Extender la vista e init JS: opción "Todo el equipo" en el selector (default con >1 miembro), render de recuentos en el día, popover/modal con miembros afectados y enlace que re-filtra el calendario al miembro elegido (FR-010, SC-003). Solo miembros activos en el selector.

**Checkpoint**: foto global → detalle individual sin cambiar de pantalla.

---

## Phase 6: User Story 4 — Acciones desde el calendario (P3)

**Goal**: detalle de día con fichajes, corrección y asignación de horario reutilizando los flujos
existentes; recarga vía `refetchEvents()`.

**Independent Test**: corregir un fichaje desde el detalle del día ⇒ mismo resultado que el flujo
de `/jornada` (evento enlazado, original intacto) y el veredicto del día cambia al recargar; solape
de asignación ⇒ toast de error del backend.

### Tests (escribir primero)

- [X] T019 [P] [US4] En `tests/Feature/CalendarioEventosTest.php`, test de que `extendedProps.fichajes[]` del `veredicto_dia` trae el detalle correcto (`id`, `tipo`, `hora`, `resultado_ubicacion`, `es_correccion`) con correcciones señaladas, y de que tras una corrección vía el endpoint existente el feed re-consultado refleja el nuevo veredicto (cálculo al vuelo). (Debe fallar.)

### Implementación

- [X] T020 [US4] Modal de detalle de día en `resources/views/calendario/index.blade.php` + `calendario.init.js`: clic en día/evento ⇒ fichajes del día y marcas de cumplimiento desde `extendedProps` (FR-011); botón "Corregir" por fichaje que abre el modal de corrección (mismos campos que la vista de jornada) y POSTea a `fichajes.corregir`; éxito ⇒ `showToast('success')` + `refetchEvents()`, error ⇒ toast con mensaje del backend (D7).
- [X] T021 [US4] Modal de asignación de horario (miembro seleccionado): precargar histórico vía `GET /miembros-equipo/{miembro}/horarios`, POST a `asignaciones-horario.store`, mismos errores/validaciones; éxito ⇒ toast + `refetchEvents()` (FR-013). `php artisan test --filter=CalendarioEventos` en verde.

**Checkpoint**: acciones operativas sin flujos paralelos; ledger intacto.

---

## Phase 7: Polish & Cross-Cutting

- [X] T022 [P] Recorrer los escenarios E1–E6 de [quickstart.md](./quickstart.md); verificación visual manual de `/calendario` (mes/semana/día, equipo, modales, light/dark) **con confirmación del usuario** para la herramienta de navegador (preferentemente Oculo, CLAUDE.md).
- [X] T023 Actualizar `docs/00-vision.md` (módulo calendario en el bloque de control horario) y `docs/04-front-guidelines.md` si surge algún patrón de UI reutilizable (FullCalendar vendorizado, leyenda de veredictos); `docs/03-modelo-datos.md` y `docs/07-control-horario-espana.md` no cambian (sin tablas ni impacto normativo). Evaluar `/speckit-constitution` (no se prevé: sin datos nuevos). Ejecutar la suite completa `php artisan test`.

---

## Dependencies & Execution Order

- **Setup (T001–T002)** → T001 lo consume la vista (T010); T002 informa a Foundational.
- **Foundational (T003–T006)** → bloquea todas las stories (T004 lo consume US2; T006 todas).
- **US1 (T007–T012)** → MVP. Depende de Foundational.
- **US2 (T013–T015)** → depende de Foundational (T004) y comparte controller con US1 (T009).
- **US3 (T016–T018)** → depende de US1 (vista/init) y de Foundational.
- **US4 (T019–T021)** → depende de US1 (`extendedProps.fichajes` de T009, modal base).
- **Polish (T022–T023)** → al final.

## Parallel Opportunities

- Setup: T001 y T002 en paralelo.
- Foundational: T003 y T005 en paralelo; T004 tras T003; T006 tras T002.
- Tests `[P]` de cada story (T007/T008, T013, T016, T019) se escriben en paralelo entre sí.
- T010–T012 (vista/JS/sidebar) paralelizables entre sí tras T009.

## MVP Scope

**US1 (T001–T012)**: mes con veredictos por miembro, ya coherente con el informe y con toda la
seguridad (403/404/422/aislamiento) probada. US2 completa la proyección horaria, US3 la vista de
equipo y US4 las acciones.



