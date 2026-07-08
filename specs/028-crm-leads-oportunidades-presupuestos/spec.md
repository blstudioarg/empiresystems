# Feature Specification: Gestión de Clientes CRM — Leads, Oportunidades y Presupuestos

**Feature Branch**: `028-crm-leads-oportunidades-presupuestos`

**Created**: 2026-07-08

**Status**: Draft

**Input**: User description: "Gestión de Clientes CRM (Kit Digital categoría 2): Leads, Oportunidades y Presupuestos — cerrar el gap de homologación de la categoría 'Gestión de Clientes' documentado en `docs/06-kit-digital.md` (líneas 52-64): captación de leads con importación por fichero y reglas de asignación, pipeline de oportunidades, y presupuestos que reutilizan el motor de facturación existente y se convierten en factura sin reintroducir importes."

## Objetivo y contexto

El producto ya cubre la vía de homologación de **Factura Electrónica** del Kit Digital. Esta
feature construye la **segunda vía de homologación** —la categoría *"Gestión de Clientes"*— cuyos
requisitos mínimos oficiales (recogidos en `docs/06-kit-digital.md`) exigen: gestión de leads con
alta manual **e importación por fichero** + reglas de asignación, gestión de oportunidades con
**ofertas/presupuestos**, y gestión documental de la actividad comercial. Los tres bloques forman
el embudo comercial previo a la factura: **Lead → Oportunidad → Presupuesto → (aceptación) → Factura**.

El principio rector es **no duplicar el motor de facturación**: un presupuesto tiene las mismas
líneas que una factura (artículo, cantidad, precio, régimen impositivo, descuentos) y sus totales
se calculan con la misma lógica de backend, pero **no tiene efectos fiscales** (no consume
numeración de serie de factura, no entra en el encadenamiento Verifactu, no es inmutable mientras
esté abierto). Convertir un presupuesto aceptado en factura reutiliza el proceso de emisión ya
existente sin volver a teclear importes.

## Clarifications

### Session 2026-07-08

- Q: Ante un lead entrante (manual o importado) con email/teléfono ya existente en el tenant, ¿qué hace el sistema? → A: Rechazar la fila/alta como duplicado e informar del motivo; el usuario decide manualmente qué hacer (no hay fusión automática).
- Q: ¿Qué estrategias de asignación automática de leads debe soportar el sistema? → A: Solo dos — asignación manual y reparto equitativo (round-robin) entre un conjunto de comerciales. Zona geográfica y carga de trabajo quedan fuera de alcance.
- Q: ¿Cómo se registra la aceptación/rechazo de un presupuesto en esta versión? → A: Registro interno — un usuario del tenant marca el estado tras la respuesta del cliente. Sin portal público de aceptación (mejora futura).
- Q: Al convertir un presupuesto aceptado en factura, ¿en qué estado nace la factura? → A: Como borrador editable; la numeración correlativa y el encadenamiento Verifactu se consumen al emitirla luego con el proceso normal, no en la conversión.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Captación y gestión de leads (Priority: P1)

Un comercial del tenant registra contactos potenciales (leads) que todavía no son clientes: bien
uno a uno desde un formulario, bien de golpe importando un fichero CSV/Excel con muchos contactos
(por ejemplo, los asistentes de una feria o una lista comprada). Cada lead entrante se **asigna a
un usuario responsable** según las reglas configuradas por el tenant, para que ningún contacto
quede sin dueño. El comercial trabaja su lista de leads asignados, actualiza su estado (nuevo,
contactado, cualificado, descartado) y registra notas de la actividad comercial.

**Why this priority**: Es el requisito funcional más señalado del gap de homologación ("Gestión de
Leads: alta manual o importación por fichero + reglas de asignación") y la entrada del embudo: sin
leads no hay oportunidades ni presupuestos. Entrega valor por sí sola como agenda comercial aunque
no se construyan las otras dos historias.

**Independent Test**: Se puede probar de forma aislada dando de alta un lead manualmente y
subiendo un CSV de varios leads; verificar que se crean todos con `tenant_id` correcto, que cada
uno queda asignado a un usuario según la regla activa, que un lead con datos inválidos en el
fichero se reporta como fila rechazada sin abortar el resto, y que los leads de un tenant no son
visibles desde otro.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado de un tenant, **When** da de alta un lead con nombre y un dato
   de contacto (email o teléfono), **Then** el lead se guarda con estado inicial "nuevo", asociado
   al `tenant_id` activo y asignado a un responsable según la regla de asignación vigente.
2. **Given** un fichero CSV/Excel con N filas de contactos, **When** el usuario lo importa, **Then**
   el sistema crea un lead por cada fila válida, informa del total importado y lista las filas
   rechazadas con el motivo (dato obligatorio ausente, formato inválido, duplicado), sin perder las
   filas válidas por culpa de las inválidas.
3. **Given** una regla de asignación "reparto equitativo entre los comerciales A, B y C", **When**
   se importan 9 leads, **Then** cada comercial recibe aproximadamente 3 leads.
4. **Given** un lead existente, **When** su responsable cambia el estado a "cualificado", **Then**
   el cambio queda registrado con autor y fecha y el lead aparece como candidato a abrir oportunidad.
5. **Given** dos tenants distintos, **When** un usuario del tenant A lista sus leads, **Then** no ve
   ningún lead del tenant B.

---

### User Story 2 - Oportunidades y pipeline comercial (Priority: P2)

A partir de un lead cualificado (o de un cliente existente que pide una nueva oferta), el comercial
abre una **oportunidad**: una posible venta en curso con un importe estimado y una etapa dentro del
embudo (nueva → en negociación → ganada / perdida). El comercial mueve la oportunidad por las etapas
a medida que avanza la negociación y la vincula con los presupuestos que va generando. Cuando la
oportunidad se marca como **ganada**, si nació de un lead, ese lead se **convierte en cliente**.

**Why this priority**: Es el segundo requisito del gap ("Gestión de oportunidades: ofertas y
presupuestos al lead/cliente") y el puente entre el lead y el presupuesto/factura. Aporta la visión
de pipeline (cuánto hay en negociación, qué se ganó/perdió) que el Kit Digital pide mostrar también
en formato gráfico. Depende conceptualmente de que exista una entidad captadora (lead o cliente),
pero es testeable de forma independiente partiendo de un cliente ya existente.

**Independent Test**: Partiendo de un cliente existente, abrir una oportunidad, moverla por las
etapas del pipeline y marcarla como ganada; verificar que la transición de estados se registra, que
al ganarla desde un lead ese lead pasa a cliente, y que el aislamiento por tenant se mantiene.

**Acceptance Scenarios**:

1. **Given** un lead cualificado, **When** el comercial abre una oportunidad desde él, **Then** se
   crea una oportunidad vinculada a ese lead, en etapa "nueva", con importe estimado opcional y
   responsable heredado del lead.
2. **Given** un cliente existente, **When** el comercial abre una oportunidad desde la ficha del
   cliente, **Then** la oportunidad queda vinculada al cliente (no a un lead).
3. **Given** una oportunidad en negociación, **When** el comercial la marca como "ganada", **Then**
   la oportunidad queda cerrada como ganada, con fecha de cierre; si estaba vinculada a un lead, ese
   lead se convierte en cliente conservando sus datos y su historial.
4. **Given** una oportunidad, **When** el comercial la marca como "perdida", **Then** se cierra como
   perdida con un motivo de pérdida y ya no admite nuevos presupuestos.
5. **Given** una oportunidad con varios presupuestos, **When** se consulta su ficha, **Then** se ven
   todos los presupuestos asociados con su estado.

---

### User Story 3 - Presupuestos y conversión a factura (Priority: P1)

El comercial genera un **presupuesto** (oferta) para una oportunidad o directamente para un
cliente/lead: añade líneas de artículos con cantidad, precio y régimen impositivo igual que en una
factura, y el sistema calcula bases, impuestos y total en el backend. El presupuesto se puede editar
mientras está en borrador, enviar al cliente (queda en estado "enviado"), y el cliente lo acepta o
rechaza. Un presupuesto **aceptado** se **convierte en factura** con un clic, arrastrando todas sus
líneas e importes al proceso de emisión ya existente, sin reintroducir nada a mano.

**Why this priority**: Es el entregable de mayor valor de negocio y el que cierra el embudo con el
módulo de facturación que ya existe. Junto con los leads (US1) forma el MVP demostrable de la
categoría. El presupuesto reutiliza el motor de líneas/importes de facturas, así que gran parte de
la lógica crítica ya está construida y probada.

**Independent Test**: Crear un presupuesto con varias líneas de artículos con distintos tipos
impositivos, comprobar que los totales calculados coinciden con los que daría una factura con esas
mismas líneas, marcarlo como aceptado, convertirlo a factura y verificar que la factura resultante
tiene exactamente las mismas líneas e importes y que consume numeración de factura (no antes).

**Acceptance Scenarios**:

1. **Given** una oportunidad abierta, **When** el comercial crea un presupuesto con líneas de
   artículos, **Then** el sistema calcula base, desglose de impuestos por tipo y total en el backend
   con la misma lógica que una factura, y el presupuesto queda en estado "borrador" **sin** consumir
   numeración de serie de factura ni entrar en el encadenamiento Verifactu.
2. **Given** un presupuesto en borrador, **When** el comercial lo edita (añade/quita líneas, cambia
   cantidades), **Then** los totales se recalculan en el backend y el presupuesto sigue editable.
3. **Given** un presupuesto terminado, **When** el comercial lo marca como "enviado", **Then** el
   presupuesto deja de ser un borrador editable libremente y queda a la espera de respuesta del
   cliente, con su fecha de envío y su fecha de validez.
4. **Given** un presupuesto enviado, **When** el cliente lo acepta, **Then** el presupuesto pasa a
   estado "aceptado" y habilita la acción de convertir a factura; si la oportunidad estaba abierta,
   se marca como ganada.
5. **Given** un presupuesto aceptado, **When** el comercial lo convierte a factura, **Then** se crea
   una factura **en borrador** con exactamente las mismas líneas e importes que el presupuesto,
   enlazada al presupuesto de origen, y el presupuesto queda marcado como "facturado" (no se puede
   facturar dos veces); la numeración correlativa y Verifactu se consumen al emitir esa factura
   después, con el proceso de emisión existente.
6. **Given** un presupuesto rechazado o caducado, **When** el comercial lo consulta, **Then** no
   ofrece la acción de convertir a factura.

---

### Edge Cases

- **Importación de leads con fichero mal formado** (columnas faltantes, codificación distinta,
  separador equivocado): el sistema rechaza el fichero completo con un mensaje claro, sin crear
  leads a medias, o bien importa lo válido reportando lo rechazado — comportamiento definido, nunca
  una importación parcial silenciosa.
- **Leads duplicados** (mismo email/teléfono ya existente en el tenant): el sistema detecta el
  duplicado en el alta manual y en la importación y lo marca como fila rechazada o lo fusiona según
  política, sin crear silenciosamente dos leads idénticos.
- **Regla de asignación sin comerciales activos** (todos de baja o rol quitado): el lead entrante
  queda sin asignar y visible en una bandeja de "sin asignar", nunca perdido.
- **Conversión de lead a cliente cuando ya existe un cliente con el mismo identificador fiscal**: el
  sistema advierte del posible duplicado en vez de crear un cliente duplicado.
- **Convertir a factura un presupuesto cuyo artículo cambió de precio o fue dado de baja** desde que
  se creó el presupuesto: la factura usa los importes **congelados en el presupuesto**, no relee el
  catálogo (el importe ofertado al cliente es el que se factura).
- **Presupuesto con artículo producto sin stock**: el presupuesto no mueve inventario (no es una
  operación de stock); solo la factura/venta resultante afecta al stock según las reglas ya
  existentes.
- **Intento de editar un presupuesto ya facturado**: bloqueado; un presupuesto facturado es
  histórico y solo se consulta.
- **Cierre de oportunidad con presupuestos aún en borrador**: al marcarla ganada/perdida, el sistema
  define qué ocurre con los presupuestos abiertos (quedan como estaban / se marcan caducados), sin
  dejar estados incoherentes.

## Requirements *(mandatory)*

### Functional Requirements

**Leads**

- **FR-001**: El sistema DEBE permitir dar de alta un lead con, como mínimo, un nombre y un dato de
  contacto (email o teléfono), asociado automáticamente al `tenant_id` activo.
- **FR-002**: El sistema DEBE permitir importar leads de forma masiva desde un fichero CSV y Excel,
  creando un lead por fila válida.
- **FR-003**: En la importación, el sistema DEBE validar cada fila y, ante filas inválidas
  (obligatorios ausentes, formato incorrecto, duplicados), DEBE rechazar solo esas filas e informar
  del motivo, sin descartar las filas válidas.
- **FR-004**: El sistema DEBE detectar leads duplicados dentro del mismo tenant (por email o
  teléfono) tanto en alta manual como en importación y **rechazarlos** (rechazo del alta / de la
  fila) informando del motivo "duplicado"; NO fusiona automáticamente ni crea duplicados
  silenciosos. La resolución (fusionar, descartar, editar) queda a decisión manual del usuario.
- **FR-005**: El sistema DEBE asignar cada lead entrante a un usuario responsable del tenant según
  una **regla de asignación configurable** con exactamente dos estrategias: (a) asignación manual y
  (b) reparto equitativo (round-robin) entre un conjunto de comerciales elegido por el tenant. El
  reparto por zona geográfica o por carga de trabajo queda **fuera de alcance** de esta versión.
- **FR-006**: Cuando ninguna regla puede asignar un responsable, el lead DEBE quedar en estado "sin
  asignar" y ser visible/recuperable, nunca descartado.
- **FR-007**: El sistema DEBE permitir cambiar el estado de un lead a lo largo de su ciclo (p. ej.
  nuevo, contactado, cualificado, descartado) y registrar quién y cuándo hizo el cambio.
- **FR-008**: El sistema DEBE permitir registrar notas/actividad comercial asociadas a un lead.
- **FR-009**: El sistema DEBE convertir un lead en cliente conservando sus datos y su historial
  comercial, sin perder la trazabilidad de que ese cliente vino de un lead.

**Oportunidades**

- **FR-010**: El sistema DEBE permitir crear una oportunidad vinculada **o** a un lead **o** a un
  cliente existente, con `tenant_id` activo, importe estimado opcional y un responsable.
- **FR-011**: El sistema DEBE gestionar las oportunidades mediante un pipeline de etapas (como
  mínimo: nueva, en negociación, ganada, perdida) y registrar cada transición con autor y fecha.
- **FR-012**: Al marcar una oportunidad como "ganada", si está vinculada a un lead, el sistema DEBE
  convertir ese lead en cliente (FR-009).
- **FR-013**: Al marcar una oportunidad como "perdida", el sistema DEBE registrar un motivo de
  pérdida y no admitir nuevos presupuestos sobre ella.
- **FR-014**: El sistema DEBE mostrar, para cada oportunidad, los presupuestos asociados con su
  estado, y una vista de pipeline agregada por etapa (recuento e importe estimado) para el tenant.

**Presupuestos**

- **FR-015**: El sistema DEBE permitir crear un presupuesto con líneas de artículos (artículo,
  cantidad, precio, descuento, régimen impositivo) vinculado a una oportunidad y/o a un cliente/lead.
- **FR-016**: El sistema DEBE calcular en el backend las bases, el desglose de impuestos por tipo y
  el total del presupuesto **reutilizando la misma lógica de cálculo que las facturas**, respetando
  el régimen impositivo (IVA/IGIC/IPSI) y sin que el cliente sea fuente de verdad de ningún importe.
- **FR-017**: Un presupuesto NO DEBE consumir numeración de serie de factura ni entrar en el
  encadenamiento/huella Verifactu; DEBE tener su propia numeración/identificación de presupuesto y
  carecer de efectos fiscales mientras no se convierta en factura.
- **FR-018**: El sistema DEBE permitir editar un presupuesto mientras esté en estado borrador y
  recalcular sus totales en el backend en cada cambio.
- **FR-019**: El sistema DEBE gestionar el ciclo de vida del presupuesto (como mínimo: borrador,
  enviado, aceptado, rechazado, caducado, facturado) con sus fechas relevantes (envío, validez). La
  transición a aceptado/rechazado la registra **un usuario interno del tenant** marcando el estado;
  un portal público de aceptación por el cliente final queda fuera de alcance de esta versión.
- **FR-020**: Al aceptar un presupuesto, el sistema DEBE habilitar su conversión a factura y, si su
  oportunidad estaba abierta, marcarla como ganada.
- **FR-021**: El sistema DEBE convertir un presupuesto aceptado en factura creándola en estado
  **borrador** con las líneas e importes **congelados en el presupuesto** (no releídos del
  catálogo) y enlazada al presupuesto de origen. La numeración correlativa de serie y el
  encadenamiento/huella Verifactu se consumen al **emitir** esa factura con el proceso de emisión
  existente, no en el momento de la conversión (Principios II y III).
- **FR-022**: Un presupuesto solo DEBE poder convertirse en factura una vez; tras facturarlo, queda
  en estado "facturado", es de solo lectura y no ofrece de nuevo la conversión.
- **FR-023**: El sistema NO DEBE mover inventario al crear/aceptar un presupuesto; el efecto sobre
  stock, si aplica, ocurre únicamente al emitir la factura resultante según las reglas ya existentes.

**Transversales (multi-tenant, datos personales, permisos)**

- **FR-024**: Leads, oportunidades y presupuestos (y sus líneas/notas) DEBEN llevar `tenant_id`
  indexado y estar cubiertos por el global scope de tenant; ninguna consulta puede cruzar tenants.
- **FR-025**: Los leads contienen datos personales de contacto; el sistema DEBE aplicar el patrón de
  retención/purga configurable por tenant ya existente en el proyecto (minimización RGPD/LOPDGDD),
  al menos para leads descartados/no convertidos.
- **FR-026**: El acceso a leads, oportunidades y presupuestos DEBE respetar el sistema de roles y
  permisos por tenant existente (feature 027): visibilidad de "mis leads" vs. todos según el rol.

### Key Entities *(include if feature involves data)*

- **Lead**: contacto potencial aún no cliente, con `tenant_id`, datos de contacto (nombre, email,
  teléfono, empresa), estado del ciclo, responsable asignado, origen (manual/importación), y enlace
  al cliente si se convirtió. Contiene datos personales sujetos a retención.
- **Nota/Actividad de lead**: registro cronológico de interacciones comerciales sobre un lead
  (llamada, email, reunión), con autor y fecha.
- **Regla de asignación**: configuración por tenant que determina a qué usuario se asigna un lead
  entrante (manual, reparto equitativo entre un conjunto de comerciales).
- **Oportunidad**: posible venta en curso, con `tenant_id`, vínculo a un lead **o** a un cliente,
  etapa del pipeline, importe estimado, responsable, fechas de apertura/cierre y motivo de pérdida.
  Relaciona los presupuestos generados.
- **Presupuesto**: oferta comercial con `tenant_id`, vínculo a oportunidad y/o cliente/lead, estado
  del ciclo de vida, numeración propia (no fiscal), fechas de emisión/validez/envío, totales
  calculados en backend, y enlace a la factura resultante si se convirtió.
- **Línea de presupuesto**: línea de la oferta (artículo, cantidad, precio, descuento, régimen
  impositivo, importes) — equivalente conceptual a una línea de factura, reutilizando su cálculo.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un comercial puede importar un fichero de 500 leads y obtener el recuento de
  importados y el listado de filas rechazadas con su motivo en una sola operación.
- **SC-002**: El 100 % de los leads entrantes queda con un responsable asignado o, si no es posible,
  visible en la bandeja de "sin asignar"; ninguno se pierde.
- **SC-003**: Los totales (base, impuestos por tipo, total) de un presupuesto coinciden al céntimo
  con los de una factura creada con las mismas líneas, para los tres regímenes impositivos
  (IVA/IGIC/IPSI).
- **SC-004**: Convertir un presupuesto aceptado en factura reproduce exactamente sus líneas e
  importes sin ninguna reintroducción manual, y la factura resultante es la primera vez que se
  consume numeración correlativa para esa venta.
- **SC-005**: Un presupuesto no puede facturarse dos veces ni editarse tras facturarse (0 casos de
  doble facturación en pruebas de concurrencia).
- **SC-006**: Ningún lead, oportunidad o presupuesto de un tenant es accesible desde otro tenant
  (tests de no-fuga con ≥2 tenants).
- **SC-007**: La categoría "Gestión de Clientes" del Kit Digital queda cubierta en sus requisitos
  funcionales mínimos de leads (alta + importación + asignación), oportunidades (con presupuestos) y
  gestión documental de la actividad comercial.

## Assumptions

- **Reutilización del motor de facturación**: existe ya una lógica de líneas y cálculo de importes
  de facturas (bases, desglose de impuestos por `regimen_impositivo`, descuentos, total) y un
  proceso de emisión; esta feature la reutiliza para presupuestos en vez de duplicarla. El
  presupuesto es un documento no fiscal previo; la factura sigue siendo la única pieza con
  numeración correlativa, inmutabilidad y encadenamiento Verifactu.
- **Cliente y catálogo existentes**: las entidades Cliente y Artículo ya existen y se reutilizan; el
  lead es una entidad nueva y separada del cliente, que se convierte en cliente al ganar.
- **Roles y permisos**: se reutiliza el sistema de roles por tenant de la feature 027 para acotar la
  visibilidad (mis leads vs. todos). No se define un sistema de permisos nuevo.
- **Retención de datos personales**: se reutiliza el patrón `RetencionLogsTenant` + comando de purga
  programado (feature 021) para la minimización de leads, sin inventar mecanismo nuevo.
- **Aceptación del cliente**: en esta versión la aceptación/rechazo de un presupuesto la registra un
  usuario interno del tenant (marca el estado); un portal público de aceptación por parte del
  cliente final queda **fuera de alcance** de esta versión (posible mejora futura).
- **Firma electrónica / envío por email del presupuesto**: el envío puede reutilizar el mailer por
  tenant existente (feature 017); la firma electrónica del presupuesto queda fuera de alcance.
- **Alertas gráficas / API**: el dashboard de pipeline cubre la "alerta gráfica" pedida por el Kit
  Digital de forma básica; la exposición de API/Web Services de integración (otro gap del doc 06) se
  trata como feature separada, no en este alcance.
- **Hosting compartido**: importación de ficheros, cálculo de importes y conversión a factura son
  operaciones síncronas puntuales viables en hosting compartido (Principio V); no se introducen
  colas persistentes ni workers dedicados para el volumen pyme objetivo.
