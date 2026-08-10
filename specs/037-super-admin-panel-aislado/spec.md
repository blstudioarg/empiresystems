# Feature Specification: Panel de Super Admin aislado + home con estadísticas de tenants

**Feature Branch**: `037-super-admin-panel-aislado`

**Created**: 2026-07-27

**Status**: Draft

**Input**: User description: "Mejorar el panel de super admin de los tenants (dominio central). Hoy, si el super admin entra a una de las URLs normales (área de tenant), la app se lo permite; no debería ser posible, porque el super admin no tiene tenant: administra tenants y lo único que debe ver es su panel. Además, crear una home propia del super admin para que al loguearse aterrice en estadísticas de los tenants creados."

## Contexto y problema actual

El **Super Admin** es un usuario del sistema sin empresa asociada (`tenant_id` nulo) que existe para
dar de alta, editar, activar/desactivar y eliminar **tenants** (las empresas clientes del SaaS) y
para gestionar los usuarios de acceso de cada tenant. Opera exclusivamente desde el **dominio
central** del SaaS; cada tenant tiene su propio dominio.

Dos problemas observados hoy:

1. **Fuga de navegación**: estando autenticado como Super Admin en el dominio central, escribir (o
   llegar por un enlace/histórico del navegador a) la URL de una pantalla del área de empresa
   —clientes, facturas, artículos, configuración, etc.— **devuelve la pantalla en vez de negarla**.
   Como el Super Admin no pertenece a ninguna empresa, esas pantallas no tienen un contexto de
   empresa al que referirse: muestran listados vacíos, datos sin dueño claro o errores, y sobre todo
   abren una vía por la que una pantalla de negocio podría ejecutarse fuera del aislamiento por
   empresa. Es un problema de seguridad y de coherencia del producto, no solo estético.

2. **Aterrizaje pobre**: al iniciar sesión, el Super Admin cae directamente en el listado de gestión
   de tenants. No tiene ninguna vista de conjunto del SaaS (cuántas empresas hay, cuántas activas,
   cuántas se dieron de alta este mes, cuáles están paradas), que es justamente la información que
   necesita para decidir a qué tenant entrar.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El Super Admin no puede entrar a pantallas de empresa (Priority: P1)

Como Super Admin autenticado en el dominio central, cuando intento abrir cualquier pantalla del área
de empresa (por URL directa, por historial del navegador, por un enlace pegado o por un marcador
antiguo), el sistema me lo impide siempre y me devuelve a mi propio panel avisándome de que esa
sección pertenece al área de empresa.

**Why this priority**: es el problema de seguridad/coherencia. Sin esto, todo lo demás es cosmético:
el panel puede ser precioso pero el Super Admin sigue pudiendo caer en pantallas que no le
corresponden y operar sin contexto de empresa.

**Independent Test**: se puede probar por sí solo, sin la home nueva: autenticarse como Super Admin,
pedir una lista representativa de URLs del área de empresa y verificar que ninguna devuelve la
pantalla; verificar también que un usuario normal de una empresa sigue accediendo con normalidad a
esas mismas URLs desde el dominio de su empresa.

**Acceptance Scenarios**:

1. **Given** un Super Admin autenticado en el dominio central, **When** navega a la pantalla de
   clientes del área de empresa, **Then** no ve la pantalla: es redirigido a la home del panel de
   Super Admin con un aviso de que esa sección pertenece al área de empresa.
2. **Given** un Super Admin autenticado, **When** intenta una acción de escritura del área de empresa
   (crear/editar/eliminar un registro de negocio, incluidas peticiones en segundo plano de la
   interfaz), **Then** la acción es rechazada con un error de acceso y no modifica ningún dato.
3. **Given** un Super Admin autenticado, **When** abre su perfil personal o cierra sesión, **Then**
   funciona con normalidad (son las únicas áreas comunes que sigue teniendo disponibles).
4. **Given** un usuario normal (administrador o no) de una empresa, **When** navega por las pantallas
   de su empresa desde el dominio de esa empresa, **Then** todo sigue funcionando exactamente igual
   que antes de este cambio.
5. **Given** un usuario normal de una empresa, **When** intenta abrir cualquier pantalla del panel de
   Super Admin, **Then** se le sigue negando el acceso (comportamiento ya existente, no debe
   romperse).
6. **Given** un Super Admin, **When** su sesión se presenta en un dominio de empresa (no el central),
   **Then** no obtiene acceso a ninguna pantalla de esa empresa.

---

### User Story 2 - Home del Super Admin con la foto del SaaS (Priority: P2)

Como Super Admin, al iniciar sesión aterrizo en una home propia del panel que me muestra de un
vistazo el estado del conjunto de tenants: cuántos hay, cuántos están activos e inactivos, cuántos se
dieron de alta en el periodo, cuántos usuarios suman, cómo evolucionaron las altas y cuáles son los
últimos tenants creados; desde ahí salto al listado de gestión cuando necesito operar sobre uno.

**Why this priority**: es el valor de producto de la feature, pero depende de que el panel exista como
área propia y bien delimitada (US1). Sin US1 la home sería una pantalla más entre pantallas a las que
el Super Admin no debería llegar.

**Independent Test**: autenticarse como Super Admin con un conjunto conocido de tenants (activos,
inactivos, con altas en fechas distintas) y verificar que cada indicador de la home refleja
exactamente ese conjunto y que el enlace al listado de gestión funciona.

**Acceptance Scenarios**:

1. **Given** un Super Admin, **When** inicia sesión correctamente, **Then** aterriza en la home del
   panel de Super Admin (no en el listado de tenants ni en una pantalla de empresa).
2. **Given** un conjunto conocido de tenants (p. ej. 10 en total, 7 activos y 3 inactivos),
   **When** el Super Admin abre la home, **Then** ve el total, los activos y los inactivos con esos
   valores exactos.
3. **Given** tenants dados de alta en meses distintos, **When** el Super Admin abre la home, **Then**
   ve la evolución de altas por mes de los últimos 12 meses y el número de altas del periodo en
   curso.
4. **Given** cualquier estado de datos, **When** el Super Admin abre la home, **Then** ve la lista de
   los últimos tenants creados, cada uno con su nombre, dominio, estado y fecha de alta, y puede
   saltar desde ahí a la gestión de ese tenant.
5. **Given** que todavía no existe ningún tenant, **When** el Super Admin abre la home, **Then** ve
   una pantalla con los indicadores en cero y un mensaje/acción claros para crear el primer tenant
   (no una pantalla rota ni gráficos vacíos sin explicación).

---

### User Story 3 - Detección de tenants que necesitan atención (Priority: P3)

Como Super Admin, además de los totales quiero identificar rápidamente qué tenants requieren
atención: los desactivados, los que no tienen ningún usuario que pueda entrar, y los que llevan
tiempo sin actividad de sus usuarios.

**Why this priority**: mejora real de gestión, pero es un extra sobre la foto básica; la home ya
aporta valor sin esto.

**Independent Test**: preparar tenants en cada situación (desactivado, sin usuarios activos, sin
actividad reciente) y verificar que aparecen señalados en la home.

**Acceptance Scenarios**:

1. **Given** un tenant desactivado, **When** el Super Admin abre la home, **Then** ese tenant aparece
   en el bloque de "requieren atención" indicando el motivo.
2. **Given** un tenant sin ningún usuario en condiciones de iniciar sesión, **When** el Super Admin
   abre la home, **Then** ese tenant aparece señalado con ese motivo.
3. **Given** un tenant cuyos usuarios no registran actividad en los últimos 30 días, **When** el
   Super Admin abre la home, **Then** ese tenant aparece señalado como inactivo por falta de uso.
4. **Given** que ningún tenant cumple ninguno de esos criterios, **When** el Super Admin abre la
   home, **Then** el bloque muestra un estado vacío positivo ("todos los tenants en orden"), no una
   lista vacía sin explicación.

---

### Edge Cases

- **Peticiones en segundo plano de la interfaz** (listados que se cargan por detrás, guardados sin
  recargar página): el bloqueo debe responder un error de acceso legible por la interfaz, no una
  redirección que la interfaz interpretaría como datos válidos.
- **Descargas y documentos** del área de empresa (PDF de factura, exportaciones): quedan bloqueados
  igual que el resto; el Super Admin no descarga documentos de un tenant desde el área de empresa.
- **Sesión iniciada antes del cambio**: un Super Admin con sesión abierta que tenga una pantalla de
  empresa cargada y pulse un botón después del despliegue recibe el bloqueo, no un comportamiento
  a medias.
- **Enlaces internos**: ninguna pantalla del panel de Super Admin puede enlazar a una pantalla del
  área de empresa (evita mandar al usuario a un bloqueo).
- **Tenant eliminado mientras se mira la home**: los indicadores se recalculan en cada carga; una
  entrada que apunte a un tenant ya inexistente no debe romper la pantalla.
- **Volumen**: con decenas de tenants y muchos usuarios, la home debe seguir abriéndose rápido; los
  indicadores son agregados, no listados completos.
- **Periodo sin datos**: meses sin altas aparecen con valor cero en la evolución, no se omiten.
- **Ruta raíz del dominio central**: abrirla como Super Admin lleva a la home del panel; abrirla sin
  sesión sigue llevando al inicio de sesión.

## Requirements *(mandatory)*

### Functional Requirements

**Aislamiento (US1)**

- **FR-001**: El sistema DEBE impedir que un usuario Super Admin acceda a cualquier pantalla o acción
  del área de empresa, con independencia de que su rol le conceda todos los permisos del catálogo.
- **FR-002**: El bloqueo DEBE aplicarse de forma estructural y por defecto sobre todo el área de
  empresa (incluidas las secciones que se añadan en el futuro sin acordarse de esta regla), no
  ocultando enlaces ni sección por sección.
- **FR-003**: Ante una navegación de pantalla, el bloqueo DEBE redirigir a la home del panel de Super
  Admin mostrando un aviso explicativo; ante una petición en segundo plano de la interfaz, DEBE
  devolver un error de acceso con mensaje legible.
- **FR-004**: Las únicas áreas comunes que el Super Admin conserva son: su **perfil personal**
  (incluido cambio de contraseña/avatar), el **cierre de sesión** y los catálogos auxiliares de apoyo
  que ya usan los formularios de su propio panel (p. ej. el catálogo geográfico de
  provincias/localidades). Todo lo demás del área de empresa queda bloqueado.
- **FR-005**: El bloqueo NO DEBE alterar en ningún caso el acceso de los usuarios de empresa a su
  propia área ni el acceso denegado que ya existe para usuarios de empresa hacia el panel de Super
  Admin.
- **FR-006**: El sistema DEBE seguir garantizando que el Super Admin solo opera desde el dominio
  central: una sesión de Super Admin presentada en un dominio de empresa no obtiene acceso a las
  pantallas de esa empresa.
- **FR-007**: El menú y cualquier navegación visible para el Super Admin DEBEN mostrar únicamente
  secciones de su propio panel (más perfil y cierre de sesión); ninguna entrada puede llevar al área
  de empresa.
- **FR-008**: Cada intento bloqueado DEBE quedar registrado como acceso denegado con la información
  de auditoría habitual (quién, cuándo, qué se intentó, IP y agente de usuario). Ese registro va al
  **diario técnico de la aplicación**, no al registro de actividad que ve un tenant: el intento no
  pertenece a ninguna empresa y no debe aparecer en el histórico de ninguna (ver `research.md`, D4).

**Home del Super Admin (US2)**

- **FR-009**: El sistema DEBE ofrecer una home propia del panel de Super Admin, distinta del listado
  de gestión de tenants, que pase a ser el destino por defecto tras iniciar sesión y al abrir la raíz
  del dominio central.
- **FR-010**: La home DEBE mostrar, como mínimo, estos indicadores agregados sobre el conjunto de
  tenants: total de tenants, tenants activos, tenants inactivos, altas en el periodo en curso y total
  de usuarios de todos los tenants.
- **FR-011**: La home DEBE mostrar la **evolución de altas de tenants** de los últimos 12 meses,
  incluyendo con valor cero los meses sin altas.
- **FR-012**: La home DEBE mostrar el listado de los **últimos tenants creados** (nombre comercial,
  dominio, estado y fecha de alta), con acceso directo a su gestión.
- **FR-013**: La home DEBE mostrar, por tenant, una medida de **tamaño/uso** que permita comparar
  tenants entre sí (número de usuarios y volumen de documentos de facturación emitidos), presentada
  como un ranking de los tenants más grandes.
- **FR-014**: Todos los indicadores DEBEN calcularse sobre datos vivos en cada carga, sin depender de
  valores precalculados que puedan quedar obsoletos.
- **FR-015**: La home DEBE tener un estado vacío explícito cuando no existe ningún tenant, con la
  acción de crear el primero.
- **FR-016**: El listado de gestión de tenants DEBE seguir existiendo como sección propia y accesible
  desde el menú del panel, con toda su funcionalidad actual intacta.

**Atención a tenants (US3)**

- **FR-017**: La home DEBE señalar los tenants que requieren atención, con el motivo, para al menos
  estos tres criterios: (a) tenant desactivado, (b) tenant sin ningún usuario en condiciones de
  iniciar sesión, (c) tenant sin actividad de sus usuarios en los últimos 30 días.
- **FR-018**: El bloque de atención DEBE mostrar un estado vacío positivo cuando ningún tenant cumple
  ninguno de los criterios.

**Transversales**

- **FR-019**: Todas las consultas agregadas de la home operan en contexto central (sin empresa
  activa) y DEBEN filtrar explícitamente por tenant cuando miran datos de negocio, sin devolver
  detalle de los datos de negocio de un tenant (solo recuentos e importes agregados).
- **FR-020**: La documentación in-app de ayuda de las pantallas nuevas del panel y la base de
  conocimiento del asistente DEBEN quedar al día con lo que esta feature añade o cambia.

### Key Entities *(include if feature involves data)*

- **Tenant**: empresa cliente del SaaS. Atributos relevantes aquí: nombre comercial, dominio, estado
  (activo/inactivo) y fecha de alta. Esta feature **no** añade campos nuevos.
- **Usuario**: persona con acceso. Puede pertenecer a un tenant o, en el caso del Super Admin, a
  ninguno. Relevante para el recuento de usuarios por tenant y para el criterio "tenant sin usuarios
  que puedan entrar".
- **Registro de actividad**: eventos de acceso y de cambios ya existentes. Relevante como fuente del
  criterio "sin actividad reciente" y como destino del registro de accesos denegados.
- **Documento de facturación emitido**: ya existente. Relevante solo como recuento agregado por
  tenant para la medida de tamaño/uso.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100 % de los intentos del Super Admin de abrir una pantalla o acción del área de
  empresa termina en bloqueo (redirección con aviso o error de acceso); ninguna devuelve la pantalla.
- **SC-002**: El bloqueo cubre automáticamente cualquier sección futura del área de empresa: añadir
  una sección nueva no requiere ninguna acción adicional para que quede bloqueada al Super Admin.
- **SC-003**: Cero regresiones en el acceso de usuarios de empresa: todas las pantallas del área de
  empresa siguen accesibles para sus usuarios con permiso, verificado por la batería de pruebas
  existente en verde.
- **SC-004**: Tras iniciar sesión, el Super Admin ve la foto del conjunto de tenants sin ninguna
  navegación adicional (0 clics desde el inicio de sesión hasta los indicadores).
- **SC-005**: Cada indicador de la home coincide exactamente con el estado real de los datos en un
  escenario de prueba controlado (diferencia 0).
- **SC-006**: La home se abre en menos de 2 segundos con al menos 100 tenants y 1.000 usuarios en
  total.
- **SC-007**: Desde la home, llegar a la gestión de un tenant concreto cuesta como máximo 2 clics.
- **SC-008**: El 100 % de los intentos bloqueados deja una entrada en el diario técnico de la
  aplicación con usuario, ruta solicitada, IP y agente de usuario; ninguna de esas entradas aparece
  en el registro de actividad de ningún tenant.

## Assumptions

- **Panel = dominio central.** El panel de Super Admin sigue viviendo solo en el dominio central del
  SaaS; esta feature no cambia la resolución de empresa por dominio ni el inicio de sesión.
- **El Super Admin conserva su perfil.** Se asume que debe poder cambiar su contraseña y su avatar;
  por eso el perfil es la única pantalla común que sigue disponible (la pantalla de perfil ya
  contempla hoy el caso "usuario sin empresa").
- **Bloquear, no suplantar.** Esta feature **no** introduce ninguna forma de que el Super Admin
  "entre como" un tenant ni vea sus pantallas de negocio. Si alguna vez hace falta soporte con
  suplantación, será una feature aparte con su propio consentimiento y auditoría.
- **Sin datos nuevos.** No se crean tablas ni columnas: todos los indicadores se derivan de datos ya
  existentes (tenants, dominios, usuarios, registro de actividad, documentos de facturación).
- **Sin cambios en el catálogo de permisos del tenant.** El panel de Super Admin se rige por su
  propia condición de acceso (rol Super Admin + sin empresa + dominio central), no por permisos de
  empresa; esta feature no añade permisos al catálogo de los tenants.
- **Volumen esperado.** Del orden de 50–80 tenants; los agregados se calculan en cada carga sin
  necesidad de precálculo ni caché.
- **Periodo de la home.** "Altas del periodo en curso" se interpreta como el mes natural en curso, y
  la evolución de altas como los últimos 12 meses, salvo indicación posterior del usuario.
- **"Sin actividad reciente"** se mide sobre el registro de actividad de los usuarios del tenant con
  una ventana de 30 días.
