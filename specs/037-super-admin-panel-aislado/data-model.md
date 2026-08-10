# Phase 1 — Data Model: Panel de Super Admin aislado + home

**No hay migraciones, tablas ni columnas nuevas.** Este documento describe únicamente qué entidades
existentes se leen, con qué filtro y con qué forma de agregación. Cualquier tarea que proponga una
migración está fuera del alcance de esta feature.

## Entidades existentes leídas

### `tenants` (central, sin `TenantScope`)

| Campo | Uso en la feature |
|---|---|
| `id` | Clave de cruce con los agregados por tenant |
| `nombre_comercial` | Etiqueta en widgets y ranking |
| `activo` | Métricas activos/inactivos; criterio de atención (a) |
| `created_at` | Altas del mes, evolución 12 meses, "últimos creados" |

Relación: `domains` (1:1 efectiva vía `Tenant::dominio()`), para mostrar el dominio en los widgets.

### `users`

- Recuento total: `where('tenant_id', '!=', null)` — el Super Admin (tenant nulo) **no** se cuenta.
- Recuento por tenant: agrupado por `tenant_id`.
- Criterio de atención (b): tenant **sin** ningún usuario con `estado = aprobado` **y** `activo = true`.

### `facturas` (tabla de tenant; en contexto central el scope no está activo)

- Solo recuento agregado por `tenant_id` de documentos con `estado != borrador`.
- **Nunca** se lee detalle de una factura ni importes de un tenant concreto (FR-019).
- Filtro `tenant_id` explícito y `groupBy('tenant_id')`, igual que ya hace
  `TenantController@destroy` al comprobar facturas emitidas.

### `logs_actividad` (tabla de tenant)

- Solo se usa el máximo de `ocurrido_at` por `tenant_id` para el criterio de atención (c)
  ("sin actividad en 30 días").
- **No se escribe nada** en esta tabla en esta feature (research D4).

## Agregados que produce `App\Services\EstadisticasTenants`

Contrato de forma en [contracts/panel-home.md](./contracts/panel-home.md). Resumen:

| Clave | Tipo | Origen |
|---|---|---|
| `totales.tenants` | int | `tenants` |
| `totales.activos` / `totales.inactivos` | int | `tenants.activo` |
| `totales.altas_mes` | int | `tenants.created_at` dentro del mes natural en curso |
| `totales.usuarios` | int | `users` con `tenant_id` no nulo |
| `serie_altas` | lista de 12 `{ etiqueta, valor }` | `tenants.created_at` agrupado por mes; meses vacíos a 0 |
| `ultimos_tenants` | lista ≤5 `{ id, nombre, dominio, activo, alta, gestion_url }` | `tenants` + `domains` |
| `ranking_tamano` | lista ≤5 `{ id, nombre, usuarios, documentos }` | agregados por `tenant_id` |
| `atencion` | lista `{ id, nombre, motivos[], gestion_url }` | criterios (a), (b), (c) |

### Reglas de cálculo

- **Un tenant puede aparecer con varios motivos** en `atencion`; los motivos son un array, no un
  campo único.
- Los meses sin altas se rellenan **en PHP** sobre un eje de 12 meses generado a partir de "hoy", no
  con funciones de calendario de SQL (portabilidad MySQL/MariaDB).
- Todos los recuentos por tenant se obtienen con **una consulta agrupada por métrica** y se cruzan en
  memoria contra la colección de tenants. Prohibido consultar dentro de un bucle de tenants (N+1).
- El servicio **no cachea**: recalcula en cada carga (FR-014).

## Estados y transiciones

Ninguna. La feature no cambia el estado de ninguna entidad; es de solo lectura salvo por el registro
en el diario técnico de la aplicación que produce el middleware de bloqueo.
