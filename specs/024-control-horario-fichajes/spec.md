# Feature Specification: Control horario y fichajes con geolocalización

**Feature Branch**: `024-control-horario-fichajes`

**Created**: 2026-07-05

**Status**: Draft

**Input**: User description: "Módulo de control horario / fichajes con geolocalización. Base normativa en docs/07-control-horario-espana.md. Los empleados fichan entrada/salida (y posiblemente pausas) desde una pantalla con un mapa que muestra su ubicación en tiempo real y el perímetro autorizado. Al fichar se captura la posición del instante y el backend valida si está dentro del radio; la hora la fija el servidor. Registro append-only inmutable, correcciones como evento nuevo enlazado. Multi-tenant. Retención: jornada 4 años, geo minimizada con retención corta configurable y purga. Prohibido rastreo continuo y biometría. Preparado para la reforma del registro digital sin envío API a Inspección todavía."

## Clarifications

### Session 2026-07-05

- Q: Nivel de detalle del dato de geolocalización que se almacena → A: Máxima minimización — solo veredicto dentro/fuera + id de ubicación + precisión reportada; NO se guardan coordenadas crudas.
- Q: ¿Quién es "empleado"? → A: Cada cuenta de usuario del tenant es un empleado y ficha por sí misma (no hay entidad `empleado` separada ni fichaje por un responsable).
- Q: ¿El geofencing es bloqueante o informativo? → A: Informativo por defecto (permite fichar fuera y lo marca "fuera de ubicación"), configurable a bloqueante por tenant.
- Q: ¿Se registran pausas en el MVP? → A: Entrada/salida obligatorias + pausas opcionales (modelo listo desde el diseño, activable por tenant).
- Q: ¿El software gestiona el consentimiento informado / EIPD? → A: Fuera de alcance — proceso externo del tenant; el módulo solo muestra los textos informativos necesarios.

### Session 2026-07-05 (revisión de alcance — miembros de equipo y alertas)

- Q: ¿Quién ficha, la cuenta de usuario directa o una entidad de empleado? → A: **Revisa la Q2 anterior.** Se introduce una entidad **Miembro de equipo** (perfil de empleado) vinculada **1:1** a una cuenta de usuario con login; el miembro ficha por sí mismo. Los fichajes cuelgan del miembro de equipo, no del `user` directo.
- Q: ¿Contra qué perímetro se valida el fichaje? → A: **Por miembro.** Cada miembro almacena su **ubicación de trabajo** (coordenadas) y su **distancia máxima permitida** para fichar. Se elimina la tabla compartida de ubicaciones; la validación Haversine es contra la ubicación de trabajo del propio miembro.
- Q: ¿Para qué se usa la dirección de casa del miembro? → A: Para **calcular la distancia casa-trabajo** como métrica del miembro; NO interviene en la validación del fichaje. Es dato personal sujeto a minimización/retención.
- Q: ¿Qué dispara una alerta? → A: Al fichar a una distancia **mayor** que la distancia máxima permitida del miembro, el sistema **crea una alerta** ligada a ese fichaje, visible para administración.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Fichar entrada y salida con verificación de ubicación (Priority: P1)

Una persona trabajadora abre la pantalla de fichaje desde su móvil al llegar al centro de trabajo. Ve un mapa con su posición actualizándose en tiempo real y el perímetro autorizado dibujado como un círculo. Pulsa "Fichar entrada": el sistema registra el evento con la hora del servidor, comprueba si la posición está dentro del radio autorizado y deja constancia del resultado. Al terminar la jornada repite la acción con "Fichar salida".

**Why this priority**: Es el núcleo legal del módulo y la obligación firme del art. 34.9 ET (registro diario de entrada y salida). Sin esto no hay producto: es el MVP mínimo que cumple la ley.

**Independent Test**: Se puede probar de forma aislada creando una persona trabajadora y una ubicación con radio, fichando entrada y salida desde dentro y desde fuera del perímetro, y verificando que se crean dos eventos inmutables con hora de servidor y con la marca dentro/fuera correcta.

**Acceptance Scenarios**:

1. **Given** una persona trabajadora sin fichaje abierto y dentro del perímetro autorizado, **When** ficha entrada, **Then** se registra un evento de entrada con la hora del servidor y marcado como "dentro de ubicación".
2. **Given** una persona trabajadora con una entrada abierta, **When** ficha salida, **Then** se registra un evento de salida enlazado a la jornada y la jornada queda cerrada.
3. **Given** una persona trabajadora fuera del perímetro autorizado, **When** ficha, **Then** el evento se registra marcado como "fuera de ubicación" para revisión (no se pierde el fichaje).
4. **Given** un intento de fichaje, **When** el navegador no concede permiso de geolocalización, **Then** el sistema informa del motivo y registra el fichaje como "sin ubicación" (según política configurada), nunca inventando una posición.
5. **Given** cualquier fichaje registrado, **When** se intenta editarlo o borrarlo, **Then** la operación se rechaza: el registro es inmutable.

---

### User Story 2 - Consultar y exportar el registro de jornada (Priority: P1)

Una persona responsable (administración del tenant) consulta los registros de jornada de la plantilla por empleado y periodo, ve el total de horas calculado a partir de los eventos y puede exportar los registros de forma legible y completa para entregarlos a la persona trabajadora, a la representación legal o a la Inspección de Trabajo.

**Why this priority**: La ley (art. 34.9 ET) exige que los registros estén disponibles y se puedan entregar. Un registro que no se puede consultar ni exportar no cumple. Es igual de crítico que registrar.

**Independent Test**: Con varios fichajes ya registrados, generar el informe de jornada de un empleado en un rango de fechas y verificar que el total de horas se calcula en backend a partir de los eventos y que la exportación contiene todos los registros del periodo.

**Acceptance Scenarios**:

1. **Given** una persona trabajadora con fichajes en un periodo, **When** administración consulta su registro de jornada, **Then** ve los eventos ordenados y el total de horas efectivas calculado por el sistema.
2. **Given** un registro de jornada de un periodo, **When** se exporta, **Then** el archivo generado contiene todos los eventos del periodo de forma legible y completa, incluidas las correcciones y su motivo.
3. **Given** registros de dos tenants distintos, **When** administración de un tenant consulta o exporta, **Then** nunca aparecen registros del otro tenant.

---

### User Story 3 - Corregir un fichaje dejando rastro (Priority: P2)

Una persona con permiso corrige un fichaje erróneo (p. ej. una salida olvidada). El sistema no edita el evento original: crea un evento de corrección nuevo, enlazado al original, con el valor anterior, el nuevo valor, el motivo obligatorio, y quién y cuándo la hizo. El informe refleja el valor corregido pero conserva la trazabilidad completa.

**Why this priority**: La inalterabilidad con trazabilidad de correcciones es requisito normativo (capa vigente y reforma digital). Sin embargo, el registro básico ya cumple sin correcciones automáticas, por lo que es P2.

**Independent Test**: Registrar un fichaje, aplicarle una corrección con motivo, y verificar que el evento original permanece intacto, que existe un evento de corrección enlazado con valor anterior/nuevo/motivo/autor, y que el informe usa el valor corregido.

**Acceptance Scenarios**:

1. **Given** un fichaje registrado, **When** una persona autorizada lo corrige indicando motivo, **Then** se crea un evento de corrección enlazado y el original no se modifica ni se borra.
2. **Given** un intento de corrección sin motivo, **When** se envía, **Then** el sistema lo rechaza (el motivo es obligatorio).
3. **Given** una persona sin permiso de corrección, **When** intenta corregir, **Then** la acción se deniega.

---

### User Story 4 - Gestionar miembros de equipo y su ubicación de trabajo (Priority: P2)

Administración del tenant da de alta miembros de equipo (empleados), cada uno vinculado 1:1 a una cuenta de usuario con login. Para cada miembro define su ubicación de trabajo (coordenadas, ayudándose de un mapa), su distancia máxima permitida para fichar y su dirección de casa (para calcular la distancia casa-trabajo). Estos datos son los que se usan para validar los fichajes de ese miembro y para las métricas.

**Why this priority**: Sin miembros con su ubicación de trabajo y su tolerancia no hay contra qué validar el fichaje ni disparar alertas; es la base de configuración del módulo.

**Independent Test**: Crear un miembro con user vinculado, ubicación de trabajo y distancia máxima, y comprobar que un fichaje dentro de esa distancia se marca "dentro" y uno más lejano se marca "fuera" y genera alerta.

**Acceptance Scenarios**:

1. **Given** administración del tenant, **When** crea un miembro con user, ubicación de trabajo, distancia máxima y dirección de casa, **Then** el miembro queda listo para fichar y se calcula su distancia casa-trabajo.
2. **Given** un miembro con distancia máxima definida, **When** ficha a una distancia mayor, **Then** el fichaje se marca "fuera de ubicación".
3. **Given** miembros de dos tenants, **When** se valida un fichaje, **Then** solo se consideran los datos del miembro del tenant correspondiente.

---

### User Story 6 - Alertas por fichaje fuera de rango (Priority: P2)

Cuando un miembro ficha a una distancia mayor que su distancia máxima permitida, el sistema crea automáticamente una alerta ligada a ese fichaje. Administración consulta las alertas (quién, cuándo, qué fichaje, a qué distancia), y puede marcarlas como vistas/resueltas.

**Why this priority**: Es el valor diferencial que pidió el negocio (detectar fichajes anómalos), pero depende de que exista el fichaje con su veredicto de distancia (US1) y la configuración del miembro (US4).

**Independent Test**: Fichar por encima de la distancia máxima de un miembro y verificar que se crea una alerta enlazada al fichaje con la distancia registrada; fichar dentro y verificar que no se crea ninguna.

**Acceptance Scenarios**:

1. **Given** un miembro con distancia máxima, **When** ficha más lejos que esa distancia, **Then** se crea una alerta enlazada a ese fichaje con la distancia registrada.
2. **Given** un fichaje dentro de la distancia permitida, **When** se registra, **Then** no se crea ninguna alerta.
3. **Given** una alerta creada, **When** administración la marca como resuelta, **Then** cambia de estado sin borrarse.
4. **Given** alertas de dos tenants, **When** administración de uno las consulta, **Then** nunca ve las del otro.

---

### User Story 5 - Portal de la persona trabajadora (Priority: P3)

Cada persona trabajadora accede a sus propios registros de jornada, los consulta y descarga, ejerciendo el derecho de acceso del art. 34.9 ET, sin ver los de otras personas.

**Why this priority**: Es un derecho legal, pero el cumplimiento mínimo se alcanza si administración puede entregar los registros; el autoservicio mejora la experiencia y es P3.

**Independent Test**: Con una persona trabajadora autenticada, consultar y descargar únicamente sus propios registros y verificar que no puede acceder a los de otra persona.

**Acceptance Scenarios**:

1. **Given** una persona trabajadora autenticada, **When** consulta sus registros, **Then** ve solo los suyos.
2. **Given** una persona trabajadora, **When** intenta acceder a los registros de otra, **Then** se le deniega.

---

### Edge Cases

- **Doble entrada / estado inconsistente**: ¿Qué pasa si se ficha entrada teniendo ya una entrada abierta, o salida sin entrada previa? El sistema debe impedir o marcar el estado inconsistente sin perder el evento.
- **Fichaje a medianoche / jornada partida**: una jornada que cruza dos días o con varias entradas/salidas en el mismo día debe reflejarse correctamente en el total.
- **Permiso de geolocalización denegado o no disponible** (navegador sin HTTPS, dispositivo sin GPS): el fichaje se comporta según la política configurada (bloqueante o informativa), nunca fabricando una posición.
- **Posición de baja precisión** (desktop por wifi/IP): el sistema conserva la precisión reportada para poder juzgar la fiabilidad del "dentro/fuera".
- **Reloj del cliente manipulado**: la hora de referencia siempre es la del servidor; la hora del cliente se ignora como fuente de verdad.
- **Retención**: al vencer el plazo de retención de la geo, el dato de ubicación se purga pero el registro de jornada (4 años) permanece.
- **Persona trabajadora dada de baja**: sus registros históricos se conservan durante el plazo legal aunque ya no fiche.
- **Miembro sin ubicación de trabajo configurada**: el sistema debe comportarse de forma definida (registrar como "sin ubicación", sin alerta de distancia).
- **Usuario que ficha sin ser miembro de equipo**: un `user` sin perfil de miembro no puede fichar (o se le indica que falta su alta como miembro).
- **Dirección de casa ausente**: la distancia casa-trabajo queda sin calcular; no bloquea el fichaje.

## Requirements *(mandatory)*

### Functional Requirements

**Registro de jornada (núcleo)**

- **FR-001**: El sistema DEBE permitir a una persona trabajadora registrar eventos de fichaje de tipo entrada y salida, y de forma opcional inicio y fin de pausa.
- **FR-002**: El sistema DEBE fijar la hora de cada fichaje con el reloj del servidor; la hora enviada por el cliente NUNCA es fuente de verdad.
- **FR-003**: Cada fichaje DEBE ser inmutable: no se puede editar ni borrar una vez registrado (registro append-only tipo ledger).
- **FR-004**: El sistema DEBE calcular en backend el total de horas efectivas de una persona trabajadora en un periodo a partir de sus eventos, sin depender de cálculos del cliente.
- **FR-005**: El sistema DEBE conservar los registros de jornada durante 4 años (art. 34.9 ET) y no purgarlos antes de ese plazo.
- **FR-006**: El sistema DEBE evitar o marcar estados inconsistentes de la secuencia de eventos (p. ej. salida sin entrada, doble entrada) sin descartar el evento.

**Geolocalización y perímetro**

- **FR-007**: La pantalla de fichaje DEBE mostrar un mapa con la posición de la persona trabajadora actualizándose en tiempo real mientras la pantalla está abierta, únicamente en el cliente.
- **FR-008**: La pantalla de fichaje DEBE mostrar el perímetro autorizado (centro y radio) sobre el mapa.
- **FR-009**: El sistema DEBE capturar la posición únicamente en el instante del fichaje y NUNCA almacenar un rastro continuo de posición.
- **FR-010**: El backend DEBE determinar si la posición del fichaje está dentro del radio autorizado; esta determinación NO puede delegarse en el cliente.
- **FR-011**: El sistema DEBE permitir dar de alta miembros de equipo, cada uno vinculado 1:1 a una cuenta de usuario con login, con su ubicación de trabajo (coordenadas), su distancia máxima permitida para fichar y su dirección de casa.
- **FR-011a**: El backend DEBE validar cada fichaje calculando la distancia (Haversine) entre la posición del fichaje y la ubicación de trabajo del propio miembro, comparándola con su distancia máxima permitida.
- **FR-011b**: El sistema DEBE calcular y exponer la distancia casa-trabajo de cada miembro como métrica; la dirección de casa NO interviene en la validación del fichaje.
- **FR-012**: Cuando no se concede o no está disponible la geolocalización, el sistema DEBE registrar el fichaje según la política configurada (bloqueante o informativa) sin fabricar una posición.
- **FR-013**: El sistema NO DEBE usar biometría (huella, reconocimiento facial) como método de fichaje.
- **FR-013a**: El geofencing DEBE ser informativo por defecto (permite fichar fuera del radio marcándolo "fuera de ubicación") y DEBE poder configurarse como bloqueante por tenant.

**Alertas de fichaje**

- **FR-013b**: El sistema DEBE crear automáticamente una alerta ligada al fichaje cuando un miembro fiche a una distancia mayor que su distancia máxima permitida, registrando la distancia del fichaje.
- **FR-013c**: El sistema NO DEBE crear alerta cuando el fichaje está dentro de la distancia permitida.
- **FR-013d**: Administración DEBE poder consultar las alertas del tenant (miembro, fichaje, fecha, distancia) y cambiar su estado (p. ej. nueva → vista → resuelta) sin borrarlas.

**Correcciones y trazabilidad**

- **FR-014**: Una corrección de un fichaje DEBE materializarse como un evento nuevo enlazado al original, conservando valor anterior, valor nuevo, motivo obligatorio, autor y fecha; nunca como una edición in-place.
- **FR-015**: El sistema DEBE restringir la corrección de fichajes a personas con el permiso correspondiente.
- **FR-016**: Los informes DEBEN reflejar el valor corregido pero conservar y poder mostrar la traza completa de correcciones.

**Consulta, exportación y portal**

- **FR-017**: Administración DEBE poder consultar los registros de jornada por persona trabajadora y periodo.
- **FR-018**: El sistema DEBE permitir exportar los registros de un periodo de forma legible y completa, incluyendo correcciones, para su entrega a la persona trabajadora, la representación legal o la Inspección.
- **FR-019**: Cada persona trabajadora DEBE poder consultar y descargar sus propios registros y NO los de otras personas.

**Multi-tenant, retención y privacidad (RGPD/LOPDGDD)**

- **FR-020**: Toda entidad del módulo (fichajes, miembros de equipo, alertas y relacionadas) DEBE estar aislada por tenant; ninguna consulta puede exponer datos de otro tenant.
- **FR-021**: El dato de geolocalización asociado al fichaje DEBE almacenarse minimizado: únicamente el veredicto dentro/fuera, el identificador de la ubicación evaluada y la precisión reportada. El sistema NO DEBE almacenar coordenadas crudas del fichaje. Aun así, ese dato tiene un plazo de retención corto configurable por tenant con purga periódica, reutilizando el patrón de retención/purga ya existente en el proyecto.
- **FR-022**: El sistema DEBE separar el plazo de conservación del registro de jornada (4 años) del plazo de retención del dato de geolocalización (corto, configurable): purgar la geo NO elimina el registro de jornada.
- **FR-022a**: La dirección de casa del miembro es dato personal y DEBE minimizarse: solo se conserva mientras el miembro esté activo y con la finalidad de calcular la distancia casa-trabajo; al dar de baja al miembro DEBE purgarse conforme a un plazo configurable, reutilizando el patrón de retención/purga del proyecto.
- **FR-023**: El sistema DEBE registrar los accesos a los datos personales de jornada/geo conforme al patrón de registro de actividad del proyecto (autorizado/denegado).

**Preparación para la reforma (sin sobre-construir)**

- **FR-024**: El modelo DEBE quedar preparado para la reforma del registro digital (inalterabilidad y exportación verificable) sin implementar todavía el envío automático a la Inspección, cuya especificación técnica aún no está publicada.
- **FR-025**: La gestión formal de la EIPD y del consentimiento informado queda FUERA del alcance del módulo (es proceso del tenant como responsable del tratamiento); el sistema solo DEBE mostrar los textos informativos de privacidad necesarios en la pantalla de fichaje, sin registrar constancia de aceptación.

### Key Entities *(include if feature involves data)*

- **Miembro de equipo (empleado)**: quien ficha. Perfil de empleado vinculado **1:1** a una cuenta de usuario con login, dentro de un tenant. Atributos: ubicación de trabajo (coordenadas), distancia máxima permitida para fichar, dirección de casa (dato personal) y distancia casa-trabajo calculada. El fichaje cuelga del miembro.
- **Fichaje (evento de jornada)**: evento inmutable con tenant, miembro de equipo, tipo (entrada/salida/inicio_pausa/fin_pausa), hora de servidor y resultado de la verificación de ubicación (dentro/fuera/sin ubicación). Enlaza a la jornada.
- **Dato de geolocalización del fichaje**: veredicto dentro/fuera + distancia al trabajo + precisión reportada (sin coordenadas crudas), con retención corta e independiente, purgable.
- **Alerta**: registro creado automáticamente cuando un fichaje supera la distancia máxima del miembro; enlaza al fichaje y al miembro, guarda la distancia, y tiene estado (nueva/vista/resuelta). No se borra.
- **Corrección de fichaje**: evento nuevo enlazado a un fichaje, con valor anterior, valor nuevo, motivo, autor y fecha.
- **Configuración de retención (por tenant)**: plazos tras los cuales se purgan el dato de geolocalización del fichaje y la dirección de casa de miembros dados de baja.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una persona trabajadora puede completar un fichaje (abrir pantalla, ver su posición en el mapa y confirmar) en menos de 15 segundos en un móvil típico.
- **SC-002**: El 100 % de los fichajes quedan sellados con la hora del servidor, verificable porque ningún registro depende de la hora del cliente.
- **SC-003**: El 100 % de los intentos de editar o borrar un fichaje existente son rechazados.
- **SC-004**: Administración puede generar y exportar el registro de jornada de cualquier persona trabajadora para cualquier periodo, con la totalidad de los eventos y sus correcciones incluidos.
- **SC-005**: En pruebas con al menos dos tenants, el 0 % de las consultas/exportaciones exponen registros de otro tenant.
- **SC-006**: Los datos de geolocalización se purgan automáticamente al vencer el plazo configurado, mientras el 100 % de los registros de jornada afectados se conservan hasta los 4 años.
- **SC-007**: Cada corrección conserva de forma verificable el valor anterior, el nuevo, el motivo y el autor, sin alterar el evento original.
- **SC-008**: El 100 % de los fichajes que superan la distancia máxima del miembro generan exactamente una alerta enlazada al fichaje, y el 0 % de los fichajes dentro de la distancia generan alerta.

## Assumptions

Decisiones confirmadas en `/speckit-clarify` (ver sección Clarifications) y defaults informados por §4 de `docs/07-control-horario-espana.md`:

- **Qué se guarda de la geo (§4.1)** — *confirmado*: nivel mínimo, solo veredicto dentro/fuera + id de ubicación + precisión reportada; sin coordenadas crudas.
- **Quién es empleado (§4.6)** — *revisado*: entidad **Miembro de equipo** (perfil de empleado) vinculada 1:1 a una cuenta de usuario con login; el miembro ficha por sí mismo. Sustituye a la decisión previa de "empleado = user directo".
- **Perímetro (§4.3, revisado)** — *confirmado*: **por miembro**. Cada miembro tiene su ubicación de trabajo y su distancia máxima permitida; se elimina la tabla compartida de ubicaciones de trabajo.
- **Dirección de casa** — *confirmado*: se usa solo para calcular la distancia casa-trabajo (métrica); no valida el fichaje. Dato personal con retención/purga al dar de baja al miembro.
- **Alertas** — *confirmado*: fichar por encima de la distancia máxima del miembro crea una alerta ligada al fichaje, con estado gestionable por administración.
- **Geofencing (§4.2)** — *confirmado*: informativo por defecto (se marca "fuera de ubicación" y se genera alerta), configurable a bloqueante por tenant.
- **Pausas (§4.5)** — *confirmado*: entrada/salida obligatorias + pausas opcionales (modelo listo, activable por tenant).
- **EIPD e información previa (§4.10)** — *confirmado*: fuera de alcance del software (proceso del tenant); el módulo solo muestra textos informativos, sin registrar constancia de aceptación.
- **Teletrabajo (§4.4)** — default: cubierto por el modo informativo; un fichaje lejos del trabajo se registra y se marca (y genera alerta), no se pierde.
- **Retención de geo (§4.7)** — default: plazo corto configurable por tenant (p. ej. 30 días), reutilizando `RetencionLogsTenant` + comando de purga programado.
- **Correcciones (§4.8)** — default: evento nuevo enlazado con motivo obligatorio; permiso restringido a rol de administración.
- **Portal del trabajador (§4.9)** — default: incluido como P3 (derecho de acceso del art. 34.9 ET); el cumplimiento mínimo se logra ya con la exportación por administración.
- El fichaje se realiza principalmente desde **móvil** (mejor precisión GPS); en desktop la precisión puede ser baja y se conserva la precisión reportada.
- La captura de posición y el mapa funcionan sobre **HTTPS**, ya disponible en el hosting.
- El módulo corre en **hosting compartido** sin infraestructura dedicada (Principio V): geofencing por aritmética, sin servicios de mapas de pago ni tiempo real server-side.
