# Phase 1 — Data Model: Facturas (núcleo mínimo)

Fuente de verdad: `docs/03-modelo-datos.md`. Aquí se concreta **solo** lo que esta feature crea y
usa. Convenciones del proyecto: `id` bigint, `timestamps`, `softDeletes` donde aplique, importes
`DECIMAL(12,2)`, porcentajes `DECIMAL(5,2)`, cantidades/precios `DECIMAL(12,4)`, `tenant_id`
indexado + `BelongsToTenant`.

## Tablas nuevas

### `series`
Serie de numeración por tenant. En esta feature se usa una serie ordinaria por defecto (seed).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk, index | `BelongsToTenant` |
| codigo | varchar(10) | prefijo, ej. `F` |
| tipo | enum(`ordinaria`,`simplificada`,`rectificativa`) | solo `ordinaria` en uso |
| ejercicio | year, nullable | reset por año (opcional) |
| proximo_numero | int unsigned | contador (default 1); se incrementa **al emitir** |
| formato | varchar | ej. `{serie}-{anio}-{numero:0000}` |
| activa | boolean | default true |
| timestamps | | |

Índice único: `(tenant_id, codigo, ejercicio)`.

### `facturas`
Cabecera. Incluye columnas Verifactu/ciclo B2B **nullable, sin usar** en esta feature.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk, index | |
| serie_id | fk → series | la serie por defecto del tenant |
| numero | int unsigned, **nullable** | vacío en borrador (se asigna al emitir) |
| numero_completo | varchar, **nullable** | idem; se muestra "Borrador" si null |
| tipo | enum | `ordinaria` (fijo en esta feature) |
| estado | enum | `borrador` (único valor alcanzable ahora) + resto reservado |
| cliente_id | fk → clientes | requerido (ordinaria) |
| fecha_expedicion | date | requerida |
| fecha_operacion | date, nullable | |
| fecha_vencimiento | date, nullable | autocompletada expedición + `factura.dias_vencimiento` (30) |
| forma_pago | enum | `transferencia`/`tarjeta`/`efectivo`/`domiciliacion` |
| moneda | char(3) | default `EUR` |
| regimen_impositivo | enum(`iva`,`igic`,`ipsi`) | **congelado** del tenant al crear |
| aplica_recargo | boolean | congelado del cliente (solo relevante si IVA) |
| base_total | decimal(12,2) | calculado |
| cuota_impuesto_total | decimal(12,2) | calculado |
| cuota_recargo_total | decimal(12,2) | calculado (0 si no IVA/recargo) |
| irpf_porcentaje | decimal(5,2), nullable | manual |
| irpf_cuota | decimal(12,2) | calculado; se resta del total |
| total | decimal(12,2) | base + impuesto + recargo − irpf |
| notas | text, nullable | |
| **Verifactu (nullable, sin usar):** huella, huella_anterior varchar(64); qr_contenido text; verifactu_estado enum default `pendiente`; registro_xml longtext; registrada_at datetime | | reservado (Principio II) |
| **Ciclo B2B (nullable, sin usar):** estado_b2b enum; estado_b2b_fecha datetime | | reservado |
| softDeletes, timestamps | | |

Índices: `(tenant_id, serie_id, numero)` único (permite múltiples `numero` NULL en MySQL),
`(tenant_id, cliente_id)`, `(tenant_id, estado)`, `(tenant_id, fecha_expedicion)`.

### `factura_lineas`
Detalle. Guarda copia propia de los datos (independiente del catálogo).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk, index | |
| factura_id | fk → facturas, index | cascade on delete |
| articulo_id | fk → articulos, nullable | origen opcional; no altera la línea si cambia |
| concepto | varchar | requerido |
| unidad | varchar(20) | copiada o libre |
| cantidad | decimal(12,4) | ≥ 0 |
| precio_unitario | decimal(12,4) | sin impuesto |
| descuento_porcentaje | decimal(5,2), nullable | 0–100 |
| base | decimal(12,2) | calculado |
| tipo_impositivo | decimal(5,2) | válido según régimen (ver `TiposImpositivos`) |
| cuota_impuesto | decimal(12,2) | calculado |
| tipo_recargo | decimal(5,2), nullable | derivado del tipo IVA (solo IVA) |
| cuota_recargo | decimal(12,2) | calculado |
| orden | smallint | orden en PDF/preview |
| timestamps | | |

### `factura_impuestos`
Desglose por tipo (obligatorio: base por cada tipo). Una fila por (tipo_impuesto, porcentaje).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk, index | |
| factura_id | fk → facturas, index | cascade on delete |
| tipo_impuesto | enum(`iva`,`igic`,`ipsi`,`recargo`,`irpf`) | |
| porcentaje | decimal(5,2) | |
| base_imponible | decimal(12,2) | base sujeta a ese tipo |
| cuota | decimal(12,2) | |

## Entidades existentes reutilizadas
- **clientes** (002): receptor; aporta `aplica_recargo_equivalencia` (congelado en factura), datos
  fiscales para el PDF. `irpf_defecto`/`tipo_impositivo_defecto` **no** se usan como default (IRPF
  es manual por decisión de Clarifications).
- **articulos** (004): fuente opcional de líneas (concepto, unidad, precio, tipo_impositivo).
- **tenants** (001/004): emisor; `regimen_impositivo` (congelado), datos fiscales + `logo_path`
  para el PDF.
- **configuraciones** (003): `factura.dias_vencimiento` (default 30) para autocompletar
  vencimiento; opcional `factura.pie_legal` para el PDF.

## Validaciones (FormRequests)
- `cliente_id`: requerido, existe y pertenece al tenant (resuelto bajo scope).
- Al menos **una** línea válida (`concepto` no vacío, `cantidad` ≥ 0, `precio_unitario` ≥ 0).
- `tipo_impositivo` de cada línea: si `TiposImpositivos::validosPara(regimen)` devuelve una lista
  (IVA/IGIC), el tipo DEBE pertenecer a ella; si devuelve `null` (IPSI, sin catálogo nacional
  fijo), se acepta cualquier valor decimal en el rango **0–100**. El servicio de validación debe
  contemplar explícitamente el caso `null` para no rechazar todo bajo IPSI.
- `descuento_porcentaje` ∈ [0, 100]; `irpf_porcentaje` ∈ [0, 100] o null.
- `forma_pago` ∈ enum; `fecha_expedicion` fecha válida; `fecha_vencimiento` ≥ expedición si viene.
- Edición/borrado: solo si `estado == borrador` (autorización en controller/policy).

## Reglas de estado / ciclo de vida (esta feature)
- Crear → `borrador` (sin número). Editable y borrable mientras sea `borrador`.
- Transición a `emitida` (asigna número vía `NumeradorFacturas`, calcula Verifactu): **fuera de
  alcance**; el modelo y el servicio quedan preparados.

## Cálculo (servicio `CalculadoraFactura`) — resumen
Entrada: líneas (cantidad, precio, descuento, tipo_impositivo), régimen congelado, aplica_recargo,
irpf%. Salida: líneas con base/cuota/recargo, `factura_impuestos` agrupado, y totales de cabecera.
Detalle de fórmulas y redondeo en `research.md` › D4. Es la **única** fuente de verdad de importes
(Principio III); el JS de la vista solo previsualiza.
