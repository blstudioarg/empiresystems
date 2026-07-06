# Data Model — Calendario de fichajes y horarios (026)

> **Sin tablas nuevas ni migraciones.** La feature es una proyección de lectura sobre el modelo de
> 024/025. Este documento define las estructuras **derivadas** (payload del feed) y los puntos de
> extensión de código.

## Tablas reutilizadas (solo lectura)

| Tabla | Feature | Uso en 026 |
|-------|---------|------------|
| `fichajes` | 024 | Intervalos reales y detalle del día, vía `InformeJornada::eventosEfectivos()` (correcciones aplicadas). Nunca se escribe desde aquí. |
| `horarios` / `horario_tramos` | 025 | Bloques previstos por día de la semana (`dia_semana` ISO 1–7, `hora_inicio`/`hora_fin`). |
| `asignaciones_horario` | 025 | Horario vigente por fecha (`ResolutorHorario`), respetando histórico. |
| `miembros_equipo` | 024 | Filtro (solo `activo = true` se ofrecen); sujeto de la evaluación. |

## Extensiones de código (sin cambios de esquema)

### `App\Support\Cumplimiento\ServicioCumplimiento` (extender)

Nuevo método público (D5) — misma semántica que el privado `intervalosTrabajo()` ya testeado:

```
intervalosDia(MiembroEquipo $miembro, Carbon $dia): array
  → lista de [inicio, fin] (timestamps o Carbon) de los segmentos realmente trabajados
    (Entrada/FinPausa → InicioPausa/Salida), con correcciones aplicadas; una entrada sin
    salida no genera intervalo (incidencia).
```

### `App\Enums\VeredictoCumplimiento` (extender, opcional)

Método `clase(): string` → clase CSS `cal-veredicto-*` (mapa único backend, D6). Alternativa menor:
mapa en el feed del controller.

## Estructuras derivadas (payload del feed, no persistidas)

### Evento de calendario (`GET /calendario/eventos`)

Formato compatible FullCalendar. Tres variantes por `extendedProps.tipo`:

**`veredicto_dia`** (modo miembro, solo fechas < hoy):

| Campo | Contenido |
|-------|-----------|
| `start` | fecha del día (`Y-m-d`), `allDay: true`, `display: background` + evento de etiqueta |
| `classNames` | `cal-veredicto-{veredicto}` |
| `extendedProps` | `veredicto`, `horas_previstas`, `horas_trabajadas`, `minutos_retraso`, `diferencia_horas`, `incidencia` (bool), `fichajes[]` (detalle para el modal: `id`, `tipo`, `hora`, `resultado_ubicacion`, `es_correccion`) |

**`previsto` / `real`** (modo miembro, vistas timeGrid; previsto también en días futuros):

| Campo | Contenido |
|-------|-----------|
| `start`/`end` | datetime del tramo previsto o del intervalo real |
| `classNames` | `cal-previsto` / `cal-real` |
| `extendedProps` | `tipo`; en `real`: referencia a los fichajes que delimitan el intervalo |

**`resumen_equipo`** (modo equipo, solo fechas < hoy):

| Campo | Contenido |
|-------|-----------|
| `start` | fecha del día, `allDay: true` |
| `extendedProps` | `ausencias`, `retrasos`, `incidencias` (ints) y `miembros[]` (`id`, `nombre`, `veredicto`) para el drill-down (FR-010) |

### Reglas del feed

- `start`/`end` obligatorios; rango ≤ 62 días → si no, 422.
- `miembro_equipo_id` opcional; si viene, se resuelve **manualmente** dentro del tenant activo
  (404 si no es del tenant — memoria `project_tenant_route_binding`).
- Fechas ≥ hoy: nunca llevan `veredicto_dia` ni `resumen_equipo` (D4); hoy sí lleva `real`.
- Todo el cálculo en backend (FR-007); el JS solo pinta.

## Invariantes que la feature NO toca

- Ledger `fichajes` append-only (correcciones = evento enlazado, flujo 024).
- Plantillas `horarios` compartidas: no se editan desde el calendario.
- Cumplimiento siempre al vuelo (FR-019a de 025): sin tabla de resultados ni caché.
