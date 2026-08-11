# Feature Specification: POS con mesas y opciones de artículo (hostelería)

**Feature Branch**: `038-pos-mesas-opciones`

**Created**: 2026-08-09

**Status**: Draft

**Input**: User description: "POS con mesas y opciones de artículo (hostelería). Inspirado en GoTPV (gotpv.es). Fase 1: zonas y mesas en grid ordenado (no plano drag & drop), cuentas abiertas en tabla propia, transferir/unir cuentas, cobro dividido por selección de líneas, suplemento configurable por zona, y opciones/modificadores de artículo organizadas en grupos con reglas de selección, precio por artículo y producto vinculado opcional. Todo el bloque se activa y se configura desde una pestaña nueva de Configuración, de modo que el POS actual siga funcionando igual para quien no lo necesite. Adaptar la vista de crear ticket sin agrandar la botonera ni romper la geometría sticky existente."

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

### User Story 1 - Activar y configurar el módulo de hostelería (Priority: P1)

Un tenant que vende ropa entra a su POS y lo encuentra exactamente como siempre: catálogo, ticket, cobrar. Ni rastro de mesas ni de opciones. Un tenant que abre un bar entra a Configuración → POS, activa el módulo de hostelería y, desde esa misma pestaña, crea sus zonas, da de alta sus mesas y decide qué partes quiere (cobro dividido sí, suplemento por zona no). A partir de ahí su POS muestra la sala y el resto de capacidades.

**Why this priority**: es la historia que hace que todas las demás sean seguras de entregar. Sin ella, esta feature le impone hostelería a comercios que no la quieren y convierte una mejora en una regresión. Además es el contenedor natural de toda la configuración (zonas, mesas, umbrales), así que se construye primero.

**Independent Test**: con el módulo desactivado, recorrer el POS actual completo y verificar que no cambió nada; activarlo y verificar que aparecen las capacidades nuevas.

**Acceptance Scenarios**:

1. **Given** un tenant recién creado, **When** entra al POS, **Then** el módulo de hostelería está **desactivado** y el POS se comporta exactamente como antes de esta feature.
2. **Given** el módulo desactivado, **When** el usuario navega por el menú, **Then** las entradas de Sala y Opciones no aparecen y sus direcciones no son accesibles.
3. **Given** el módulo desactivado, **When** el administrador lo activa desde Configuración → POS, **Then** las capacidades nuevas quedan disponibles sin reiniciar sesión ni migrar datos.
4. **Given** el módulo activado, **When** el administrador lo desactiva, **Then** el sistema advierte si hay cuentas abiertas y no permite desactivar hasta cerrarlas o anularlas; los datos ya configurados (zonas, mesas, opciones) se conservan para cuando se reactive.
5. **Given** el módulo activado, **When** el administrador desactiva una capacidad concreta (p. ej. suplemento por zona), **Then** esa capacidad deja de aparecer en el POS sin afectar al resto.
6. **Given** un ticket ya emitido con mesa y opciones, **When** el módulo se desactiva después, **Then** el documento emitido sigue siendo consultable e íntegro.

---

### User Story 2 - Atender varias mesas a la vez (Priority: P1)

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

### User Story 3 - Definir las opciones de los platos (Priority: P2)

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

### User Story 4 - Vender un plato con sus opciones (Priority: P3)

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

### User Story 5 - Cobrar la cuenta por partes (Priority: P4)

Una mesa de cuatro pide pagar por separado. El camarero abre la cuenta, marca el solomillo y la copa de vino que consumió uno de ellos, y cobra solo esa parte: se emite un ticket por ese importe. La mesa **sigue ocupada** con lo que queda pendiente. Repite hasta que no queda nada por pagar; entonces la mesa se libera.

**Why this priority**: es de las situaciones más frecuentes en un restaurante y hoy se resuelve a mano, con errores. Va después del núcleo porque necesita que las cuentas abiertas ya existan.

**Independent Test**: con una cuenta de 4 líneas, cobrar 2 líneas en un ticket y las otras 2 en otro, comprobando importes, numeración y estado final de la mesa.

**Acceptance Scenarios**:

1. **Given** una cuenta con varias líneas, **When** el camarero selecciona un subconjunto y cobra, **Then** se emite un ticket solo por esas líneas y la cuenta conserva el resto.
2. **Given** una cuenta parcialmente cobrada, **When** se consulta la mesa, **Then** sigue ocupada y muestra el importe **pendiente**, no el total original.
3. **Given** una cuenta con todo cobrado, **When** se cobra la última parte, **Then** la cuenta se cierra y la mesa queda libre.
4. **Given** una línea ya cobrada, **When** se intenta volver a seleccionarla, **Then** el sistema no lo permite y la muestra como saldada.
5. **Given** varios cobros parciales de una misma cuenta, **When** se revisa la numeración de tickets, **Then** es correlativa y sin huecos, y cada ticket es un documento independiente e inmutable.
6. **Given** una línea de un artículo con cantidad mayor a 1, **When** se cobra parcialmente, **Then** el sistema permite repartir unidades de esa línea entre cobros distintos.
7. **Given** una cuenta parcialmente cobrada, **When** se intenta transferirla o unirla a otra, **Then** solo se mueve lo pendiente, nunca lo ya facturado.

---

### User Story 6 - Mover y juntar cuentas (Priority: P5)

Un cliente que estaba tomando algo en la barra se sienta en la terraza: el camarero pasa la cuenta de barra a la mesa 12. Más tarde dos grupos de mesas contiguas piden pagar juntos: une la cuenta de la mesa 3 con la de la 4.

**Why this priority**: es de uso diario en un bar, pero el TPV ya es utilizable sin ello (se puede rehacer la cuenta a mano). Va después del núcleo.

**Independent Test**: con dos cuentas abiertas, transferir una a otra mesa y unir dos, comprobando importes y estados de mesa resultantes.

**Acceptance Scenarios**:

1. **Given** una cuenta abierta en una mesa, **When** se transfiere a una mesa libre, **Then** la mesa origen queda libre y la destino ocupada con el mismo consumo e importe.
2. **Given** dos mesas ocupadas, **When** se unen, **Then** la cuenta resultante contiene todas las líneas de ambas, el importe es la suma y una de las dos mesas queda libre.
3. **Given** una cuenta abierta, **When** se intenta transferirla a una mesa ya ocupada, **Then** el sistema ofrece unir las cuentas en vez de sobrescribir, y nunca pierde consumo.
4. **Given** una transferencia o unión, **When** se consulta el historial de esa cuenta, **Then** queda registrado quién la hizo y cuándo.

---

### User Story 7 - Cobrar distinto según la zona (Priority: P6)

El encargado crea sus zonas con los nombres que quiera y decide si alguna lleva un valor añadido. Por ejemplo, le pone un 10 % a la zona que él llama "Terraza" y deja el resto en cero. Al cobrar una cuenta de una mesa de esa zona, el importe lo refleja automáticamente.

**Why this priority**: diferenciador comercial real pero prescindible en el arranque; la mayoría de locales empiezan sin suplemento. Es además la parte con más impacto fiscal, así que conviene aislarla al final.

**Nota de diseño**: ninguna zona es especial para el sistema. No existe el concepto "terraza" en el modelo: existe "zona con suplemento configurable", que arranca en cero. Un local puede tener tres zonas con recargo y ninguna llamada Terraza.

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
- **Capacidad desactivada con datos ya creados**: apagar "opciones de artículo" oculta la selección al vender, pero no borra grupos ni asignaciones; volver a encenderla los recupera tal cual.
- **Cobro parcial que por sí solo supera el tope de la simplificada**: se comprueba sobre el importe de **ese** cobro, no sobre el total de la cuenta.
- **Cobro parcial que deja un pendiente de importe cero** (por redondeo o descuentos futuros): la cuenta se cierra y la mesa se libera igualmente, sin emitir un documento vacío.
- **Cuenta parcialmente cobrada que se quiere anular**: solo se puede anular lo pendiente; los documentos ya emitidos son inmutables y solo se corrigen por rectificativa, como cualquier factura.
- **Selección de cobro parcial que queda vacía**: no se permite emitir un ticket sin líneas.

## Requirements *(mandatory)*

### Functional Requirements

#### Activación y configuración del módulo

- **FR-001**: El sistema MUST ofrecer una pestaña "POS" dentro de Configuración donde el administrador del tenant activa y configura todo lo introducido por esta feature.
- **FR-002**: El módulo MUST estar **desactivado por defecto** en todo tenant, existente o nuevo. Con el módulo desactivado, el POS MUST comportarse exactamente igual que antes de esta feature, sin elementos nuevos en pantalla.
- **FR-003**: Con el módulo desactivado, las pantallas nuevas MUST ser inaccesibles también por dirección directa, no solo ocultas en el menú.
- **FR-004**: El sistema MUST permitir activar o desactivar de forma independiente las capacidades opcionales: opciones de artículo, cobro dividido y suplemento por zona. Las mesas y cuentas abiertas son el núcleo del módulo y se activan con él.
- **FR-005**: La pestaña de configuración MUST ser el lugar donde se gestionan zonas y mesas, y donde se fija el umbral de "mesa olvidada".
- **FR-006**: El sistema MUST impedir desactivar el módulo mientras existan cuentas abiertas, indicando cuántas hay y dónde.
- **FR-007**: Desactivar el módulo MUST conservar la configuración y los datos ya creados (zonas, mesas, opciones), para que reactivarlo los recupere intactos.
- **FR-008**: Los documentos ya emitidos con mesa u opciones MUST seguir siendo consultables e íntegros aunque el módulo se desactive después.

#### Zonas y mesas

- **FR-009**: El sistema MUST permitir gestionar zonas del local (crear, editar, eliminar, ordenar), cada una con un nombre libre elegido por el tenant y un suplemento de precio opcional que arranca en cero. NINGUNA zona MUST tener comportamiento especial por su nombre: no existe el concepto "terraza" en el sistema, solo "zona con suplemento configurable".
- **FR-010**: El sistema MUST permitir gestionar mesas (crear, editar, eliminar, ordenar), cada una perteneciente a exactamente una zona y con un nombre único dentro de su zona.
- **FR-011**: El sistema MUST ofrecer una pantalla "Sala" que muestre las mesas agrupadas por zona en un **grid ordenado automáticamente**; NO debe permitir posicionar libremente las mesas sobre un plano.
- **FR-012**: La pantalla Sala MUST permitir filtrar por zona mediante el mismo componente de filtros táctiles ya usado en el catálogo del POS, incluyendo un filtro "Todas" y el conteo de mesas por zona.
- **FR-013**: Cada mesa MUST mostrar su nombre, su estado (libre u ocupada) y, si está ocupada, el importe pendiente y el tiempo transcurrido desde que se abrió la cuenta.
- **FR-014**: Una mesa ocupada durante más tiempo que un umbral configurable por tenant MUST distinguirse visualmente de una recién abierta.
- **FR-015**: El sistema MUST impedir eliminar una zona con mesas asociadas, o una mesa con una cuenta abierta, informando el motivo.

#### Cuentas abiertas

- **FR-016**: El sistema MUST permitir mantener varias cuentas abiertas simultáneamente, cada una asociada opcionalmente a una mesa.
- **FR-017**: Una cuenta abierta MUST NOT consumir numeración de factura ni serie, MUST NOT aparecer en el listado de tickets emitidos, y MUST NOT generar registro Verifactu hasta que se cobre.
- **FR-018**: El sistema MUST permitir guardar una cuenta y recuperarla más tarde con todo su contenido (líneas, cantidades, opciones elegidas, receptor y comensales).
- **FR-019**: Al cobrar una cuenta por completo, el sistema MUST emitir un ticket simplificado por el mismo flujo de emisión que la venta directa actual, y MUST cerrar la cuenta liberando la mesa.
- **FR-020**: El sistema MUST permitir anular una cuenta abierta sin emitir documento, previa confirmación explícita, dejando registro de quién y cuándo.
- **FR-021**: El sistema MUST seguir permitiendo la venta directa sin mesa, sin pasos adicionales respecto al comportamiento actual.
- **FR-022**: El sistema MUST permitir registrar el número de comensales de una cuenta.
- **FR-023**: El sistema MUST registrar qué usuario abrió cada cuenta y quién realizó cada modificación relevante (transferencia, unión, anulación, cobro total o parcial).
- **FR-024**: El sistema MUST detectar que una cuenta fue modificada por otro usuario desde que se cargó y MUST impedir sobrescribir esos cambios sin avisar.
- **FR-025**: El sistema MUST avisar en la cuenta cuando el importe pendiente supera el tope legal de la factura simplificada, antes de intentar cobrarlo.

#### Cobro dividido por selección de líneas

- **FR-026**: El sistema MUST permitir cobrar una cuenta abierta **parcialmente**, seleccionando qué líneas se pagan en ese cobro.
- **FR-027**: Un cobro parcial MUST emitir un documento independiente, correlativo e inmutable, exactamente igual que cualquier otro ticket.
- **FR-028**: Tras un cobro parcial, la cuenta MUST permanecer abierta con lo pendiente y la mesa MUST seguir ocupada mostrando el importe **pendiente**, no el original.
- **FR-029**: El sistema MUST impedir cobrar dos veces la misma línea o unidad, y MUST distinguir visualmente lo ya saldado de lo pendiente.
- **FR-030**: El sistema MUST permitir repartir las unidades de una línea de cantidad mayor a 1 entre cobros distintos.
- **FR-031**: Al saldarse la última línea pendiente, el sistema MUST cerrar la cuenta y liberar la mesa automáticamente.
- **FR-032**: Una transferencia o unión de una cuenta parcialmente cobrada MUST mover únicamente lo pendiente; lo ya facturado nunca se mueve ni se duplica.
- **FR-033**: La suma de todos los cobros parciales de una cuenta MUST coincidir al céntimo con el importe total consumido.

#### Transferencia y unión

- **FR-034**: El sistema MUST permitir transferir una cuenta abierta a otra mesa libre.
- **FR-035**: El sistema MUST permitir unir dos cuentas abiertas en una sola, conservando todas las líneas de ambas.
- **FR-036**: Si el destino de una transferencia está ocupado, el sistema MUST ofrecer unir en vez de sobrescribir, y MUST NOT perder consumo en ningún caso.

#### Opciones de artículo

- **FR-037**: El sistema MUST permitir gestionar un catálogo de **grupos de opciones**, cada uno con nombre, regla de selección (mínimo y máximo de opciones elegibles) y obligatoriedad.
- **FR-038**: El sistema MUST permitir gestionar un catálogo de **opciones** reutilizables, cada una con nombre, un precio por defecto y, opcionalmente, un artículo del catálogo vinculado.
- **FR-039**: El sistema MUST permitir asignar grupos de opciones a un artículo, y ajustar el precio de cada opción **para ese artículo concreto** sin alterar su precio por defecto ni el de los demás artículos.
- **FR-040**: El sistema MUST permitir ordenar las opciones dentro de un grupo y los grupos dentro de un artículo, y ese orden MUST ser el que se presenta al vender.
- **FR-041**: La asignación de opciones a un artículo MUST usar un único control de búsqueda con creación inline, y MUST NOT usar un patrón de dos listas con botón de traspaso.
- **FR-042**: Al añadir al ticket un artículo con grupos asignados, el sistema MUST presentar una selección táctil de opciones y MUST validar las reglas de cada grupo antes de permitir confirmar.
- **FR-043**: Al añadir al ticket un artículo sin grupos asignados, el sistema MUST añadirlo directamente, sin paso intermedio.
- **FR-044**: El sistema MUST mostrar las opciones elegidas junto a la línea correspondiente del ticket y MUST reflejarlas en el documento emitido.
- **FR-045**: Dos líneas del mismo artículo con selecciones de opciones distintas MUST tratarse como líneas separadas.
- **FR-046**: El sistema MUST permitir editar las opciones de una línea ya añadida, sin obligar a eliminarla y volver a crearla.
- **FR-047**: El sistema MUST impedir o advertir al eliminar del catálogo una opción o grupo en uso, indicando en cuántos artículos se usa.

#### Precios, impuestos y cumplimiento

- **FR-048**: Todos los importes (suplementos de opción, suplemento de zona, base, cuotas y total) MUST calcularse en el servidor; el cliente nunca es fuente de verdad de un importe.
- **FR-049**: El cálculo impositivo de suplementos MUST respetar el régimen impositivo del tenant (IVA / IGIC / IPSI), sin asumir IVA.
- **FR-050**: El suplemento de zona MUST materializarse **incrementando el precio unitario de cada línea** en el porcentaje configurado, NO como una línea adicional en el documento. Consecuencia buscada: cada suplemento hereda automáticamente el tipo impositivo del artículo que lo genera, de modo que un ticket con artículos de tipos distintos produce un desglose correcto sin ninguna regla de reparto adicional.
- **FR-051**: El suplemento de zona MUST calcularse con el valor vigente **en el momento del cobro** y según la zona en la que la cuenta se cobra.
- **FR-052**: Cuando una opción tiene artículo vinculado, el sistema MUST descontar el consumo del inventario de ese artículo. La opción MUST NOT generar una línea propia en el documento: se representa como detalle bajo la línea del artículo principal y su suplemento se suma al importe de esa línea.
- **FR-053**: Todo documento emitido al cobrar una cuenta (total o parcial) MUST ser inmutable y MUST cumplir las mismas reglas de numeración correlativa, desglose impositivo y Verifactu que un ticket actual.

#### Multi-tenant, permisos y navegación

- **FR-054**: Todas las entidades nuevas MUST estar aisladas por tenant y MUST ser inaccesibles desde otro tenant, incluyendo el acceso directo por identificador.
- **FR-055**: Las pantallas nuevas (Sala, Opciones) MUST tener cada una su permiso propio en el catálogo de permisos, aplicado como control de acceso en las rutas, no solo ocultando la entrada del menú.
- **FR-056**: Las entradas nuevas del menú MUST añadirse al catálogo de menú del tenant, para que sean personalizables como el resto, y MUST respetar tanto el permiso como el estado de activación del módulo.
- **FR-057**: Las pantallas nuevas MUST incluir su guía in-app contextual, y la guía existente de "Crear ticket" MUST actualizarse para reflejar mesas, opciones y cobro dividido.
- **FR-058**: La base de conocimiento del asistente IA MUST incorporar las pantallas y reglas nuevas.

#### Interfaz de la vista "Crear ticket"

- **FR-059**: La botonera inferior MUST conservar sus tres franjas actuales (total, acciones secundarias, cobrar) y su altura táctil; las acciones nuevas MUST agruparse dentro de la franja central en vez de añadir franjas.
- **FR-060**: La cabecera del ticket MUST mostrar el contexto de la cuenta (mesa, zona y comensales) cuando lo haya, y MUST dar acceso desde ahí a cambiar mesa, cambiar comensales y transferir/unir.
- **FR-061**: El reparto en dos columnas y su comportamiento de desplazamiento actuales MUST conservarse; los elementos nuevos MUST reutilizar las familias visuales táctiles ya existentes en el POS.
- **FR-062**: Las notificaciones de resultado MUST usar el sistema de avisos global existente, no alertas ad-hoc dentro de la vista.
- **FR-063**: Al cobrar en efectivo, el sistema MUST permitir introducir el importe entregado por el cliente y MUST mostrar el cambio a devolver, calculado en el momento. El importe entregado es una ayuda de caja: MUST NOT alterar el importe cobrado ni figurar en el documento emitido.
- **FR-064**: El modal de cobro MUST mostrar el contexto de la cuenta que se está cobrando (mesa y zona) cuando lo haya, para evitar cobrar la mesa equivocada.

### Key Entities

- **Configuración POS del tenant**: interruptor maestro del módulo (apagado por defecto), interruptores de las capacidades opcionales (opciones de artículo, cobro dividido, suplemento por zona) y umbral de "mesa olvidada".
- **Zona**: parte del local con nombre libre elegido por el tenant. Orden de presentación y suplemento opcional que arranca en cero. Pertenece a un tenant. Agrupa mesas. Ningún nombre tiene significado para el sistema.
- **Mesa**: punto de consumo dentro de una zona. Nombre único en su zona, orden de presentación, estado derivado (libre/ocupada según tenga o no cuenta abierta). Pertenece a un tenant.
- **Cuenta abierta**: venta en curso todavía no facturada por completo. Mesa opcional, comensales, usuario que la abrió, momento de apertura, estado (abierta / cerrada / anulada). **No tiene número ni serie.** Puede quedar enlazada a **varios** documentos emitidos, uno por cobro parcial. Pertenece a un tenant.
- **Línea de cuenta**: artículo, concepto, cantidad, precio unitario, tipo impositivo y opciones elegidas. Lleva cuántas unidades están ya saldadas y en qué documento, para soportar el cobro dividido. Conserva concepto y precio aunque el artículo cambie después.
- **Cobro de cuenta**: cada emisión realizada sobre una cuenta —una sola si se cobra entera, varias si se divide—, con las unidades que saldó y el documento resultante.
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
- **SC-012**: Con el módulo desactivado, el POS es indistinguible del actual: 0 elementos nuevos en pantalla y 0 pasos añadidos en el flujo de venta directa.
- **SC-013**: Un administrador activa el módulo y deja su sala operativa (zonas y mesas creadas) en menos de 10 minutos.
- **SC-014**: Una cuenta de 6 líneas se reparte y cobra en 3 pagos distintos sin que ninguna unidad se cobre dos veces ni quede sin cobrar, y la suma de los 3 tickets coincide al céntimo con el consumo.

## Assumptions

Decisiones tomadas por defecto donde la descripción no lo fijaba, y decisiones explícitas del usuario que conviene dejar registradas:

- **[Decisión explícita del usuario]** Todo el bloque se entrega como **módulo opcional apagado por defecto**, activable y configurable desde una pestaña "POS" en Configuración. Motivo: el producto tiene tenants de comercio, tienda y servicios a los que las mesas no les aportan nada; imponerles la sala sería una regresión. Es también lo que permite entregar la feature sin riesgo para los tenants actuales.
- **[Decisión explícita del usuario]** Las mesas se presentan en **grid ordenado por zona (opción A)**. El plano del local con arrastrar y soltar (opción B, lo que hace GoTPV y lo que muestra su captura de "Diseña un plano de tu local en minutos") queda **fuera de alcance**, descartado por coste frente a valor: no ahorra toques al camarero y exige construir y mantener un editor de posiciones que además debe funcionar en tablets de distinto tamaño. Se contempla como posible fase 2; el modelo de datos no debe impedirlo, pero tampoco anticiparlo. Lo que sí se toma de esa pantalla son las **pestañas de zona**, ya presentes en la opción A.
- **[Decisión explícita del usuario]** El suplemento por zona es **totalmente configurable y sin zonas privilegiadas**: el tenant crea las zonas que quiera con los nombres que quiera y decide cuáles llevan recargo. "Terraza" es un ejemplo, no un concepto del sistema.
- **[Decisión explícita del usuario, Q1]** El suplemento de zona sube el precio unitario de cada línea en vez de añadir una línea "Suplemento". Descartada la línea aparte porque, con artículos de tipos impositivos distintos en el mismo ticket, obligaría a inventar una regla de reparto del suplemento entre tipos, y esa regla quedaría impresa en un documento fiscal inmutable.
- **[Decisión explícita del usuario, Q2]** Una opción con artículo vinculado descuenta inventario pero **no** genera línea propia en el documento. Consecuencia aceptada: el artículo vinculado no figura como vendido en los informes de ventas por artículo; el motivo del campo (que el inventario cuadre) sí queda cubierto.
- **[Decisión explícita del usuario, Q3]** El cobro dividido entra en esta fase en su forma completa: **por selección de líneas**, no a partes iguales. Consecuencia asumida: cobrar deja de ser una operación única que cierra la cuenta, y una cuenta puede generar varios documentos correlativos. Es el bloque más costoso de la feature junto con las cuentas abiertas.
- **[Decisión explícita del usuario]** Las opciones se organizan en **grupos con reglas de selección**, no en el pool plano de GoTPV, para garantizar que una comanda no salga incompleta.
- **[Decisión explícita del usuario]** La asignación de opciones a artículos **no** replica el doble-listbox con botón `>>` de GoTPV: es un patrón de escritorio inusable con el dedo.
- Una cuenta abierta vive en su propia entidad y **no** es un documento en borrador. Modelarla como factura en borrador ensuciaría la numeración correlativa y el encadenamiento Verifactu, que la constitución protege (Principio II).
- El umbral de "mesa olvidada" se asume configurable por tenant con un valor por defecto de 45 minutos.
- Una cuenta abierta no caduca ni se borra automáticamente: solo se cierra cobrándola o anulándola.
- El acceso a las cuentas no se restringe por camarero: cualquier usuario con permiso de POS puede operar cualquier cuenta del tenant. Un local pequeño trabaja así; restringirlo por usuario sería complejidad no pedida (Principio V).
- Las cuentas abiertas no incorporan datos personales más allá del receptor opcional ya existente en el ticket actual, por lo que no introducen una obligación de retención nueva más allá de la ya aplicable a facturas.
- El listado de cuentas abiertas se consulta desde la pantalla Sala y desde la acción "cuentas aparcadas" de la vista de crear ticket; no se crea un módulo de listado independiente.
- Se reutilizan sin cambios el motor de cálculo, la emisión, la numeración y el flujo Verifactu existentes.
- **[Aportado por el usuario desde la pantalla de cobro de GoTPV]** Se incorpora el cálculo de
  **cambio a devolver** (campos "Entregado" y "Devolver"): es solo interfaz, sin efecto fiscal, y
  encaja en el teclado numérico que el modal de cobro ya tiene.
- **[Descartado de esa misma pantalla, con motivo]** Los "métodos" **Autoconsumo, Rotura,
  Invitación y Cuenta cliente** de GoTPV **no** entran aquí. No son formas de pago sino salidas sin
  venta (o venta aplazada), y tratarlas como una forma de pago más produciría documentos a 0 € o
  descuadres de caja. Autoconsumo, rotura e invitación pertenecen al spec de arqueo/cierre de caja;
  "cuenta cliente" es venta a crédito, que el sistema ya cubre por el flujo de factura ordinaria con
  vencimiento.
- Quedan **fuera de alcance** (fase 2 o spec aparte): plano drag & drop, impresión de comandas por
  zona de cocina, comandero en móvil del camarero, carta digital/QR, autopedido, reservas online,
  descuentos, arqueo/cierre de caja (incluidos autoconsumo/rotura/invitación) y devoluciones desde
  caja.

