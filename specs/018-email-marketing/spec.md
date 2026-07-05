# Feature Specification: Email Marketing (Campañas a Clientes)

**Feature Branch**: `018-email-marketing`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Módulo de email marketing / envío de emails a múltiples clientes (campañas). Un usuario del tenant puede crear una campaña de email: elegir destinatarios seleccionando entre sus clientes, escribir asunto y cuerpo con editor de texto enriquecido, opcionalmente basándose en plantillas reutilizables. El envío se hace sin colas ni cron (hosting compartido): el frontend trocea la lista en tandas y las manda secuencialmente con barra de progreso; el backend reutiliza la infra SMTP por tenant (feature 017) y devuelve resultado por destinatario. Todo respeta aislamiento multi-tenant y se registra en histórico."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Enviar una campaña a varios clientes (Priority: P1)

Un usuario del tenant abre la pantalla de nueva campaña, selecciona varios de sus clientes
como destinatarios (multi-selección desde el listado de clientes), escribe un asunto y un
cuerpo con formato (editor de texto enriquecido), y pulsa "Enviar". El sistema envía el email
a cada destinatario y muestra una barra de progreso que avanza a medida que se completan los
envíos, indicando al final cuántos se enviaron correctamente y cuántos fallaron.

**Why this priority**: Es el núcleo del módulo y el valor entregable mínimo — sin esto no hay
email marketing. Todo lo demás (plantillas, reintentos, historial) es soporte de este flujo.

**Independent Test**: Se puede probar por completo creando una campaña, seleccionando ≥2
clientes con email válido, escribiendo asunto y cuerpo, enviando, y verificando que cada
destinatario recibe el correo y que el resumen final refleja el resultado real por destinatario.

**Acceptance Scenarios**:

1. **Given** un tenant con SMTP configurado y ≥2 clientes con email, **When** el usuario crea
   una campaña con asunto y cuerpo y selecciona esos clientes y pulsa enviar, **Then** cada
   cliente recibe el correo y el sistema muestra "N enviados, 0 fallidos".
2. **Given** una campaña en envío, **When** el proceso avanza, **Then** la barra de progreso
   refleja el número de destinatarios ya procesados sobre el total sin bloquear el navegador.
3. **Given** un destinatario cuyo envío falla (p. ej. email inexistente o rechazo SMTP),
   **When** finaliza la campaña, **Then** el resumen lista ese destinatario como fallido con
   el motivo, y los demás se envían igualmente.
4. **Given** un tenant sin SMTP configurado, **When** el usuario intenta enviar una campaña,
   **Then** el sistema lo impide con un mensaje claro que remite a la configuración de email.

---

### User Story 2 - Reutilizar plantillas de email (Priority: P2)

El usuario gestiona un catálogo de plantillas de email (crear, editar, activar/desactivar,
eliminar) con título, asunto y cuerpo. Al crear una campaña puede partir de una plantilla
activa, que precarga asunto y cuerpo para editarlos antes de enviar.

**Why this priority**: Acelera el trabajo recurrente y da consistencia de marca, pero la
campaña puede enviarse sin plantillas (US1 funciona sola). Es una mejora, no un bloqueante.

**Independent Test**: Crear una plantilla, verla en el listado con su estado, editarla,
desactivarla (deja de ofrecerse en campañas) y usar una activa para precargar una campaña.

**Acceptance Scenarios**:

1. **Given** el usuario en la pantalla de plantillas, **When** crea una plantilla con título,
   asunto y cuerpo, **Then** aparece en el listado con estado "activa" y fecha de modificación.
2. **Given** una plantilla activa, **When** el usuario inicia una campaña y la selecciona,
   **Then** el asunto y cuerpo de la campaña se precargan con el contenido de la plantilla y
   siguen siendo editables.
3. **Given** una plantilla desactivada, **When** el usuario crea una campaña, **Then** esa
   plantilla no aparece entre las seleccionables.

---

### User Story 3 - Revisar historial y reintentar fallidos (Priority: P3)

Tras enviar una campaña, el usuario puede consultar el resultado del envío (por campaña y por
destinatario) y reintentar el envío únicamente a los destinatarios que fallaron, sin reenviar
a quienes ya recibieron el correo.

**Why this priority**: Mejora la fiabilidad operativa y evita duplicados, pero el envío base
(US1) ya deja registro por destinatario; el reintento es una comodidad sobre esa base.

**Independent Test**: Provocar una campaña con algún fallo, abrir su detalle, ver el desglose
por destinatario, pulsar "reintentar fallidos" y verificar que solo se reenvía a los fallidos.

**Acceptance Scenarios**:

1. **Given** una campaña enviada con algunos fallos, **When** el usuario abre su detalle,
   **Then** ve la lista de destinatarios con su resultado (ok/fallido y motivo).
2. **Given** una campaña con fallidos, **When** el usuario reintenta fallidos, **Then** solo se
   reenvía a los destinatarios en estado fallido y el resumen se actualiza.

---

### Edge Cases

- **Cliente sin email**: un cliente seleccionado que no tiene dirección de email se marca como
  fallido con motivo "sin email" y no interrumpe el resto del envío.
- **Timeout / corte de conexión a mitad**: si el navegador se cierra o pierde conexión durante
  el envío por tandas, los destinatarios ya enviados quedan registrados como enviados; los no
  procesados quedan como pendientes/no enviados y pueden reintentarse.
- **Doble envío accidental**: reenviar una campaña completa no debe duplicar a quienes ya
  recibieron el correo si el usuario elige "reintentar fallidos"; el reenvío total explícito sí
  reenvía a todos (acción distinta y confirmada).
- **Destinatarios duplicados**: si el mismo cliente aparece dos veces en la selección, se
  envía una sola vez.
- **Campaña sin destinatarios o sin asunto/cuerpo**: el sistema impide enviar y lo indica.
- **Aislamiento de tenant**: un usuario solo puede seleccionar clientes de su propio tenant y
  solo ve plantillas/campañas de su tenant.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir a un usuario del tenant crear una campaña de email
  compuesta por un asunto, un cuerpo con formato (texto enriquecido) y un conjunto de
  destinatarios elegidos entre los clientes del tenant.
- **FR-002**: El sistema DEBE permitir seleccionar múltiples destinatarios desde el listado de
  clientes del tenant (multi-selección), mostrando de forma reconocible los clientes elegidos.
- **FR-003**: El sistema DEBE enviar el correo a cada destinatario reutilizando la
  configuración SMTP por tenant existente, sin depender de colas en segundo plano ni de tareas
  programadas (cron), por compatibilidad con hosting compartido (Principio V).
- **FR-004**: El sistema DEBE procesar el envío en tandas de tamaño acotado, de forma que el
  usuario vea una barra de progreso que avanza sobre el total de destinatarios y la interfaz no
  quede bloqueada hasta el final.
- **FR-005**: El sistema DEBE devolver y mostrar el resultado del envío por destinatario
  (enviado / fallido + motivo del fallo), no un único resultado global.
- **FR-006**: El sistema DEBE continuar con el resto de destinatarios aunque uno o varios
  fallen (un fallo individual no aborta la campaña).
- **FR-007**: El sistema DEBE impedir el envío cuando el tenant no tiene SMTP configurado, o la
  campaña no tiene destinatarios, asunto o cuerpo, informando el motivo.
- **FR-008**: El sistema DEBE registrar cada envío en el histórico de eventos, con al menos:
  destinatario, resultado (ok/error) y, en su caso, el motivo del error; reutilizando el patrón
  de eventos existente (`envio_email` u equivalente) y respetando que estos eventos no
  participan del encadenamiento Verifactu.
- **FR-009**: El sistema DEBE permitir gestionar plantillas de email reutilizables (crear,
  editar, activar/desactivar, eliminar) con al menos título, asunto y cuerpo, y fecha de
  modificación visible en el listado.
- **FR-010**: El sistema DEBE permitir iniciar una campaña a partir de una plantilla activa,
  precargando asunto y cuerpo de forma editable; las plantillas inactivas no se ofrecen.
- **FR-011**: El sistema DEBE permitir consultar el detalle de una campaña enviada con el
  desglose de resultado por destinatario.
- **FR-012**: El sistema DEBE permitir reintentar el envío únicamente a los destinatarios en
  estado fallido de una campaña, sin reenviar a los ya enviados con éxito.
- **FR-013**: El sistema DEBE deduplicar destinatarios repetidos para enviar una sola vez por
  dirección/cliente dentro de una misma campaña.
- **FR-014**: Todos los datos de campañas, plantillas y sus destinatarios/resultados DEBEN
  estar aislados por tenant (Principio I): un usuario solo accede a los de su propio tenant.
- **FR-015**: El sistema DEBE aplicar el envío únicamente sobre clientes con dirección de email
  válida; un cliente sin email se registra como fallido con motivo "sin email" en lugar de
  intentar el envío.

### Key Entities *(include if feature involves data)*

- **Campaña de email**: representa un envío masivo. Atributos: asunto, cuerpo, estado del envío
  (borrador / en curso / finalizada), fecha de creación y de envío, autor, tenant. Relacionada
  con sus destinatarios y, opcionalmente, con la plantilla de origen.
- **Destinatario de campaña**: cada cliente incluido en una campaña y el resultado de su envío.
  Atributos: referencia al cliente, dirección de email usada, estado (pendiente / enviado /
  fallido), motivo del fallo, marca temporal del intento.
- **Plantilla de email**: contenido reutilizable. Atributos: título, asunto, cuerpo, estado
  (activa / inactiva), fecha de modificación, tenant.
- **Evento de envío (histórico)**: registro append-only por cada intento de envío, con
  destinatario, resultado (ok/error) y motivo; reutiliza el patrón de `factura_eventos` /
  `envio_email` sin participar del encadenamiento Verifactu.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede crear y enviar una campaña a 10 clientes en menos de 3 minutos
  desde que abre la pantalla de nueva campaña.
- **SC-002**: En una campaña de hasta 50 destinatarios en hosting compartido, ningún envío
  individual provoca un tiempo de espera bloqueante perceptible; el progreso es visible de
  forma continua durante todo el proceso.
- **SC-003**: El 100% de los envíos, tanto exitosos como fallidos, quedan reflejados por
  destinatario en el resultado mostrado al usuario y en el histórico.
- **SC-004**: Tras una campaña con fallos, el usuario puede reintentar solo los fallidos y el
  sistema no reenvía a ninguno de los ya entregados con éxito (0 duplicados no deseados).
- **SC-005**: En pruebas de aislamiento con ≥2 tenants, ningún usuario puede ver ni enviar a
  clientes, plantillas o campañas de otro tenant (0 fugas).

## Assumptions

- Se reutiliza la infraestructura SMTP por tenant de la feature 017 (`TenantMailer` /
  `EmailTenant`) tal como está; este módulo no cambia cómo se configura el SMTP.
- El tamaño de tanda por defecto es de 5 a 10 destinatarios por petición; el valor concreto se
  fija en la fase de plan y puede ajustarse según el tiempo real de envío del proveedor SMTP.
- El envío es saliente y transaccional simple (asunto + cuerpo HTML); quedan fuera del alcance
  de esta feature: bandeja de entrada / lectura de correos (inbox/read del template), analítica
  de aperturas/clics, gestión de listas de suscripción/bajas, adjuntos por campaña y
  programación de envíos a futuro (requeriría cron/colas, descartado por Principio V).
- La selección de destinatarios se basa en el listado de clientes existente; no se introduce un
  gestor de contactos independiente.
- El cuerpo se compone con el editor de texto enriquecido ya disponible en el banco de piezas
  del template; no se incorpora un constructor visual de bloques (drag & drop).
- Los importes/impuestos/Verifactu no intervienen en esta feature; los eventos de envío no
  forman parte del encadenamiento Verifactu.
