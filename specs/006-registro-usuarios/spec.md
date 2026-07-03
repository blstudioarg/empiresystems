# Feature Specification: Registro y aprobación de usuarios

**Feature Branch**: `006-registro-usuarios`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: "Vista de usuarios (lista + cards informativas) y vista de registro basada en la página page-register del template NexaDash. Flujo: al registrarse se crea el usuario en estado 'pendiente' (solicitante) y no puede loguearse; un usuario existente lo aprueba desde la tabla de usuarios y a partir de ahí el usuario puede iniciar sesión. Multi-tenant (tenant_id, respetar el global scope de tenant)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registro de un nuevo solicitante (Priority: P1)

Una persona que aún no tiene acceso al CRM abre la página pública de registro, introduce
sus datos (nombre, email, contraseña) y envía el formulario. El sistema crea su cuenta en
estado **pendiente**: la cuenta existe pero todavía no puede iniciar sesión. La persona ve
un mensaje claro de que su solicitud quedó registrada y debe esperar aprobación.

**Why this priority**: Es la puerta de entrada del flujo; sin registro no hay solicitantes
que aprobar. Entrega valor por sí sola (capturar solicitudes) aunque la aprobación aún no
exista.

**Independent Test**: Enviar el formulario de registro con datos válidos y verificar que se
crea una cuenta en estado pendiente, que no puede iniciar sesión, y que se muestra el mensaje
de "solicitud registrada".

**Acceptance Scenarios**:

1. **Given** un visitante en la página de registro, **When** envía nombre, email único y
   contraseña válida, **Then** se crea su cuenta en estado pendiente y ve confirmación de
   que debe esperar aprobación.
2. **Given** un email que ya existe en el tenant, **When** intenta registrarse con ese email,
   **Then** el sistema rechaza el registro con un mensaje de validación y no crea duplicado.
3. **Given** una cuenta recién registrada en estado pendiente, **When** intenta iniciar
   sesión, **Then** el sistema rechaza el acceso indicando que su cuenta aún no está aprobada.

---

### User Story 2 - Aprobar (o rechazar) solicitantes desde la lista de usuarios (Priority: P1)

Un usuario autorizado del tenant entra a la vista de **Usuarios**, ve la lista de cuentas de
su tenant con su estado (pendiente / aprobado), y puede aprobar a un solicitante pendiente.
Al aprobarlo, ese usuario pasa a estado aprobado y desde ese momento puede iniciar sesión.
También puede rechazar/desactivar una solicitud pendiente.

**Why this priority**: Cierra el flujo: sin aprobación, los solicitantes nunca acceden. Es
la contraparte imprescindible del registro.

**Independent Test**: Con un solicitante pendiente existente, un usuario autorizado pulsa
"Aprobar" y se verifica que (a) el estado cambia a aprobado y (b) ese usuario ya puede
iniciar sesión.

**Acceptance Scenarios**:

1. **Given** un usuario autorizado en la lista de usuarios, **When** aprueba a un solicitante
   pendiente, **Then** ese solicitante pasa a estado aprobado y puede iniciar sesión.
2. **Given** un solicitante pendiente, **When** el usuario autorizado lo rechaza, **Then** la
   cuenta queda marcada como no aprobada y no puede iniciar sesión.
3. **Given** un usuario aprobado, **When** el usuario autorizado lo desactiva, **Then** deja
   de poder iniciar sesión.
4. **Given** usuarios de otro tenant, **When** el usuario autorizado abre la lista, **Then**
   solo ve usuarios de su propio tenant (aislamiento).

---

### User Story 3 - Panorama de usuarios con cards informativas (Priority: P2)

En la parte superior de la vista de Usuarios, el usuario autorizado ve tarjetas resumen
(p. ej. total de usuarios, pendientes de aprobación, activos) que le dan una foto rápida del
estado de las cuentas de su tenant, para saber de un vistazo cuántas solicitudes esperan
acción.

**Why this priority**: Mejora la usabilidad y prioriza el trabajo (ver pendientes de un
vistazo), pero el flujo funciona sin ella.

**Independent Test**: Con un conjunto conocido de usuarios en distintos estados, cargar la
vista y verificar que los contadores de las cards reflejan exactamente esos números para el
tenant activo.

**Acceptance Scenarios**:

1. **Given** un tenant con N usuarios totales y M pendientes, **When** se carga la vista de
   usuarios, **Then** las cards muestran N total y M pendientes.
2. **Given** dos tenants con distintos conteos, **When** cada usuario autorizado ve sus cards,
   **Then** los números corresponden solo a su tenant.

---

### Edge Cases

- ¿Qué pasa si un solicitante intenta registrarse dos veces con el mismo email? → validación
  de unicidad, sin duplicados.
- ¿Qué pasa si se aprueba a un usuario ya aprobado, o se pulsa aprobar dos veces? → operación
  idempotente, sin efectos secundarios ni error.
- ¿Puede un usuario aprobarse/rechazarse/desactivarse a sí mismo? → no debe poder dejarse a sí
  mismo sin acceso.
- ¿Qué pasa si el tenant del solicitante está inactivo? → aunque se apruebe, el login sigue
  bloqueado por el tenant inactivo (comportamiento actual de login).
- ¿Qué ocurre con el rate limiting / abuso del formulario público de registro?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE ofrecer una página pública (accesible sin sesión) de registro
  con, al menos, nombre, email y contraseña, basada visualmente en la página `page-register`
  del template NexaDash.
- **FR-002**: Al registrarse con datos válidos, el sistema DEBE crear la cuenta en estado
  **pendiente de aprobación**, que NO permite iniciar sesión.
- **FR-003**: El sistema DEBE validar que el email sea único dentro del ámbito correspondiente
  y rechazar registros duplicados con un mensaje claro.
- **FR-004**: El sistema DEBE impedir el inicio de sesión a cuentas en estado pendiente o
  rechazado, mostrando un mensaje que distinga "cuenta no aprobada" de "credenciales
  incorrectas" en la medida en que no comprometa la seguridad.
- **FR-005**: El sistema DEBE ofrecer una vista de **Usuarios** que liste las cuentas del
  tenant activo, mostrando al menos nombre, email, rol y estado.
- **FR-006**: Un usuario autorizado DEBE poder **aprobar** una cuenta pendiente, tras lo cual
  esa cuenta puede iniciar sesión.
- **FR-007**: Un usuario autorizado DEBE poder **rechazar/desactivar** una cuenta, tras lo cual
  esa cuenta no puede iniciar sesión.
- **FR-008**: La vista de Usuarios DEBE mostrar **cards informativas** con conteos resumidos
  (total, pendientes, activos) del tenant activo.
- **FR-009**: Todas las operaciones (lista, cards, aprobación) DEBEN respetar el aislamiento
  multi-tenant: un usuario solo ve y actúa sobre usuarios de su propio tenant (Principio I).
- **FR-010**: Las acciones de aprobación/rechazo DEBEN ser idempotentes y registrar quién y
  cuándo aprobó (auditoría mínima). *(ver Assumptions sobre nivel de auditoría)*
- **FR-011**: Un usuario NO DEBE poder rechazar/desactivar su propia cuenta.
- **FR-012**: La acción de aprobar/rechazar puede ejecutarla **cualquier usuario ya aprobado**
  del tenant (no se restringe a roles administrativos en esta fase). Un usuario pendiente o
  rechazado nunca puede aprobar a otros (no tiene acceso).
- **FR-013**: Al registrarse, la cuenta del solicitante se asocia al **único tenant de la
  instalación actual** (tenant activo/por defecto). El registro público NO crea tenants nuevos
  ni requiere elegir tenant en esta fase (Principio V, YAGNI).

### Key Entities *(include if feature involves data)*

- **Usuario (User)**: cuenta de acceso al CRM. Atributos relevantes: nombre, email, contraseña,
  rol, **estado de aprobación** (pendiente / aprobado / rechazado), `tenant_id`, marcas de
  auditoría de aprobación (quién/cuándo). Pertenece a un Tenant. Nota: ya existe un campo
  `activo` (booleano) que el login usa hoy; esta feature debe reconciliar "pendiente vs
  aprobado" con ese campo (reusarlo o extenderlo — decisión de la fase de plan).
- **Tenant**: organización dueña de los datos; ya existe y tiene su propio estado `activo`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una persona puede completar el registro en menos de 1 minuto y recibe
  confirmación inmediata de que su solicitud quedó pendiente.
- **SC-002**: El 100% de las cuentas recién registradas quedan bloqueadas para login hasta que
  son aprobadas.
- **SC-003**: Un usuario autorizado puede aprobar un solicitante en 2 acciones o menos desde la
  lista de usuarios, y el solicitante puede iniciar sesión inmediatamente después.
- **SC-004**: En pruebas con ≥2 tenants, ningún usuario ve o actúa sobre usuarios de otro
  tenant (0 fugas de datos entre tenants).
- **SC-005**: Los conteos de las cards coinciden exactamente con los usuarios del tenant en
  cada estado en el 100% de los casos verificados.

## Assumptions

- Se reutiliza el sistema de autenticación existente (login ya filtra por `activo=true` y por
  tenant activo); esta feature se apoya en ese comportamiento en vez de reimplementarlo.
- El estado "aprobado" se materializa de forma que el login existente lo respete sin cambios
  mayores (probablemente sobre el campo `activo` ya presente, más un marcador de estado si se
  requiere distinguir "nunca aprobado" de "desactivado").
- Auditoría mínima: se guarda quién aprobó y cuándo; no se requiere historial completo de
  cambios de estado en esta feature.
- La vista de registro reutiliza el layout `fullwidth` del template (como login) y la vista de
  usuarios el layout con sidebar; las notificaciones usan toastr según las guías del proyecto.
- Notificaciones por email al aprobar/registrar están fuera de alcance de esta feature (se
  puede añadir después); la comunicación es dentro de la app.
- Recuperación de contraseña y verificación de email quedan fuera de alcance de esta feature.
