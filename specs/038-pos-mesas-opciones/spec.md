# Feature Specification: POS con mesas y opciones de artículo (hostelería)

**Feature Branch**: `038-pos-mesas-opciones`

**Created**: 2026-08-09

**Status**: Draft

**Input**: User description: "POS con mesas y opciones de artículo (hostelería). Inspirado en GoTPV (gotpv.es). Fase 1: zonas y mesas en grid ordenado (no plano drag & drop), cuentas abiertas en tabla propia, transferir/unir cuentas, suplemento por zona, y opciones/modificadores de artículo organizadas en grupos con reglas de selección, precio por artículo y producto vinculado opcional. Adaptar la vista de crear ticket sin agrandar la botonera ni romper la geometría sticky existente."

## Contexto y origen

El POS actual solo emite tickets de venta directa: se arma el ticket, se cobra y se emite. No existe forma de dejar una venta a medias, de asociarla a una mesa, ni de indicar cómo se prepara un plato.

Para un local de hostelería eso es un bloqueo operativo: no se puede atender dos mesas a la vez. Esta feature cubre ese hueco tomando como referencia GoTPV, un TPV español de hostelería que el cliente objetivo valora, con dos desviaciones **deliberadas** respecto a él (ambas decisión explícita del usuario, registradas en Assumptions):

- las mesas se listan en un **grid ordenado por zona**, no en un plano del local con posiciones libres;
- las opciones de artículo se organizan en **grupos con reglas de selección**, no en un pool plano.

### Convenciones de front que condicionan esta spec

Leídas antes de redactar, según la REGLA DE ORO de `CLAUDE.md`. Cada una se cita aquí para que quede trazable que condicionó el alcance, no solo la implementación:

- `docs/04-front-guidelines.md` § **"Catálogo del POS (TPV): filtros de categoría y badge de stock"** — el selector de zonas de la pantalla Sala reutiliza el componente de filtros ya existente (botones grandes táctiles con badge de conteo y estado activo en el color del tenant). No se diseña un selector de zonas nuevo.
- `docs/04-front-guidelines.md` § **"Listados: SIEMPRE DataTable, nunca una `<table>` plana"** — el listado del catálogo de opciones y el de grupos son DataTables.
- `docs/04-front-guidelines.md` § **"CRUD simple: alta/edición en modal + AJAX (patrón por defecto)"** — el alta/edición de opciones y de grupos usa un único modal reutilizado, no páginas dedicadas.
- `docs/04-front-guidelines.md` § **"Select dinámico con CRUD inline (catálogos)"** — la asignación de opciones a un artículo usa un control único de búsqueda/creación inline. Esto **reemplaza explícitamente** el doble-listbox con botón `>>` de GoTPV, inusable en tablet.
- `docs/04-front-guidelines.md` § **"Modales: siempre centrados verticalmente"**, § **"Confirmación de acciones irreversibles"**, § **"Notificaciones"** (toastr, nunca alerts ad-hoc), § **"Estado de carga en botones (AJAX/fetch)"**.
- `docs/04-front-guidelines.md` § **"Nueva entrada de menú ⇒ nuevo permiso (obligatorio)"** — Sala y Opciones son entradas nuevas del menú y por tanto exigen permisos propios (`ver-pos-sala`, `ver-pos-opciones`), alta en el catálogo de permisos y en el catálogo de menú, y `can:` en las rutas.
- `docs/04-front-guidelines.md` § **"Ayuda contextual"** — Sala y Opciones son pantallas nuevas y necesitan su guía in-app; la guía de "Crear ticket" ya existe y cambia, así que se actualiza en el mismo cambio.
- `.specify/memory/constitution.md` — Principio I (tenant_id + global scope + tests de aislamiento), Principio II (inmutabilidad de la factura emitida, numeración correlativa sin huecos, régimen impositivo parametrizado), Principio III (todo importe se calcula en servidor), Principio IV (test-first en aislamiento y cálculo), Principio V (simplicidad; el plano drag & drop se descarta por YAGNI).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Atender varias mesas a la vez (Priority: P1)

Un camarero abre el TPV y ve la sala. Toca la mesa 4, le carga dos cañas y una tapa, y guarda la cuenta sin cobrar. Vuelve a la sala, atiende la mesa 7 igual. Más tarde la mesa 4 pide la cuenta: la toca, ve lo consumido, añade un café y cobra. Al cobrar, la mesa vuelve a quedar libre y se emite un ticket exactamente igual que hoy.

**Why this priority**: es el bloqueo operativo que hace inviable el POS actual en hostelería. Sin esto, ninguna otra parte de la feature tiene sentido. Entregado solo, ya es un TPV de bar usable.

**Independent Test**: se puede probar de punta a punta sin opciones de artículo, sin transferencias y con una sola zona: abrir dos cuentas simultáneas, guardarlas, recuperarlas y cobrar una.

**Acceptance Scenarios**:

1. **Given** una mesa libre, **When** el camarero la toca y añade artículos, **Then** la mesa pasa a ocupada y muestra el importe acumulado.
2. **Given** una cuenta con artículos, **When** el camarero guarda y vuelve a la sala, **Then** el consumo persiste y sigue ahí al volver a tocar esa mesa.
3. **Given** una cuenta abierta, **When** el camarero la cobra, **Then** se emite un ticket simplificado con numeración correlativa y la mesa queda libre.
4. **Given** una cuenta abierta, **When** el camarero la anula (vacía sin cobrar), **Then** el sistema pide confirmación explícita, la mesa queda libre y **no** se emite ningún documento.
5. **Given** una cuenta abierta que nunca se cobró, **When** se consulta el listado de tickets emitidos, **Then** esa cuenta **no** aparece, porque no es una venta.
6. **Given** un camarero sin mesa asignada, **When** vende en barra/mostrador, **Then** puede seguir emitiendo un ticket directo sin pasar por la sala, exactamente como hoy.

---

### User Story 2 - Definir las opciones de los platos (Priority: P2)

El encargado entra a POS → Opciones. Crea el grupo "Punto de cocción" (elegir exactamente 1, obligatorio) con las opciones "Poco hecho", "Al punto", "Muy hecho", y el grupo "Extras" (elegir varias, opcional) con "Extra queso" (+1,00 €) y "Sin patatas" (+0,00 €). Después abre el artículo "Solomillo" y le asigna ambos grupos, ajustando el precio de "Extra queso" a 1,50 € solo para ese plato. A "Extra queso" además le vincula el artículo "Queso rallado" del catálogo, para que consumir el extra descuente inventario.

**Why this priority**: es prerrequisito de la historia 3 y aporta valor propio (el local deja documentado su recetario de opciones). Entregada sola es configurable y verificable, aunque todavía no se venda con ella.

**Independent Test**: crear grupos y opciones, asignarlos a un artículo y comprobar que persisten y que el precio del pivot es independiente del precio por defecto de la opción.

**Acceptance Scenarios**:

1. **Given** el catálogo de opciones vacío, **When** el encargado crea un grupo con su regla de selección, **Then** el grupo queda disponible para asignar a cualquier artículo.
2. **Given** una opción con precio por defecto, **When** se asigna a un artículo y se le cambia el precio, **Then** el precio por defecto de la opción y el de los demás artículos no cambian.
3. **Given** una opción asignada a uno o más artículos, **When** se intenta eliminarla del catálogo, **Then** el sistema avisa de en cuántos artículos está en uso y pide confirmación explícita.
4. **Given** un grupo "elegir exactamente 1, obligatorio", **When** se intenta guardarlo con menos de una opción, **Then** el sistema lo rechaza con un mensaje claro.
5. **Given** un artículo con opciones asignadas, **When** el encargado reordena la lista, **Then** ese orden es el que verá el camarero al vender.

---

### User Story 3 - Vender un plato con sus opciones (Priority: P3)

El camarero toca "Solomillo" en el catálogo. En vez de añadirse directo, se abre una pantalla táctil con "Punto de cocción" y "Extras". Elige "Al punto" y "Extra queso", ve que la línea pasa de 18,00 € a 19,50 € y confirma. La línea del ticket muestra el plato con sus opciones debajo. Cuando toca una caña —que no tiene opciones— se añade directo, sin ningún paso extra.

**Why this priority**: es donde la configuración de la historia 2 se convierte en valor real (comanda correcta, suplementos cobrados). Depende de la 2, por eso va después.

**Independent Test**: vender un artículo con opciones obligatorias y otro sin opciones en el mismo ticket, y comprobar el importe y el detalle de la línea.

**Acceptance Scenarios**:

1. **Given** un artículo sin opciones asignadas, **When** el camarero lo toca, **Then** se añade directamente al ticket sin ningún paso intermedio.
2. **Given** un artículo con un grupo obligatorio, **When** el camarero intenta confirmar sin elegir en ese grupo, **Then** el sistema no permite confirmar e indica qué falta.
3. **Given** un grupo "elegir exactamente 1", **When** el camarero toca una segunda opción del grupo, **Then** la anterior se deselecciona automáticamente.
4. **Given** opciones con suplemento elegidas, **When** se confirma la línea, **Then** el importe de la línea y el total del ticket incluyen los suplementos, calculados por el servidor.
5. **Given** dos unidades del mismo plato con opciones distintas, **When** se añaden al ticket, **Then** aparecen como dos líneas separadas y no se agrupan en una sola de cantidad 2.
6. **Given** una línea ya añadida con opciones, **When** el camarero la toca, **Then** puede editar sus opciones sin borrarla y volver a crearla.

---

### User Story 4 - Mover y juntar cuentas (Priority: P4)

Un cliente que estaba tomando algo en la barra se sienta en la terraza: el camarero pasa la cuenta de barra a la mesa 12. Más tarde dos grupos de mesas contiguas piden pagar juntos: une la cuenta de la mesa 3 con la de la 4.

**Why this priority**: es de uso diario en un bar, pero el TPV ya es utilizable sin ello (se puede rehacer la cuenta a mano). Va después del núcleo.

**Independent Test**: con dos cuentas abiertas, transferir una a otra mesa y unir dos, comprobando importes y estados de mesa resultantes.

**Acceptance Scenarios**:

1. **Given** una cuenta abierta en una mesa, **When** se transfiere a una mesa libre, **Then** la mesa origen queda libre y la destino ocupada con el mismo consumo e importe.
2. **Given** dos mesas ocupadas, **When** se unen, **Then** la cuenta resultante contiene todas las líneas de ambas, el importe es la suma y una de las dos mesas queda libre.
3. **Given** una cuenta abierta, **When** se intenta transferirla a una mesa ya ocupada, **Then** el sistema ofrece unir las cuentas en vez de sobrescribir, y nunca pierde consumo.
4. **Given** una transferencia o unión, **When** se consulta el historial de esa cuenta, **Then** queda registrado quién la hizo y cuándo.

---

### User Story 5 - Cobrar distinto según la zona (Priority: P5)

El local cobra un 10 % más en terraza. El encargado configura ese suplemento en la zona; al cobrar una cuenta de una mesa de terraza el importe lo refleja automáticamente y el cliente lo ve desglosado en el ticket.

**Why this priority**: diferenciador comercial real pero prescindible en el arranque; la mayoría de locales empiezan sin suplemento. Es además la parte con más impacto fiscal, así que conviene aislarla al final.

**Independent Test**: configurar una zona con suplemento y otra sin él, cobrar una cuenta en cada una y comparar importes y desglose impositivo.

**Acceptance Scenarios**:

1. **Given** una zona sin suplemento, **When** se cobra una cuenta de esa zona, **Then** el importe es idéntico al del mismo consumo en venta directa.
2. **Given** una zona con suplemento, **When** se cobra una cuenta de esa zona, **Then** el suplemento aparece identificado en el ticket y no como un aumento silencioso de precios.
3. **Given** una cuenta que se transfiere de una zona sin suplemento a una con suplemento, **When** se cobra, **Then** se aplica el suplemento de la zona **en la que se cobra**.

---

### Edge Cases

- **Cuenta que supera el tope legal de la factura simplificada**: una cuenta puede crecer sin límite mientras está abierta, pero al cobrar rige el mismo tope que hoy; el sistema debe avisar **antes** de intentar cobrar, mostrando el aviso en la propia cuenta cuando se supera, no solo al pulsar Cobrar.
- **Dos dispositivos abren la misma mesa a la vez**: el segundo debe recibir un aviso claro de que la cuenta fue modificada y no puede pisar en silencio los cambios del primero.
- **Artículo eliminado o desactivado del catálogo mientras está en una cuenta abierta**: la línea ya cargada conserva su concepto y precio; la cuenta sigue siendo cobrable.
- **Opción eliminada del catálogo mientras está en una cuenta abierta**: igual que arriba, la línea conserva lo elegido y su suplemento.
- **Mesa eliminada teniendo una cuenta abierta**: no se permite eliminarla hasta cobrar o anular la cuenta.
- **Zona eliminada con mesas dentro**: no se permite; primero hay que mover o eliminar las mesas.
- **Cuenta abierta durante días** (se olvidó cerrar): la tarjeta de mesa la marca visualmente y sigue siendo cobrable o anulable; el sistema nunca la borra solo.
- **Cambio del suplemento de una zona con cuentas abiertas**: rige el suplemento vigente **en el momento del cobro**.
- **Artículo sin stock con opciones**: se puede vender igual, como hoy (el POS permite stock negativo).
- **Grupo obligatorio añadido a un artículo que ya está en cuentas abiertas**: las líneas existentes no se invalidan; la regla aplica a las líneas nuevas.

## Requirements *(mandatory)*

### Functional Requirements

#### Zonas y mesas

- **FR-001**: El sistema MUST permitir gestionar zonas del local (crear, editar, eliminar, ordenar), cada una con nombre y un suplemento de precio opcional.
- **FR-002**: El sistema MUST permitir gestionar mesas (crear, editar, eliminar, ordenar), cada una perteneciente a exactamente una zona y con un nombre único dentro de su zona.
- **FR-003**: El sistema MUST ofrecer una pantalla "Sala" que muestre las mesas agrupadas por zona en un **grid ordenado automáticamente**; NO debe permitir posicionar libremente las mesas sobre un plano.
- **FR-004**: La pantalla Sala MUST permitir filtrar por zona mediante el mismo componente de filtros táctiles ya usado en el catálogo del POS, incluyendo un filtro "Todas" y el conteo de mesas por zona.
- **FR-005**: Cada mesa MUST mostrar su nombre, su estado (libre u ocupada) y, si está ocupada, el importe acumulado y el tiempo transcurrido desde que se abrió la cuenta.
- **FR-006**: Una mesa ocupada durante más tiempo que un umbral configurable por tenant MUST distinguirse visualmente de una recién abierta.
- **FR-007**: El sistema MUST impedir eliminar una zona con mesas asociadas, o una mesa con una cuenta abierta, informando el motivo.

#### Cuentas abiertas

- **FR-008**: El sistema MUST permitir mantener varias cuentas abiertas simultáneamente, cada una asociada opcionalmente a una mesa.
- **FR-009**: Una cuenta abierta MUST NOT consumir numeración de factura ni serie, MUST NOT aparecer en el listado de tickets emitidos, y MUST NOT generar registro Verifactu hasta que se cobre.
- **FR-010**: El sistema MUST permitir guardar una cuenta y recuperarla más tarde con todo su contenido (líneas, cantidades, opciones elegidas, receptor y comensales).
- **FR-011**: Al cobrar una cuenta, el sistema MUST emitir un ticket simplificado por el mismo flujo de emisión que la venta directa actual, y MUST cerrar la cuenta liberando la mesa.
- **FR-012**: El sistema MUST permitir anular una cuenta abierta sin emitir documento, previa confirmación explícita, dejando registro de quién y cuándo.
- **FR-013**: El sistema MUST seguir permitiendo la venta directa sin mesa, sin pasos adicionales respecto al comportamiento actual.
- **FR-014**: El sistema MUST permitir registrar el número de comensales de una cuenta.
- **FR-015**: El sistema MUST registrar qué usuario abrió cada cuenta y quién realizó cada modificación relevante (transferencia, unión, anulación, cobro).
- **FR-016**: El sistema MUST detectar que una cuenta fue modificada por otro usuario desde que se cargó y MUST impedir sobrescribir esos cambios sin avisar.
- **FR-017**: El sistema MUST avisar en la cuenta cuando su importe supera el tope legal de la factura simplificada, antes de intentar cobrarla.

#### Transferencia y unión

- **FR-018**: El sistema MUST permitir transferir una cuenta abierta a otra mesa libre.
- **FR-019**: El sistema MUST permitir unir dos cuentas abiertas en una sola, conservando todas las líneas de ambas.
- **FR-020**: Si el destino de una transferencia está ocupado, el sistema MUST ofrecer unir en vez de sobrescribir, y MUST NOT perder consumo en ningún caso.

#### Opciones de artículo

- **FR-021**: El sistema MUST permitir gestionar un catálogo de **grupos de opciones**, cada uno con nombre, regla de selección (mínimo y máximo de opciones elegibles) y obligatoriedad.
- **FR-022**: El sistema MUST permitir gestionar un catálogo de **opciones** reutilizables, cada una con nombre, un precio por defecto y, opcionalmente, un artículo del catálogo vinculado.
- **FR-023**: El sistema MUST permitir asignar grupos de opciones a un artículo, y ajustar el precio de cada opción **para ese artículo concreto** sin alterar su precio por defecto ni el de los demás artículos.
- **FR-024**: El sistema MUST permitir ordenar las opciones dentro de un grupo y los grupos dentro de un artículo, y ese orden MUST ser el que se presenta al vender.
- **FR-025**: La asignación de opciones a un artículo MUST usar un único control de búsqueda con creación inline, y MUST NOT usar un patrón de dos listas con botón de traspaso.
- **FR-026**: Al añadir al ticket un artículo con grupos asignados, el sistema MUST presentar una selección táctil de opciones y MUST validar las reglas de cada grupo antes de permitir confirmar.
- **FR-027**: Al añadir al ticket un artículo sin grupos asignados, el sistema MUST añadirlo directamente, sin paso intermedio.
- **FR-028**: El sistema MUST mostrar las opciones elegidas junto a la línea correspondiente del ticket y MUST reflejarlas en el documento emitido.
- **FR-029**: Dos líneas del mismo artículo con selecciones de opciones distintas MUST tratarse como líneas separadas.
- **FR-030**: El sistema MUST permitir editar las opciones de una línea ya añadida, sin obligar a eliminarla y volver a crearla.
- **FR-031**: El sistema MUST impedir o advertir al eliminar del catálogo una opción o grupo en uso, indicando en cuántos artículos se usa.

#### Precios, impuestos y cumplimiento

- **FR-032**: Todos los importes (suplementos de opción, suplemento de zona, base, cuotas y total) MUST calcularse en el servidor; el cliente nunca es fuente de verdad de un importe.
- **FR-033**: El cálculo impositivo de suplementos MUST respetar el régimen impositivo del tenant (IVA / IGIC / IPSI), sin asumir IVA.
- **FR-034**: El suplemento de zona MUST aplicarse según la zona en la que la cuenta se **cobra**, con el valor vigente en ese momento, y MUST aparecer identificado en el documento emitido. [NEEDS CLARIFICATION: ver Q1 — cómo se materializa fiscalmente el suplemento]
- **FR-035**: Cuando una opción tiene artículo vinculado, el sistema MUST reflejar ese consumo en el inventario del artículo vinculado. [NEEDS CLARIFICATION: ver Q2 — si además aparece como línea propia del documento]
- **FR-036**: El documento emitido al cobrar una cuenta MUST ser inmutable y MUST cumplir las mismas reglas de numeración correlativa, desglose impositivo y Verifactu que un ticket actual.

#### Multi-tenant, permisos y navegación

- **FR-037**: Todas las entidades nuevas MUST estar aisladas por tenant y MUST ser inaccesibles desde otro tenant, incluyendo el acceso directo por identificador.
- **FR-038**: Las pantallas nuevas (Sala, Opciones) MUST tener cada una su permiso propio en el catálogo de permisos, aplicado como control de acceso en las rutas, no solo ocultando la entrada del menú.
- **FR-039**: Las entradas nuevas del menú MUST añadirse al catálogo de menú del tenant, para que sean personalizables como el resto.
- **FR-040**: Las pantallas nuevas MUST incluir su guía in-app contextual, y la guía existente de "Crear ticket" MUST actualizarse para reflejar mesas y opciones.
- **FR-041**: La base de conocimiento del asistente IA MUST incorporar las pantallas y reglas nuevas.

#### Interfaz de la vista "Crear ticket"

- **FR-042**: La botonera inferior MUST conservar sus tres franjas actuales (total, acciones secundarias, cobrar) y su altura táctil; las acciones nuevas MUST agruparse dentro de la franja central en vez de añadir franjas.
- **FR-043**: La cabecera del ticket MUST mostrar el contexto de la cuenta (mesa, zona y comensales) cuando lo haya, y MUST dar acceso desde ahí a cambiar mesa, cambiar comensales y transferir/unir.
- **FR-044**: El reparto en dos columnas y su comportamiento de desplazamiento actuales MUST conservarse; los elementos nuevos MUST reutilizar las familias visuales táctiles ya existentes en el POS.
- **FR-045**: Las notificaciones de resultado MUST usar el sistema de avisos global existente, no alertas ad-hoc dentro de la vista.

### Key Entities

- **Zona**: parte del local (Barra, Comedor, Terraza). Nombre, orden de presentación, suplemento opcional. Pertenece a un tenant. Agrupa mesas.
- **Mesa**: punto de consumo dentro de una zona. Nombre único en su zona, orden de presentación, estado derivado (libre/ocupada según tenga o no cuenta abierta). Pertenece a un tenant.
- **Cuenta abierta**: venta en curso todavía no facturada. Mesa opcional, comensales, usuario que la abrió, momento de apertura, estado (abierta / cobrada / anulada). **No tiene número ni serie.** Al cobrarse queda enlazada al documento emitido. Pertenece a un tenant.
- **Línea de cuenta**: artículo, concepto, cantidad, precio unitario, tipo impositivo y opciones elegidas. Conserva concepto y precio aunque el artículo cambie después.
- **Grupo de opciones**: conjunto con nombre y regla de selección (mínimo, máximo, obligatorio). Ej.: "Punto de cocción" (1–1, obligatorio), "Extras" (0–N, opcional).
- **Opción**: modificador reutilizable con nombre, precio por defecto y artículo vinculado opcional. Pertenece a un grupo.
- **Asignación artículo–opción**: relación entre un artículo y una opción, con **precio propio** y orden. Es lo que permite que la misma opción cueste distinto en platos distintos.
- **Selección de línea**: opciones concretas elegidas para una línea, con el suplemento congelado en el momento de añadirla.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un camarero puede mantener al menos 20 cuentas abiertas simultáneas sin que la pantalla de sala tarde más de 2 segundos en mostrarse.
- **SC-002**: Abrir una mesa, cargar 3 artículos y guardar la cuenta se completa en menos de 30 segundos y en 6 toques o menos.
- **SC-003**: Cobrar una cuenta existente lleva el mismo número de toques que cobrar una venta directa hoy (sin penalización por usar mesas).
- **SC-004**: Un artículo sin opciones se añade al ticket en 1 toque, exactamente igual que antes de esta feature — 0 % de fricción añadida al catálogo no configurado.
- **SC-005**: Un artículo con dos grupos de opciones se completa y confirma en 4 toques o menos.
- **SC-006**: El 100 % de las líneas con grupos obligatorios llega al documento con una opción elegida en ese grupo; es imposible emitir una comanda incompleta.
- **SC-007**: El importe cobrado coincide al céntimo con el calculado en servidor en el 100 % de los casos, incluidos suplementos de opción y de zona.
- **SC-008**: Ninguna cuenta abierta aparece jamás en el listado de ventas ni consume numeración: la numeración de tickets sigue siendo correlativa y sin huecos tras abrir y anular cuentas.
- **SC-009**: Un tenant no puede ver ni operar ninguna zona, mesa, cuenta u opción de otro tenant, ni siquiera accediendo por identificador directo.
- **SC-010**: Un encargado configura un grupo de opciones nuevo y lo asigna a un artículo en menos de 2 minutos, sin ayuda externa.
- **SC-011**: La vista de crear ticket conserva su comportamiento actual en tablet apaisada: ninguna acción existente pierde tamaño táctil ni cambia de sitio.

## Assumptions

Decisiones tomadas por defecto donde la descripción no lo fijaba, y decisiones explícitas del usuario que conviene dejar registradas:

- **[Decisión explícita del usuario]** Las mesas se presentan en **grid ordenado por zona (opción A)**. El plano del local con arrastrar y soltar (opción B, lo que hace GoTPV) queda **fuera de alcance**, descartado por coste frente a valor: no ahorra toques al camarero y exige construir y mantener un editor de posiciones. Se contempla como posible fase 2; el modelo de datos no debe impedirlo, pero tampoco anticiparlo.
- **[Decisión explícita del usuario]** Las opciones se organizan en **grupos con reglas de selección**, no en el pool plano de GoTPV, para garantizar que una comanda no salga incompleta.
- **[Decisión explícita del usuario]** La asignación de opciones a artículos **no** replica el doble-listbox con botón `>>` de GoTPV: es un patrón de escritorio inusable con el dedo.
- Una cuenta abierta vive en su propia entidad y **no** es un documento en borrador. Modelarla como factura en borrador ensuciaría la numeración correlativa y el encadenamiento Verifactu, que la constitución protege (Principio II).
- El umbral de "mesa olvidada" se asume configurable por tenant con un valor por defecto de 45 minutos.
- Una cuenta abierta no caduca ni se borra automáticamente: solo se cierra cobrándola o anulándola.
- El acceso a las cuentas no se restringe por camarero: cualquier usuario con permiso de POS puede operar cualquier cuenta del tenant. Un local pequeño trabaja así; restringirlo por usuario sería complejidad no pedida (Principio V).
- Las cuentas abiertas no incorporan datos personales más allá del receptor opcional ya existente en el ticket actual, por lo que no introducen una obligación de retención nueva más allá de la ya aplicable a facturas.
- El listado de cuentas abiertas se consulta desde la pantalla Sala y desde la acción "cuentas aparcadas" de la vista de crear ticket; no se crea un módulo de listado independiente.
- Se reutilizan sin cambios el motor de cálculo, la emisión, la numeración y el flujo Verifactu existentes.
- Quedan **fuera de alcance** (fase 2 o spec aparte): plano drag & drop, impresión de comandas por zona de cocina, comandero en móvil del camarero, carta digital/QR, autopedido, reservas online, descuentos, arqueo/cierre de caja y devoluciones desde caja.

## Clarifications pendientes

Tres decisiones que cambian el alcance o el resultado fiscal y no tienen un valor por defecto seguro. Se resuelven en `/speckit-clarify` antes de planificar.

### Q1: Cómo se materializa el suplemento de zona

**Contexto**: FR-034 — "El suplemento de zona MUST aplicarse según la zona en la que la cuenta se cobra […] y MUST aparecer identificado en el documento emitido."

**Qué hay que decidir**: un recargo por zona puede materializarse de tres formas muy distintas, y cada una produce un desglose impositivo diferente en un documento que después es inmutable y auditable.

| Opción | Respuesta | Implicaciones |
| ------ | --------- | ------------- |
| A | Porcentaje sobre cada línea, subiendo el precio unitario | El desglose por tipo impositivo sale solo y es correcto por construcción; el cliente ve precios distintos a los de la carta, lo que puede generar reclamaciones |
| B | Línea adicional al final ("Suplemento terraza 10 %") | Totalmente transparente para el cliente; obliga a decidir con qué tipo impositivo se grava esa línea cuando el ticket mezcla tipos (comida y bebida tributan distinto) |
| C | Importe fijo por comensal, no porcentaje | Simple de entender y de calcular; no sirve al caso de uso típico ("un 10 % más en terraza") |

**Recomendación**: A, por corrección fiscal automática con tipos mezclados. B es más transparente pero exige una regla explícita de reparto del suplemento entre tipos impositivos.

### Q2: Qué hace exactamente el "producto vinculado" de una opción

**Contexto**: FR-035 — "Cuando una opción tiene artículo vinculado, el sistema MUST reflejar ese consumo en el inventario del artículo vinculado."

**Qué hay que decidir**: en GoTPV la columna "Producto Vinculado" existe junto a "PVP", pero no queda claro si la opción se convierte en una venta del artículo vinculado o solo descuenta su stock.

| Opción | Respuesta | Implicaciones |
| ------ | --------- | ------------- |
| A | Solo mueve stock; en el documento la opción es texto bajo la línea padre y su suplemento suma a esa línea | Ticket limpio y legible; el inventario cuadra; el artículo vinculado no figura como vendido en informes de ventas |
| B | Genera una línea propia en el documento, como una venta más | Los informes de ventas por artículo son exactos; el ticket se alarga y se ensucia (un solomillo con 3 extras son 4 líneas) |
| C | Configurable por opción | Cubre ambos casos; añade un campo y una decisión más al alta de cada opción |

**Recomendación**: A. Es lo que el camarero y el cliente esperan ver en un ticket, y el inventario —que es el motivo de existir del campo— queda igual de correcto.

### Q3: ¿Entra dividir la cuenta entre comensales en esta fase?

**Contexto**: la historia 1 asume que una cuenta se cobra entera. Partirla entre comensales es un caso muy frecuente en hostelería que no se mencionó en el alcance acordado.

**Qué hay que decidir**: si entra en fase 1 o se aparta.

| Opción | Respuesta | Implicaciones |
| ------ | --------- | ------------- |
| A | Fuera de fase 1 | Mantiene esta feature acotada y entregable; el camarero resuelve el caso partiendo la cuenta a mano en dos cuentas |
| B | Dentro: dividir a partes iguales entre N comensales | Cubre el caso más común con poco coste; genera varios documentos desde una cuenta |
| C | Dentro: dividir eligiendo qué líneas paga cada uno | Es lo que realmente se pide en un restaurante; es prácticamente una feature entera (selección por línea, cuentas parciales, cobros sucesivos) |

**Recomendación**: A. Ya hay cinco historias en esta feature; C duplicaría el alcance del cobro y merece su propia spec, apoyada en las cuentas abiertas que esta feature deja construidas.
