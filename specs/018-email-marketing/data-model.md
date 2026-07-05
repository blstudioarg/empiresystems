# Data Model: Email Marketing

Todas las tablas de negocio llevan `tenant_id` indexado y sus modelos usan `BelongsToTenant`
(Principio I), igual que `clientes`/`facturas`.

## Tabla `plantillas_email`

Plantillas reutilizables de asunto + cuerpo.

| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | FK → tenants | indexado |
| titulo | varchar(150) | nombre interno de la plantilla |
| asunto | varchar(255) | |
| cuerpo | longtext | HTML del editor |
| activa | boolean | default `true`; inactivas no se ofrecen en campañas (FR-010) |
| softDeletes | | eliminar = soft delete |
| timestamps | | `updated_at` → "modificado" en el listado |

**Reglas**: `titulo`, `asunto`, `cuerpo` requeridos. Solo `activa = true` seleccionable al crear
campaña.

## Tabla `campanas`

Cabecera de una campaña de email.

| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | FK → tenants | indexado |
| user_id | FK → users | autor (nullable) |
| plantilla_email_id | FK → plantillas_email | nullable (plantilla de origen; `nullOnDelete`) |
| asunto | varchar(255) | copiado/editado, no vive ligado a la plantilla |
| cuerpo | longtext | HTML final enviado |
| estado | enum | `borrador`, `en_curso`, `finalizada` |
| total_destinatarios | int unsigned | cache de conteo para el listado/progreso |
| enviados | int unsigned | cache de `enviado` (default 0) |
| fallidos | int unsigned | cache de `fallido` (default 0) |
| enviada_at | datetime | nullable; primera vez que se lanza el envío |
| timestamps | | |

**Estados**:
- `borrador`: creada, aún no se ha lanzado ninguna tanda.
- `en_curso`: hay tandas enviadas pero quedan `pendiente`.
- `finalizada`: no quedan `pendiente` (todos `enviado` o `fallido`).

Los contadores cache (`enviados`/`fallidos`/`total_destinatarios`) se recalculan desde
`campana_destinatarios` tras cada tanda; la fuente de verdad son las filas de destinatarios.

## Tabla `campana_destinatarios`

Una fila por cliente incluido en la campaña; guarda el resultado del envío (fuente de verdad).

| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | FK → tenants | indexado (derivado de la campaña; se afirma el scope igual) |
| campana_id | FK → campanas | `cascadeOnDelete` |
| cliente_id | FK → clientes | `cascadeOnDelete` |
| email | varchar(255) | dirección usada (copiada del cliente al crear; nullable si sin email) |
| estado | enum | `pendiente`, `enviado`, `fallido` |
| error | varchar(500) | nullable; motivo del fallo (p. ej. "sin email", excepción SMTP) |
| enviado_at | datetime | nullable; marca del intento con resultado ok |
| timestamps | | |

**Índices/constraints**: único `(campana_id, cliente_id)` para deduplicar (FR-013). Índice por
`(campana_id, estado)` para el reintento de fallidos.

**Transiciones de estado por destinatario**:
- `pendiente` → `enviado` (envío SMTP ok).
- `pendiente` → `fallido` (excepción SMTP o cliente sin email).
- `fallido` → `enviado` / `fallido` (reintento; FR-012). Un `enviado` nunca se reenvía (evita
  duplicados, FR-012/SC-004).

## Relaciones

- `Campana` hasMany `CampanaDestinatario`; belongsTo `PlantillaEmail` (nullable), `User`, `Tenant`.
- `CampanaDestinatario` belongsTo `Campana`, `Cliente`, `Tenant`.
- `PlantillaEmail` hasMany `Campana`; belongsTo `Tenant`.

## Reglas derivadas de requisitos

- FR-007: no se puede pasar a envío una campaña sin destinatarios, sin asunto o sin cuerpo, o si
  el tenant no tiene SMTP (`EmailTenant::estaConfigurado`).
- FR-013: dedup por `(campana_id, cliente_id)` al crear.
- FR-015: cliente sin email → fila con `email` null y estado directo `fallido` motivo "sin email".
- FR-014 / Principio I: toda query pasa por el scope de tenant; tests de aislamiento con ≥2
  tenants para las 3 tablas.
