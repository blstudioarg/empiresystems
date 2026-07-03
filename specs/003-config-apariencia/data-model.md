# Data Model — Configuración del tenant (Apariencia / Marca)

Fase 1. Entidades, campos, validación y relaciones. Coherente con `docs/03-modelo-datos.md`.

## Entidad nueva: `configuraciones`

Almacén clave-valor por tenant para ajustes flexibles. En esta feature guarda los colores de marca;
queda disponible para futuras tabs de configuración.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, index | **NOT NULL en esta feature** (valores por tenant). El doc contempla `null` para globales del SaaS; no se usa aquí. |
| clave | varchar(100) | ej. `apariencia.color_primario` |
| valor | text, nullable | valor serializado (aquí, HEX `#RRGGBB`) |
| tipo | varchar(20) | `string` \| `integer` \| `boolean` \| `decimal` \| `json` (cómo castear `valor`). Aquí `string`. |
| grupo | varchar(50) | agrupación para la UI. Aquí `apariencia`. |
| descripcion | varchar, nullable | etiqueta legible |
| timestamps | | created_at / updated_at |

**Índices**: único `(tenant_id, clave)`; index `(tenant_id, grupo)` para leer un grupo de una vez.

**Modelo**: `App\Models\Configuracion` con trait `BelongsToTenant` (mismo patrón que `Cliente`), para
que el global scope filtre por tenant activo automáticamente. `fillable`: `tenant_id, clave, valor,
tipo, grupo, descripcion`. Relación `tenant()` → `belongsTo(Tenant::class)`.

**Claves usadas por esta feature** (grupo `apariencia`, tipo `string`):

| clave | descripción | ejemplo de valor |
|-------|-------------|------------------|
| `apariencia.color_primario` | Color primario de marca | `#1D69D6` |
| `apariencia.color_secundario` | Color secundario de marca | `#1F2025` |
| `apariencia.color_topbar` | Color de fondo de la barra superior | `#FFFFFF` |

> Ausencia de una clave ⇒ se usa el valor por defecto del template (no se emite override).

## Entidad existente: `tenants` (modificada)

Se añade la columna documentada:

| Campo | Tipo | Notas |
|-------|------|-------|
| logo_path | varchar, nullable | ruta relativa del logo en disco `public` (ej. `logos/9/logo.png`). `null` ⇒ logo por defecto del template. |

**Cambios en el modelo `Tenant`**: añadir `logo_path` a `$fillable` y a `getCustomColumns()` (columna
virtual requerida por `stancl/tenancy` en single-database).

## Reglas de validación (resumen; detalle en contracts/)

- **Colores**: si se envían, deben cumplir HEX `#RRGGBB` (regex `/^#[0-9A-Fa-f]{6}$/`). Un color
  inválido ⇒ error de validación, no se persiste (FR-006).
- **Logo**: `nullable|image|mimes:png,jpg,jpeg,webp|max:1024` KB. Un archivo que no cumpla ⇒ rechazo con
  mensaje y el logo vigente no cambia (FR-005). (SVG queda como decisión abierta en research/tasks.)
- **Restablecer**: bandera booleana `restablecer` que, si es true, elimina las 3 claves de color del
  tenant y pone `logo_path = null` (borrando el fichero anterior si existía) (FR-010).

## Relaciones

```text
tenants (1) ──< (N) configuraciones     [por tenant_id]
tenants (1) ── logo_path (columna)      [logo del tenant]
```

## Aislamiento (Principio I)

- `configuraciones` filtra por `tenant_id` vía `BelongsToTenant`; ninguna lectura/escritura cruza de
  tenant.
- El logo se toma exclusivamente del tenant activo (`tenant()->logo_path`).
- Tests obligatorios: ≥2 tenants, afirmar que cada uno ve/edita solo su configuración (test-first).

## Estado / transiciones

No hay máquina de estados. Cada clave es un valor vigente único por tenant; guardar = upsert por
`(tenant_id, clave)`. Restablecer = borrado de claves + logo.
