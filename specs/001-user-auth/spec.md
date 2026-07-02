# Feature Specification: Autenticación de usuarios (login multi-tenant)

**Feature Branch**: `001-user-auth`

**Created**: 2026-07-02

**Status**: Draft

**Input**: User description: "Sistema de autenticación del SaaS (login). Los usuarios operan dentro de un tenant (multi-tenant single-database, columna tenant_id según docs/01-arquitectura.md y docs/03-modelo-datos.md): tabla users con tenant_id y rol (super_admin, admin, usuario). El super_admin es global (gestiona todos los tenants, no pertenece a uno). Login con email y contraseña, con remember me, logout, y protección de todas las rutas de la app detrás de auth. Al iniciar sesión, el contexto de tenant del usuario queda activo para el scoping de datos. La vista de login usa la plantilla page-login del template NexaDash (layout fullwidth, sin sidebar), adaptada: sin registro público, sin social login, textos en español, branding Empire Systems. Incluir un seeder para crear el primer usuario super_admin y un tenant de prueba con un usuario admin. Forgot password fuera de alcance."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Iniciar sesión y operar dentro de mi empresa (Priority: P1)

Un usuario de una empresa cliente (tenant) llega a la pantalla de login, ingresa su email y contraseña, y accede al panel de la aplicación. A partir de ese momento, todo lo que ve y hace queda limitado a los datos de su propia empresa: nunca puede ver ni tocar datos de otro tenant.

**Why this priority**: Es la puerta de entrada al sistema. Sin login funcional con contexto de tenant activo no se puede construir ni probar ninguna otra feature de negocio. Además, el aislamiento de datos entre clientes es el Principio I (NON-NEGOTIABLE) de la constitución del proyecto.

**Independent Test**: Puede probarse de punta a punta creando dos tenants con un usuario cada uno (vía seeder), iniciando sesión con cada usuario y verificando que (a) ambos entran al panel y (b) el contexto activo corresponde a su tenant y solo al suyo.

**Acceptance Scenarios**:

1. **Given** un usuario activo de un tenant activo, **When** ingresa email y contraseña correctos en la pantalla de login, **Then** accede al panel principal y su sesión queda asociada a su tenant.
2. **Given** un usuario autenticado de un tenant, **When** navega por la aplicación, **Then** solo ve datos pertenecientes a su tenant.
3. **Given** un visitante en la pantalla de login, **When** ingresa credenciales incorrectas (email inexistente o contraseña errónea), **Then** ve un mensaje de error genérico que no revela si el email existe, y permanece en la pantalla de login.
4. **Given** la pantalla de login, **When** se muestra al usuario, **Then** los textos están en español, el branding es de Empire Systems, y no existe opción de registro público ni de acceso con redes sociales.

---

### User Story 2 - Rutas protegidas y cierre de sesión (Priority: P2)

Nadie puede ver ninguna pantalla de la aplicación sin haber iniciado sesión. Un usuario autenticado puede cerrar sesión desde el menú y vuelve a la pantalla de login; a partir de ahí, ya no puede acceder al panel sin volver a autenticarse.

**Why this priority**: Sin protección de rutas, el login es decorativo. Es el complemento de seguridad inmediato de la historia P1, pero es verificable de forma independiente.

**Independent Test**: Sin sesión iniciada, intentar acceder a cualquier URL interna de la app y verificar que redirige al login. Con sesión iniciada, cerrar sesión y verificar que el acceso vuelve a estar bloqueado.

**Acceptance Scenarios**:

1. **Given** un visitante sin sesión, **When** intenta acceder a cualquier pantalla interna de la aplicación, **Then** es redirigido a la pantalla de login.
2. **Given** un usuario autenticado, **When** hace clic en "Cerrar sesión", **Then** su sesión termina y es llevado a la pantalla de login.
3. **Given** un usuario que cerró sesión, **When** intenta volver a una pantalla interna (por ejemplo con el botón "atrás" del navegador), **Then** no ve contenido de la aplicación y es redirigido al login.

---

### User Story 3 - Recordar mi sesión (Priority: P3)

Un usuario marca "Recordarme" al iniciar sesión y, al volver a abrir el navegador días después, sigue teniendo su sesión activa sin volver a ingresar credenciales.

**Why this priority**: Mejora de comodidad, no bloquea ninguna otra funcionalidad. El login funciona sin esto.

**Independent Test**: Iniciar sesión con "Recordarme" activado, cerrar el navegador (expirar la sesión de corta duración) y verificar que al volver el usuario sigue autenticado. Repetir sin "Recordarme" y verificar que la sesión no persiste.

**Acceptance Scenarios**:

1. **Given** la pantalla de login con "Recordarme" marcado, **When** el usuario inicia sesión y la sesión de navegador expira, **Then** al volver a entrar sigue autenticado sin reingresar credenciales.
2. **Given** la pantalla de login con "Recordarme" sin marcar, **When** el usuario inicia sesión y la sesión de navegador expira, **Then** al volver debe autenticarse de nuevo.

---

### User Story 4 - Entrar al sistema desde el día uno (Priority: P1)

El equipo puede entrar al sistema recién instalado sin pasos manuales en la base de datos: existe un usuario super_admin global (no pertenece a ningún tenant) y un tenant de prueba con su usuario admin, creados automáticamente al preparar el entorno.

**Why this priority**: Sin usuarios iniciales no hay forma de probar el login ni ninguna feature posterior. Es parte del "definition of done" de esta spec: poder dejar la autenticación funcionando y usable.

**Independent Test**: Sobre una base de datos recién creada, ejecutar la preparación del entorno e iniciar sesión con las credenciales del super_admin y con las del admin del tenant de prueba.

**Acceptance Scenarios**:

1. **Given** una instalación nueva con datos iniciales cargados, **When** se inicia sesión con las credenciales del super_admin, **Then** el acceso es exitoso y ese usuario no está asociado a ningún tenant en particular.
2. **Given** la misma instalación, **When** se inicia sesión con las credenciales del admin del tenant de prueba, **Then** el acceso es exitoso y su contexto queda asociado al tenant de prueba.

---

### Edge Cases

- Usuario con credenciales válidas pero cuyo **tenant está inactivo**: no debe poder iniciar sesión; ve el mismo tipo de mensaje de acceso denegado.
- Usuario con credenciales válidas pero marcado como **inactivo**: no debe poder iniciar sesión.
- **Reintentos repetidos** de login fallido: el sistema debe limitar la frecuencia de intentos para dificultar ataques de fuerza bruta.
- **Super_admin**: inicia sesión sin tenant asociado; el sistema no debe fallar por la ausencia de tenant ni filtrarle datos como si perteneciera a uno.
- **Sesión expirada** a mitad de uso: al siguiente intento de navegación el usuario es redirigido al login sin errores confusos.
- Envío del formulario con **campos vacíos o email mal formado**: se muestran mensajes de validación en español sin procesar el intento.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST permitir iniciar sesión con email y contraseña desde una pantalla de login pública.
- **FR-002**: El sistema MUST rechazar credenciales inválidas con un mensaje genérico que no revele si el email existe en el sistema.
- **FR-003**: El sistema MUST impedir el acceso a toda pantalla interna de la aplicación a usuarios no autenticados, redirigiéndolos al login.
- **FR-004**: Los usuarios MUST poder cerrar sesión desde la interfaz, terminando su sesión de forma efectiva.
- **FR-005**: El sistema MUST ofrecer la opción "Recordarme" que mantiene la sesión activa entre cierres de navegador.
- **FR-006**: Cada usuario (salvo el super_admin) MUST pertenecer a exactamente un tenant, y al iniciar sesión el contexto de ese tenant MUST quedar activo para el aislamiento de datos (Principio I de la constitución).
- **FR-007**: El sistema MUST soportar tres roles de usuario: super_admin (global, sin tenant), admin (administra su tenant) y usuario (opera dentro de su tenant). En esta feature los roles solo se almacenan y se exponen en el contexto de sesión; la gestión fina de permisos por rol queda para features posteriores.
- **FR-008**: El sistema MUST impedir el inicio de sesión de usuarios inactivos y de usuarios cuyo tenant esté inactivo.
- **FR-009**: El sistema MUST limitar la frecuencia de intentos fallidos de login por usuario/origen para mitigar fuerza bruta.
- **FR-010**: La pantalla de login MUST seguir el diseño de la plantilla de login del template NexaDash (layout de página completa, sin sidebar), adaptada con: textos en español, branding Empire Systems, sin enlace de registro público, sin botones de redes sociales y sin enlace de recuperación de contraseña.
- **FR-011**: El sistema MUST NOT ofrecer registro público de usuarios (self-registration); la creación de usuarios queda fuera del alcance de esta feature.
- **FR-012**: El sistema MUST incluir datos iniciales reproducibles que creen: un usuario super_admin global, un tenant de prueba activo y un usuario admin de ese tenant, de modo que se pueda ingresar al sistema inmediatamente después de instalar.
- **FR-013**: Las contraseñas MUST almacenarse de forma irreversible (hash), nunca en texto plano.
- **FR-014**: Los intentos de login fallidos y los inicios/cierres de sesión MUST quedar registrados en el log de la aplicación.

### Key Entities

- **Tenant**: empresa cliente del SaaS. Atributos relevantes para esta feature: identificador, nombre y estado activo/inactivo. El resto de sus datos fiscales ya está definido en `docs/03-modelo-datos.md` y no es alcance de esta spec.
- **Usuario**: persona que opera el sistema. Atributos: nombre, email (único en todo el sistema), contraseña (hash), rol (super_admin / admin / usuario), referencia a su tenant (vacía solo para super_admin) y estado activo/inactivo.
- **Sesión**: vínculo temporal entre un usuario autenticado y el sistema; lleva asociado el contexto de tenant activo del usuario.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario con credenciales válidas completa el inicio de sesión y ve el panel principal en menos de 30 segundos desde que abre la pantalla de login.
- **SC-002**: El 100% de las pantallas internas de la aplicación son inaccesibles sin sesión iniciada (verificable recorriendo todas las rutas registradas).
- **SC-003**: En una instalación con dos tenants de prueba, un usuario de un tenant no puede ver ningún dato del otro tenant en ninguna pantalla (0 fugas en la suite de pruebas de aislamiento).
- **SC-004**: Sobre una instalación limpia, una persona del equipo puede entrar al sistema usando las credenciales iniciales documentadas sin tocar la base de datos a mano.
- **SC-005**: Tras 5 intentos fallidos consecutivos de login, los intentos siguientes del mismo origen son bloqueados temporalmente.

## Assumptions

- **Destino tras el login**: el usuario aterriza en el dashboard principal (`/`). No hay redirección por rol en esta feature.
- **Enlace "¿Olvidaste tu contraseña?"**: se elimina de la pantalla (no se deja un enlace muerto). La recuperación de contraseña será una feature posterior que lo reintroduzca.
- **Tenant inactivo bloquea el login** de todos sus usuarios; se asume que es el comportamiento deseado para poder suspender clientes del SaaS.
- **Límite de intentos**: se asume el estándar de la plataforma (bloqueo temporal tras ~5 intentos fallidos); no se requiere configuración por tenant.
- **Idioma**: la interfaz de esta feature es solo en español; no hay selector de idioma.
- **Los usuarios iniciales del seeder son para entorno de desarrollo/instalación inicial**; sus credenciales se documentan en el README del proyecto y deben cambiarse en producción.
- **El super_admin todavía no tiene panel propio**: en esta feature entra al mismo dashboard que el resto; su experiencia diferenciada (gestión de tenants) es una feature futura.
- **La sesión y su expiración** siguen los valores por defecto de la plataforma web (sesión de navegador estándar, "Recordarme" de larga duración).
