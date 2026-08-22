# Feature Specification: Módulo de Cobros de facturas

**Feature Branch**: `043-cobros-facturas`

**Created**: 2026-08-21

**Status**: Draft

**Input**: User description: "El módulo de Facturas ya permite gestionar los pagos, pero el cliente solicita una vista nueva, completa, para gestionar todo el tema de los cobros de facturas: cards informativas, DataTable, etc. No se trata de crear lógica nueva sino de dar una UI más completa, ya que la facturación es lo más importante del producto."

## Contexto y encuadre

Hoy el cobro de una factura solo se puede tocar **desde dentro de cada factura**: hay que entrar
al listado de facturas, encontrar la factura, abrirla y registrar el pago ahí. No existe ninguna
pantalla que responda a la pregunta de negocio real del usuario: *"¿cuánto dinero me deben, quién
me lo debe, desde cuándo, y qué cobré este mes?"*.

Esta feature crea esa pantalla: un **módulo de Cobros** que centraliza la gestión de cobro de
facturas emitidas. **No introduce reglas de negocio nuevas**: reutiliza tal cual las reglas ya
existentes de registro y anulación de pagos, de saldo pendiente y de estado de cobro
(pendiente / parcial / cobrada). Es una feature de **experiencia de usuario y visibilidad**, no
de lógica financiera.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver de un vistazo la situación de cobro del negocio (Priority: P1)

Como responsable de administración, entro al módulo de Cobros y en la parte superior veo un
resumen del estado de cobro del negocio para el periodo que elija: cuánto tengo pendiente de
cobro, cuánto he cobrado en el periodo, cuánto está vencido (en mora) y cuántas facturas
siguen sin cobrarse del todo. Debajo veo el listado de facturas con su situación de cobro.

**Why this priority**: Es el valor central de la feature y lo único que hoy no existe en ninguna
parte del producto. Por sí sola ya entrega valor: convierte datos dispersos en una foto del
estado de cobro. Sin ella, el resto de historias son solo atajos a algo que ya se podía hacer.

**Independent Test**: Con un conjunto de facturas de prueba en distintos estados de cobro
(sin cobrar, cobradas a medias, cobradas del todo, vencidas), entrar al módulo y comprobar que
las cifras del resumen y las filas del listado coinciden con esas facturas.

**Acceptance Scenarios**:

1. **Given** el tenant tiene facturas emitidas con cobros parciales y totales, **When** el
   usuario abre el módulo de Cobros, **Then** ve un resumen con el importe total pendiente de
   cobro, el importe cobrado en el periodo seleccionado, el importe vencido y el número de
   facturas con saldo pendiente, todos coherentes con las facturas del tenant.
2. **Given** el usuario está en el módulo de Cobros, **When** cambia el rango de fechas del
   resumen, **Then** las cifras se recalculan para ese rango sin recargar toda la pantalla.
3. **Given** el usuario está en el módulo de Cobros, **When** consulta el listado, **Then** cada
   fila muestra la factura (serie/número), el cliente, la fecha de emisión, la fecha de
   vencimiento, el total, lo cobrado, el saldo pendiente, el estado de cobro y, si procede, los
   días de retraso.
4. **Given** hay facturas de otro tenant en el sistema, **When** el usuario consulta resumen y
   listado, **Then** no ve ni un solo importe ni fila que no pertenezca a su tenant.
5. **Given** el tenant todavía no tiene ninguna factura emitida, **When** abre el módulo,
   **Then** ve el resumen a cero y un mensaje de listado vacío, sin errores.

---

### User Story 2 - Encontrar rápido las facturas que hay que perseguir (Priority: P1)

Como responsable de administración, necesito filtrar el listado para trabajar solo con lo que me
interesa: las que están vencidas, las de un cliente concreto, las de una serie, las de un rango
de fechas, o las que están a medio cobrar. Y quiero poder ordenar por saldo pendiente o por días
de retraso para atacar primero lo más grande o lo más viejo.

**Why this priority**: Un listado sin filtros es inservible en cuanto hay volumen real. Va junto
a la historia 1 porque el resumen sin la capacidad de bajar al detalle no permite actuar.

**Independent Test**: Cargar facturas variadas y comprobar que cada filtro (estado de cobro,
cliente, serie, rango de fechas, "solo vencidas") deja exactamente el subconjunto esperado, y
que el orden por saldo pendiente y por días de retraso es correcto.

**Acceptance Scenarios**:

1. **Given** facturas en los tres estados de cobro, **When** el usuario filtra por "pendiente",
   **Then** solo ve facturas sin ningún cobro registrado.
2. **Given** facturas de varios clientes, **When** el usuario filtra por un cliente, **Then**
   solo ve las facturas de ese cliente.
3. **Given** hay facturas con fecha de vencimiento pasada y saldo pendiente, **When** el usuario
   activa "solo vencidas", **Then** solo ve esas, ordenables por días de retraso.
4. **Given** el usuario aplica varios filtros a la vez, **When** consulta el resultado, **Then**
   los filtros se combinan (se cumplen todos), y el resumen superior usa **el mismo rango de
   fechas** que el listado —cada indicador con el criterio de fecha que le corresponde según
   FR-008/FR-009—, de modo que cards y listado nunca muestran periodos distintos.
5. **Given** hay cientos de facturas, **When** el usuario navega por el listado, **Then** puede
   paginar y buscar sin que la pantalla se vuelva lenta.

---

### User Story 3 - Registrar un cobro sin salir del listado (Priority: P2)

Como responsable de administración, cuando veo que un cliente me ha pagado, quiero registrar el
cobro directamente desde la fila de esa factura, indicando importe, fecha, forma de pago y una
referencia opcional, y ver inmediatamente cómo cambia su saldo y su estado.

**Why this priority**: Es la acción que convierte la pantalla de "informe" en "herramienta de
trabajo". Se puede entregar después de 1 y 2 porque registrar cobros ya es posible hoy desde la
factura; aquí solo se elimina el rodeo.

**Independent Test**: Desde el listado, registrar un cobro parcial sobre una factura pendiente y
comprobar que su saldo baja, su estado pasa a parcial y el resumen superior se actualiza.

**Acceptance Scenarios**:

1. **Given** una factura con saldo pendiente, **When** el usuario registra un cobro por parte del
   saldo, **Then** recibe confirmación, la fila pasa a estado parcial y el saldo se reduce en ese
   importe.
2. **Given** una factura con saldo pendiente, **When** el usuario registra un cobro por el total
   del saldo, **Then** la factura pasa a estado cobrada y deja de aparecer bajo el filtro de
   pendientes.
3. **Given** una factura con saldo pendiente, **When** el usuario intenta registrar un importe
   mayor que el saldo pendiente, o cero, o negativo, **Then** el sistema lo rechaza con un
   mensaje claro y no registra nada.
4. **Given** el formulario de cobro abierto, **When** el usuario no indica fecha, **Then** se
   propone la fecha de hoy como valor por defecto.
5. **Given** una factura ya cobrada por completo, **When** el usuario mira sus acciones, **Then**
   la opción de registrar cobro no está disponible.

---

### User Story 4 - Revisar y corregir el historial de cobros de una factura (Priority: P2)

Como responsable de administración, quiero abrir el detalle de una factura del listado y ver
todos los cobros registrados sobre ella (fecha, importe, forma de pago, referencia, y si alguno
fue anulado), y poder anular un cobro que se registró por error, con una confirmación explícita.

**Why this priority**: La corrección de errores es imprescindible para que el usuario confíe en
registrar cobros ahí. Depende de la historia 3 en la práctica, pero es testeable por separado.

**Independent Test**: Sobre una factura con dos cobros registrados, abrir el historial, anular
uno con confirmación y comprobar que el saldo y el estado vuelven al valor esperado y que el
cobro anulado sigue visible marcado como anulado.

**Acceptance Scenarios**:

1. **Given** una factura con varios cobros, **When** el usuario abre su historial, **Then** ve
   todos los cobros con fecha, importe, forma de pago y referencia, del más reciente al más
   antiguo.
2. **Given** un cobro vigente, **When** el usuario pide anularlo, **Then** el sistema pide
   confirmación explícita antes de hacer nada.
3. **Given** el usuario confirma la anulación, **When** se completa, **Then** el saldo pendiente
   de la factura aumenta en ese importe, el estado de cobro se recalcula y el resumen superior se
   actualiza.
4. **Given** un cobro ya anulado, **When** el usuario mira el historial, **Then** lo sigue viendo,
   marcado como anulado, sin opción de volver a anularlo.
5. **Given** el usuario cancela la confirmación, **When** vuelve al historial, **Then** nada ha
   cambiado.

---

### User Story 5 - Ver la factura sin perder el contexto (Priority: P3)

Como responsable de administración, cuando dudo de un importe quiero ver el documento de la
factura sin salir del módulo de Cobros ni perder los filtros que tenía aplicados.

**Why this priority**: Comodidad importante pero no bloqueante: la factura ya es accesible desde
su propio módulo. Se entrega al final.

**Independent Test**: Desde una fila del listado, abrir la vista de la factura, cerrarla y
comprobar que se vuelve al listado con los mismos filtros y la misma página.

**Acceptance Scenarios**:

1. **Given** una fila del listado, **When** el usuario elige ver la factura, **Then** el
   documento se muestra sin abandonar la pantalla de Cobros ni abrir una pestaña nueva.
2. **Given** la vista de la factura abierta, **When** el usuario la cierra, **Then** el listado
   conserva filtros, orden y página.

---

### User Story 6 - Saber usar la pantalla sin que se lo expliquen (Priority: P3)

Como usuario del tenant, quiero poder consultar desde la propia pantalla una guía que me explique
qué significan las cifras del resumen, qué es el estado de cobro y cómo registrar o anular un
cobro. Y quiero que si le pregunto al asistente de la aplicación por los cobros, sepa
responderme sobre esta pantalla.

**Why this priority**: Obligatorio por las reglas del proyecto (guía in-app y base de conocimiento
del asistente se actualizan en el mismo cambio), pero no bloquea el uso del módulo.

**Independent Test**: Abrir la ayuda contextual desde el módulo de Cobros y comprobar que
describe el resumen, los filtros, el registro y la anulación de cobros; preguntar al asistente
por "cobros" y comprobar que responde con el contenido de esta pantalla.

**Acceptance Scenarios**:

1. **Given** el usuario está en el módulo de Cobros, **When** abre la ayuda de la pantalla,
   **Then** ve una guía específica de cobros, no un texto genérico ni el de otra pantalla.
2. **Given** el asistente de la aplicación, **When** se le pregunta cómo registrar un cobro,
   **Then** responde describiendo el módulo de Cobros.

---

### Edge Cases

- **Factura sin fecha de vencimiento**: no puede considerarse vencida ni mostrar días de retraso;
  aparece en el listado con ese dato vacío y no suma al importe vencido.
- **Cobro con fecha anterior a la emisión de la factura**: se trata igual que hoy en el módulo de
  facturas; esta pantalla no introduce una regla nueva al respecto.
- **Factura cobrada de más por cobros históricos**: si un dato heredado deja saldo negativo, la
  pantalla lo muestra tal cual (no lo trunca a cero silenciosamente) y no permite registrar más
  cobros.
- **Dos usuarios registran un cobro sobre la misma factura a la vez**: el segundo recibe el error
  de importe superior al saldo si ya no cabe, y el listado le muestra el estado real al refrescar.
- **Anular el único cobro de una factura cobrada**: vuelve a estado pendiente y reaparece bajo los
  filtros de pendiente/vencida según corresponda.
- **Facturas en borrador**: no son objeto de cobro y no aparecen en este módulo.
- **Facturas rectificativas**: nunca se cobran por sí mismas; no aparecen como fila propia. El
  cobro se gestiona siempre desde la factura original rectificada, que sí aparece, y cuyo importe
  a cobrar es el importe efectivo tras la rectificación (sustitución o diferencias), no su total
  bruto. La fila debe dejar visible que ese importe viene de una rectificación, para que el
  usuario no crea que hay un error de importe.
- **Usuario sin permiso de cobros**: no ve la entrada de menú ni puede acceder a la pantalla por
  URL directa.
- **Rango de fechas inválido** (fin anterior a inicio): el selector no permite componerlo, y si
  aun así llega al servidor (URL manipulada), este no falla: cae al rango por defecto (mes en
  curso) y lo muestra como tal, en vez de devolver un error.
- **Usuario con acceso a Facturas pero no a Cobros**: en el módulo de Facturas no se le ofrecen las
  acciones de cobro, en vez de ofrecérselas y que fallen al pulsarlas.
- **Periodo sin ningún cobro**: el resumen muestra cero cobrado en el periodo, no un hueco.

## Requirements *(mandatory)*

### Funcionales — Acceso y encuadre

- **FR-001**: El sistema DEBE ofrecer un módulo de Cobros accesible como entrada propia del menú
  de navegación del tenant.
- **FR-002**: El acceso al módulo DEBE estar gobernado por un permiso propio, independiente del
  permiso de facturas; sin ese permiso, ni la entrada de menú ni la pantalla son accesibles.
- **FR-003**: El módulo DEBE mostrar únicamente datos del tenant activo, sin excepción.
- **FR-004**: El módulo NO DEBE introducir reglas de negocio nuevas sobre cobros: registro,
  anulación, cálculo de saldo pendiente y determinación del estado de cobro se comportan
  exactamente igual que hoy en el módulo de Facturas.
- **FR-005**: Todos los importes mostrados DEBEN calcularse en el servidor; la pantalla nunca
  deriva ni corrige un importe por su cuenta.

### Funcionales — Resumen (cards informativas)

- **FR-006**: El módulo DEBE mostrar un resumen con, como mínimo: importe total pendiente de
  cobro, importe cobrado en el periodo seleccionado, importe vencido (facturas con saldo
  pendiente y vencimiento pasado) y número de facturas con saldo pendiente.
- **FR-007**: El resumen DEBE permitir seleccionar un rango de fechas y recalcularse para ese
  rango sin recargar la pantalla completa.
- **FR-008**: Cada indicador DEBE dejar claro sobre qué fecha se calcula (fecha de emisión de la
  factura o fecha del cobro), para que el usuario no interprete mal la cifra.
- **FR-009**: El importe "pendiente de cobro" y el "vencido" DEBEN reflejar el estado actual
  acumulado, no solo el rango seleccionado; el "cobrado" DEBE ceñirse al rango.

### Funcionales — Listado

- **FR-010**: El módulo DEBE mostrar un listado paginado de facturas emitidas con, por fila:
  serie y número, cliente, fecha de emisión, fecha de vencimiento, importe a cobrar, importe
  cobrado, saldo pendiente, estado de cobro y días de retraso cuando aplique.
- **FR-011**: El listado DEBE poder filtrarse por estado de cobro, cliente, serie, rango de
  fechas y un modo "solo vencidas", combinables entre sí.
- **FR-012**: El listado DEBE poder ordenarse al menos por fecha, por saldo pendiente y por días
  de retraso.
- **FR-013**: El listado DEBE soportar búsqueda por texto sobre número de factura y nombre del
  cliente.
- **FR-014**: El estado de cobro DEBE mostrarse de forma visualmente distinguible (pendiente /
  parcial / cobrada) y las facturas vencidas DEBEN ser identificables a simple vista.
- **FR-015**: El paginado, la búsqueda y el filtrado DEBEN resolverse en el servidor, para que el
  rendimiento no dependa del número total de facturas del tenant.
- **FR-016**: Solo DEBEN aparecer las facturas que admiten cobro según las reglas ya vigentes del
  producto: los borradores no aparecen, y una factura rectificativa nunca aparece como fila propia
  (su cobro se gestiona desde la original rectificada, con el importe efectivo tras la
  rectificación).

### Funcionales — Registrar cobro

- **FR-017**: Desde cada fila con saldo pendiente, el usuario DEBE poder registrar un cobro
  indicando importe, fecha, forma de pago y una referencia opcional, sin abandonar el listado.
- **FR-018**: La fecha DEBE proponerse por defecto como la fecha de hoy y el importe DEBE
  proponerse por defecto como el saldo pendiente de esa factura.
- **FR-019**: El sistema DEBE rechazar importes nulos, negativos o superiores al saldo pendiente,
  con un mensaje explicativo, sin registrar nada.
- **FR-020**: Tras registrar un cobro, la fila afectada y el resumen superior DEBEN reflejar el
  nuevo estado sin que el usuario tenga que recargar la pantalla, y se DEBE confirmar la acción
  con una notificación.
- **FR-021**: La acción de registrar cobro NO DEBE ofrecerse sobre facturas ya cobradas por
  completo.

### Funcionales — Historial y anulación

- **FR-022**: Desde cada fila, el usuario DEBE poder consultar el historial completo de cobros de
  esa factura (fecha, importe, forma de pago, referencia, estado vigente/anulado), ordenado del
  más reciente al más antiguo.
- **FR-023**: El usuario DEBE poder anular un cobro vigente desde ese historial.
- **FR-024**: La anulación DEBE requerir una confirmación explícita del usuario antes de
  ejecutarse.
- **FR-025**: Un cobro anulado DEBE seguir siendo visible en el historial, marcado como anulado, y
  no DEBE poder anularse otra vez.
- **FR-026**: Tras anular, el saldo pendiente, el estado de cobro de la fila y el resumen superior
  DEBEN actualizarse, con notificación de confirmación.

### Funcionales — Consulta del documento

- **FR-027**: El usuario DEBE poder ver el documento de la factura desde el listado sin abandonar
  la pantalla ni abrir una pestaña nueva.
- **FR-028**: Al cerrar la vista del documento, el listado DEBE conservar filtros, orden y página.

### Funcionales — Documentación de producto

- **FR-029**: El módulo DEBE disponer de guía contextual propia accesible desde la pantalla, que
  explique el resumen, los filtros, el registro y la anulación de cobros.
- **FR-030**: La base de conocimiento del asistente de la aplicación DEBE incluir este módulo,
  de forma que el asistente pueda explicar al usuario cómo gestionar sus cobros.

### Key Entities

- **Factura**: documento emitido a un cliente, con un importe total, una fecha de emisión y,
  opcionalmente, una fecha de vencimiento. Ya existe; esta feature no la modifica.
- **Cobro (pago)**: importe recibido contra una factura, con fecha, forma de pago y referencia
  opcional; puede estar vigente o anulado. Ya existe; esta feature no lo modifica.
- **Estado de cobro**: situación derivada de una factura respecto a lo cobrado —pendiente (nada
  cobrado), parcial (cobrado en parte), cobrada (saldo cero)—. Ya existe; se calcula, no se
  almacena como dato editable.
- **Saldo pendiente**: diferencia entre el total de la factura y la suma de sus cobros vigentes.
  Ya existe; se calcula en el servidor.
- **Resumen de cobros**: agregación de las anteriores para un periodo, sin persistencia propia.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede responder "¿cuánto me deben y quién?" en menos de 10 segundos
  desde que abre el módulo, sin aplicar ningún filtro ni abrir ninguna factura.
- **SC-002**: Registrar un cobro sobre una factura del listado requiere como máximo 3
  interacciones (abrir acciones, abrir el formulario, confirmar) y no obliga a navegar a la
  factura.
- **SC-003**: El número de pasos para localizar todas las facturas vencidas de un cliente baja de
  "revisar factura por factura" a una sola combinación de dos filtros.
- **SC-004**: El listado responde en menos de 2 segundos con 5.000 facturas en el tenant, tanto al
  paginar como al filtrar o buscar.
- **SC-005**: El 100 % de los importes mostrados (pendiente, cobrado, vencido, saldo por fila)
  coincide con lo que muestra la factura correspondiente en su propio módulo: cero discrepancias
  entre las dos pantallas.
- **SC-006**: Ningún dato de otro tenant es accesible desde el módulo, verificado con al menos dos
  tenants con facturas en ambos.
- **SC-007**: Un intento de cobro inválido (importe mayor al saldo, cero o negativo) nunca deja
  registro: 0 cobros creados en esos casos.
- **SC-008**: Añadir este módulo no cambia el comportamiento del módulo de Facturas: la suite de
  pruebas existente de facturas y pagos sigue en verde sin modificaciones.

## Assumptions

- Se reutilizan íntegramente las reglas y el almacenamiento de cobros ya existentes (registro,
  anulación, saldo pendiente, estado de cobro). **No se crean tablas ni columnas nuevas.**
- "Vencida" significa: la factura tiene fecha de vencimiento, esa fecha ya pasó respecto a hoy, y
  su saldo pendiente es mayor que cero. Las facturas sin fecha de vencimiento nunca son vencidas.
- "Días de retraso" se cuenta desde la fecha de vencimiento hasta hoy, y solo se muestra en
  facturas vencidas.
- El indicador "cobrado en el periodo" se calcula por **fecha del cobro**; "pendiente" y "vencido"
  son fotos del estado actual y no dependen del rango elegido (FR-009).
- Los cobros anulados no cuentan para ningún importe ni para el estado de cobro, pero siguen
  siendo visibles en el historial por trazabilidad.
- El rango de fechas por defecto al abrir la pantalla es el mes en curso, coherente con el resto
  de pantallas con rango de fechas del producto.
- El importe de referencia de cada fila es el **importe efectivo a cobrar** de la factura, que en
  facturas rectificadas no coincide con su total bruto. Esa regla ya existe y no se redefine aquí.
- El módulo cubre cobros a clientes (facturas emitidas). Los pagos a proveedores (compras) quedan
  **fuera de alcance**.
- Los cobros registrados desde el TPV mediante tickets siguen su propio circuito y no se gestionan
  desde esta pantalla; si una factura tiene cobros de ese origen, se reflejan en su saldo igual
  que hoy.
- La exportación del listado a Excel/CSV queda **fuera de alcance** de esta feature.
- La conciliación bancaria, los recordatorios automáticos de impago y los planes de pago
  fraccionado quedan **fuera de alcance**.
- El módulo es de consulta y gestión desde escritorio; debe ser utilizable en pantallas pequeñas,
  pero no se diseña como pantalla mobile-first.
