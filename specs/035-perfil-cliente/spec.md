# Feature Specification: Perfil del cliente

**Feature Branch**: `035-perfil-cliente`

**Created**: 2026-07-26

**Status**: Draft

**Input**: User description: "Como usuario del CRM, quiero poder ver un \"Perfil del cliente\" completo desde un botón \"Ver perfil\" en el dropdown de acciones del listado de clientes, que abra una vista/página dedicada con: datos generales, resumen financiero, listado de facturas, listado de presupuestos/cotizaciones, listado de albaranes, oportunidades CRM (pipeline), historial de actividad/timeline y accesos rápidos para crear factura/presupuesto/albarán/oportunidad. La vista debe usar el mismo formato de pestañas (tabs) que ya tiene el perfil de usuario (`resources/views/profile/show.blade.php`)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver ficha completa de un cliente (Priority: P1)

Como usuario con acceso a clientes, desde el listado de clientes quiero abrir el "Ver perfil" de un cliente concreto y ver, en una sola pantalla organizada en pestañas, sus datos generales y un resumen financiero de su relación comercial, para entender de un vistazo quién es el cliente y cómo está su situación de cobro sin tener que buscar esa información en varias pantallas distintas.

**Why this priority**: Es el núcleo del feature — sin esto no hay "perfil de cliente", solo un listado más. Aporta valor por sí solo aunque el resto de pestañas (facturas, presupuestos, etc.) todavía no existieran.

**Independent Test**: Puede probarse entrando al listado de clientes, haciendo clic en "Ver perfil" de un cliente con facturas emitidas, y verificando que la pestaña de datos generales muestra la ficha completa y que el resumen financiero muestra cifras coherentes con las facturas reales de ese cliente.

**Acceptance Scenarios**:

1. **Given** un cliente con facturas emitidas, **When** el usuario hace clic en "Ver perfil" desde el dropdown de acciones del listado de clientes, **Then** se abre una página dedicada con pestañas, mostrando por defecto la pestaña de datos generales (nombre/razón social, NIF, tipo, dirección completa, contacto, recargo de equivalencia, notas).
2. **Given** el perfil de un cliente abierto, **When** el usuario cambia a la pestaña de resumen financiero, **Then** ve el total facturado histórico, el importe pendiente de cobro, el número/importe de facturas vencidas y el ticket medio, calculados sobre las facturas de ese cliente.
3. **Given** un cliente sin ninguna factura emitida, **When** el usuario abre su perfil y va a la pestaña financiera, **Then** el sistema muestra el resumen en cero/vacío con un mensaje claro, sin error.
4. **Given** un usuario sin permiso para ver clientes, **When** intenta acceder directamente a la URL del perfil de un cliente, **Then** el sistema deniega el acceso igual que ya hace hoy con el listado de clientes.
5. **Given** un cliente que pertenece a otro tenant, **When** un usuario autenticado en un tenant distinto intenta acceder a su perfil por URL, **Then** el sistema responde como si el cliente no existiera (404), sin filtrar datos de otro tenant.

---

### User Story 2 - Consultar el historial documental del cliente (facturas, presupuestos, albaranes) (Priority: P2)

Como usuario del CRM, desde el perfil del cliente quiero consultar, en pestañas separadas, el listado de facturas, el de presupuestos/cotizaciones y el de albaranes de ese cliente, con acceso directo a cada documento, para no tener que ir a cada módulo por separado y filtrar manualmente por cliente.

**Why this priority**: Es el complemento natural del resumen financiero: convierte las cifras agregadas en detalle accionable. Depende de que exista la página de perfil (User Story 1) pero es independiente de las pestañas de oportunidades/timeline.

**Independent Test**: Puede probarse abriendo el perfil de un cliente con facturas, presupuestos y albaranes previos, y verificando que cada pestaña lista únicamente los documentos de ese cliente, con enlace a cada uno.

**Acceptance Scenarios**:

1. **Given** un cliente con varias facturas, **When** el usuario abre la pestaña de facturas del perfil, **Then** ve una tabla con número, fecha, importe y estado de cobro de cada factura, ordenada de más reciente a más antigua, y puede acceder al detalle de cada una.
2. **Given** un cliente con presupuestos en distintos estados, **When** el usuario abre la pestaña de presupuestos, **Then** ve cada presupuesto con su estado (pendiente/aceptado/rechazado) y puede acceder a su detalle.
3. **Given** un cliente con albaranes registrados, **When** el usuario abre la pestaña de albaranes, **Then** ve el listado de albaranes de entrega de ese cliente con acceso a cada uno.
4. **Given** un cliente sin presupuestos (o sin albaranes), **When** el usuario abre esa pestaña, **Then** ve un estado vacío claro en lugar de una tabla en blanco o un error.
5. **Given** el usuario no tiene permiso para ver facturas (aunque sí para ver clientes), **When** abre el perfil del cliente, **Then** la pestaña de facturas no muestra datos de facturación (se oculta o se informa que no tiene acceso), respetando los permisos existentes de cada módulo.

---

### User Story 3 - Ver oportunidades CRM y actividad reciente del cliente (Priority: P3)

Como usuario comercial, desde el perfil del cliente quiero ver sus oportunidades de venta (pipeline) y una línea de tiempo con la actividad reciente (facturas, presupuestos, albaranes y oportunidades combinados), para entender rápidamente el estado comercial del cliente y su historial de interacción sin cruzar información de varias pantallas.

**Why this priority**: Aporta valor añadido de visión comercial/CRM, pero depende conceptualmente de que ya existan las pestañas de documentos (User Story 2) para poder combinarlas en una línea de tiempo. Es la pestaña más "agregada" y por tanto la de mayor complejidad relativa.

**Independent Test**: Puede probarse abriendo el perfil de un cliente con oportunidades abiertas y documentos de distintos tipos, y verificando que la pestaña de oportunidades muestra el pipeline correcto y que la pestaña de actividad muestra un feed cronológico mezclando los distintos tipos de evento.

**Acceptance Scenarios**:

1. **Given** un cliente con oportunidades en distintas etapas del pipeline, **When** el usuario abre la pestaña de oportunidades, **Then** ve cada oportunidad con su etapa y valor estimado, y puede acceder a su detalle.
2. **Given** un cliente con facturas, presupuestos, albaranes y oportunidades registrados en fechas distintas, **When** el usuario abre la pestaña de actividad, **Then** ve un único listado cronológico (más reciente primero) que combina esos cuatro tipos de evento, identificando claramente el tipo de cada uno.
3. **Given** un cliente recién creado sin ninguna actividad, **When** el usuario abre la pestaña de actividad, **Then** ve un estado vacío explicando que aún no hay actividad registrada.

---

### User Story 4 - Crear un documento nuevo directamente desde el perfil del cliente (Priority: P3)

Como usuario del CRM, desde el perfil del cliente quiero tener accesos rápidos para crear una nueva factura, presupuesto, albarán u oportunidad para ese mismo cliente, para no tener que volver al listado de clientes ni volver a buscar/seleccionar el cliente en el formulario de alta.

**Why this priority**: Es una mejora de eficiencia sobre un flujo que ya existe hoy desde el listado de clientes (los accesos "+ Nueva oportunidad" / "+ Nuevo albarán" del dropdown); replicarlo en el perfil es conveniente pero no bloquea el valor central del feature (ver información del cliente).

**Independent Test**: Puede probarse abriendo el perfil de un cliente y haciendo clic en cada acceso rápido, verificando que lleva al formulario de alta correspondiente con el cliente ya preseleccionado.

**Acceptance Scenarios**:

1. **Given** el perfil de un cliente abierto, **When** el usuario hace clic en el acceso rápido "Nueva factura" (o presupuesto/albarán/oportunidad), **Then** se abre el flujo de alta correspondiente con el cliente ya preseleccionado, igual que ocurre hoy al usar esos mismos accesos desde el listado de clientes.
2. **Given** un usuario sin permiso para crear un tipo de documento concreto (p. ej. sin permiso para crear facturas), **When** abre el perfil del cliente, **Then** ese acceso rápido no se muestra, respetando los permisos existentes de cada módulo.

---

### Edge Cases

- ¿Qué pasa si el cliente tiene un volumen muy alto de facturas/presupuestos/albaranes/oportunidades? Cada listado de pestaña debe paginarse (no cargar todo el historial de una vez) para no degradar el tiempo de carga del perfil.
- ¿Qué pasa si el cliente fue eliminado (soft delete) mientras alguien tiene su perfil abierto en otra pestaña del navegador? Las acciones de crear documento nuevo desde ese perfil deben fallar de forma controlada, no crear un documento huérfano.
- ¿Qué pasa si un cliente tiene documentos (facturas, presupuestos, albaranes) cuyo importe está en distintas monedas o con impuestos distintos? El resumen financiero y el ticket medio se calculan sobre el importe total en la moneda/base de facturación estándar del tenant, consistente con cómo se calculan hoy los totales en el módulo de facturación.
- ¿Qué pasa si el usuario tiene permiso para ver clientes pero no para ver ninguno de los módulos relacionados (facturas, presupuestos, albaranes, oportunidades)? El perfil se muestra igualmente con la pestaña de datos generales, y las pestañas sin permiso quedan ocultas (puede quedar solo la pestaña de datos generales visible).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE ofrecer una acción "Ver perfil" en el menú de acciones de cada fila del listado de clientes, visible para cualquier usuario con permiso para ver clientes.
- **FR-002**: El sistema DEBE proveer una página dedicada de perfil de cliente, accesible por URL propia (no solo mediante un modal), que muestre información de un único cliente.
- **FR-003**: La página de perfil DEBE organizarse en pestañas dentro de la misma pantalla, siguiendo el mismo patrón visual e interactivo ya usado en el perfil de usuario (`resources/views/profile/show.blade.php`): pestañas de navegación con contenido asociado que se alterna sin recargar la página.
- **FR-004**: La pestaña de datos generales DEBE mostrar: nombre/razón social, tipo de cliente, NIF, dirección completa, email, teléfono, si aplica recargo de equivalencia, y notas internas.
- **FR-005**: La pestaña de resumen financiero DEBE mostrar, calculados sobre las facturas del cliente: total facturado histórico, importe pendiente de cobro, facturas vencidas (cantidad e importe) y ticket medio.
- **FR-006**: La pestaña de facturas DEBE listar las facturas del cliente (número, fecha, importe, estado de cobro), paginadas y ordenadas de más reciente a más antigua, con acceso al detalle de cada factura.
- **FR-007**: La pestaña de presupuestos DEBE listar los presupuestos/cotizaciones del cliente con su estado, paginados, con acceso al detalle de cada uno.
- **FR-008**: La pestaña de albaranes DEBE listar los albaranes de entrega del cliente, paginados, con acceso al detalle de cada uno.
- **FR-009**: La pestaña de oportunidades DEBE listar las oportunidades CRM del cliente con su etapa del pipeline y valor estimado, con acceso al detalle de cada una.
- **FR-010**: La pestaña de actividad DEBE mostrar una línea de tiempo cronológica (más reciente primero) que combine eventos de facturas, presupuestos, albaranes y oportunidades del cliente, identificando el tipo de cada evento.
- **FR-011**: La página de perfil DEBE ofrecer accesos rápidos para crear una nueva factura, presupuesto, albarán u oportunidad, preseleccionando el cliente actual, reutilizando los mismos flujos de alta que ya existen desde el listado de clientes.
- **FR-012**: El sistema DEBE restringir el acceso a la página de perfil de cliente al mismo permiso que ya protege hoy el listado de clientes (`ver-clientes`); sin ese permiso, el acceso directo por URL debe denegarse.
- **FR-013**: Cada pestaña o sección relacionada con otro módulo (facturas, presupuestos, albaranes, oportunidades) DEBE respetar el permiso propio de ese módulo (p. ej. `ver-facturas`, `ver-presupuestos`, `ver-albaranes`, `ver-oportunidades`): si el usuario no tiene el permiso del módulo, esa pestaña y sus datos no se muestran, aunque sí tenga permiso para ver el cliente.
- **FR-014**: Cada acceso rápido de creación (FR-011) DEBE respetar el permiso de creación propio de ese módulo; si el usuario no tiene permiso para crear ese tipo de documento, el acceso rápido correspondiente no se muestra.
- **FR-015**: El sistema DEBE garantizar que un usuario solo puede ver el perfil de clientes que pertenecen a su propio tenant; el acceso a un cliente de otro tenant (por URL directa) DEBE tratarse como recurso inexistente.
- **FR-016**: Cuando un cliente no tenga datos en una sección determinada (sin facturas, sin presupuestos, sin albaranes, sin oportunidades, sin actividad), esa pestaña DEBE mostrar un estado vacío explicativo en vez de un listado en blanco o un error.

### Key Entities *(include if feature involves data)*

- **Cliente**: entidad ya existente; pasa a ser el punto de entrada de un perfil agregado que reúne su propia ficha y sus documentos/relaciones comerciales.
- **Factura, Presupuesto, Albarán, Oportunidad**: entidades ya existentes que hoy se relacionan con Cliente mediante un identificador de cliente, pero sin una relación inversa navegable desde Cliente; el perfil requiere poder listar, para un cliente dado, todos los registros de cada una de estas entidades que le pertenecen.
- **Resumen financiero del cliente**: no es una entidad persistida, sino un cálculo agregado (a partir de Factura) que se muestra en el perfil: total facturado, pendiente de cobro, facturas vencidas, ticket medio.
- **Actividad del cliente**: no es una entidad persistida, sino una vista combinada y ordenada cronológicamente de eventos provenientes de Factura, Presupuesto, Albarán y Oportunidad asociados a un mismo cliente.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede llegar desde el listado de clientes hasta ver los datos generales y el resumen financiero completo de un cliente en 2 clics o menos (clic en "Acciones" + clic en "Ver perfil").
- **SC-002**: Un usuario puede consultar el estado de cobro y el historial documental (facturas, presupuestos, albaranes, oportunidades) de un cliente sin salir de una única pantalla, en menos de 30 segundos desde que abre su perfil.
- **SC-003**: El 100% de los importes mostrados en el resumen financiero del perfil coincide con la suma real de las facturas del cliente en el módulo de facturación (sin discrepancias).
- **SC-004**: Un usuario puede iniciar el alta de una factura, presupuesto, albarán u oportunidad para un cliente concreto directamente desde su perfil, sin tener que volver a buscar o seleccionar ese cliente en el formulario de alta.
- **SC-005**: Ningún usuario puede ver, desde el perfil de un cliente, datos de un módulo (facturas, presupuestos, albaranes, oportunidades) para el que no tiene permiso, ni datos de clientes de un tenant distinto al suyo.

## Assumptions

- El perfil de cliente es de solo lectura respecto a los datos del propio cliente; la edición de la ficha del cliente sigue haciéndose mediante el modal de edición ya existente en el listado (no se duplica un formulario de edición dentro del perfil en esta iteración).
- Los permisos de módulo ya existentes (`ver-facturas`, `ver-presupuestos`, `ver-albaranes`, `ver-oportunidades` y sus equivalentes de creación) son la única fuente de verdad para decidir qué pestañas y accesos rápidos se muestran; esta feature no introduce permisos nuevos.
- El resumen financiero se calcula en el momento de cargar el perfil (sin necesidad de una tabla de cifras precalculadas/cacheadas), asumiendo que el volumen de facturas por cliente es razonable para un cálculo en caliente.
- "Facturas vencidas" se refiere a facturas con importe pendiente de cobro cuya fecha de vencimiento ya pasó, siguiendo la misma noción de vencimiento que ya usa el módulo de facturación.
- La línea de tiempo de actividad (User Story 3) muestra los eventos más recientes primero y, al igual que los listados de documentos, se pagina o limita para no degradar el rendimiento en clientes con mucho historial.
