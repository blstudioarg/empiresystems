# Feature Specification: Historial de conversaciones del asistente IA

**Feature Branch**: `045-historial-conversaciones-asistente`

**Created**: 2026-09-07

**Status**: Draft

**Input**: User description: "Historial de conversaciones del asistente IA con compactación automática, al estilo del de Claude. Persistencia por usuario, lista con retomar y borrar, compactación por resumen con IA al superar un umbral, y retención RGPD de 90 días configurable con comando de purga."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Retomar una conversación anterior (Priority: P1)

Una persona del tenant usa el asistente, cierra el navegador (o se le vence la sesión) y al día
siguiente vuelve a abrirlo. En vez de encontrarse un panel en blanco, ve su última conversación tal
como la dejó y puede seguir escribiendo con el asistente recordando todo lo hablado. Desde un icono
de historial accede a la lista de sus conversaciones anteriores, identificadas por un título y una
fecha, y puede abrir cualquiera para continuarla.

**Why this priority**: es el problema que motiva la feature. Hoy la conversación muere con la
sesión, así que cualquier trabajo de varios días con el asistente empieza de cero cada vez. Sin
esto, las otras dos historias no tienen sobre qué apoyarse.

Esta historia **no puede desplegarse sin la retención y la purga automática** descritas en FR-016 a
FR-019: en cuanto las conversaciones se guardan pasan a ser datos personales conservados, y el
Principio II de la constitución exige plazo de retención y purga desde el primer diseño. La purga
forma parte de esta rebanada, no de la historia 3.

**Independent Test**: se prueba entero manteniendo una conversación, cerrando la sesión, volviendo a
entrar y comprobando que la conversación sigue ahí y que el asistente responde teniendo en cuenta lo
hablado antes. Entrega valor por sí sola aunque no existan la compactación ni el borrado manual.

**Acceptance Scenarios**:

1. **Given** una persona con una conversación en curso, **When** cierra la sesión y vuelve a entrar,
   **Then** el panel muestra esa conversación con todos sus mensajes y puede continuarla.
2. **Given** una persona con varias conversaciones guardadas, **When** abre el historial, **Then** ve
   la lista de sus conversaciones con título y fecha, la más reciente primero.
3. **Given** una persona viendo el historial, **When** elige una conversación anterior, **Then** el
   panel carga sus mensajes y el asistente responde teniendo en cuenta ese contexto y no el de la
   conversación que estaba abierta antes.
4. **Given** una persona con una conversación abierta, **When** pide iniciar una conversación nueva,
   **Then** empieza un hilo vacío y el anterior queda guardado en el historial, no se pierde.
5. **Given** dos personas distintas del mismo tenant, **When** cada una abre su historial, **Then**
   cada una ve únicamente sus propias conversaciones.
6. **Given** dos tenants distintos, **When** se listan las conversaciones, **Then** ninguna
   conversación de un tenant es visible ni accesible desde el otro.

---

### User Story 2 - Conversaciones largas que no pierden el hilo (Priority: P2)

Una persona mantiene una conversación larga con el asistente a lo largo de varios días. Al superar
cierta longitud, en vez de que el asistente empiece a olvidar de golpe lo hablado al principio, el
sistema resume automáticamente los turnos más antiguos y sigue trabajando con ese resumen. La
persona ve que está ocurriendo y entiende que parte del hilo quedó condensado.

**Why this priority**: mejora sustancialmente conversaciones largas, pero el historial ya aporta
valor sin ella. Hoy el comportamiento es truncar y olvidar; con esta historia se conserva el sentido
de lo hablado.

**Independent Test**: se prueba llevando una conversación por encima del umbral y comprobando que el
asistente sigue respondiendo correctamente sobre datos mencionados al principio, y que la persona vio
el aviso de compactación.

**Acceptance Scenarios**:

1. **Given** una conversación que supera el umbral de longitud, **When** la persona envía un mensaje
   nuevo, **Then** los turnos más antiguos quedan sustituidos por un resumen y el asistente sigue
   respondiendo con conocimiento de lo que decían.
2. **Given** una compactación en curso, **When** la persona espera la respuesta, **Then** el panel le
   indica que se está compactando la conversación, no se queda sin señal.
3. **Given** una conversación ya compactada, **When** la persona la reabre desde el historial,
   **Then** ve que hay una parte anterior resumida, diferenciada de los mensajes normales.
4. **Given** que la compactación falla porque el servicio de IA no responde, **When** la persona
   envía su mensaje, **Then** recibe igualmente una respuesta —el sistema recurre al recorte simple
   que ya existía— y no pierde su mensaje ni la conversación.

---

### User Story 3 - Controlar qué queda guardado (Priority: P3)

Una persona quiere borrar una conversación concreta de su historial, porque trató un tema puntual o
simplemente no quiere conservarla.

**Why this priority**: la retención automática ya cubre la obligación legal (va con la historia 1);
el borrado manual es control del usuario, valioso pero no bloqueante.

**Independent Test**: se prueba borrando una conversación del historial y comprobando que desaparece
de la lista y deja de ser accesible.

**Acceptance Scenarios**:

1. **Given** una persona viendo su historial, **When** borra una conversación, **Then** desaparece de
   la lista y sus mensajes dejan de ser recuperables.
2. **Given** una persona que borra la conversación que tiene abierta, **When** confirma el borrado,
   **Then** el panel queda en una conversación nueva y vacía.
3. **Given** una persona que intenta borrar una conversación que no le pertenece, **When** lo
   intenta, **Then** la operación se rechaza.

---

### Edge Cases

- **Conversación vacía**: alguien abre el panel y no escribe nada. No debe aparecer una conversación
  en blanco en el historial: una conversación existe en el historial cuando tiene al menos un
  mensaje de la persona.
- **Acción pendiente al cambiar de hilo**: si el asistente propuso crear algo y la persona cambia de
  conversación o abre otra sin confirmar, la propuesta queda descartada (nunca se ejecuta una
  escritura sin confirmación explícita en el hilo donde se propuso).
- **Purga de la conversación abierta**: si la conversación que la persona tiene abierta supera el
  plazo de retención y se purga, al volver encuentra una conversación nueva y vacía, sin error.
- **Servicio de IA no configurado**: sin clave de API el panel sigue sin permitir conversar, pero el
  historial ya guardado permanece accesible para leer y borrar mientras no lo alcance la purga.
- **Dos pestañas abiertas**: si la misma persona tiene el panel en dos pestañas y borra en una la
  conversación que la otra tiene abierta, la segunda no debe escribir mensajes en un hilo inexistente.
- **Conversación muy larga ya compactada**: una conversación puede compactarse varias veces; el
  resumen anterior debe entrar en el siguiente resumen en vez de acumular resúmenes sueltos.
- **Mensaje enviado justo al alcanzarse el umbral**: la compactación no puede hacer que la persona
  pierda el mensaje que acaba de enviar.

## Requirements *(mandatory)*

### Functional Requirements

**Persistencia e historial**

- **FR-001**: El sistema MUST conservar las conversaciones del asistente más allá de la sesión, de
  modo que sigan disponibles tras cerrar sesión o cerrar el navegador.
- **FR-002**: Cada conversación MUST pertenecer a una única persona dentro de un único tenant, y
  MUST ser accesible solo por esa persona.
- **FR-003**: El sistema MUST impedir cualquier acceso a conversaciones de otro tenant, incluso
  conociendo su identificador.
- **FR-004**: El sistema MUST registrar cada conversación con un título y la fecha de su última
  actividad, para poder reconocerla en la lista.
- **FR-005**: Las personas MUST poder ver la lista de sus conversaciones, ordenada por actividad más
  reciente primero.
- **FR-006**: Las personas MUST poder abrir una conversación anterior y continuarla, con el
  asistente teniendo en cuenta lo hablado en ella.
- **FR-007**: Las personas MUST poder iniciar una conversación nueva sin que se pierda la anterior.
- **FR-008**: El sistema MUST registrar una conversación en el historial solo cuando contiene al
  menos un mensaje de la persona.
- **FR-009**: Al reabrir el asistente sin elegir nada, el sistema MUST mostrar la conversación con
  actividad más reciente.

**Compactación**

- **FR-010**: Cuando una conversación supera un umbral de longitud, el sistema MUST sustituir los
  turnos más antiguos por un resumen que conserve la información necesaria para seguir la
  conversación, en lugar de descartarlos.
- **FR-011**: El sistema MUST informar visualmente a la persona mientras la compactación está en
  curso, reutilizando el indicador de progreso existente.
- **FR-012**: El sistema MUST distinguir visualmente, dentro de la conversación, la parte que quedó
  resumida de los mensajes literales.
- **FR-013**: Si la compactación no puede completarse, el sistema MUST responder igualmente al
  mensaje de la persona recurriendo al recorte simple actual, sin perder el mensaje enviado ni la
  conversación.
- **FR-014**: Una conversación compactada varias veces MUST integrar el resumen previo en el nuevo,
  sin acumular resúmenes independientes.
- **FR-015**: La compactación MUST ocurrir dentro del propio intercambio de mensajes, sin depender de
  procesos en segundo plano.

**Retención y borrado (obligatorio junto con la historia 1)**

- **FR-016**: El sistema MUST conservar las conversaciones un plazo limitado y configurable por
  tenant, con un valor por defecto de 90 días desde la última actividad.
- **FR-017**: El sistema MUST disponer de un mecanismo de purga periódica que elimine las
  conversaciones que superan ese plazo, siguiendo el mismo patrón que las purgas ya existentes en el
  producto en vez de introducir uno nuevo.
- **FR-018**: Quien administra el tenant MUST poder consultar y modificar el plazo de retención.
- **FR-019**: La purga MUST eliminar también los mensajes y resúmenes asociados a la conversación,
  sin dejar restos.
- **FR-020**: Las personas MUST poder borrar una conversación propia, y tras borrarla sus mensajes
  MUST dejar de ser recuperables.
- **FR-021**: El sistema MUST rechazar el borrado de una conversación que no pertenece a quien lo
  solicita.

**Compatibilidad con el comportamiento actual**

- **FR-022**: El flujo de confirmación de escrituras MUST seguir funcionando igual: como máximo una
  propuesta pendiente, confirmada explícitamente por la persona, y el turno termina al proponer.
- **FR-023**: Una propuesta pendiente MUST quedar descartada al cambiar de conversación o iniciar una
  nueva, de modo que nunca se ejecute una escritura fuera del hilo donde se propuso.

**Documentación (parte del entregable)**

- **FR-024**: La documentación del modelo de datos MUST dejar de describir la conversación del
  asistente como efímera y sin retención, y MUST recoger el nuevo plazo y su mecanismo de purga,
  actualizada en el mismo cambio que el código.
- **FR-025**: La documentación de arquitectura y la base de conocimiento del asistente MUST
  reflejar el historial, la compactación y la retención.

### Key Entities

- **Conversación**: un hilo de diálogo entre una persona y el asistente. Pertenece a una persona
  dentro de un tenant. Tiene título, momento de última actividad y, opcionalmente, un resumen de la
  parte antigua ya compactada.
- **Mensaje**: cada turno dentro de una conversación —lo que escribió la persona, lo que respondió el
  asistente y las consultas a datos que hizo por el camino—, con su orden dentro del hilo.
- **Plazo de retención del asistente**: valor configurable por tenant que determina cuántos días se
  conservan las conversaciones sin actividad antes de purgarse.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una persona que vuelve al día siguiente encuentra su conversación anterior y puede
  continuarla sin repetir contexto, en el 100 % de los casos dentro del plazo de retención.
- **SC-002**: Abrir el historial y retomar una conversación anterior se completa en menos de 3
  segundos con 50 conversaciones guardadas.
- **SC-003**: Sobre un guion fijo de 10 datos concretos mencionados antes del corte (nombres,
  importes, referencias), tras la compactación el asistente responde correctamente sobre al menos 9
  de ellos. El guion se ejecuta como parte de la validación descrita en el quickstart.
- **SC-004**: Ninguna persona puede ver ni abrir conversaciones de otra persona ni de otro tenant:
  0 accesos indebidos en las pruebas de aislamiento.
- **SC-005**: Las conversaciones sin actividad más allá del plazo configurado desaparecen por
  completo tras la purga, verificable en el 100 % de los casos.
- **SC-006**: Una compactación fallida nunca deja a la persona sin respuesta ni le hace perder el
  mensaje enviado.
- **SC-007**: El turno en el que ocurre una compactación tarda como mucho el doble que un turno
  normal equivalente.

## Assumptions

- **Título de la conversación**: se deriva del primer mensaje de la persona, recortado. Generar
  títulos con IA queda explícitamente fuera de alcance, igual que renombrar y buscar en el historial.
- **Umbral de compactación**: se parte del límite de longitud que ya usa hoy el asistente para
  truncar; el valor exacto se ajusta en la fase de planificación y no es una decisión de producto.
- **Coste y latencia**: la compactación implica una consulta adicional al proveedor de IA, pagada con
  la clave del propio tenant, y añade espera en el turno donde ocurre. Es un coste aceptado a cambio
  de no perder el contexto; por eso FR-011 exige avisar a la persona.
- **Privacidad entre personas del mismo tenant**: las conversaciones son privadas de cada persona.
  Compartirlas o que un administrador las lea queda fuera de alcance.
- **Alcance del historial**: solo el asistente. No aplica a ningún otro chat del producto.
- **Adjuntos**: enviar imágenes o PDF al asistente sigue fuera de alcance; el historial guarda
  únicamente texto.
- **Retención existente**: se reutiliza el mecanismo de configuración por tenant y de purga
  programada ya presentes en el producto para logs, leads y documentos de compra.
- **Cambio de decisión documentada**: esta feature revierte deliberadamente la decisión previa de que
  la conversación fuera efímera. El cambio de plazo de conservación de datos personales se refleja en
  la documentación del modelo de datos como parte del entregable (FR-024).
