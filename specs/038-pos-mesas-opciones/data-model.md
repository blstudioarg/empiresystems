# Phase 1 — Modelo de datos: POS con mesas y opciones de artículo

Convenciones heredadas de `docs/03-modelo-datos.md`, sin excepciones: `id` BIGINT autoincrement,
`timestamps` en toda tabla, importes `DECIMAL(12,2)`, porcentajes `DECIMAL(5,2)`, **`tenant_id`
indexado y cubierto por `TenantScope`** en las nueve tablas (Principio I).

Prefijo `pos_` justificado en [research.md](research.md) D2.

## Diagrama

```
tenants ──< pos_zonas ──< pos_mesas
                              │
                              └──< pos_cuentas ──< pos_cuenta_lineas ──< pos_cuenta_linea_opciones
                                       │
                                       └──< pos_cobros ──> facturas   (1 cuenta → N documentos)

tenants ──< pos_opcion_grupos ──< pos_opciones ──(opcional)──> articulos   (artículo vinculado)
articulos >──< pos_opcion_grupos          vía pos_articulo_grupo
articulos >──< pos_opciones               vía pos_articulo_opcion (precio propio)
```

---

## Configuración (sin tabla nueva)

Vive en `configuraciones` (clave/valor por tenant), leída por `App\Support\ConfigPos`. Ver
[research.md](research.md) D1 para claves y defaults. **No se siembra ninguna fila**: la ausencia de
la clave equivale a apagado, que es lo que hace que FR-002 se cumpla para los tenants existentes sin
migración de datos.

---

## `pos_zonas`

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | Principio I |
| nombre | varchar(60) | libre; único por tenant. **Sin significado para el sistema** (FR-009) |
| suplemento_porcentaje | decimal(5,2) | default `0.00`; se aplica solo si `pos.suplemento_zona_activo` |
| orden | int unsigned | presentación en la Sala |
| timestamps, softDeletes | | |

**Reglas**: no se puede eliminar con mesas asociadas (FR-015) — comprobación explícita en el
controller respondiendo 422, no dejar reventar la FK (patrón ya documentado para borrado con
`RESTRICT`).

---

## `pos_mesas`

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | |
| zona_id | bigint FK → `pos_zonas` | exactamente una zona (FR-010) |
| nombre | varchar(40) | único por `(tenant_id, zona_id)` |
| orden | int unsigned | |
| timestamps, softDeletes | | |

**Estado libre/ocupada NO se almacena**: se deriva de la existencia de una `pos_cuentas` en estado
`abierta` apuntando a la mesa. Guardarlo sería un dato duplicado que puede desincronizarse; el
sistema ya sigue ese criterio con `stock_actual` como caché de lectura y el kardex como verdad.

**Reglas**: no se puede eliminar con cuenta abierta (FR-015).

---

## `pos_cuentas`

El corazón de la feature. **No es una factura en borrador** (FR-017).

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | |
| mesa_id | bigint FK nullable → `pos_mesas` | null = venta directa sin mesa (FR-021) |
| comensales | tinyint unsigned nullable | FR-022 |
| estado | enum | `abierta`, `cerrada`, `anulada` |
| abierta_por | bigint FK → `users` | FR-023 |
| abierta_en | timestamp | base del cálculo de "mesa olvidada" (FR-014) |
| cerrada_en | timestamp nullable | |
| version | int unsigned, default 1 | bloqueo optimista (FR-024), ver research D7 |
| receptor_* | igual que en `facturas` | receptor opcional de la simplificada cualificada |
| notas | text nullable | |
| timestamps | | |

**Sin `numero` ni `serie_id`**: es la garantía estructural de FR-017. La numeración solo la asigna
`EmisorFacturas` al emitir.

**Sin `total`**: el importe pendiente se calcula desde las líneas. Cachearlo abriría la puerta a que
la mesa muestre un importe distinto del que se cobra.

**Transiciones**: `abierta → cerrada` (al saldarse la última unidad pendiente, FR-031) y
`abierta → anulada` (FR-020). Ninguna transición sale de `cerrada` o `anulada`.

---

## `pos_cuenta_lineas`

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | |
| cuenta_id | bigint FK → `pos_cuentas` | |
| articulo_id | bigint FK nullable → `articulos` | nullable: la línea sobrevive al borrado del artículo |
| concepto | varchar | **congelado** al añadir (edge case: artículo eliminado) |
| unidad | varchar nullable | |
| cantidad | decimal(12,2) | |
| precio_unitario | decimal(12,2) | precio base **sin** suplemento de zona (se aplica al cobrar) |
| suplemento_opciones | decimal(12,2), default 0 | suma de los suplementos elegidos, congelada |
| tipo_impositivo | decimal(5,2) | congelado al añadir |
| **cantidad_saldada** | decimal(12,2), default 0 | **soporte de cobro parcial** (FR-030) |
| orden | int unsigned | |
| timestamps | | |

**`cantidad_saldada` existe desde la primera migración** aunque el cobro parcial se implemente
después — decisión D9 de research: añadirla luego obligaría a migrar datos reales y revalidar la
numeración.

**Invariante**: `0 ≤ cantidad_saldada ≤ cantidad`. Pendiente de una línea =
`cantidad − cantidad_saldada`. Una cuenta está saldada cuando todas sus líneas tienen
`cantidad_saldada = cantidad`.

**Por qué el suplemento de zona NO se congela aquí**: FR-051 exige el valor vigente *en el momento
del cobro* y de la zona *donde se cobra*. Congelarlo al añadir la línea daría el valor equivocado
tras una transferencia entre zonas.

---

## `pos_cuenta_linea_opciones`

Opciones concretas elegidas para una línea.

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | |
| cuenta_linea_id | bigint FK → `pos_cuenta_lineas` | |
| opcion_id | bigint FK nullable → `pos_opciones` | nullable: sobrevive al borrado de la opción |
| nombre | varchar | **congelado** (edge case: opción eliminada) |
| precio | decimal(12,2) | **congelado** en el momento de añadir |
| articulo_vinculado_id | bigint FK nullable → `articulos` | copiado al añadir, para el movimiento de stock |
| timestamps | | |

Congelar nombre y precio es lo que permite que una cuenta abierta durante días siga siendo cobrable
y coherente aunque el catálogo cambie por debajo.

---

## `pos_cobros`

Cada emisión realizada sobre una cuenta. Una si se cobra entera, varias si se divide.

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | |
| cuenta_id | bigint FK → `pos_cuentas` | |
| factura_id | bigint FK → `facturas` | documento emitido, inmutable |
| zona_suplemento_aplicado | decimal(5,2) | **congelado**: el % vigente al cobrar (FR-051) |
| cobrado_por | bigint FK → `users` | FR-023 |
| timestamps | | |

Y el detalle de qué unidades saldó cada cobro, para poder auditar y para impedir el doble cobro
(FR-029):

### `pos_cobro_lineas`

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | |
| cobro_id | bigint FK → `pos_cobros` | |
| cuenta_linea_id | bigint FK → `pos_cuenta_lineas` | |
| cantidad | decimal(12,2) | unidades saldadas en ESTE cobro |

**Invariante crítico (FR-033)**: para cada línea, la suma de `cantidad` en `pos_cobro_lineas` debe
igualar `cuenta_lineas.cantidad_saldada`. Es la comprobación que hace imposible cobrar dos veces lo
mismo o dejar algo sin cobrar, y debe tener test propio (Principio IV).

---

## `pos_opcion_grupos`

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | |
| nombre | varchar(60) | único por tenant. Ej.: "Punto de cocción" |
| min_selecciones | tinyint unsigned, default 0 | |
| max_selecciones | tinyint unsigned nullable | null = sin límite |
| obligatorio | boolean, default false | atajo de `min_selecciones ≥ 1` |
| orden | int unsigned | |
| timestamps, softDeletes | | |

**Reglas**: `min ≤ max` cuando `max` no es null; un grupo obligatorio debe tener al menos una
opción (FR-037 y escenario 4 de US3).

---

## `pos_opciones`

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint FK, indexado | |
| grupo_id | bigint FK → `pos_opcion_grupos` | |
| nombre | varchar(60) | único por grupo |
| precio_defecto | decimal(12,2), default 0 | punto de partida al asignar a un artículo |
| articulo_vinculado_id | bigint FK nullable → `articulos` | mueve stock, no genera línea (FR-052) |
| orden | int unsigned | FR-040 |
| timestamps, softDeletes | | |

---

## `pos_articulo_grupo` y `pos_articulo_opcion`

Dos pivots, no uno. El grupo se asigna al artículo; el precio se ajusta por opción.

### `pos_articulo_grupo`

| Campo | Tipo | Notas |
|---|---|---|
| tenant_id | bigint FK, indexado | |
| articulo_id | bigint FK → `articulos` | |
| grupo_id | bigint FK → `pos_opcion_grupos` | |
| orden | int unsigned | orden de los grupos dentro del artículo (FR-040) |

Único por `(articulo_id, grupo_id)`.

### `pos_articulo_opcion`

| Campo | Tipo | Notas |
|---|---|---|
| tenant_id | bigint FK, indexado | |
| articulo_id | bigint FK → `articulos` | |
| opcion_id | bigint FK → `pos_opciones` | |
| **precio** | decimal(12,2) | **precio para ESTE artículo** (FR-039) |
| orden | int unsigned | |

Único por `(articulo_id, opcion_id)`.

**Este es el campo que hace que "Extra queso" cueste 1,50 € en el solomillo y 0,50 € en el
bocadillo**, sin tocar el `precio_defecto` de la opción ni el de los demás artículos. Una fila
ausente en este pivot significa "esta opción no aplica a este artículo".

---

## Impacto en tablas existentes

**Ninguna migración destructiva. Ninguna columna nueva en tablas existentes.**

`facturas`, `factura_lineas`, `movimientos_stock`, `articulos` y `configuraciones` se usan tal cual.
La relación cuenta → factura vive en `pos_cobros`, del lado nuevo, para no tocar el esquema de
facturación (Principio II: no se toca lo que está bajo auditoría fiscal si se puede evitar).

---

## Índices

Además de los `tenant_id` (Principio I) y las FK:

| Tabla | Índice | Para qué |
|---|---|---|
| `pos_cuentas` | `(tenant_id, estado, mesa_id)` | pintar la Sala en una consulta |
| `pos_cuenta_lineas` | `(cuenta_id, orden)` | recuperar una cuenta ordenada |
| `pos_cobros` | `(tenant_id, cuenta_id)` | historial de cobros de una cuenta |
| `pos_articulo_opcion` | `(articulo_id)` | saber si un artículo tiene opciones al pintar el catálogo |

El último importa para SC-004 ("un artículo sin opciones se añade en 1 toque"): el catálogo necesita
saber, sin coste, qué artículos abren modal y cuáles no.
