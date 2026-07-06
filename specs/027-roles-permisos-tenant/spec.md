# Feature Specification: Sistema de roles y permisos por tenant

**Feature Branch**: `027-roles-permisos-tenant`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "Sistema de roles y permisos multi-tenant con spatie/laravel-permission. Cada tenant gestiona sus propios usuarios y roles de forma aislada (feature teams de spatie usando tenant_id como team key). Los permisos representan vistas del menú/sidebar y son un catálogo global fijo definido por la app. Los roles son configurables por cada tenant desde una vista de gestión con datatable con checkboxes y cards informativas. Modal para crear rol: nombre + permisos agrupados por módulo. Sin permiso → la entrada se oculta del sidebar Y la ruta queda protegida en backend. Al crear un tenant se crea automáticamente un rol Administrador con todos los permisos asignado al usuario admin inicial. El super_admin es central y tiene bypass total. Refactor del sidebar y guards de ruta del enum UserRole a permisos. Documentar que cada elemento nuevo del menú requiere un permiso nuevo."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Control de acceso por vista según rol (Priority: P1)

Como administrador de un tenant, quiero que cada usuario de mi empresa solo vea y pueda acceder a las secciones del sistema que su rol le permite, para que un empleado de mostrador no entre a facturación o a la configuración de horarios.

**Why this priority**: Es el corazón de la feature: sin el enforcement (ocultar del menú + bloquear la ruta en servidor), la gestión de roles no protege nada. Además reemplaza el control binario actual (admin/usuario) que ya se quedó corto.

**Independent Test**: Crear dos usuarios en un tenant con conjuntos de permisos distintos, iniciar sesión con cada uno y verificar que (a) el menú lateral solo muestra las secciones permitidas y (b) acceder por URL directa a una sección no permitida devuelve un error de autorización (403), no la página.

**Acceptance Scenarios**:

1. **Given** un usuario con permiso para "Clientes" pero no para "Facturas", **When** inicia sesión, **Then** el menú lateral muestra la entrada Clientes y no muestra la entrada Facturas.
2. **Given** ese mismo usuario, **When** navega por URL directa a la pantalla de facturas, **Then** el sistema le deniega el acceso (403) sin mostrar ningún dato.
3. **Given** un usuario cuyo rol pierde un permiso mientras tiene sesión iniciada, **When** vuelve a cargar cualquier página, **Then** el menú y las rutas reflejan el nuevo conjunto de permisos sin necesidad de cerrar sesión.
4. **Given** el super admin de la plataforma (usuario central, sin tenant), **When** accede a cualquier pantalla de su ámbito, **Then** no le afecta ninguna restricción de permisos de tenant.

---

### User Story 2 - Gestión de roles del tenant (Priority: P2)

Como administrador de un tenant, quiero una pantalla donde ver mis roles con sus permisos y usuarios asignados (datatable con checkboxes de selección y cards informativas), y poder crear un rol nuevo desde un modal indicando nombre y marcando permisos agrupados por módulo del menú.

**Why this priority**: Es la interfaz de administración del P1. Sin ella los roles solo podrían sembrarse por código; con ella cada tenant se autogestiona, que es el objetivo del producto.

**Independent Test**: Con el rol Administrador, entrar a la pantalla de roles, crear un rol "Ventas" con permisos de Clientes y Facturas, editarlo para quitarle Facturas, y eliminarlo; verificar cada cambio en la datatable y en las cards.

**Acceptance Scenarios**:

1. **Given** un administrador del tenant, **When** abre la pantalla de roles, **Then** ve una datatable con cada rol (nombre, nº de permisos, nº de usuarios asignados) y cards informativas de resumen (total de roles, usuarios con rol, etc.).
2. **Given** el modal de "Agregar rol", **When** el administrador escribe un nombre y marca permisos agrupados por módulo (cada permiso = una vista del menú), **Then** el rol queda creado para su tenant y aparece en la datatable con un aviso de éxito.
3. **Given** un rol existente, **When** el administrador edita sus permisos o su nombre, **Then** los usuarios con ese rol ven el efecto en su siguiente carga de página.
4. **Given** un rol con usuarios asignados, **When** el administrador intenta eliminarlo, **Then** el sistema lo impide (o exige reasignación previa) y explica el motivo.
5. **Given** dos tenants A y B, **When** el administrador de A abre su pantalla de roles, **Then** solo ve los roles de A; los de B no existen para él (ni en listados ni al asignar).
6. **Given** la datatable de roles, **When** el administrador marca un rol como "rol por defecto", **Then** los usuarios que se registren a partir de entonces por el formulario público reciben ese rol automáticamente; el rol por defecto anterior deja de serlo (solo hay uno).
7. **Given** el rol marcado como "por defecto", **When** el administrador intenta eliminarlo, **Then** el sistema lo impide hasta designar otro rol por defecto.
8. **Given** el rol "Administrador" del tenant, **When** un administrador intenta editarlo o eliminarlo de forma que ningún usuario del tenant conserve la gestión de roles/usuarios, **Then** el sistema lo impide para evitar que el tenant quede sin administración.

---

### User Story 3 - Asignación de roles a usuarios (Priority: P3)

Como administrador de un tenant, quiero asignar y cambiar el rol de cada usuario de mi empresa desde la gestión de usuarios existente, para que los permisos efectivos de cada persona salgan de su rol.

**Why this priority**: Cierra el circuito rol→usuario. Depende de P1/P2 pero es imprescindible para operar el sistema en el día a día.

**Independent Test**: Desde la pantalla de usuarios, cambiar el rol de un usuario de "Administrador" a un rol restringido y verificar que su menú y accesos cambian en su siguiente navegación.

**Acceptance Scenarios**:

1. **Given** la gestión de usuarios del tenant, **When** el administrador asigna un rol a un usuario, **Then** el usuario adquiere exactamente los permisos de ese rol.
2. **Given** un usuario sin ningún rol asignado, **When** inicia sesión, **Then** solo ve las secciones personales básicas (p. ej. su perfil, fichar y su jornada) y ninguna sección de gestión.
3. **Given** el administrador editándose a sí mismo, **When** intenta quitarse el rol que le da acceso a la gestión de usuarios/roles siendo el único con ese acceso, **Then** el sistema lo impide.

---

### User Story 4 - Provisión automática al crear un tenant (Priority: P2)

Como super admin de la plataforma, quiero que al dar de alta un tenant se cree automáticamente su rol "Administrador" con todos los permisos del catálogo y quede asignado al usuario administrador inicial, para que el tenant sea operable desde el primer login sin configuración manual.

**Why this priority**: Sin esto, cada tenant nuevo nace "ciego" (sin roles, sin menú) y requiere intervención manual; rompe el flujo de alta ya existente.

**Independent Test**: Crear un tenant desde la pantalla de super admin, iniciar sesión con el admin inicial y verificar que ve el menú completo y puede entrar a la gestión de roles.

**Acceptance Scenarios**:

1. **Given** el formulario de alta de tenant del super admin, **When** se crea el tenant, **Then** en la misma operación queda creado el rol "Administrador" de ese tenant con todos los permisos del catálogo y asignado al usuario admin inicial.
2. **Given** un fallo en cualquier paso del alta (tenant, dominio, usuario o rol), **When** ocurre, **Then** no queda ningún dato parcial creado (operación atómica).
3. **Given** tenants ya existentes antes de esta feature, **When** se despliega la feature, **Then** cada tenant existente recibe su rol "Administrador" con todos los permisos, asignado a sus usuarios que hoy son admin (migración de datos).

### Edge Cases

- Usuario autenticado cuyo rol pierde permisos (edición del rol o reasignación) mientras navega: la siguiente carga de página refleja el nuevo conjunto, nunca un error de servidor. (Eliminar un rol con usuarios está prohibido, así que ese caso no existe.)
- Usuario sin permiso de Dashboard tras iniciar sesión: la página de aterrizaje lo redirige a su sección personal (mi jornada), nunca a un 403 de bienvenida.
- Se añade una vista nueva al menú (permiso nuevo en el catálogo): los roles existentes NO la reciben automáticamente (opt-in por tenant), salvo el rol "Administrador" de cada tenant, que debe recibirla para no degradarse silenciosamente. La decisión debe quedar documentada.
- Nombre de rol duplicado dentro del mismo tenant: rechazado con mensaje claro; el mismo nombre en tenants distintos es válido.
- El super admin no aparece en ninguna gestión de usuarios/roles de tenant y ningún administrador de tenant puede crearle o quitarle nada.
- Usuario que pertenece a un tenant desactivado: la restricción de acceso existente prevalece sobre cualquier permiso.
- Peticiones AJAX/JSON a rutas no permitidas: responden 403 en JSON, no con una redirección HTML.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE mantener un catálogo global y fijo de permisos donde cada permiso representa una vista/sección del menú lateral, agrupado por módulo (Control de fichaje, Clientes, Stock, Facturas, POS, Archivos, Marketing, Usuarios, Roles, Dashboard…). El catálogo lo define la aplicación (no los tenants) y se siembra una sola vez de forma idempotente y re-ejecutable.
- **FR-002**: Los roles DEBEN pertenecer a un tenant: crearse, listarse, editarse, eliminarse y asignarse siempre dentro del tenant activo, sin visibilidad ni efecto cruzado entre tenants (Principio I). DEBEN existir tests con ≥2 tenants que verifiquen el aislamiento.
- **FR-003**: Cada entrada del menú lateral DEBE mostrarse solo si el usuario tiene el permiso correspondiente; un grupo del menú sin ninguna entrada visible no se muestra.
- **FR-004**: Toda ruta asociada a una vista del catálogo DEBE estar protegida en servidor por su permiso; el acceso sin permiso devuelve 403 (HTML o JSON según la petición). Ocultar el enlace nunca es la única barrera.
- **FR-005**: El tenant DEBE disponer de una pantalla de gestión de roles con: cards informativas de resumen, datatable de roles con checkboxes de selección, botón "Agregar" que abre un modal con nombre del rol y lista de permisos agrupados por módulo, y acciones de editar/eliminar.
- **FR-006**: El sistema DEBE impedir eliminar un rol con usuarios asignados y DEBE impedir que el tenant quede sin ningún usuario con acceso a la gestión de usuarios/roles (auto-bloqueo).
- **FR-007**: Al crear un tenant, el sistema DEBE crear en la misma operación atómica el rol "Administrador" del tenant con todos los permisos del catálogo y asignarlo al usuario administrador inicial.
- **FR-008**: Los tenants existentes al desplegar la feature DEBEN recibir su rol "Administrador" con todos los permisos, asignado a sus usuarios actuales con rol admin (migración de datos única).
- **FR-009**: El super admin central DEBE quedar fuera del sistema de roles de tenant y conservar acceso total a su ámbito mediante un bypass global de autorización.
- **FR-010**: Los chequeos de acceso existentes basados en el rol binario (admin/usuario) en menú, rutas y vistas DEBEN migrarse al nuevo sistema de permisos, manteniendo el comportamiento equivalente para los usuarios actuales tras la migración.
- **FR-011**: La asignación de rol a usuario DEBE integrarse en la gestión de usuarios existente del tenant (un rol por usuario como modelo operativo; ver Assumptions).
- **FR-012**: El nombre de rol DEBE ser único dentro del tenant; se permite el mismo nombre en tenants distintos.
- **FR-013**: Tras implementar, la documentación del proyecto DEBE recoger el procedimiento obligatorio: cada elemento nuevo del menú requiere crear su permiso en el catálogo (seeder) y proteger su ruta, incluyendo qué roles lo reciben por defecto (solo "Administrador" de cada tenant).
- **FR-014**: Cada tenant DEBE poder marcar exactamente un rol como "rol por defecto"; los usuarios que se registran por el formulario público del tenant reciben automáticamente ese rol al ser creados. El rol por defecto no puede eliminarse (además de RN-01) sin designar otro antes. Tras la migración inicial, el rol "Usuario" de cada tenant queda como rol por defecto.

### Key Entities

- **Permiso**: entrada del catálogo global; representa una vista del menú. Atributos: clave estable (p. ej. `ver-facturas`), etiqueta visible, módulo/grupo. No pertenece a ningún tenant.
- **Rol**: conjunto de permisos con nombre, propiedad de un tenant. El rol "Administrador" de cada tenant es especial: se aprovisiona automáticamente y está protegido contra el auto-bloqueo.
- **Asignación usuario–rol**: vincula un usuario de un tenant con un rol de ese mismo tenant; nunca cruza tenants.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las vistas del menú lateral están cubiertas por un permiso del catálogo y su ruta correspondiente rechaza con 403 a usuarios sin ese permiso (verificable por test automatizado por ruta).
- **SC-002**: En un entorno con ≥2 tenants, ningún listado, alta o asignación de roles de un tenant expone o afecta datos de otro (tests de aislamiento en verde).
- **SC-003**: Un administrador de tenant puede crear un rol nuevo con sus permisos y asignarlo a un usuario en menos de 2 minutos sin ayuda externa.
- **SC-004**: Un tenant recién creado es operable en el primer login de su administrador (menú completo visible, gestión de roles accesible) sin ninguna configuración manual posterior.
- **SC-005**: Tras la migración, ningún usuario existente pierde acceso a las secciones que hoy puede usar (equivalencia admin→Administrador, usuario→rol base).

## Assumptions

- **Un rol por usuario** como modelo operativo (aunque la tecnología subyacente soporte varios): simplifica la UI de usuarios y cubre el caso de negocio actual. Ampliable en el futuro sin migración destructiva.
- **Granularidad = vista del menú**, no acción (no se distingue "ver facturas" de "crear facturas" en esta fase). Las acciones internas de cada vista quedan fuera de alcance.
- Los usuarios existentes con rol `usuario` (enum actual) se migran a un rol "Usuario" por tenant con **exactamente las secciones que hoy ve un no-admin**: todo el catálogo excepto el bloque de gestión de fichajes (jornada/calendario/miembros/horarios/alertas), la gestión de roles, la gestión de usuarios, la configuración y los logs — manteniendo SC-005. Ese rol "Usuario" queda como rol por defecto inicial del tenant (FR-014).
- El enum de rol actual se conserva solo para distinguir super admin central vs usuario de tenant; deja de ser la fuente de verdad de acceso a vistas de tenant.
- Las secciones personales (perfil propio, fichar, mi jornada) no requieren permiso del catálogo: son accesibles para todo usuario autenticado del tenant.
- La pantalla de gestión de roles es en sí misma una vista del catálogo (permiso `ver-roles`), incluida por defecto solo en el rol "Administrador".
- El alta de nuevos elementos de menú es un evento de desarrollo (no de runtime): se gestiona vía seeder re-ejecutable + documentación (FR-013), no con UI.
