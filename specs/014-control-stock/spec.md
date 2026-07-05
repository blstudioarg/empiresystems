# Feature Specification: Control de stock con proveedores, compras y kardex

**Feature Branch**: `014-control-stock`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Control de stock con proveedores, compras y kardex de movimientos. CRUD de proveedores; compras/facturas de proveedor que al confirmar generan entradas de stock; movimientos_stock como ledger append-only (entrada/salida/ajuste/devolución); la emisión de facturas descuenta stock; alertas de stock mínimo. Multi-tenant y test-first en la lógica de stock. Compatible con docs/03-modelo-datos.md."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Trazabilidad de stock por movimientos (Priority: P1)

Como responsable del inventario de un tenant, necesito que cada cambio en las
existencias de un artículo quede registrado como un movimiento con su sentido
(entrada, salida, ajuste, devolución), la cantidad y el stock resultante tras el
movimiento, para poder auditar y reconstruir cómo se llegó al stock actual.

**Why this priority**: Es el cimiento de todo el control de stock. Sin el ledger
append-only no hay fuente de verdad histórica; el resto de historias (compras,
salidas por factura, ajustes) escriben en él. Entrega valor por sí sola: permite
registrar ajustes manuales de inventario y ver un historial fiable aunque compras
y descuento por factura no existan todavía.

**Independent Test**: Crear un artículo producto con `gestion_stock=true`, registrar
un ajuste manual de entrada y otro de salida, y verificar que se crean dos
movimientos con el `stock_resultante` correcto y encadenado, que `stock_actual` del
artículo coincide con el último movimiento, y que ningún movimiento puede editarse
ni borrarse.

**Acceptance Scenarios**:

1. **Given** un artículo producto con `gestion_stock=true` y `stock_actual=10`, **When** registro un ajuste manual de entrada de 5 unidades (motivo "inventario"), **Then** se crea un movimiento `ajuste` con cantidad 5, `stock_resultante=15`, y `stock_actual` pasa a 15.
2. **Given** un artículo con `stock_actual=15`, **When** registro un ajuste manual de salida de 3 unidades (motivo "rotura"), **Then** se crea un movimiento con `stock_resultante=12` y `stock_actual` pasa a 12.
3. **Given** un movimiento de stock ya registrado, **When** se intenta editarlo o borrarlo, **Then** el sistema lo impide (ledger append-only); una corrección solo puede hacerse con un movimiento inverso.
4. **Given** un artículo `servicio` o un producto con `gestion_stock=false`, **When** se intenta registrar un movimiento de stock, **Then** el sistema lo rechaza (no se controla stock para ese artículo).
5. **Given** dos tenants distintos con artículos propios, **When** el tenant A consulta o registra movimientos, **Then** nunca ve ni afecta movimientos ni stock del tenant B.

---

### User Story 2 - Compras a proveedores que reponen stock (Priority: P2)

Como usuario de compras de un tenant, quiero registrar las facturas de compra
recibidas de mis proveedores con sus líneas (artículo, cantidad, coste, impuesto
soportado) y, al confirmarlas, que el stock de los artículos entre automáticamente,
para reponer inventario con trazabilidad de quién me lo vendió.

**Why this priority**: Es la vía principal de entrada de stock y aporta la
trazabilidad de origen (proveedor). Depende de la US1 (escribe entradas en el
ledger) y de disponer de proveedores. Sin ella, el stock solo crecería por ajustes
manuales.

**Independent Test**: Dar de alta un proveedor, crear una compra en borrador con una
línea de un artículo producto+gestión de stock, confirmarla y verificar que se
genera un movimiento `entrada` (origen `compra`) que suma al `stock_actual`;
después anular la compra y verificar el movimiento inverso.

**Acceptance Scenarios**:

1. **Given** un proveedor y un artículo producto con `gestion_stock=true` y `stock_actual=0`, **When** creo una compra con una línea de 20 unidades de ese artículo y la **confirmo**, **Then** se genera un movimiento `entrada` (origen `compra`, con `compra_id`) de 20 y `stock_actual` pasa a 20.
2. **Given** una compra en borrador, **When** la edito (líneas, cantidades, coste), **Then** no se ha generado ningún movimiento de stock todavía (solo se genera al confirmar).
3. **Given** una compra confirmada que sumó stock, **When** la anulo, **Then** se genera un movimiento inverso (salida/devolución origen `compra`) que descuenta el stock previamente sumado.
4. **Given** una compra con una línea de concepto libre (sin artículo) o de artículo sin gestión de stock, **When** la confirmo, **Then** esa línea NO genera movimiento de stock, pero sí computa en los totales de la compra.
5. **Given** una compra confirmada, **When** se intenta editar sus líneas, **Then** el sistema lo impide (una compra confirmada es inmutable; para corregir se anula y se crea otra).
6. **Given** proveedores y compras de dos tenants, **When** el tenant A opera, **Then** solo ve y afecta sus propios proveedores y compras.

---

### User Story 3 - Salida de stock al emitir facturas (Priority: P2)

Como emisor de facturas de un tenant, quiero que al emitir una factura que incluye
líneas de artículos con gestión de stock, ese stock se descuente automáticamente y
quede registrado como salida, para que las existencias reflejen las ventas sin
tener que ajustarlas a mano.

**Why this priority**: Cierra el ciclo entrada/salida y corrige el gap actual (hoy
la emisión no toca stock). Depende de la US1. Se separa de US2 porque toca el
servicio de emisión existente (`EmisorFacturas`) y las simplificadas (POS).

**Independent Test**: Emitir una factura con una línea de un artículo
producto+gestión de stock y verificar que se crea un movimiento `salida` (origen
`factura`) que descuenta `stock_actual`; anular/rectificar y verificar el
movimiento inverso.

**Acceptance Scenarios**:

1. **Given** un artículo producto con `gestion_stock=true` y `stock_actual=30`, **When** se **emite** una factura con una línea de 4 unidades de ese artículo, **Then** se genera un movimiento `salida` (origen `factura`, con `factura_id`) de 4 y `stock_actual` pasa a 26.
2. **Given** una factura en `borrador` con líneas de artículos con stock, **When** aún no se emite, **Then** no se ha descontado stock (el descuento ocurre al pasar a `emitida`).
3. **Given** una factura emitida que descontó stock, **When** se anula o se emite su rectificativa por anulación, **Then** se genera el movimiento inverso (entrada/devolución origen `factura`) que devuelve el stock.
4. **Given** una factura con líneas de concepto libre o de artículos sin gestión de stock, **When** se emite, **Then** esas líneas no generan movimiento de stock.
5. **Given** un ticket simplificado del módulo POS con artículo con stock, **When** se emite, **Then** descuenta stock igual que una factura ordinaria.

---

### User Story 4 - Gestión de proveedores (Priority: P1)

Como usuario de un tenant, quiero dar de alta, editar, listar y dar de baja
proveedores con sus datos fiscales y de contacto, para poder asociarlos a las
compras.

**Why this priority**: Prerrequisito de las compras (US2). Es un CRUD sencillo y
autónomo, análogo a `clientes`, que aporta valor mínimo por sí solo (agenda de
proveedores) y desbloquea US2.

**Independent Test**: Crear un proveedor con NIF y domicilio, editarlo, listarlo y
darlo de baja lógica; verificar aislamiento entre tenants y que no aparezca en el
selector de compras tras la baja.

**Acceptance Scenarios**:

1. **Given** el formulario de alta de proveedor, **When** guardo con nombre/razón social, NIF y domicilio válidos, **Then** el proveedor queda disponible para asociarlo a compras.
2. **Given** un proveedor con compras asociadas, **When** intento darlo de baja, **Then** se hace baja lógica (soft delete) sin romper las compras existentes que lo referencian.
3. **Given** proveedores de dos tenants, **When** el tenant A lista proveedores, **Then** solo ve los suyos.

---

### User Story 5 - Alertas de stock mínimo (Priority: P3)

Como responsable de inventario, quiero ver de forma destacada qué artículos están
en o por debajo de su stock mínimo, para reponer a tiempo.

**Why this priority**: Mejora operativa que se apoya en el stock ya calculado por
las historias anteriores. No bloquea el ciclo de inventario; es visibilidad.

**Independent Test**: Configurar un `stock_minimo` en un artículo, dejar su
`stock_actual` en o por debajo del umbral y verificar que aparece en el listado/alerta
de reposición; subirlo por encima y verificar que desaparece.

**Acceptance Scenarios**:

1. **Given** un artículo con `stock_minimo=5` y `stock_actual=5`, **When** consulto las alertas de stock, **Then** el artículo aparece como "en umbral / a reponer".
2. **Given** un artículo con `stock_actual=3` y `stock_minimo=5`, **When** consulto las alertas, **Then** aparece como bajo mínimo.
3. **Given** un artículo con `stock_actual` por encima de su mínimo o sin `stock_minimo` definido, **When** consulto las alertas, **Then** no aparece.

---

### Edge Cases

- **Stock negativo por venta**: al emitir una factura cuya cantidad supera el `stock_actual`, el sistema permite la operación pero registra el `stock_resultante` negativo y lo marca visualmente (no bloquea la venta; el negativo es señal de descuadre a corregir con ajuste). Decisión confirmada: **permitir y marcar** (no bloquear ni exigir confirmación), por no frenar la operativa de venta ni chocar con la inmutabilidad fiscal.
- **Concurrencia**: dos operaciones simultáneas sobre el mismo artículo (p. ej. dos emisiones) no deben corromper el `stock_actual` ni dejar `stock_resultante` inconsistentes; el cálculo del resultante y la actualización del stock ocurren de forma atómica y serializada por artículo.
- **Compra/factura con el mismo artículo en varias líneas**: cada línea genera su propio movimiento; el `stock_resultante` se encadena línea a línea en un orden determinista.
- **Anular una compra ya parcialmente vendida**: la anulación genera el inverso completo aunque el stock haya bajado por ventas posteriores, pudiendo dejar el stock negativo (mismo criterio que el edge de venta).
- **Cambiar `gestion_stock` de un artículo con historial**: al desactivar la gestión de stock de un producto que ya tiene movimientos, el histórico se conserva (no se borra) y deja de generar movimientos nuevos.
- **Devolución**: se modela como movimiento `entrada`/`devolución` según su origen (venta devuelta = entrada; compra devuelta = salida), siempre con motivo.

## Requirements *(mandatory)*

### Functional Requirements

**Movimientos de stock (kardex)**

- **FR-001**: El sistema MUST registrar cada cambio de existencias de un artículo como un movimiento con: tipo (`entrada`, `salida`, `ajuste`), sentido efectivo, cantidad positiva, origen (`factura`, `compra`, `ajuste_manual`, `inventario`, `devolucion`), `stock_resultante` tras el movimiento, referencia opcional a factura o compra, motivo opcional y marca temporal.
- **FR-002**: El sistema MUST tratar los movimientos como un ledger **append-only**: no se pueden editar ni borrar; toda corrección se hace con un movimiento inverso.
- **FR-003**: El sistema MUST calcular el `stock_resultante` de cada movimiento a partir del stock previo del artículo y mantener `stock_actual` del artículo sincronizado con el último movimiento.
- **FR-004**: El sistema MUST permitir movimientos únicamente sobre artículos `producto` con `gestion_stock=true`; para servicios o productos sin gestión de stock MUST rechazar el movimiento.
- **FR-005**: El sistema MUST realizar el cálculo del resultante y la actualización de `stock_actual` de forma atómica por artículo para evitar descuadres en concurrencia.
- **FR-006**: El sistema MUST permitir ajustes manuales de stock (entrada o salida) con un motivo (inventario, rotura, merma, etc.), generando el movimiento `ajuste`/`inventario` correspondiente.

**Proveedores**

- **FR-007**: El sistema MUST permitir crear, editar, listar y dar de baja lógica proveedores con nombre/razón social, NIF, domicilio (dirección, CP, ciudad, provincia, país con default España), email, teléfono y notas.
- **FR-008**: El sistema MUST impedir el borrado físico de un proveedor referenciado por compras; la baja es lógica (soft delete) para no romper el histórico.

**Compras**

- **FR-009**: El sistema MUST permitir registrar compras (facturas de proveedor) con proveedor asociado, número de documento externo, fecha, y líneas con artículo opcional, concepto, unidad, cantidad, precio de coste e impuesto soportado.
- **FR-010**: El sistema MUST calcular en el backend los totales de la compra (base, cuota de impuesto soportado, total) a partir de sus líneas.
- **FR-011**: El sistema MUST soportar los estados de compra `borrador`, `confirmada`, `anulada`. Solo `borrador` es editable.
- **FR-012**: Al **confirmar** una compra, el sistema MUST generar un movimiento `entrada` (origen `compra`) por cada línea de artículo producto con gestión de stock y sumar a su `stock_actual`; las líneas sin artículo o sin gestión de stock no generan movimiento.
- **FR-013**: Al **anular** una compra confirmada, el sistema MUST generar el movimiento inverso que descuenta el stock que había sumado.
- **FR-014**: El sistema MUST impedir editar las líneas de una compra `confirmada` (inmutable); la corrección se hace anulando y creando una nueva.

**Salida por facturación**

- **FR-015**: Al **emitir** una factura (ordinaria o simplificada/POS), el sistema MUST generar un movimiento `salida` (origen `factura`) por cada línea de artículo producto con gestión de stock y descontar `stock_actual`. Las facturas en `borrador` NO descuentan stock.
- **FR-016**: Al **anular** una factura emitida o emitir su rectificativa por anulación, el sistema MUST generar el movimiento inverso que devuelve el stock.

**Alertas de stock mínimo**

- **FR-017**: El sistema MUST señalar los artículos cuyo `stock_actual` sea menor o igual a su `stock_minimo` definido, en un listado/alerta de reposición.

**Transversal (multi-tenant y auditoría)**

- **FR-018**: Todas las entidades nuevas (`proveedores`, `compras`, `compra_lineas`, `movimientos_stock`) MUST llevar `tenant_id` y pasar por el global scope de tenant; ninguna operación puede ver o afectar datos de otro tenant.
- **FR-019**: El sistema MUST registrar el actor/momento suficiente para auditar cada movimiento de stock (marca temporal; el origen y las referencias a factura/compra dan la trazabilidad del porqué).

### Key Entities *(include if feature involves data)*

- **Proveedor**: entidad de quien se compra stock. Datos fiscales y de contacto análogos a un cliente (nombre/razón social, NIF, domicilio, email, teléfono, notas). Pertenece a un tenant. Se relaciona con compras.
- **Compra**: documento de compra / factura recibida de un proveedor. Tiene proveedor, número de documento externo, fecha, estado (borrador/confirmada/anulada), totales calculados y líneas. Al confirmarse produce entradas de stock; al anularse, las revierte.
- **Línea de compra**: detalle de una compra, con artículo opcional, concepto, unidad, cantidad, precio de coste e impuesto soportado. La línea con artículo producto+gestión de stock es la que mueve inventario.
- **Movimiento de stock**: registro append-only del kardex. Tipo (entrada/salida/ajuste), cantidad, origen, stock resultante, referencias opcionales a factura o compra, motivo y marca temporal. Fuente de verdad histórica del stock.
- **Artículo** (existente): gana el comportamiento vivo de stock (`stock_actual` sincronizado por los movimientos; `stock_minimo` para alertas). Solo `producto` + `gestion_stock=true` participa.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los cambios de existencias de artículos con gestión de stock (compras confirmadas, facturas emitidas, ajustes) queda reflejado como un movimiento en el kardex, sin excepciones.
- **SC-002**: En cualquier momento, el `stock_actual` de un artículo coincide exactamente con el `stock_resultante` de su último movimiento (0 descuadres) tras cualquier secuencia de operaciones, incluidas anulaciones.
- **SC-003**: Ningún usuario puede ver, modificar o afectar proveedores, compras o movimientos de stock de otro tenant (0 fugas en las pruebas de aislamiento con ≥2 tenants).
- **SC-004**: Ningún movimiento de stock registrado puede editarse ni borrarse; el 100% de las correcciones se materializan como movimientos inversos.
- **SC-005**: Un responsable de inventario puede identificar todos los artículos a reponer (stock ≤ mínimo) en una sola vista.
- **SC-006**: Reconstruir el stock de cualquier artículo sumando/restando su histórico de movimientos da el mismo valor que su `stock_actual`.

## Assumptions

- Se reutiliza el patrón de datos ya documentado en `docs/03-modelo-datos.md` para `proveedores`, `compras`, `compra_lineas` y `movimientos_stock` (esta feature materializa esa "Fase 2").
- Los proveedores replican el patrón de `clientes` (provincia/ciudad encadenadas, país libre default `ES`).
- El impuesto soportado en compras sigue el régimen impositivo del tenant (IVA/IGIC/IPSI), coherente con el resto del sistema; esta feature no introduce liquidación de impuestos ni libro de IVA soportado (fuera de alcance).
- La feature descuenta stock tanto en facturas ordinarias como en simplificadas (POS), ya que ambas comparten la tabla `facturas` y el flujo de emisión.
- Las compras registran un documento externo del proveedor; NO emiten numeración fiscal propia del tenant ni pasan por Verifactu (no son facturas emitidas por el tenant).
- No entra en alcance: valoración de inventario por coste (FIFO/PMP), multi-almacén/ubicaciones, órdenes de compra previas a la factura, recepción parcial de mercancía, ni conciliación bancaria de pagos a proveedores.
- **Stock negativo (resuelto)**: la emisión de facturas nunca se bloquea por falta de stock; el negativo se permite y se marca como descuadre a corregir con un ajuste manual.
