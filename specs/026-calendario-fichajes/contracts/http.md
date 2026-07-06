# Contrato HTTP — Calendario de fichajes y horarios (026)

> Todas las rutas dentro del grupo autenticado + `can:gestiona-fichajes` de `routes/web.php`.
> Aislamiento de tenant vía global scope; el miembro del filtro se resuelve manualmente.

## Rutas nuevas

### `GET /calendario` — vista del módulo

- **Nombre**: `calendario.index`
- **Respuesta**: HTML (`resources/views/calendario/index.blade.php`): contenedor del calendario,
  selector de miembro (activos del tenant + opción "Todo el equipo"), leyenda de veredictos y
  modales de detalle de día / corrección / asignación de horario.
- **403** si el usuario no tiene `gestiona-fichajes` (también por URL directa, SC-006).

### `GET /calendario/eventos` — feed JSON por rango visible

- **Nombre**: `calendario.eventos`
- **Query params**:

| Param | Tipo | Reglas |
|-------|------|--------|
| `start` | date (ISO) | requerido (lo envía FullCalendar) |
| `end` | date (ISO) | requerido; `end > start`; rango ≤ 62 días → si no, **422** |
| `miembro_equipo_id` | int | opcional; debe pertenecer al tenant activo → si no, **404**. Ausente = modo equipo |

- **Respuesta 200**: array JSON de eventos FullCalendar (ver [data-model.md](../data-model.md)):
  - Modo **miembro**: `veredicto_dia` (días pasados) + `previsto` (todos los días con horario
    vigente, incluido futuro) + `real` (días con fichajes, correcciones aplicadas).
  - Modo **equipo**: `resumen_equipo` (días pasados con ≥1 incumplimiento/incidencia).
- **Garantías**: solo datos del tenant activo; veredictos idénticos a los del informe de
  cumplimiento para el mismo rango (SC-001); sin veredicto en fechas ≥ hoy (D4); cálculo íntegro
  en backend.

## Rutas reutilizadas (sin cambios)

| Ruta | Uso desde el calendario |
|------|-------------------------|
| `POST /fichajes/{fichaje}/corregir` | Modal de corrección desde el detalle de día (FR-012). Mismo request/validación que la vista de jornada. |
| `POST /miembros-equipo/{miembro}/horarios` | Modal de asignación de horario (FR-013). Mismas validaciones de vigencia/solape. |
| `GET /miembros-equipo/{miembro}/horarios` | Histórico para precargar el modal de asignación. |

Tras cualquier acción con éxito el front hace `calendar.refetchEvents()`; los errores de validación
se muestran con `window.showToast('error', ...)` (patrón toastr global).

## Errores

| Código | Caso |
|--------|------|
| 401/redirect | no autenticado |
| 403 | sin permiso `gestiona-fichajes` |
| 404 | `miembro_equipo_id` inexistente o de otro tenant |
| 422 | rango inválido o > 62 días |
