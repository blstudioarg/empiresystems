# Research — Calendario de fichajes y horarios (026)

## D1 — Qué build de FullCalendar vendorizar

- **Decision**: `template/.../public/vendor/fullcalendar-5.11.0/lib` → `public/vendor/fullcalendar/`
  (`main.min.js`, `main.min.css`, `locales/es.js`). Build **global UMD** (sin bundler), igual que el
  resto de vendors del proyecto.
- **Rationale**: el banco trae 4 copias (`fullcalendar`, `fullcalendar-5.11.0`, `day-fullcalendar`,
  `fullcalendar_old`); la 5.11.0 es la más nueva, su API global (`FullCalendar.Calendar`) es la que
  usa el init de referencia del template, y `style.css` ya trae theming para `.app-fullcalendar`.
  Locale `es` disponible (`locales/es.js`).
- **Alternatives considered**: npm/bundler (no hay pipeline de assets en el proyecto, todo es
  vendorizado estático — descartado); FullCalendar 6 por CDN (CSP/patrón del proyecto prohíbe CDN y
  sería dependencia nueva fuera del banco — descartado).
- **Gotcha conocido** (CLAUDE.md): el CSS del plugin va en `@stack('styles')` **antes** de
  `css/style.css` para que el theming del template gane la cascada. El init de la demo usa
  `setTimeout(...,1000)` — se descarta; init directo en `DOMContentLoaded`/`window load`.

## D2 — Diseño del feed de eventos

- **Decision**: un único endpoint `GET /calendario/eventos` (JSON) con `start`/`end` (los envía
  FullCalendar por rango visible) y `miembro_equipo_id` opcional (ausente = vista equipo). El
  backend valida el rango (máx. 62 días) y devuelve tres clases de items según el modo:
  1. **Veredicto de día** (miembro, para dayGridMonth): evento `allDay` de fondo con
     `extendedProps.veredicto`, horas previstas/trabajadas, minutos de retraso e incidencia.
  2. **Tramo previsto** + **intervalo real** (miembro, para timeGrid): eventos con hora
     inicio/fin, distinguidos por `extendedProps.tipo` (`previsto`/`real`) y el detalle de
     fichajes del día en `extendedProps` (para el modal de detalle, sin endpoint extra).
  3. **Resumen de equipo** (sin miembro, dayGridMonth): un evento `allDay` por día con recuentos
     `{ausencias, retrasos, incidencias}` y la lista de miembros afectados (id+nombre) para el
     drill-down de US3.
- **Rationale**: FullCalendar consume `events` como función/URL con `start`/`end` de forma nativa;
  un solo endpoint mantiene la superficie mínima y el modal de detalle no necesita otra llamada
  (los datos del día ya viajan en `extendedProps`). El tope de 62 días protege el cálculo al vuelo.
- **Alternatives considered**: endpoints separados por tipo de evento (más superficie sin
  beneficio); endpoint de detalle de día aparte (llamada extra evitable a esta escala).

## D3 — Cálculo de la vista de equipo

- **Decision**: iterar miembros activos del tenant × días del rango con
  `ServicioCumplimiento::evaluarDia()` al vuelo, agregando recuentos por día. Sin precálculo,
  sin caché, sin tabla.
- **Rationale**: escala pyme (≤50 miembros × ≤31 días ≈ 1.550 evaluaciones; cada una son 2-3
  queries ligeras indexadas). Coherente con FR-019a de 025 (cumplimiento siempre al vuelo, refleja
  cambios retroactivos). Si algún día duele, el punto de optimización es eager-load de asignaciones
  y fichajes del rango en bloque — no una tabla de resultados.
- **Alternatives considered**: leer `alertas` ya generadas por el comando diario (no cubre
  incidencias ni días re-evaluados retroactivamente, y acopla el calendario a que el cron haya
  corrido — descartado); tabla de resultados persistida (contradice FR-019a — descartado).

## D4 — Veredicto solo en días pasados

- **Decision**: el feed solo emite veredicto para fechas `< hoy` (zona del servidor). Hoy y futuro:
  solo tramos previstos; hoy además muestra los fichajes ya existentes sin veredicto cerrado.
- **Rationale**: assumption de la spec; evaluar un día no terminado daría falsas ausencias/parciales.
  El comando diario de 025 ya evalúa "ayer" con la misma filosofía.

## D5 — Exposición de intervalos reales del día

- **Decision**: añadir a `ServicioCumplimiento` un método **público** que devuelva los intervalos
  trabajados de un día (hoy `intervalosTrabajo()` es privado), reutilizando
  `InformeJornada::eventosEfectivos()` (correcciones aplicadas) y la misma semántica de
  emparejado/pausas/incidencia ya testeada.
- **Rationale**: la vista timeGrid necesita cada sub-intervalo (no solo el total); la lógica ya
  existe privada — exponerla evita duplicarla en el controller.
- **Alternatives considered**: reimplementar el emparejado en `CalendarioController` (duplicación
  de lógica crítica — descartado).

## D6 — Colores/estilo de veredictos

- **Decision**: mapear veredictos a clases propias (`cal-veredicto-{ausencia|retraso|parcial|
  cumplido|exceso|libre|incidencia}`) definidas en `public/css/app-overrides.css`, apoyadas en la
  paleta semántica del template (danger/warning/info/success/secondary). Definición del mapa en un
  solo lugar del backend (p. ej. `VeredictoCumplimiento->clase()` o el propio feed) para que la
  leyenda y los eventos no diverjan.
- **Rationale**: consistencia con el resto del producto y con la bandeja de alertas; la vista
  mensual usa color de fondo del día + etiqueta corta accesible (no solo color).

## D7 — Acciones desde el calendario (US4)

- **Decision**: modales en `calendario/index.blade.php` que POSTean a los endpoints existentes:
  corrección → `POST /fichajes/{fichaje}/corregir` (mismo formato que la vista de jornada);
  asignación → `POST /miembros-equipo/{miembro}/horarios`. Tras éxito: `showToast('success', ...)` +
  `calendar.refetchEvents()`. Errores de validación → toast de error con el mensaje del backend.
- **Rationale**: FR-012/FR-013 exigen reutilizar los flujos existentes; `refetchEvents()` recarga
  el rango visible y refleja el recálculo al vuelo (D3/FR-019a).
- **Alternatives considered**: endpoints propios del calendario (flujo paralelo prohibido por la
  spec — descartado).

## D8 — Autorización y navegación

- **Decision**: rutas bajo el grupo `can:gestiona-fichajes` existente en `routes/web.php`; entrada
  "Calendario" en el bloque admin del dropdown de fichajes del sidebar (junto a Jornada/Horarios/
  Alertas, `resources/views/partials/sidebar.blade.php:57-63`).
- **Rationale**: mismo permiso que el informe de jornada — el calendario es otra proyección del
  mismo dato; el acceso directo por URL queda cubierto por el middleware (SC-006).
