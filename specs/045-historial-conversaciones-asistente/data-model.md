# Data Model: Historial de conversaciones del asistente IA

**Feature**: 045-historial-conversaciones-asistente | **Fecha**: 2026-09-07

Dos tablas nuevas. Convenciones de `docs/03-modelo-datos.md`: `id` BIGINT autoincrement,
`timestamps` en toda tabla, `tenant_id` indexado y cubierto por el scope de tenant.

---

## `asistente_conversaciones`

Un hilo de diálogo entre una persona y el asistente.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `tenant_id` | bigint unsigned, FK → `tenants.id`, indexado | Principio I. `onDelete('cascade')` |
| `user_id` | bigint unsigned, FK → `users.id`, indexado | dueño del hilo. `onDelete('cascade')` |
| `titulo` | varchar(60) | primer mensaje del usuario recortado (research D7) |
| `resumen` | text, nullable | resumen acumulado de la parte compactada (research D4). `null` = nunca compactada |
| `resumido_hasta_mensaje_id` | bigint unsigned, nullable | último mensaje incluido en `resumen`; los posteriores van literales |
| `ultima_actividad_en` | timestamp, indexado | base del orden de la lista (FR-005) y de la purga (research D8) |
| `created_at` / `updated_at` | timestamps | |

**Índices**:
- `(tenant_id, user_id, ultima_actividad_en)` — sirve al listado, que es la consulta caliente.
- `(tenant_id, ultima_actividad_en)` — sirve al barrido de la purga.

**Sin `softDeletes`**: el borrado es definitivo por diseño. Un borrado lógico dejaría datos
personales vivos en la tabla después de que la persona pidió eliminarlos, que es justo lo contrario
de lo que exigen FR-019 y FR-020. Mismo criterio que aplica `PurgarLeads`, que usa `forceDelete()`.

**Reglas de validación**:
- `titulo` no vacío; se genera en servidor, nunca llega del cliente.
- `ultima_actividad_en` se actualiza en cada mensaje añadido al hilo.
- Una conversación sin ningún mensaje de rol `user` no se lista (FR-008).

---

## `asistente_mensajes`

Cada turno dentro de una conversación, en el formato que consume la API de Chat Completions
(research D3).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `tenant_id` | bigint unsigned, FK → `tenants.id`, indexado | Principio I |
| `conversacion_id` | bigint unsigned, FK → `asistente_conversaciones.id`, indexado | `onDelete('cascade')` |
| `rol` | varchar(16) | `user`, `assistant` o `tool` |
| `contenido` | longtext, nullable | `null` es válido en un `assistant` que solo trae `tool_calls` |
| `metadatos` | json, nullable | `tool_calls` (en `assistant`) o `tool_call_id` (en `tool`) |
| `created_at` / `updated_at` | timestamps | el orden del hilo es `id` ascendente |

**Índice**: `(conversacion_id, id)` — reconstruir el hilo en orden es la consulta caliente.

**Borrado en cascada**: al borrar una conversación (manual o por purga) desaparecen sus mensajes.
La cascada se declara en la migración **y** se ejecuta explícitamente en el comando de purga, porque
un borrado por lotes con `whereIn` no siempre dispara la cascada de la base de datos según motor.

**Reglas de validación** (heredadas de `ConversacionAsistente::truncar()`, research D3):
- El primer mensaje literal reconstruido debe ser de rol `user`.
- Un mensaje `tool` nunca queda huérfano: siempre le precede el `assistant` con el `tool_calls` que
  lo originó.

---

## Configuración (sin tabla nueva)

Mismo patrón clave/valor en `configuraciones` que el resto del producto.

| Clave | Grupo | Default | Descripción |
|---|---|---|---|
| `asistente.retencion_dias` | `ia` | `90` | días sin actividad antes de purgar una conversación |

Se lee vía `App\Support\RetencionAsistenteTenant`, calcado de `App\Support\RetencionLogsTenant`.

---

## Qué deja de vivir en la sesión

La clave de sesión `asistente.conversacion` cambia de contenido (research D1, D2):

| Antes | Ahora |
|---|---|
| `mensajes[]` — el hilo completo | *(eliminado: vive en `asistente_mensajes`)* |
| `accion_pendiente` | `accion_pendiente`, más el `conversacion_id` en el que se propuso |
| — | `conversacion_id` activa |

---

## Cambios en documentación existente (FR-024, FR-025)

`docs/03-modelo-datos.md`, sección "Asistente IA (feature 030) — **sin tablas nuevas**":

- El título de la sección deja de ser cierto y debe cambiar.
- El párrafo "Conversación del asistente: estado efímero en la sesión de Laravel... Se destruye con
  la sesión (RGPD: efímero por diseño, sin retención adicional)" queda **invertido** por esta
  feature y debe reescribirse describiendo las dos tablas, el plazo de 90 días y `asistente:purgar`.
- Añadir `asistente.retencion_dias` a la tabla de claves de configuración, junto a
  `logs.retencion_dias` y `leads.retencion_dias`.

`docs/01-arquitectura.md`, Decisión 9: el bullet "Conversación efímera en la sesión de Laravel"
debe reflejar la persistencia, la compactación y la retención.

`resources/ia/conocimiento/asistente.md`: el asistente debe saber explicar que hay historial, que las
conversaciones se conservan 90 días y que las largas se resumen solas.
