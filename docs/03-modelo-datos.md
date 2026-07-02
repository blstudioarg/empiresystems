# Modelo de datos — facturación multi-tenant

> MySQL/MariaDB. Base compartida: **toda tabla de negocio lleva `tenant_id`** con índice y global scope.
> Convención: `id` BIGINT autoincrement, `timestamps` (created_at/updated_at), `softDeletes` donde aplique.
> Importes monetarios: `DECIMAL(12,2)`; porcentajes: `DECIMAL(5,2)`.

## Diagrama de relaciones (resumen)

```
tenants ──< users
tenants ──< clientes
tenants ──< series ──< facturas
tenants ──< facturas ──< factura_lineas
                      ├──< factura_impuestos   (desglose por tipo)
                      ├──< pagos
                      └──< factura_eventos      (log Verifactu)
clientes ──< facturas
facturas ──< facturas   (rectificativa → factura_rectificada_id)
articulos ──(opcional)── factura_lineas
articulos ──< movimientos_stock   (kardex, solo producto con stock)
facturas ──< movimientos_stock    (salida al emitir)
proveedores ──< compras ──< compra_lineas    (Fase 2)
compras ──< movimientos_stock     (entrada al confirmar)
```

---

## Capa CENTRAL (global del SaaS)

### `tenants` — empresa cliente del SaaS
Datos fiscales del emisor + config de facturación.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| nombre_comercial | varchar | |
| razon_social | varchar | |
| nif | varchar(15) | NIF/CIF del emisor |
| direccion, cp, ciudad, provincia, pais | varchar | domicilio fiscal (país default 'ES') |
| email, telefono | varchar | |
| regimen_fiscal | enum | `general`, `autonomo`, `recargo_equivalencia`… |
| regimen_impositivo | enum | **`iva`** (Península/Baleares), `igic` (Canarias), `ipsi` (Ceuta/Melilla) — impuesto indirecto por defecto |
| irpf_defecto | decimal(5,2) | retención por defecto (ej. 15.00) |
| aplica_recargo_equivalencia | boolean | |
| logo_path | varchar | para el PDF |
| verifactu_activo | boolean | activa el registro con huella/QR |
| activo | boolean | |
| timestamps | | |

### `users` (Laravel) — usuarios que operan un tenant
Se añade `tenant_id` (fk) + `rol` (`super_admin`, `admin`, `usuario`). El `super_admin` gestiona todos los tenants.

> **Nota:** por ahora **no se modela el billing del SaaS** (planes, suscripciones, cobro a los tenants). Los precios son flexibles y se gestionan fuera del sistema. Ningún registro indica qué plan o suscripción tiene un tenant.

---

## Capa TENANT (negocio — todas con `tenant_id`)

### `clientes`
Receptor de las facturas.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| tipo | enum | `empresa`, `particular` |
| nombre / razon_social | varchar | |
| nif | varchar(15) | NIF/CIF/NIE; puede ir nulo en particular de simplificada |
| direccion, cp, ciudad, provincia, pais | varchar | pais default 'ES' |
| email, telefono | varchar | |
| aplica_recargo_equivalencia | boolean | minorista en recargo |
| irpf_defecto | decimal(5,2) | nullable |
| tipo_impositivo_defecto | decimal(5,2) | nullable (% IVA/IGIC/IPSI habitual del cliente) |
| notas | text | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, nif)`, `(tenant_id, nombre)`.

### `series`
Series de numeración. Numeración correlativa sin huecos por serie.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| codigo | varchar(10) | prefijo, ej. `F`, `S`, `R` |
| tipo | enum | `ordinaria`, `simplificada`, `rectificativa` |
| ejercicio | year | opcional, si se resetea por año |
| proximo_numero | int unsigned | contador |
| formato | varchar | ej. `{serie}-{anio}-{numero:0000}` |
| activa | boolean | |
| timestamps | | |

Índice único: `(tenant_id, codigo, ejercicio)`.

### `facturas`
Cabecera de factura. **Núcleo del sistema.**

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| serie_id | fk → series | |
| numero | int unsigned | correlativo dentro de la serie |
| numero_completo | varchar | ej. `F-2026-0001` (desnormalizado para mostrar) |
| tipo | enum | `ordinaria`, `simplificada`, `rectificativa` |
| estado | enum | `borrador`, `emitida`, `pagada`, `vencida`, `anulada`, `rectificada` |
| cliente_id | fk → clientes | nullable en simplificada |
| fecha_expedicion | date | |
| fecha_operacion | date | nullable si = expedición |
| fecha_vencimiento | date | nullable |
| forma_pago | enum | `transferencia`, `tarjeta`, `efectivo`, `domiciliacion`… |
| moneda | char(3) | default `EUR` |
| regimen_impositivo | enum | `iva` / `igic` / `ipsi` — **congelado al emitir** (copiado del tenant/cliente) |
| **Totales (calculados desde líneas):** | | |
| base_total | decimal(12,2) | suma de bases |
| cuota_impuesto_total | decimal(12,2) | cuota del impuesto indirecto (IVA/IGIC/IPSI según régimen) |
| cuota_recargo_total | decimal(12,2) | recargo de equivalencia (solo IVA) |
| irpf_porcentaje | decimal(5,2) | nullable |
| irpf_cuota | decimal(12,2) | se **resta** del total |
| total | decimal(12,2) | base + impuesto + recargo − IRPF |
| notas / observaciones | text | |
| **Rectificativa:** | | |
| es_rectificativa | boolean | |
| factura_rectificada_id | fk → facturas | nullable |
| motivo_rectificacion | text | nullable |
| tipo_rectificacion | enum | `sustitucion`, `diferencias` |
| **Verifactu (RD 1007/2023):** | | |
| huella | varchar(64) | hash SHA-256 del registro |
| huella_anterior | varchar(64) | hash del registro anterior (encadenamiento) |
| qr_contenido | text | URL/datos de verificación AEAT |
| verifactu_estado | enum | `pendiente`, `registrada`, `enviada`, `error` |
| registro_xml | longtext / path | XML del registro (Orden HAC/1177/2024) |
| registrada_at | datetime | timestamp del registro inalterable |
| **Ciclo B2B (Ley Crea y Crece):** | | |
| estado_b2b | enum | nullable: `emitida`, `aceptada`, `rechazada`, `pagada` |
| estado_b2b_fecha | datetime | nullable |
| softDeletes, timestamps | | |

Índices: `(tenant_id, serie_id, numero)` único, `(tenant_id, cliente_id)`, `(tenant_id, estado)`, `(tenant_id, fecha_expedicion)`.

> **Regla de inmutabilidad:** una factura `emitida` no se edita ni se borra (Verifactu). Cualquier corrección se hace con una **rectificativa**. En `borrador` sí es editable.

### `factura_lineas`
Detalle de conceptos.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| factura_id | fk | índice |
| articulo_id | fk → articulos | nullable (línea de catálogo o concepto libre) |
| concepto | varchar | descripción (siempre presente, aunque venga de catálogo) |
| unidad | varchar(20) | `ud`, `hora`, `kg`, `m2`, `servicio`… (copiada del artículo o libre) |
| cantidad | decimal(12,4) | |
| precio_unitario | decimal(12,4) | |
| descuento_porcentaje | decimal(5,2) | nullable |
| base | decimal(12,2) | cantidad × precio − descuento |
| tipo_impositivo | decimal(5,2) | % del impuesto indirecto según régimen (IVA 21/10/4/0 · IGIC 0/3/7/9,5/15/20 · IPSI propio) |
| cuota_impuesto | decimal(12,2) | |
| tipo_recargo | decimal(5,2) | recargo de equivalencia, solo IVA (5.2 / 1.4 / 0.5 / 0) |
| cuota_recargo | decimal(12,2) | |
| orden | smallint | ordenación en el PDF |
| timestamps | | |

### `factura_impuestos`
**Desglose por tipo impositivo** (obligatorio: base imponible por cada tipo). Una fila por combinación de impuesto+tipo dentro de la factura.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| factura_id | fk | |
| tipo_impuesto | enum | `iva`, `igic`, `ipsi`, `recargo`, `irpf` |
| porcentaje | decimal(5,2) | |
| base_imponible | decimal(12,2) | base sujeta a ese tipo |
| cuota | decimal(12,2) | |

### `pagos`
Cobros aplicados a facturas (soporta pagos parciales).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| factura_id | fk | |
| fecha | date | |
| importe | decimal(12,2) | |
| metodo | enum | igual que forma_pago |
| referencia | varchar | nullable |
| timestamps | | |

### `factura_eventos`
**Registro de eventos** exigido por Verifactu (log inalterable de operaciones del SIF).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| factura_id | fk | nullable (eventos de sistema) |
| tipo_evento | varchar | `alta`, `anulacion`, `rectificacion`, `envio_aeat`, `error`… |
| detalle | json | payload del evento |
| huella | varchar(64) | hash del evento |
| ocurrido_at | datetime | |

### `articulos` — catálogo unificado (producto o servicio)
Catálogo del que se pueden traer líneas de factura. Un artículo puede ser un **producto** (bien físico, puede llevar stock) o un **servicio** (mano de obra, mantenimiento… nunca lleva stock). El catálogo es opcional: siempre se puede facturar un concepto libre sin artículo asociado.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| tipo | enum | `producto`, `servicio` |
| sku / codigo | varchar(50) | nullable, código interno/referencia |
| nombre | varchar | |
| descripcion | text | |
| unidad | varchar(20) | `ud`, `hora`, `kg`, `m2`, `servicio`… |
| precio | decimal(12,4) | precio unitario base |
| tipo_impositivo | decimal(5,2) | % del impuesto indirecto según régimen del tenant (IVA/IGIC/IPSI) |
| **Stock (solo `producto`):** | | |
| gestion_stock | boolean | si `false` o es servicio → no se controla stock |
| stock_actual | decimal(12,4) | nullable; solo si `gestion_stock` |
| stock_minimo | decimal(12,4) | nullable; umbral de alerta de reposición |
| activo | boolean | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, sku)`, `(tenant_id, tipo)`.

> **Adaptabilidad:** el `tipo servicio` cubre mantenimiento, horas de trabajo, consultoría, etc. — sin stock. El `tipo producto` con `gestion_stock=false` cubre productos que no querés inventariar. Solo `producto` + `gestion_stock=true` mueve inventario.

### `movimientos_stock` — kardex de inventario
Historial de entradas/salidas de stock. Solo aplica a artículos `producto` con `gestion_stock=true`. Da trazabilidad y permite recalcular/auditar el stock.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| articulo_id | fk → articulos | índice |
| tipo | enum | `entrada`, `salida`, `ajuste` |
| cantidad | decimal(12,4) | positiva; el `tipo` marca el sentido |
| stock_resultante | decimal(12,4) | snapshot tras el movimiento (auditoría) |
| origen | enum | `factura`, `compra`, `ajuste_manual`, `inventario`, `devolucion` |
| factura_id | fk → facturas | nullable (si el movimiento viene de una venta) |
| compra_id | fk → compras | nullable (si el movimiento viene de una compra) |
| motivo | varchar | nullable |
| ocurrido_at | datetime | |
| timestamps | | |

Índice: `(tenant_id, articulo_id, ocurrido_at)`.

**Reglas de stock:**
- Al **emitir** una factura, cada línea con `articulo` producto+`gestion_stock` genera una **`salida`** y descuenta `stock_actual`.
- Al **anular/rectificar** una venta, se genera el movimiento inverso (`entrada`/`devolucion`).
- Compras/reposición y ajustes manuales generan `entrada`/`ajuste`.
- `stock_actual` en `articulos` es el valor vivo (rápido de leer); `movimientos_stock` es la fuente de verdad histórica.

### `proveedores` — (Fase 2) proveedores de compra
Para trazar de quién se compra el stock. Estructura análoga a `clientes`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| nombre / razon_social | varchar | |
| nif | varchar(15) | |
| direccion, cp, ciudad, provincia, pais | varchar | pais default 'ES' |
| email, telefono | varchar | |
| notas | text | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, nif)`, `(tenant_id, nombre)`.

### `compras` — (Fase 2) documento de compra / factura de proveedor
Registra la factura recibida del proveedor. Al confirmarla, genera **entradas** de stock.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| proveedor_id | fk → proveedores | |
| numero_documento | varchar | nº de factura del proveedor (externo) |
| fecha | date | fecha de la factura de compra |
| estado | enum | `borrador`, `confirmada`, `anulada` |
| base_total | decimal(12,2) | |
| cuota_impuesto_total | decimal(12,2) | IVA/IGIC/IPSI soportado según régimen |
| total | decimal(12,2) | |
| notas | text | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, proveedor_id)`, `(tenant_id, fecha)`.

### `compra_lineas` — (Fase 2)
Detalle de la compra. Igual que las líneas de factura, con `articulo_id` opcional.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| compra_id | fk → compras | índice |
| articulo_id | fk → articulos | nullable |
| concepto | varchar | |
| unidad | varchar(20) | |
| cantidad | decimal(12,4) | |
| precio_unitario | decimal(12,4) | coste de compra |
| base | decimal(12,2) | |
| tipo_impositivo | decimal(5,2) | % IVA/IGIC/IPSI soportado |
| cuota_impuesto | decimal(12,2) | |

> **Flujo de stock en compras:** al **confirmar** una compra, cada línea con `articulo` producto+`gestion_stock` genera una **`entrada`** en `movimientos_stock` (origen `compra`, con `compra_id`) y suma a `stock_actual`. Al **anular** la compra, movimiento inverso.

### `configuraciones` — valores de configuración
Almacén clave-valor por tenant para parámetros ajustables sin tocar código (textos legales del pie de factura, IVA/IRPF por defecto, datos de contacto para el PDF, integraciones, etc.). Un valor puede ser global del SaaS si `tenant_id` es nulo.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | **nullable** → nulo = valor global del SaaS |
| clave | varchar(100) | ej. `factura.pie_legal`, `iva.defecto`, `serie.reset_anual` |
| valor | text | valor serializado |
| tipo | enum | `string`, `integer`, `boolean`, `decimal`, `json` (cómo castear `valor`) |
| grupo | varchar(50) | agrupación para la UI: `facturacion`, `verifactu`, `email`, `general`… |
| descripcion | varchar | etiqueta legible para la pantalla de ajustes |
| timestamps | | |

Índice único: `(tenant_id, clave)`.

**Resolución de valores:** buscar primero la clave con el `tenant_id` activo; si no existe, caer al valor global (`tenant_id` nulo) como default. Conviene cachear el conjunto por tenant.

**Ejemplos de claves:**

| clave | grupo | ejemplo de valor |
|-------|-------|------------------|
| `impuesto.regimen` | facturacion | `iva` / `igic` / `ipsi` |
| `impuesto.tipo_defecto` | facturacion | `21` (IVA) · `7` (IGIC) · según régimen |
| `irpf.defecto` | facturacion | `15` |
| `serie.reset_anual` | facturacion | `true` |
| `factura.pie_legal` | facturacion | texto de protección de datos / condiciones |
| `factura.dias_vencimiento` | facturacion | `30` |
| `articulo.precio_incluye_iva` | facturacion | `false` (precios sin IVA) / `true` (IVA incluido, retail) |
| `verifactu.activo` | verifactu | `false` |
| `verifactu.entorno` | verifactu | `pruebas` / `produccion` |
| `email.remitente` | email | `facturas@empresa.com` |

> Alternativa/complemento: parámetros de facturación muy usados (IVA/IRPF por defecto, recargo) también viven como columnas en `tenants` para acceso directo; `configuraciones` cubre el resto de ajustes flexibles y los globales del SaaS.

---

## Notas de implementación
- **Totales:** calcular siempre en el backend a partir de las líneas; nunca confiar en el cliente. Guardar los totales desnormalizados en `facturas` para rendimiento de listados y para congelar el importe al emitir.
- **Global scope de tenant:** aplicar en un `TenantScope` sobre un `BaseModel`; todas las consultas filtran por `tenant_id` automáticamente. Cubrir con tests para evitar fugas entre tenants.
- **Numeración:** asignar `numero` dentro de una transacción con bloqueo (evitar huecos/duplicados en concurrencia).
- **Verifactu:** el cálculo de huella y encadenamiento se hace al **emitir** (pasar de borrador a emitida), en un servicio dedicado; a partir de ahí la factura es inmutable.
