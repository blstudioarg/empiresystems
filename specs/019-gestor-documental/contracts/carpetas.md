# Contrato — Carpetas

Rutas dentro del grupo `['tenant.context', 'auth']` (mismo grupo que el resto del negocio). Todas
operan sobre el tenant activo; ningún endpoint acepta `tenant_id` como parámetro.

## POST `/archivos/carpetas` — crear carpeta

**Nombre de ruta**: `carpetas.store`

**Body**:
| Campo | Regla |
|-------|-------|
| nombre | required, string, max:255 |
| parent_id | nullable, integer, existe en `carpetas` del tenant activo |

**Validación**: `nombre` único en `(tenant_id, parent_id)` sobre no borrados (FR-005). `parent_id`
debe ser del tenant (si no → falla validación / 404).

**Respuestas**:
- `201`/redirect con flash `success` → carpeta creada (JSON con el registro si es AJAX).
- `422` → nombre duplicado en el nivel o datos inválidos (mensaje claro).

## PUT/PATCH `/archivos/carpetas/{id}` — renombrar y/o mover carpeta

**Nombre de ruta**: `carpetas.update`

**Body**:
| Campo | Regla |
|-------|-------|
| nombre | sometimes, required, string, max:255 |
| parent_id | sometimes, nullable, integer, existe en `carpetas` del tenant activo; no puede ser la propia carpeta ni uno de sus descendientes (evita ciclos) |

**Validación**: la unicidad de `nombre` se evalúa contra el **nivel destino efectivo** (el
`parent_id` enviado, o el actual si no se envía) y el **nombre efectivo** (el enviado, o el actual
si no se envía) — así un movimiento por drag&drop que solo manda `parent_id` también respeta la
unicidad por nivel. `parent_id` debe ser del tenant (si no → falla validación / 404).

**Resolución**: el `{id}` se resuelve **manualmente** acotado al tenant activo (no binding
implícito). Carpeta de otro tenant → `404`.

**Respuestas**: `200`/redirect `success`; `422` duplicado o ciclo detectado; `404` fuera de tenant.

## DELETE `/archivos/carpetas/{id}` — borrar carpeta (cascada)

**Nombre de ruta**: `carpetas.destroy`

**Comportamiento**: borra en cascada la carpeta, sus subcarpetas y todos los archivos contenidos
(soft delete de registros + borrado de ficheros físicos), en una transacción. El front pide
**confirmación reforzada** informando del número de elementos afectados antes de llamar (FR-018).

**Resolución**: `{id}` resuelto acotado al tenant activo. Otro tenant → `404`.

**Respuestas**: `200`/redirect `success` con conteo de elementos borrados; `404` fuera de tenant.

## Aislamiento (obligatorio en tests)

- Tenant B no puede crear una carpeta con `parent_id` de A, ni renombrar/borrar carpetas de A →
  `404`/`422`, nunca efecto sobre datos de A.
