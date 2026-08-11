# Data Model: Plano de sala arrastrable (POS)

## `pos_zonas` (ampliación)

Tabla existente desde feature 038. Se añade:

| Columna | Tipo | Notas |
|---|---|---|
| `version` | `unsigned int`, default `1` | Bloqueo optimista del plano de la zona (D5). Se incrementa en cada guardado exitoso de `PUT /pos/sala/zonas/{zona}/plano`. |

Sin cambios en las columnas existentes (`nombre`, `suplemento_porcentaje`, `orden`, `tenant_id`,
`softDeletes`).

## `pos_mesas` (ampliación)

Tabla existente desde feature 038. Se añade:

| Columna | Tipo | Notas |
|---|---|---|
| `fila` | `unsigned tinyint`, nullable | 0-indexada, rango válido 0-5 (rejilla 6 filas, D2). `null` solo transitoriamente durante backfill (D7); tras la migración, toda mesa activa tiene una posición. |
| `columna` | `unsigned tinyint`, nullable | 0-indexada, rango válido 0-7 (rejilla 8 columnas, D2). |
| `forma` | `enum('redonda','cuadrada','rectangular','barra')`, default `'cuadrada'` | Propiedad física del mobiliario (FR-003). |
| `tamano` | `enum('pequena','mediana','grande')`, default `'mediana'` | Escala discreta de 3 valores (Clarifications 2026-08-11). |

**Invariante de unicidad**: `UNIQUE (tenant_id, zona_id, fila, columna)` — garantiza ausencia de
solapamiento por construcción (D2), aplicable solo a filas no borradas lógicamente (la unicidad
convive con `softDeletes`: una mesa eliminada no debe seguir "ocupando" su celda para el
`UNIQUE`; usar el mismo patrón ya existente de índice único condicionado por `deleted_at` que sigue
`nombre` en esta misma tabla — ver migración original, `unique(['tenant_id', 'zona_id', 'nombre'])`
combinado con `whereNull('deleted_at')` a nivel de validación de aplicación, replicado aquí para
`fila`/`columna`).

Sin cambios en las columnas existentes (`nombre`, `orden`, `zona_id`, `tenant_id`, `softDeletes`).
El campo `orden` se conserva como estaba (fallback/orden de backfill, D7), sin nuevo significado.

## Relaciones

Sin cambios respecto a feature 038: `pos_zonas 1—N pos_mesas` (FK `zona_id`), ambas con
`tenant_id` bajo `TenantScope` (`BelongsToTenant`). El "plano" no es una entidad nueva: es un
conjunto de atributos sobre `pos_mesas` agrupados por `zona_id`, más el contador `version` que vive
en la zona porque el guardado es una operación a nivel de zona completa, no de mesa individual.

## Validación de payload de guardado (server-side, D4)

Al recibir `PUT /pos/sala/zonas/{zona}/plano` con una lista de `{mesa_id, fila, columna, forma,
tamano}` y un `version` esperado:

1. Resolver la zona bajo el tenant activo (`PosZona::query()->findOrFail($zona)` — nunca binding
   implícito, ver pitfall de memoria del proyecto sobre `TenantScope`).
2. Comparar `version` recibido contra `zona->version`; si difiere, 409 (D5).
3. Verificar que el conjunto de `mesa_id` recibido sea exactamente el de mesas activas de esa zona
   (ni de más — otro tenant/zona — ni de menos sin justificación); mesas ausentes del payload no
   se tocan (permite guardados parciales solo si el cliente los omite deliberadamente, aunque el
   flujo normal siempre envía el plano completo de la zona, D4).
4. Verificar que ninguna `fila`/`columna` recibida se repita dentro del payload ni esté fuera de
   rango (0-5 / 0-7); si algo no cumple, rechazar toda la petición (422) sin persistir nada
   parcialmente — es una operación atómica.
5. Persistir en una transacción: actualizar cada mesa, incrementar `zona->version`.

## Estados / transiciones

No hay máquina de estados nueva. `forma`/`tamano`/`fila`/`columna` son atributos mutables en
cualquier momento (a diferencia de los campos congelados de `pos_cuenta_lineas` en feature 038):
no hay razón de negocio para "congelar" la forma de una mesa, es una propiedad física vigente que
cambia si el local reordena su mobiliario.
