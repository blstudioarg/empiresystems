# Feature Specification: Panel Super Admin — Gestión de Tenants por Dominio

**Feature Branch**: `007-super-admin-tenants`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: "Panel de Super Admin para gestión de tenants del SaaS. Identificación de tenant por dominio; cada tenant tiene un dominio asociado que determina qué tenant está activo. Panel en la ruta `super_admin` para listar, crear, editar y eliminar tenants. Solo el rol super_admin (tenant_id null) puede acceder."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Identificación del tenant por dominio (Priority: P1)

Como visitante de la aplicación, cuando entro por el dominio asociado a un tenant, la
aplicación reconoce automáticamente qué tenant estoy usando a partir de ese dominio, sin
que yo tenga que elegirlo. El dominio es el único indicador del tenant activo.

**Why this priority**: Es el cambio de arquitectura de contexto que sostiene todo lo demás. Sin
resolución de tenant por dominio, el panel de creación de tenants no tiene sentido (crear un
tenant es, ante todo, asociarle un dominio). Es el fundamento sobre el que se apoyan el resto de
historias.

**Independent Test**: Configurar dos tenants con dominios distintos, acceder por cada dominio y
verificar que el contexto de tenant activo (y por tanto los datos de negocio visibles) es el que
corresponde al dominio, de forma aislada.

**Acceptance Scenarios**:

1. **Given** un tenant A con dominio `a.example.com` y un tenant B con dominio `b.example.com`,
   **When** un usuario accede por `a.example.com`, **Then** el tenant activo de la petición es A.
2. **Given** el mismo escenario, **When** un usuario accede por `b.example.com`, **Then** el
   tenant activo de la petición es B, y no ve ningún dato de A.
3. **Given** un dominio que no está asociado a ningún tenant (y no es el dominio central),
   **When** alguien accede por él, **Then** la aplicación no resuelve ningún tenant y responde con
   un error/estado controlado (no muestra datos de un tenant arbitrario).
4. **Given** un usuario que pertenece al tenant B (`tenant_id` = B), **When** intenta iniciar
   sesión desde el dominio del tenant A, **Then** el acceso se rechaza porque su tenant no coincide
   con el tenant del dominio.

---

### User Story 2 - Listar tenants existentes (Priority: P1)

Como Super Admin, quiero ver el listado de todas las empresas cliente (tenants) del SaaS, con su
dominio asociado y sus datos identificativos, para tener una visión global de las altas.

**Why this priority**: Es la vista de entrada del panel y la base para editar/eliminar. Un Super
Admin necesita ver qué tenants existen antes de operar sobre ellos.

**Independent Test**: Con varios tenants dados de alta, acceder al panel super_admin como Super
Admin y comprobar que el listado los muestra a todos con su dominio y datos, y que ningún otro
rol puede ver ese listado.

**Acceptance Scenarios**:

1. **Given** que hay 3 tenants dados de alta, **When** el Super Admin abre el panel de tenants,
   **Then** ve los 3 con su nombre comercial, dominio asociado, NIF y estado (activo/inactivo).
2. **Given** un usuario que NO es Super Admin (rol admin o usuario de un tenant), **When** intenta
   acceder al panel super_admin, **Then** el acceso se deniega.
3. **Given** un visitante no autenticado, **When** intenta acceder al panel super_admin, **Then**
   se le redirige al login y no ve ningún dato.

---

### User Story 3 - Crear un tenant nuevo (Priority: P1)

Como Super Admin, quiero dar de alta una empresa cliente nueva indicando su dominio y sus datos
fiscales básicos de emisor, para incorporarla al SaaS de forma instantánea.

**Why this priority**: Es la razón de ser del panel: el Super Admin da de alta clientes del SaaS
desde el propio software (docs/00-vision.md). Sin esto no hay tenants que gestionar.

**Independent Test**: Como Super Admin, completar el formulario de alta con un dominio no usado y
datos válidos, guardar, y verificar que el tenant aparece en el listado y que el nuevo dominio ya
resuelve a ese tenant.

**Acceptance Scenarios**:

1. **Given** el Super Admin en el formulario de alta, **When** introduce dominio único + datos
   fiscales válidos y guarda, **Then** el tenant se crea, aparece en el listado y su dominio queda
   asociado y operativo para identificarlo.
2. **Given** el Super Admin, **When** intenta crear un tenant con un dominio ya asignado a otro
   tenant, **Then** la creación se rechaza con un mensaje indicando que el dominio ya está en uso.
3. **Given** el Super Admin, **When** intenta crear un tenant con un dominio con formato inválido
   o dejando campos obligatorios vacíos, **Then** la creación se rechaza con los errores de
   validación correspondientes.

---

### User Story 4 - Editar un tenant existente (Priority: P2)

Como Super Admin, quiero modificar los datos de un tenant (incluido su dominio y sus datos
fiscales) para mantener su información actualizada.

**Why this priority**: Necesario para el mantenimiento, pero secundario respecto a crear/listar:
un tenant recién creado ya es funcional; la edición corrige o actualiza.

**Independent Test**: Editar el dominio y el nombre de un tenant existente, guardar, y comprobar
que el listado refleja los cambios y que el nuevo dominio resuelve al tenant (y el anterior ya no).

**Acceptance Scenarios**:

1. **Given** un tenant existente, **When** el Super Admin cambia su nombre comercial y guarda,
   **Then** el cambio se refleja en el listado.
2. **Given** un tenant existente, **When** el Super Admin cambia su dominio por otro no usado y
   guarda, **Then** el nuevo dominio identifica al tenant y el anterior deja de hacerlo.
3. **Given** un tenant existente, **When** el Super Admin intenta cambiar su dominio por uno ya
   asignado a otro tenant, **Then** la edición se rechaza indicando que el dominio ya está en uso.
4. **Given** un tenant existente, **When** el Super Admin lo marca como inactivo, **Then** el
   login de todos sus usuarios queda bloqueado (comportamiento existente de `activo`).

---

### User Story 5 - Eliminar un tenant existente (Priority: P2)

Como Super Admin, quiero eliminar un tenant que ya no debe operar, respetando la inmutabilidad de
los datos fiscales de los tenants que ya han facturado.

**Why this priority**: Cierra el ciclo de gestión, pero es menos frecuente y está condicionado por
el cumplimiento fiscal, por lo que va por detrás de crear/listar.

**Independent Test**: Intentar eliminar un tenant sin facturas emitidas (se elimina) y un tenant
con facturas emitidas (se impide y se ofrece desactivarlo), verificando ambos resultados.

**Acceptance Scenarios**:

1. **Given** un tenant sin facturas emitidas, **When** el Super Admin confirma su eliminación,
   **Then** el tenant se elimina y su dominio deja de resolver.
2. **Given** un tenant con al menos una factura emitida, **When** el Super Admin intenta
   eliminarlo, **Then** la eliminación se impide con un mensaje que explica que tiene facturas
   emitidas y que solo puede desactivarse.
3. **Given** cualquier eliminación, **When** el Super Admin la inicia, **Then** se le pide
   confirmación antes de ejecutarla (no se elimina de un solo clic).

---

### Edge Cases

- **Dominio del panel super_admin**: el panel vive en el dominio central de la aplicación, que no
  pertenece a ningún tenant. Acceder al dominio central NO debe resolver a ningún tenant de negocio.
- **Super Admin y dominios de tenant**: ¿qué pasa si el Super Admin (tenant_id null) accede por el
  dominio de un tenant? El dominio fija el contexto de ese tenant; el área super_admin solo está
  disponible desde el dominio central.
- **Dominio duplicado en concurrencia**: dos altas simultáneas con el mismo dominio deben resultar
  en que solo una gane (unicidad garantizada a nivel de datos, no solo de validación).
- **Cambio de mayúsculas/espacios en el dominio**: `A.Example.com ` y `a.example.com` deben tratarse
  como el mismo dominio (normalización a minúsculas, sin espacios, sin protocolo `http(s)://` ni
  path).
- **Eliminar/desactivar el tenant del propio dominio activo**: operación de super_admin siempre
  desde el dominio central, por lo que no se opera sobre "el tenant en el que estoy".
- **Usuario cuyo tenant fue desactivado o eliminado mientras tenía sesión abierta**: su acceso
  debe cortarse (comportamiento existente de `activo` + resolución por dominio).

## Requirements *(mandatory)*

### Functional Requirements

**Identificación por dominio**

- **FR-001**: El sistema MUST determinar el tenant activo de cada petición a partir del dominio por
  el que se accede, sin intervención del usuario.
- **FR-002**: El sistema MUST tratar un dominio como identificador único: un dominio pertenece como
  máximo a un tenant, y cada tenant tiene exactamente un dominio asociado.
- **FR-003**: El sistema MUST normalizar el dominio (minúsculas, sin espacios, sin esquema
  `http(s)://` ni path) antes de almacenarlo o compararlo.
- **FR-004**: El sistema MUST distinguir el dominio central de la aplicación (donde vive el área
  super_admin y que no resuelve a ningún tenant) de los dominios de tenant.
- **FR-005**: Cuando se accede por un dominio no asociado a ningún tenant ni al dominio central, el
  sistema MUST responder con un estado controlado (error/no encontrado) y NO exponer datos de
  ningún tenant.
- **FR-006**: El sistema MUST rechazar el inicio de sesión de un usuario cuyo `tenant_id` no
  coincida con el tenant resuelto por el dominio de acceso (el Super Admin, con `tenant_id` null,
  opera desde el dominio central).

**Control de acceso al panel**

- **FR-007**: El sistema MUST exponer el panel de gestión de tenants bajo la ruta `super_admin`.
- **FR-008**: El sistema MUST permitir el acceso al área super_admin únicamente a usuarios con rol
  super_admin (usuarios con `tenant_id` null), y solo desde el dominio central.
- **FR-009**: El sistema MUST denegar el acceso al área super_admin a cualquier usuario que no sea
  super_admin (roles admin/usuario de un tenant) y a los visitantes no autenticados (redirigiendo
  a login).

**Listado**

- **FR-010**: El sistema MUST mostrar al Super Admin el listado de todos los tenants con, al menos:
  nombre comercial, dominio asociado, NIF y estado (activo/inactivo).

**Creación**

- **FR-011**: El sistema MUST permitir al Super Admin crear un tenant nuevo indicando su dominio y
  sus datos fiscales básicos de emisor (al menos: nombre comercial, razón social, NIF, régimen
  impositivo, email).
- **FR-012**: El sistema MUST validar en el alta que el dominio es único y con formato válido, y
  que los campos obligatorios están presentes; en caso contrario, rechazar con mensajes de error.
- **FR-013**: Al crear un tenant, el sistema MUST dejar su dominio operativo de inmediato para
  identificarlo (alta instantánea, sin pasos manuales de infraestructura por parte del Super Admin
  dentro de la aplicación).

**Edición**

- **FR-014**: El sistema MUST permitir al Super Admin editar los datos de un tenant existente,
  incluido su dominio y sus datos fiscales.
- **FR-015**: Al editar el dominio, el sistema MUST validar su unicidad (no puede coincidir con el
  de otro tenant) y, al guardar, dejar de resolver el dominio anterior y pasar a resolver el nuevo.
- **FR-016**: El sistema MUST permitir marcar un tenant como activo/inactivo; un tenant inactivo
  bloquea el login de todos sus usuarios (comportamiento existente de `activo`).

**Eliminación**

- **FR-017**: El sistema MUST impedir la eliminación de un tenant que tenga al menos una factura
  emitida, ofreciendo en su lugar desactivarlo, para respetar la inmutabilidad de los datos
  fiscales (Principio II de la constitución).
- **FR-018**: El sistema MUST permitir eliminar un tenant que no tenga facturas emitidas, tras
  confirmación explícita del Super Admin.
- **FR-019**: Tras eliminar un tenant, su dominio MUST dejar de resolver a ningún tenant.

**Notificaciones**

- **FR-020**: El sistema MUST informar al Super Admin del resultado de cada operación (alta,
  edición, eliminación, bloqueo por facturas) mediante notificaciones consistentes con el resto de
  la aplicación.

### Key Entities *(include if feature involves data)*

- **Tenant**: empresa cliente del SaaS. Atributos relevantes para esta feature: identificador,
  nombre comercial, razón social, NIF, régimen impositivo, email, estado (activo/inactivo), y su
  **dominio asociado** (único). Ya existe como entidad central; esta feature añade la relación con
  el dominio como identificador de contexto.
- **Dominio de tenant**: la dirección web única por la que se accede a un tenant y que determina el
  tenant activo de la petición. Relación 1:1 con Tenant. Distinto del dominio central de la app.
- **Dominio central**: dominio principal de la aplicación, no perteneciente a ningún tenant, donde
  reside el área super_admin y desde el que operan los usuarios super_admin.
- **Usuario Super Admin**: usuario con `tenant_id` null y rol super_admin; único autorizado a
  gestionar tenants. Ya existe como entidad; esta feature define su ámbito de acceso.
- **Factura (referencia)**: entidad existente; su presencia en estado "emitida" condiciona si un
  tenant puede eliminarse. No se modifica, solo se consulta para la regla de eliminación.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Accediendo por dos dominios de tenant distintos, el 100% de las peticiones resuelve
  el tenant correcto y ninguna expone datos del otro tenant (aislamiento verificado).
- **SC-002**: Un Super Admin puede dar de alta un tenant nuevo y dejar su dominio operativo en una
  sola operación, sin pasos manuales dentro de la aplicación, en menos de 2 minutos.
- **SC-003**: El 100% de los intentos de acceso al área super_admin por usuarios no super_admin o
  no autenticados son denegados.
- **SC-004**: El 100% de los intentos de eliminar un tenant con facturas emitidas se impiden y
  ofrecen la desactivación como alternativa; ninguna factura emitida se pierde por una operación
  de eliminación de tenant.
- **SC-005**: No es posible tener dos tenants con el mismo dominio en ningún momento, ni siquiera
  ante altas concurrentes.
- **SC-006**: Un usuario que intenta iniciar sesión desde un dominio que no corresponde a su tenant
  es rechazado el 100% de las veces.

## Assumptions

- **Infraestructura de DNS fuera de alcance**: la creación del dominio real y su apuntamiento a la
  app se hace en el hosting por parte del usuario (fuera de la aplicación). La app solo gestiona la
  **asociación** dominio→tenant; "dejar el dominio operativo" significa que la app lo reconoce, no
  que la app configure DNS.
- **Un solo dominio por tenant** (decisión confirmada): no se soportan múltiples dominios/alias por
  tenant en esta feature; podría ampliarse más adelante sin romper el modelo.
- **Panel super_admin en el dominio central** (decisión confirmada): el área super_admin solo es
  accesible desde el dominio central de la aplicación, no desde los dominios de tenant.
- **Dominio manda y valida** (decisión confirmada): el dominio fija el tenant activo y el login se
  valida contra él; se refuerza el aislamiento respecto al modelo anterior basado solo en
  `tenant_id` del usuario.
- **Eliminación condicionada por facturas** (decisión confirmada): se bloquea la eliminación de
  tenants con facturas emitidas y se ofrece desactivar; los tenants sin facturas emitidas sí pueden
  eliminarse.
- **Se reutiliza el rol y la entidad Tenant existentes** (features 001/006): esta feature no
  redefine el modelo de usuarios ni el de tenant, solo añade la dimensión de dominio y el área de
  gestión.
- **UI consistente con el resto del panel**: el panel super_admin sigue las guías de front del
  proyecto (docs/04-front-guidelines.md): listado con DataTable + dropdown de acciones, modal de
  confirmación de borrado, notificaciones toastr. El detalle visual se define en el plan.
- **Datos fiscales mínimos al crear**: se toman como obligatorios los ya presentes en el modelo
  Tenant (nombre_comercial, razon_social, nif, regimen_impositivo, email); columnas fiscales
  adicionales quedan para features de facturación que las necesiten.
