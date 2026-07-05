# Feature Specification: POS — Facturas simplificadas (tickets)

**Feature Branch**: `012-facturas-simplificadas`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Facturas simplificadas (tipo = simplificada) como un nuevo módulo POS, separado del módulo de facturas ordinarias. Nuevo dropdown 'POS' en el sidebar con dos vistas: (1) listado con DataTable propio de las facturas simplificadas, (2) 'Crear ticket', una vista pensada para tablets de TPV, táctil, moderna e interactiva, aprovechando que la simplificada requiere menos datos. La creación/edición de facturas ordinarias existente NO se toca: permanece dentro del módulo Facturas tal cual está; el index de ordinarias solo debe excluir las simplificadas filtrando por tipo. Reglas de negocio: serie propia con prefijo 'S' (mismo patrón que la serie rectificativa 'R'); validación de importe con BLOQUEO DURO (no permite emitir simplificada si el total con impuestos incluidos supera el tope aplicable, obligando a usar una factura ordinaria); tope según configuración por tenant: 400 € por defecto, o 3.000 € si el tenant opera en un sector con tope ampliado (venta al por menor, hostelería/restauración, transporte de personas, peluquerías, aparcamiento, etc.); soporta variante 'simple' (sin datos de receptor) y 'cualificada' (receptor rellena NIF+domicilio, opcional); la rectificativa en formato simplificado queda FUERA DE ALCANCE. El PDF del ticket puede generarse en formato ticket 80 mm o A4, elegible al ver/descargar. No requiere columnas nuevas en facturas: cliente_id y snapshot cliente_* ya son nullable y tipo=simplificada ya existe en el enum. Ver docs/02-facturacion-espana.md §3.1 y docs/03-modelo-datos.md:234."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Emitir un ticket rápido desde la vista POS (Priority: P1)

Un usuario del tenant (por ejemplo en un mostrador, con una tablet) necesita cobrar y entregar un
comprobante a un cliente que **no exige factura completa**. Entra al módulo **POS → Crear ticket**,
que le presenta una interfaz táctil y ágil pensada para pantalla de TPV: añade uno o varios
artículos/líneas, el sistema calcula el total con impuestos en tiempo real, y **emite** el ticket.
Al emitir, el sistema asigna el siguiente número correlativo de la **serie simplificada** (prefijo
"S"), congela los datos y deja el ticket **emitido e inmutable**, sin necesidad de introducir datos
del receptor.

**Why this priority**: Es el núcleo del módulo. La razón de ser de la factura simplificada es cobrar
rápido sin capturar los datos del cliente; sin poder crear y emitir el ticket, el módulo no aporta
valor. Todo lo demás (listado, PDF, cualificada) depende de que el ticket exista y se emita.

**Independent Test**: Se puede probar entrando a "Crear ticket", añadiendo líneas, comprobando que el
total con impuestos se calcula en backend, emitiendo, y verificando que se crea una factura con
`tipo = simplificada`, número de la serie simplificada, estado "emitida", inmutable, y sin datos de
receptor obligatorios.

**Acceptance Scenarios**:

1. **Given** el usuario en la vista "Crear ticket" con al menos una línea válida y total dentro del
   tope, **When** emite el ticket, **Then** se crea una factura `tipo = simplificada` con el siguiente
   número correlativo de la serie simplificada (prefijo "S"), estado "emitida", `cliente_id` y el
   snapshot `cliente_*` vacíos, totales calculados en backend, y queda inmutable.
2. **Given** dos tickets emitidos uno tras otro en la misma serie simplificada, **When** se emiten,
   **Then** reciben números correlativos consecutivos sin huecos ni duplicados, incluso ante emisiones
   concurrentes.
3. **Given** una serie simplificada cuya última factura fue del año anterior, **When** se emite el
   primer ticket del nuevo año, **Then** recibe el número 1 de ese año (reinicio anual, igual que la
   serie ordinaria y la rectificativa).
4. **Given** un ticket recién emitido, **When** se consulta su historial de eventos, **Then** existe
   un evento append-only de tipo "emitida" con su fecha/hora, igual que en una ordinaria.
5. **Given** el usuario emitiendo un ticket, **When** se completa la emisión, **Then** todos los
   importes (bases, cuotas por tipo impositivo, total) provienen del cálculo de backend a partir de
   las líneas; el cliente nunca es fuente de verdad de un importe ni del número asignado.

---

### User Story 2 - Respetar el tope de importe de la simplificada (Priority: P1)

El usuario intenta emitir un ticket cuyo total (impuestos incluidos) **supera el tope legal** de la
factura simplificada. El sistema **bloquea** la emisión con un mensaje claro indicando que ese
importe requiere una **factura ordinaria**, y no emite el ticket. El tope aplicable depende de la
configuración del tenant: **400 €** por defecto, o **3.000 €** si el tenant está marcado como sector
con tope ampliado.

**Why this priority**: Es una restricción normativa dura (Principio II): una simplificada por encima
del tope es un incumplimiento. Va junto a US1 porque define el límite de validez de cada ticket que
se emite; sin ella, el módulo permitiría emitir documentos fiscalmente inválidos.

**Independent Test**: Configurar el tenant con tope 400 €, intentar emitir un ticket de 450 € y
verificar que se rechaza; cambiar el tenant a sector ampliado (3.000 €), emitir 450 € y verificar
que se permite; intentar 3.100 € y verificar que se rechaza.

**Acceptance Scenarios**:

1. **Given** un tenant con tope por defecto (400 €), **When** el usuario intenta emitir un ticket con
   total impuestos incluidos > 400 €, **Then** el sistema rechaza la emisión con un mensaje claro
   ("supera el importe máximo de una factura simplificada; use una factura ordinaria") y no crea ni
   emite ningún documento.
2. **Given** un tenant marcado como sector con tope ampliado (3.000 €), **When** el usuario emite un
   ticket con total entre 400 € y 3.000 €, **Then** la emisión se permite.
3. **Given** un tenant con tope ampliado, **When** el usuario intenta emitir un ticket con total >
   3.000 €, **Then** el sistema rechaza la emisión igual que en el caso general.
4. **Given** un ticket cuyo total está exactamente en el tope (400 € o 3.000 €), **When** se emite,
   **Then** se permite (el tope es inclusivo: ≤ tope).
5. **Given** la validación del tope, **When** se ejecuta, **Then** se realiza en backend sobre el
   total calculado por el servidor (impuestos incluidos), nunca confiando en un importe enviado por el
   cliente.

---

### User Story 3 - Listar y consultar los tickets en su propia vista POS (Priority: P2)

El usuario entra a **POS → Facturas simplificadas** y ve un listado (DataTable) exclusivo de los
tickets emitidos por su tenant, separado del listado de facturas ordinarias. Desde ahí consulta el
detalle de cada ticket y descarga/imprime su PDF. El listado de **facturas ordinarias** existente
sigue mostrando únicamente ordinarias (y rectificativas) y **no** mezcla las simplificadas.

**Why this priority**: Da visibilidad y trazabilidad al trabajo del cajero y mantiene los dos flujos
(facturación B2B vs. tickets de mostrador) claramente separados, como pidió el negocio. Depende de
que existan tickets emitidos (US1) pero es independiente de la validación de tope (US2).

**Independent Test**: Emitir varios tickets y varias facturas ordinarias; verificar que la DataTable
de POS muestra solo `tipo = simplificada` del tenant y la de Facturas muestra solo las no
simplificadas; abrir el detalle de un ticket y descargar su PDF.

**Acceptance Scenarios**:

1. **Given** un tenant con tickets y facturas ordinarias, **When** el usuario abre "POS → Facturas
   simplificadas", **Then** la DataTable lista únicamente las facturas `tipo = simplificada` de ese
   tenant, sin mezclar ordinarias ni rectificativas.
2. **Given** el mismo tenant, **When** el usuario abre el listado de Facturas (módulo existente),
   **Then** este muestra las ordinarias/rectificativas y **excluye** las simplificadas.
3. **Given** un ticket emitido, **When** el usuario abre su detalle, **Then** ve su número completo
   (serie "S"), fecha, líneas, desglose de impuestos y total, y la indicación de si es simple o
   cualificada.
4. **Given** dos tenants distintos, **When** cada uno abre su listado POS, **Then** solo ve sus
   propios tickets (Principio I: 0 fugas entre tenants).

---

### User Story 4 - Ticket cualificado con datos del receptor (Priority: P2)

Un cliente pide que el ticket incluya sus datos fiscales para poder deducirse el impuesto. El usuario,
desde la vista de crear ticket, **añade opcionalmente** los datos del receptor (NIF y domicilio). El
ticket sigue siendo `tipo = simplificada` (no pasa a ordinaria), pero ahora es una **simplificada
cualificada**: incorpora NIF y domicilio del destinatario y el desglose de la cuota repercutida, en
lugar de solo "impuesto incluido".

**Why this priority**: Cubre un caso real y frecuente (el cliente quiere deducir), pero es una
extensión del flujo base: la mayoría de tickets serán simples. Depende del flujo de emisión (US1).

**Independent Test**: Emitir un ticket rellenando NIF y domicilio del receptor y verificar que se
persisten en el snapshot `cliente_*`, que el tipo sigue siendo `simplificada`, y que el detalle/PDF
muestra los datos del receptor y el desglose de la cuota.

**Acceptance Scenarios**:

1. **Given** el usuario en "Crear ticket", **When** rellena los datos del receptor (NIF + domicilio) y
   emite, **Then** el ticket se emite como `tipo = simplificada` con el snapshot `cliente_*` relleno
   (cualificada), sin cambiar de tipo a ordinaria.
2. **Given** el usuario en "Crear ticket", **When** emite sin rellenar datos del receptor, **Then** el
   ticket se emite como simplificada simple con `cliente_id` y snapshot `cliente_*` vacíos.
3. **Given** un ticket cualificado, **When** se ve su detalle o PDF, **Then** muestra el NIF y
   domicilio del receptor y el desglose de la cuota del impuesto repercutido (no solo "impuesto
   incluido").
4. **Given** el receptor es un cliente existente del tenant, **When** el usuario lo selecciona, **Then**
   sus datos se precargan como snapshot editable de la factura (igual que en la ordinaria), sin
   re-sincronizar si el cliente cambia después.

---

### User Story 5 - Descargar/imprimir el ticket en formato ticket 80 mm o A4 (Priority: P3)

Al ver un ticket, el usuario puede **descargar o imprimir** su PDF eligiendo el formato: **ticket de
80 mm** (rollo de impresora térmica de TPV, alto variable) o **A4** (mismo tamaño que una factura
normal). El contenido cumple el contenido mínimo de la simplificada; en la variante cualificada añade
los datos del receptor y el desglose de cuota.

**Why this priority**: Mejora la experiencia de entrega del comprobante (impresora de tickets vs.
impresión/archivo A4), pero el módulo ya es funcional sin la elección de formato. Depende de que el
ticket exista y esté emitido (US1).

**Independent Test**: Abrir un ticket emitido, elegir "80 mm" y comprobar que el PDF sale en formato
estrecho de rollo; elegir "A4" y comprobar que sale en tamaño A4; verificar que ambos incluyen el
contenido mínimo de la simplificada (y datos de receptor si es cualificada).

**Acceptance Scenarios**:

1. **Given** un ticket emitido, **When** el usuario elige el formato "ticket 80 mm" al ver/descargar,
   **Then** obtiene un PDF de ancho de rollo (80 mm) con alto variable según el número de líneas.
2. **Given** el mismo ticket, **When** el usuario elige el formato "A4", **Then** obtiene un PDF en
   tamaño A4 con el mismo contenido.
3. **Given** cualquiera de los dos formatos, **When** se genera el PDF, **Then** incluye el contenido
   mínimo de la simplificada (número y serie, fecha, identificación del emisor, descripción, tipo
   impositivo y total); y si es cualificada, además NIF+domicilio del receptor y el desglose de cuota.

---

### Edge Cases

- **Tope superado al añadir la última línea**: si al añadir una línea el total pasa a superar el tope,
  la vista de crear ticket avisa y la emisión se bloquea hasta que el importe vuelva a estar dentro
  del tope o se cambie a factura ordinaria (fuera de este módulo).
- **Cambio de sector del tenant a mitad de operativa**: el tope aplicable se resuelve en el momento de
  emitir según la configuración vigente del tenant; tickets ya emitidos no se recalculan.
- **Falta la serie simplificada**: si el tenant no tiene serie simplificada configurada, la emisión
  usa la serie simplificada por defecto del tenant (sembrada), sin exponer CRUD de series (fuera de
  alcance), igual que se resolvió para la serie rectificativa en 009.
- **Ticket sin líneas**: no se puede emitir un ticket sin al menos una línea válida con importe > 0.
- **Aislamiento entre tenants**: la serie simplificada, su numeración y el listado POS son
  independientes por tenant; emitir un ticket en el tenant A nunca afecta la numeración ni expone
  tickets del tenant B.
- **Concurrencia en la serie simplificada**: dos emisiones simultáneas de tickets de la misma serie
  obtienen números distintos y consecutivos, sin huecos ni duplicados (mismo mecanismo de bloqueo que
  la ordinaria).
- **Régimen impositivo**: el desglose del ticket respeta el `regimen_impositivo` del tenant
  (IVA/IGIC/IPSI), congelado al emitir, igual que una ordinaria; el recargo de equivalencia solo
  aplica bajo IVA.
- **Fallo a mitad de la emisión**: si algo falla al emitir, no queda un ticket a medias con número
  huérfano ni un hueco en la serie; o se completa todo (número + estado + evento) o no cambia nada.
- **No se toca el flujo ordinario**: crear/editar de facturas ordinarias sigue funcionando igual; esta
  feature solo añade un filtro por `tipo` en el index existente y un módulo nuevo aparte.

## Requirements *(mandatory)*

### Functional Requirements

**Módulo POS y separación de vistas**

- **FR-001**: El sistema DEBE ofrecer un módulo "POS" accesible desde el sidebar como dropdown con dos
  vistas: (a) listado (DataTable) de facturas simplificadas del tenant y (b) "Crear ticket".
- **FR-002**: La vista de listado POS DEBE mostrar únicamente facturas `tipo = simplificada` del
  tenant activo, y NO DEBE mezclar facturas ordinarias ni rectificativas.
- **FR-003**: El listado de facturas ordinarias existente DEBE **excluir** las facturas
  `tipo = simplificada`, filtrando por tipo, sin alterar el resto de su comportamiento.
- **FR-004**: El flujo existente de creación y edición de facturas ordinarias NO DEBE modificarse en
  su lógica: la feature solo añade el filtro por tipo en su listado y un módulo POS independiente.
- **FR-005**: La vista "Crear ticket" DEBE estar orientada a uso en tablet/TPV: interacción táctil,
  captura de líneas ágil y visualización del total con impuestos actualizada a medida que se editan las
  líneas. (La calidad visual/UX concreta se aborda en plan/implementación; el requisito es que sea una
  vista de captura rápida optimizada para pantalla táctil, no el formulario largo de la ordinaria.)

**Emisión, serie y numeración**

- **FR-006**: Al emitir un ticket, el sistema DEBE asignarle el siguiente número correlativo de la
  **serie simplificada** del tenant (serie separada, con su propio prefijo "S"), garantizando
  numeración correlativa sin huecos ni duplicados, incluso ante emisiones concurrentes.
- **FR-007**: La numeración de la serie simplificada DEBE reiniciarse a 1 al comienzo de cada año
  natural (contador por combinación serie-año), igual que la ordinaria y la rectificativa.
- **FR-008**: La numeración de la serie simplificada DEBE ser independiente de la serie ordinaria, de
  la rectificativa y de otros tenants (Principio I): emitir un ticket no altera el contador de ninguna
  otra serie ni tenant.
- **FR-009**: Al emitir, el sistema DEBE fijar la `fecha_expedicion` del ticket a la fecha del acto de
  emisión (hoy), congelar los datos (régimen impositivo, totales, receptor si lo hay), y dejar el
  ticket emitido e inmutable; la asignación de número y el avance del contador DEBEN ser atómicos.
- **FR-010**: Al emitir, el sistema DEBE registrar un evento de emisión en el historial append-only de
  la factura (mismo mecanismo que la ordinaria).
- **FR-011**: Un ticket en estado "emitido" DEBE ser inmutable: el sistema DEBE impedir editarlo,
  actualizarlo, eliminarlo o re-emitirlo, con las mismas garantías que una ordinaria emitida.

**Cálculo de importes y tope**

- **FR-012**: Todos los importes del ticket (bases, cuotas por tipo impositivo, recargo si aplica,
  total) DEBEN calcularse en el backend a partir de las líneas (Principio III); el cliente nunca es
  fuente de verdad de un importe.
- **FR-013**: El desglose de impuestos del ticket DEBE ser por tipo impositivo y coherente con el
  `regimen_impositivo` congelado (IVA/IGIC/IPSI), igual que en una ordinaria; el recargo de
  equivalencia solo aplica bajo IVA.
- **FR-014**: El sistema DEBE **bloquear** (rechazar) la emisión de un ticket cuyo total con impuestos
  incluidos **supere el tope aplicable**, con un mensaje claro que indique que ese importe requiere una
  factura ordinaria; el tope es inclusivo (se permite hasta el tope, ≤ tope).
- **FR-015**: El tope aplicable DEBE resolverse según la configuración del tenant: **400 €** por
  defecto, o **3.000 €** si el tenant está marcado como sector con tope ampliado. Esta marca vive en la
  configuración del tenant.
- **FR-016**: La validación del tope DEBE ejecutarse en backend sobre el total calculado por el
  servidor, nunca sobre un importe enviado por el cliente.

**Receptor: simple vs. cualificada**

- **FR-017**: El sistema DEBE permitir emitir un ticket **sin** datos de receptor (simplificada
  simple): `cliente_id` y el snapshot `cliente_*` quedan vacíos.
- **FR-018**: El sistema DEBE permitir, opcionalmente, capturar los datos del receptor (NIF y
  domicilio) para emitir una **simplificada cualificada**, manteniendo `tipo = simplificada` (no cambia
  a ordinaria) y persistiendo el snapshot `cliente_*` igual que en una ordinaria; si el receptor es un
  cliente existente, sus datos se precargan como snapshot editable y no se re-sincronizan después.
- **FR-019**: El detalle y el PDF de un ticket cualificado DEBEN mostrar el NIF y domicilio del
  receptor y el desglose de la cuota del impuesto repercutido; el de un ticket simple, el contenido
  mínimo de la simplificada.

**PDF del ticket**

- **FR-020**: El sistema DEBE permitir generar el PDF del ticket en dos formatos elegibles al
  ver/descargar: **ticket 80 mm** (ancho de rollo, alto variable) y **A4**.
- **FR-021**: Ambos formatos de PDF DEBEN incluir el contenido mínimo de la factura simplificada
  (número y serie, fecha de expedición y de operación si difiere, identificación del emisor,
  descripción de la operación, tipo impositivo y total); y en la variante cualificada, además, los
  datos del receptor y el desglose de la cuota.

### Key Entities *(include if feature involves data)*

- **Factura simplificada (nueva instancia sobre la tabla `facturas` existente)**: una factura con
  `tipo = simplificada`. Reutiliza las columnas existentes; `cliente_id` y el snapshot `cliente_*` ya
  son **nullable** (vacíos en la variante simple, rellenos en la cualificada). NO requiere columnas
  nuevas en `facturas`. Comparte el ciclo de emisión/inmutabilidad/eventos con la ordinaria.
- **Serie simplificada (existente en el modelo, a sembrar)**: serie con `tipo = simplificada` y su
  propio formato/prefijo "S", con contador por año natural. El CRUD de series sigue fuera de alcance;
  se usa una serie simplificada por defecto del tenant, sembrada igual que la ordinaria y la
  rectificativa.
- **Configuración de tope por tenant**: marca/ajuste en la configuración del tenant que determina si
  el tope de la simplificada es 400 € (por defecto) o 3.000 € (sector con tope ampliado). Vive en el
  mecanismo de configuración por tenant existente, no como columna nueva en `facturas`.
- **Evento de factura (existente, append-only)**: registra el evento "emitida" del ticket como base de
  auditoría, igual que en la ordinaria.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los tickets emitidos reciben número de la **serie simplificada** (nunca de la
  ordinaria ni la rectificativa), correlativo, sin huecos ni duplicados, con reinicio anual; emitir
  tickets nunca altera el próximo número de otra serie.
- **SC-002**: El 100% de los intentos de emitir un ticket cuyo total supera el tope aplicable
  (400 € / 3.000 € según config del tenant) son rechazados; el 100% de los que están en o bajo el tope
  se permiten.
- **SC-003**: El listado POS muestra solo `tipo = simplificada` del tenant y el listado de Facturas
  excluye las simplificadas en el 100% de los casos; 0 mezcla entre ambos listados.
- **SC-004**: 0 fugas entre tenants en las pruebas de aislamiento: la serie, numeración y listado de
  tickets de cada tenant son independientes.
- **SC-005**: El flujo de creación/edición de facturas ordinarias mantiene su comportamiento previo:
  su suite de pruebas existente sigue en verde tras esta feature (0 regresiones).
- **SC-006**: El 100% de los tickets cualificados persisten y muestran los datos del receptor y el
  desglose de cuota; el 100% de los simples se emiten sin datos de receptor.
- **SC-007**: Ningún ticket emitido puede editarse, eliminarse ni re-emitirse: el 100% de esos
  intentos se rechazan y el ticket permanece intacto.
- **SC-008**: El PDF del ticket puede generarse en formato 80 mm y en A4, ambos con el contenido
  mínimo de la simplificada, en el 100% de los tickets emitidos.

## Assumptions

- La transición a "emitida", la numeración por (serie, año) con bloqueo, el candado de inmutabilidad y
  el registro append-only de eventos ya existen (feature 008): esta feature los **reutiliza** para el
  `tipo = simplificada` en vez de reimplementarlos. El backend fiscal es el mismo; lo nuevo es la vista
  POS y las reglas específicas de la simplificada (tope, serie "S", receptor opcional).
- No se añaden columnas a `facturas`: `cliente_id` y el snapshot `cliente_*` ya son nullable y
  `tipo = simplificada` ya existe en el enum (`facturas.tipo` y `series.tipo`) desde el diseño inicial.
- El tenant dispone de una **serie simplificada por defecto** (prefijo "S", formato análogo a la
  ordinaria) **sembrada** por el sistema; el CRUD de series queda fuera de alcance (se añade en una
  feature posterior), igual que se resolvió para la serie rectificativa en 009.
- La marca de "sector con tope ampliado" (3.000 €) se gestiona como un ajuste de **configuración por
  tenant**; por defecto el tope es 400 €. No se modela la lista concreta de sectores como datos: es una
  decisión del tenant al configurarse. La responsabilidad de marcar correctamente el sector es del
  tenant.
- El diseño visual/UX concreto de la vista "Crear ticket" (layout táctil, componentes, interacción) se
  define en la fase de plan/implementación usando las guías de front del proyecto
  (`docs/04-front-guidelines.md`) y las skills de diseño disponibles; el spec solo fija que debe ser
  una vista de captura rápida optimizada para tablet/TPV, distinta del formulario de la ordinaria.
- Los cobros/pagos del ticket (feature 010) y los movimientos de stock no forman parte del alcance de
  esta feature salvo lo que ya herede automáticamente el motor de emisión reutilizado; si se requiere
  cobro inmediato en el POS, se especifica aparte.
- "Verifactu real" (huella/hash encadenado, QR, XML, envío AEAT) sigue **fuera de alcance**, igual que
  para ordinarias y rectificativas; las columnas correspondientes siguen reservadas y nulas.
- Queda **fuera de alcance**: la rectificativa en formato simplificado (los tipos `simplificada` y
  `rectificativa` siguen siendo mutuamente excluyentes), el CRUD de series, el ciclo comercial B2B, y
  cualquier cambio a la lógica de facturas ordinarias más allá del filtro por tipo en su listado.
