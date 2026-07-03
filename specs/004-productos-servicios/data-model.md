# Data Model: Catálogo de Productos/Servicios

## Entidad: `Articulo` (tabla `articulos`)

| Campo | Tipo | Reglas |
|-------|------|--------|
| id | bigint PK | autoincrement |
| tenant_id | unsignedBigInteger, fk | índice; asignado automáticamente vía `BelongsToTenant` |
| tipo | string (enum `TipoArticulo`: `producto`\|`servicio`) | required |
| sku | string(50), nullable | opcional, sin restricción de unicidad (Assumptions de la spec) |
| nombre | string(255) | required |
| descripcion | text, nullable | opcional |
| unidad | string(20), nullable | libre: `ud`, `hora`, `kg`, `m2`, `servicio`… |
| precio | decimal(12,4) | required, `min:0` (no negativo) |
| tipo_impositivo | decimal(5,2) | required, `between:0,100`; además MUST pertenecer al conjunto de
  tipos válidos para el `regimen_impositivo` del tenant activo (ver regla de negocio abajo) |
| gestion_stock | boolean | default `false`; solo relevante si `tipo = producto` |
| stock_actual | decimal(12,4), nullable | required si `tipo=producto` y `gestion_stock=true`; en
  cualquier otro caso se fuerza a `null` |
| stock_minimo | decimal(12,4), nullable | opcional; solo aplica si `gestion_stock=true` |
| irpf_defecto | decimal(5,2), nullable | opcional, `between:0,100` |
| aplica_recargo_equivalencia | boolean | default `false`; solo tiene sentido si el régimen del
  tenant es `iva` (recargo no aplica a IGIC/IPSI — Principio II), pero no se bloquea a nivel de
  base de datos, solo se ignora/oculta en UI para regímenes distintos de `iva` |
| activo | boolean | default `true` |
| deleted_at | timestamp, nullable | soft delete |
| created_at / updated_at | timestamp | |

**Índices**: `(tenant_id, tipo)`, `(tenant_id, sku)`.

**Relaciones**:
- `Articulo belongsTo Tenant` (vía `tenant_id`, gestionado por `BelongsToTenant`).
- (Futuro, fuera de alcance) `Articulo hasMany FacturaLinea` — no se implementa aquí.

**Reglas de negocio**:
1. El `tipo_impositivo` enviado en alta/edición MUST pertenecer al conjunto resuelto por
   `TiposImpositivos::validosPara($tenant->regimen_impositivo)`:
   - `iva` → `{0, 4, 10, 21}` (más "exento" tratado como `0` con posible flag futuro; para esta
     feature "exento" se representa como tipo `0`, sin campo booleano adicional — el porcentaje 0
     ya es válido para IVA)
   - `igic` → `{0, 3, 7, 9.5, 15, 20}`
   - `ipsi` → sin catálogo cerrado; se valida solo `between:0,100` (ver research.md #1)
2. Si `tipo = servicio`: `gestion_stock` se fuerza a `false` y `stock_actual`/`stock_minimo` se
   fuerzan a `null` en el backend, independientemente de lo recibido en el request.
3. Si `tipo = producto` y `gestion_stock = true`: `stock_actual` es obligatorio (`stock_minimo`
   sigue siendo opcional).

## Entidad afectada: `Tenant` (tabla `tenants`) — cambio incremental

| Campo nuevo | Tipo | Reglas |
|-------------|------|--------|
| regimen_impositivo | string (enum `RegimenImpositivo`: `iva`\|`igic`\|`ipsi`) | default `iva`;
  no editable desde esta feature (se gestiona en una futura pantalla de configuración fiscal del
  tenant; aquí solo se lee) |

**Migración**: `ALTER TABLE tenants ADD COLUMN regimen_impositivo VARCHAR(10) NOT NULL DEFAULT
'iva'` — no rompe tenants existentes (todos quedan en `iva` por defecto, coherente con la
Assumption de la spec).

## Enums nuevos

- `App\Enums\TipoArticulo`: `Producto = 'producto'`, `Servicio = 'servicio'` (paralelo a
  `TipoCliente`).
- `App\Enums\RegimenImpositivo`: `Iva = 'iva'`, `Igic = 'igic'`, `Ipsi = 'ipsi'`.

## Catálogo fijo: `App\Support\TiposImpositivos`

Clase estática con un método `validosPara(RegimenImpositivo $regimen): array<float>|null` que
devuelve la lista cerrada de porcentajes válidos para `iva`/`igic`, o `null` para `ipsi` (indicando
"sin catálogo cerrado, validar solo por rango"). Fuente de los valores:
`docs/02-facturacion-espana.md` (líneas sobre IVA/IGIC citadas en `research.md`).
