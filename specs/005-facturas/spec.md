# Feature Specification: Facturas — emisión de facturas ordinarias (núcleo mínimo)

**Feature Branch**: `005-facturas`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: emisión de facturas ordinarias en estado borrador, con listado en datatable, vista full-page de creación con preview en vivo, cálculo de totales/impuestos en backend, numeración correlativa sobre una serie por defecto, y visualización del PDF. Sin Verifactu real, sin simplificada/rectificativa, sin pagos ni stock.

## Clarifications

### Session 2026-07-03

- Q: ¿El borrador consume número correlativo o se asigna al emitir? → A: El borrador NO lleva número fiscal; se muestra como "Borrador" (sin nº). El correlativo se asigna al emitir (feature futura), garantizando numeración sin huecos.
- Q: ¿Los precios llevan impuesto incluido o excluido? → A: Precios SIN impuesto en esta feature; el impuesto se añade sobre la base (base = cantidad × precio − descuento).
- Q: ¿De dónde sale el IRPF por defecto? → A: No hay IRPF por defecto; el usuario lo selecciona/introduce manualmente al crear la factura (0 si no aplica).
- Q: ¿Cómo se calcula la fecha de vencimiento? → A: Autocompletar expedición + `factura.dias_vencimiento` (default 30 días), editable por el usuario.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear una factura ordinaria en borrador (Priority: P1)

Un usuario de un tenant necesita facturar a un cliente identificado. Desde el listado de facturas pulsa "Nueva factura" y llega a una vista full-page dedicada donde ve los datos de su empresa (emisor), elige el cliente, ajusta fechas y forma de pago, y añade una o varias líneas (traídas del catálogo de artículos o como concepto libre). Mientras arma la factura, una preview en vivo le muestra cómo quedará el documento y los totales calculados. Al guardar, la factura se crea en estado borrador con su número correlativo asignado y todos los importes calculados en el servidor.

**Why this priority**: Es el corazón del producto y de esta feature. Sin la creación de la factura con cálculo correcto y numeración, nada más tiene valor. Entrega un MVP demostrable por sí solo.

**Independent Test**: Se puede probar de punta a punta creando una factura con cliente y varias líneas de distintos tipos impositivos y verificando que se persiste en borrador, con número correlativo y con base, cuotas, IRPF, recargo y total calculados por el backend.

**Acceptance Scenarios**:

1. **Given** un tenant con al menos un cliente y una serie ordinaria por defecto, **When** el usuario crea una factura con dos líneas (una del catálogo, una libre) y guarda, **Then** la factura se persiste en estado borrador (sin número fiscal, identificada como "Borrador"), con base total, desglose de impuestos por tipo, IRPF y total calculados en el servidor.
2. **Given** la vista de creación con líneas cargadas, **When** el usuario cambia una cantidad, precio o descuento, **Then** la preview y los totales se actualizan reflejando el recálculo (el valor persistido siempre es el del backend al guardar).
3. **Given** el usuario selecciona un porcentaje de IRPF en la factura, **When** guarda, **Then** el IRPF se resta del total; si no selecciona IRPF, la factura no lleva retención.
4. **Given** una factura que se intenta guardar sin cliente o sin ninguna línea válida, **When** el usuario guarda, **Then** el sistema rechaza el guardado con un mensaje de validación claro y no crea la factura.

---

### User Story 2 - Listar y gestionar facturas en borrador (Priority: P2)

El usuario necesita ver todas las facturas de su empresa en un listado tipo datatable, con búsqueda y orden, y ejecutar acciones básicas sobre cada una: abrir la factura (que en un borrador equivale a editarla en la vista de creación), eliminar un borrador y abrir su PDF.

**Why this priority**: Sin listado, las facturas creadas quedan inaccesibles. Es el punto de entrada natural, pero depende de que exista al menos la creación (P1).

**Independent Test**: Con varias facturas creadas, se puede abrir el listado y verificar que aparecen con sus columnas clave (identificador, cliente, fecha, total, estado), que la búsqueda/orden funciona y que las acciones por fila operan sobre la factura correcta.

**Acceptance Scenarios**:

1. **Given** un tenant con varias facturas, **When** el usuario abre el listado, **Then** ve únicamente las facturas de su tenant, con su identificador ("Borrador" o número), cliente, fecha de expedición, total y estado.
2. **Given** una factura en borrador, **When** el usuario pulsa editar, **Then** llega a la vista de creación precargada con los datos de esa factura para modificarla.
3. **Given** una factura en borrador, **When** el usuario la elimina y confirma, **Then** la factura deja de aparecer en el listado.
4. **Given** el listado, **When** el usuario busca por nombre de cliente o identificador, **Then** el listado se filtra a las coincidencias.

---

### User Story 3 - Visualizar el PDF de la factura (Priority: P2)

Desde el listado, el usuario abre el PDF de una factura ya creada para revisarla o compartirla. El PDF muestra los datos fiscales del emisor (con su logo), los del cliente, las líneas, el desglose de impuestos y el total, con un formato profesional acorde a una factura española.

**Why this priority**: Es el entregable tangible de la factura y valor directo para el usuario, pero depende de que la factura exista (P1).

**Independent Test**: Con una factura creada, abrir su PDF y verificar que refleja fielmente los datos persistidos (emisor, cliente, líneas, desglose por tipo, IRPF, recargo, total) y muestra el logo del tenant.

**Acceptance Scenarios**:

1. **Given** una factura creada, **When** el usuario abre su PDF, **Then** el documento muestra el identificador ("Borrador" o número), fechas, emisor con logo, cliente, líneas, desglose de impuestos por tipo y total, coincidiendo con lo persistido.
2. **Given** una factura de un tenant, **When** un usuario de otro tenant intenta abrir su PDF, **Then** el acceso es denegado.

---

### Edge Cases

- **Numeración concurrente** (al emitir, feature futura): dos usuarios del mismo tenant emiten a la vez → cada factura recibe un número distinto y correlativo, sin huecos ni duplicados. En esta feature los borradores no consumen número, por lo que el problema no se materializa aún.
- **Sin clientes o sin serie por defecto**: si el tenant no tiene clientes, la vista de creación lo indica y guía a crear uno; la serie ordinaria por defecto debe existir para poder numerar.
- **Líneas con distintos tipos impositivos**: el desglose agrupa por tipo y suma bases y cuotas por cada tipo por separado.
- **Descuento del 100% o cantidad/precio en cero**: la base de esa línea es cero y no rompe los totales.
- **Régimen impositivo del tenant** (IVA/IGIC/IPSI): los tipos válidos y el recargo dependen del régimen; IVA e IGIC validan contra su lista de tipos, IPSI acepta un tipo libre (0–100) por no tener catálogo nacional fijo; el recargo de equivalencia solo aplica bajo IVA.
- **Redondeo**: las cuotas se redondean a 2 decimales de forma consistente y el total cuadra con la suma del desglose.
- **Cambio del catálogo tras crear la factura**: modificar o borrar el artículo de origen no altera la línea ya guardada (la factura conserva su copia).
- **Edición de una factura que ya no está en borrador**: no se permite editar ni eliminar una factura que no esté en estado borrador.

## Requirements *(mandatory)*

### Functional Requirements

**Creación y edición**

- **FR-001**: El sistema DEBE permitir crear una factura de tipo ordinaria asociada a un cliente identificado del propio tenant.
- **FR-002**: El sistema DEBE ofrecer una vista de creación full-page dedicada (no un modal) que incluya, como mínimo, secciones para: datos del emisor (tenant), selección de cliente, fechas (expedición, operación, vencimiento) y forma de pago, líneas de detalle editables, IRPF/recargo, y totales.
- **FR-003**: La vista de creación DEBE mostrar una preview en vivo de la factura y de sus totales que se actualice a medida que el usuario edita los datos y las líneas.
- **FR-004**: Cada línea de factura DEBE poder crearse a partir de un artículo del catálogo (autocompletando concepto, unidad, precio y tipo impositivo) o como concepto libre; en ambos casos la línea DEBE guardar su propia copia de esos datos, independiente del catálogo posterior.
- **FR-005**: Cada línea DEBE soportar cantidad, precio unitario y descuento porcentual, y su base DEBE calcularse como cantidad × precio − descuento. Los precios se interpretan SIN impuesto incluido; el impuesto indirecto se añade sobre la base (el modo "precio con impuesto incluido" queda fuera de esta feature).
- **FR-006**: El sistema DEBE permitir editar y eliminar únicamente facturas en estado borrador.
- **FR-007**: El sistema DEBE validar antes de guardar que la factura tiene cliente y al menos una línea válida, rechazando el guardado con mensajes claros en caso contrario.
- **FR-024**: Al fijar la fecha de expedición, el sistema DEBE autocompletar la fecha de vencimiento como expedición + `factura.dias_vencimiento` (por defecto 30 días), permitiendo al usuario editarla o dejarla vacía.

**Cálculo (server-side)**

- **FR-008**: Todos los importes de la factura (base por línea, base total, cuota del impuesto indirecto, recargo, IRPF y total) DEBEN calcularse en el backend a partir de las líneas; los valores enviados por el cliente NUNCA son la fuente de verdad del importe persistido.
- **FR-009**: El impuesto indirecto DEBE calcularse según el `regimen_impositivo` del tenant (IVA, IGIC o IPSI), usando los tipos válidos de ese régimen; la lógica NO DEBE asumir IVA de forma implícita. IVA e IGIC validan el tipo contra su lista fija; IPSI, al no tener catálogo nacional único, acepta un tipo libre en el rango 0–100 en esta feature.
- **FR-010**: El recargo de equivalencia solo DEBE aplicarse bajo régimen IVA y cuando corresponda al cliente/factura.
- **FR-011**: El IRPF DEBE aplicarse a nivel de factura como una retención que se resta del total. El porcentaje de IRPF lo introduce/selecciona el usuario manualmente al crear la factura; NO se propone un valor por defecto desde el cliente ni el tenant. Si el usuario no indica IRPF, la factura no lleva retención.
- **FR-012**: El sistema DEBE generar un desglose de impuestos por tipo (una entrada por combinación de impuesto y porcentaje) con su base imponible y cuota, y los totales de cabecera DEBEN cuadrar con ese desglose.
- **FR-013**: El sistema DEBE persistir los totales desnormalizados en la cabecera de la factura para lectura rápida en listados.

**Numeración y series**

- **FR-014**: Cada tenant DEBE disponer de una serie ordinaria por defecto que permita numerar facturas sin que el usuario tenga que gestionarla (el CRUD de series queda fuera de esta feature).
- **FR-015**: Una factura en estado borrador NO consume número correlativo fiscal; se identifica como "Borrador" (sin número). El número correlativo dentro de su serie, sin huecos ni duplicados y de forma segura ante concurrencia, se asignará al emitir la factura (transición fuera del alcance de esta feature). La lógica de asignación de número queda diseñada para ejecutarse en ese momento.
- **FR-016**: El sistema DEBE mostrar, en listado, vista y PDF, el identificador de la factura: "Borrador" mientras no tenga número, o el número completo legible (serie + número) una vez asignado.

**Estado y ciclo de vida**

- **FR-017**: Las facturas creadas en esta feature DEBEN quedar en estado borrador; NO se implementa la emisión definitiva ni el registro Verifactu (hash/QR/encadenamiento/envío AEAT) en esta feature.
- **FR-018**: El modelo de datos DEBE contemplar los campos requeridos por Verifactu y el ciclo B2B para el futuro, aunque en esta feature no se calculen ni se rellenen.

**Listado y PDF**

- **FR-019**: El sistema DEBE ofrecer un listado tipo datatable de las facturas del tenant con, como mínimo, identificador, cliente, fecha de expedición, total y estado, con búsqueda y ordenación.
- **FR-020**: El listado DEBE ofrecer acciones por fila para abrir la factura (en un borrador, la vista de creación en modo edición), eliminar (si borrador) y abrir el PDF. No se implementa una vista de solo lectura separada en esta feature.
- **FR-021**: El sistema DEBE generar un PDF de la factura con formato profesional que incluya emisor (con logo del tenant), cliente, líneas, desglose de impuestos por tipo, IRPF/recargo y total, reflejando fielmente los datos persistidos.

**Aislamiento y seguridad**

- **FR-022**: Toda operación sobre facturas (listar, crear, editar, eliminar, PDF) DEBE estar restringida al tenant activo; un usuario NUNCA puede ver ni operar facturas de otro tenant.
- **FR-023**: El PDF y la vista de edición de una factura DEBEN denegar el acceso a usuarios de otro tenant.

### Key Entities *(include if feature involves data)*

- **Factura (cabecera)**: representa una factura ordinaria de un tenant, dirigida a un cliente. Atributos clave: serie, número correlativo (vacío mientras es borrador) y número completo legible, tipo (ordinaria), estado (borrador), fechas (expedición, operación, vencimiento), forma de pago, régimen impositivo congelado, totales (base, cuota impuesto, recargo, IRPF, total), y campos reservados de Verifactu/ciclo B2B sin usar aún. Pertenece a un tenant y a un cliente.
- **Línea de factura**: cada concepto facturado. Atributos: referencia opcional al artículo de origen, concepto, unidad, cantidad, precio unitario, descuento, base, tipo impositivo y cuota, recargo y cuota de recargo, orden. Guarda copia propia de los datos del artículo.
- **Desglose de impuestos**: agrupación por tipo de impuesto y porcentaje, con base imponible y cuota; sostiene la obligación normativa de mostrar base por cada tipo.
- **Serie**: secuencia de numeración correlativa de un tenant; en esta feature se usa una serie ordinaria por defecto por tenant.
- **Cliente** (existente): receptor identificado de la factura; aporta datos fiscales y posibles valores por defecto de IRPF/recargo/tipo impositivo.
- **Artículo** (existente): fuente opcional de datos de una línea (concepto, precio, unidad, tipo impositivo).
- **Tenant** (existente): emisor; aporta datos fiscales, logo y régimen impositivo por defecto.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede crear una factura ordinaria completa (cliente + varias líneas) y guardarla en borrador en menos de 3 minutos.
- **SC-002**: Los borradores no consumen número fiscal; se muestran como "Borrador". La lógica de numeración correlativa (sin huecos ni duplicados, segura ante concurrencia) queda diseñada y cubierta por tests para ejecutarse en la emisión (feature futura).
- **SC-003**: En el 100% de los casos, los totales persistidos (base, cuotas por tipo, recargo, IRPF, total) coinciden con el cálculo de referencia del backend y el total cuadra con el desglose, con redondeo a 2 decimales.
- **SC-004**: Ningún usuario puede ver, editar, eliminar o abrir el PDF de una factura que no pertenezca a su tenant (0 fugas verificadas en tests con ≥2 tenants).
- **SC-005**: Los totales de la preview reflejan los cambios de las líneas de forma inmediata para el usuario mientras edita.
- **SC-006**: Cualquier factura creada puede abrirse como PDF que refleja fielmente sus datos persistidos.

## Assumptions

- El régimen impositivo aplicado a la factura se toma del tenant en el momento de la creación (se congela en la factura), coherente con `docs/03-modelo-datos.md`.
- Existe (o se seedea) una serie ordinaria por defecto por tenant; su gestión (CRUD) se aborda en una feature posterior.
- Los clientes y artículos ya existen (features 002 y 004) y se reutilizan como fuentes de datos.
- El PDF se genera a partir de los datos persistidos de la factura; su plantilla visual sigue las convenciones del template NexaDash y `docs/04-front-guidelines.md`.
- En esta feature no hay transición a estado "emitida", ni pagos, ni movimientos de stock, ni facturas simplificadas/rectificativas, ni Verifactu real ni factura electrónica B2B.
- La preview en vivo es una ayuda de UX; el importe válido y persistido es siempre el que calcula el backend al guardar.
- Los tenants pueden estar en distintos regímenes (IVA/IGIC/IPSI); la feature no asume IVA.
