# Feature Specification: Ampliación del perfil de usuario (Mi perfil)

**Feature Branch**: `034-perfil-usuario-ampliado`

**Created**: 2026-07-26

**Status**: Draft

**Input**: User description: "Enriquecer la vista de perfil de usuario, que hoy es muy básica (solo avatar, nombre, badge de estado, rol legacy, email, tenant, fecha de alta), agregando: rol y permisos reales (Spatie), cambio de contraseña self-service, edición de nombre y email, actividad reciente propia (reutilizando logs_actividad), datos de empleado y fichajes (si el usuario está vinculado a un miembro de equipo), e info de aprobación de cuenta (quién aprobó y cuándo)."

## Clarifications

### Session 2026-07-26

- Q: ¿Cómo se verifica un cambio de correo electrónico antes de aplicarlo? → A: Enlace firmado temporal (expira, ej. 24h) enviado al correo nuevo; no se adopta el framework `MustVerifyEmail` de Laravel (pensado para verificación inicial de cuenta), se construye un mecanismo ligero propio para cambios de email.
- Q: ¿Cómo se invalidan otras sesiones activas al cambiar la contraseña? → A: Se eliminan las filas de la tabla de sesiones (driver `database`) del usuario correspondientes a otros dispositivos, preservando la sesión actual desde la que se hizo el cambio.
- Q: ¿Cuántos eventos de "actividad reciente" se muestran por defecto en el perfil? → A: 5, con enlace "ver más" hacia la sección de logs completa para quien tenga el permiso correspondiente.
- Q: ¿El cambio de nombre y/o email debe pedir la contraseña actual como confirmación? → A: Solo el cambio de email la pide (por ser el identificador de login); el cambio de nombre se guarda sin fricción adicional al no tener impacto en el acceso.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver mi rol y permisos reales (Priority: P1)

Un usuario autenticado entra a "Mi perfil" y ve el rol que realmente tiene asignado en el
sistema (el rol dinámico configurado por su administrador), junto con la lista de permisos
efectivos que ese rol le concede. Hoy la pantalla muestra un rol heredado (`super_admin` /
`admin` / `usuario`) que ya no refleja el sistema de roles granulares vigente desde la feature
027, lo que puede llevar a un usuario a creer que tiene menos o más acceso del que realmente
tiene.

**Why this priority**: Es una corrección de un dato incorrecto ya visible en producción, de
bajo esfuerzo y alto impacto en confianza: el usuario necesita saber a qué tiene acceso.

**Independent Test**: Iniciar sesión con un usuario cuyo rol Spatie sea distinto al valor legado
en la columna `rol`, entrar a "Mi perfil" y comprobar que el rol mostrado coincide con el rol
Spatie real, junto con el listado de permisos que ese rol otorga.

**Acceptance Scenarios**:

1. **Given** un usuario con un rol dinámico asignado (p. ej. "Contable"), **When** entra a "Mi
   perfil", **Then** ve "Contable" como su rol, no el valor legado de la columna `rol`.
2. **Given** un usuario cuyo rol le concede un conjunto de permisos, **When** entra a "Mi
   perfil", **Then** ve el listado de esos permisos en términos entendibles (no claves técnicas
   crudas).
3. **Given** un superadministrador (fuera del sistema de roles por tenant), **When** entra a "Mi
   perfil", **Then** ve indicado su carácter de superadministrador con acceso total, sin listar
   permisos uno por uno.

---

### User Story 2 - Cambiar mi contraseña (Priority: P1)

Un usuario autenticado quiere cambiar su propia contraseña sin depender de un administrador.
Desde "Mi perfil" introduce su contraseña actual y la nueva contraseña (con confirmación), y el
sistema la actualiza si los datos son válidos.

**Why this priority**: Es una capacidad de autoservicio básica de seguridad, ausente hoy por
completo (solo un administrador puede resetear contraseñas actualmente), y es requisito
razonable en cualquier SaaS.

**Independent Test**: Iniciar sesión, ir a "Mi perfil", cambiar la contraseña con datos válidos,
cerrar sesión e iniciar sesión de nuevo con la nueva contraseña.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado, **When** introduce su contraseña actual correcta y una
   nueva contraseña válida (con confirmación coincidente), **Then** la contraseña se actualiza y
   ve una confirmación de éxito.
2. **Given** un usuario autenticado, **When** introduce una contraseña actual incorrecta,
   **Then** ve un error claro y la contraseña no cambia.
3. **Given** un usuario autenticado, **When** la nueva contraseña y su confirmación no
   coinciden, o la nueva contraseña no cumple los requisitos mínimos de seguridad, **Then** ve un
   error de validación específico y la contraseña no cambia.
4. **Given** un usuario que cambió su contraseña exitosamente, **When** el cambio se confirma,
   **Then** las demás sesiones activas de ese usuario (si las hubiera en otros dispositivos)
   dejan de ser válidas, y la propia sesión actual permanece iniciada.

---

### User Story 3 - Editar mi nombre y correo electrónico (Priority: P2)

Un usuario autenticado quiere corregir o actualizar su nombre visible y su dirección de correo
electrónico (que además es su usuario de acceso) directamente desde su perfil, sin depender de
un administrador.

**Why this priority**: Autoservicio útil pero de menor urgencia que seguridad de contraseña;
además el email es dato sensible (es el identificador de login), por lo que requiere
verificación antes de aplicarse.

**Independent Test**: Iniciar sesión, editar el nombre desde "Mi perfil" y comprobar que se
refleja de inmediato; editar el email y comprobar que el cambio no se aplica hasta confirmar el
nuevo correo mediante el enlace recibido en él.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado, **When** cambia su nombre visible y guarda, **Then** el
   nuevo nombre se aplica de inmediato y se refleja en toda la interfaz (sidebar, cabecera,
   etc.).
2. **Given** un usuario autenticado, **When** introduce su contraseña actual, un nuevo correo
   electrónico y guarda, **Then** el sistema envía un enlace de verificación temporal (con
   vencimiento) a la nueva dirección y el email de acceso actual sigue siendo válido hasta que el
   usuario confirme el nuevo desde ese enlace.
3. **Given** un usuario que solicitó cambio de correo pero no lo confirmó, **When** vuelve a
   "Mi perfil", **Then** ve indicado que tiene un cambio de correo pendiente de confirmación, con
   opción de reenviar el correo de verificación o cancelarlo.
4. **Given** un usuario autenticado, **When** intenta cambiar su correo a uno ya usado por otra
   cuenta del mismo tenant, **Then** ve un error de validación y el cambio no se envía.
5. **Given** un usuario autenticado, **When** introduce una contraseña actual incorrecta al
   intentar solicitar un cambio de correo, **Then** ve un error claro y no se envía ningún correo
   de verificación.

---

### User Story 4 - Ver mi actividad reciente (Priority: P2)

Un usuario autenticado quiere ver un resumen de sus últimos accesos al sistema (fecha, resultado
éxito/fallo, ubicación aproximada y dispositivo/navegador) directamente desde su perfil, como
forma de detectar accesos no reconocidos.

**Why this priority**: Valor de seguridad y transparencia, reutiliza datos ya recolectados por
el sistema (visibles hoy solo en el listado global de logs, no accesible a todos los roles).

**Independent Test**: Iniciar sesión, cerrar sesión, volver a iniciar sesión con una contraseña
incorrecta una vez y luego correctamente; entrar a "Mi perfil" y comprobar que los tres eventos
aparecen en el bloque de actividad reciente con su resultado correcto.

**Acceptance Scenarios**:

1. **Given** un usuario con historial de accesos, **When** entra a "Mi perfil", **Then** ve una
   lista de sus eventos de actividad más recientes (al menos inicios de sesión), cada uno con
   fecha/hora, resultado (éxito/fallo), navegador legible y ubicación aproximada si está
   disponible.
2. **Given** un usuario sin actividad registrada todavía (cuenta recién creada), **When** entra
   a "Mi perfil", **Then** ve un estado vacío claro en lugar de una lista rota o un error.
3. **Given** un usuario con muchos eventos de actividad, **When** entra a "Mi perfil", **Then**
   ve solo los más recientes (no el historial completo) con opción de ver el listado completo si
   su rol tiene acceso a la sección de logs.

---

### User Story 5 - Ver mis datos de empleado y fichajes (Priority: P3)

Un usuario que además es miembro de equipo (ficha su jornada laboral) quiere ver, desde su
perfil, su puesto, su centro de trabajo asignado y un resumen de su situación de fichaje actual
(fichado / no fichado / en pausa) junto con sus fichajes más recientes.

**Why this priority**: Valor de conveniencia para el subconjunto de usuarios que también son
empleados con fichaje; no aplica a todos los usuarios del sistema (p. ej. administradores sin
ficha de empleado), de ahí prioridad más baja.

**Independent Test**: Iniciar sesión como un usuario vinculado a un miembro de equipo con
fichajes registrados, entrar a "Mi perfil" y comprobar que aparece su puesto, centro de trabajo y
estado de fichaje actual coincidente con lo mostrado en el módulo de fichajes.

**Acceptance Scenarios**:

1. **Given** un usuario vinculado a un miembro de equipo activo, **When** entra a "Mi perfil",
   **Then** ve su puesto y su centro de trabajo asignado.
2. **Given** un usuario vinculado a un miembro de equipo, **When** entra a "Mi perfil", **Then**
   ve su estado de fichaje actual (fichado / no fichado / en pausa) y sus fichajes más recientes,
   coherente con lo que muestra el módulo de fichajes para el mismo miembro.
3. **Given** un usuario que NO está vinculado a ningún miembro de equipo, **When** entra a "Mi
   perfil", **Then** no ve ninguna sección de empleado/fichajes (se omite por completo, sin
   mensajes de "sin datos" que confundan).
4. **Given** un usuario vinculado a un miembro de equipo dado de baja, **When** entra a "Mi
   perfil", **Then** ve reflejado que su vínculo de empleado ya no está activo, sin mostrar un
   estado de fichaje en vivo.

---

### User Story 6 - Ver quién aprobó mi cuenta (Priority: P3)

Un usuario cuya cuenta ya fue aprobada quiere ver, desde su perfil, quién aprobó su alta y
cuándo, como confirmación de que su acceso fue verificado por alguien de su organización.

**Why this priority**: Dato informativo de bajo esfuerzo (ya existe en el modelo), valor
secundario de transparencia.

**Independent Test**: Iniciar sesión con un usuario aprobado, entrar a "Mi perfil" y comprobar
que se muestra el nombre del aprobador y la fecha de aprobación.

**Acceptance Scenarios**:

1. **Given** un usuario cuya cuenta fue aprobada por otro usuario, **When** entra a "Mi perfil",
   **Then** ve el nombre de quien aprobó su cuenta y la fecha en que ocurrió.
2. **Given** un usuario cuya cuenta está pendiente de aprobación, **When** entra a "Mi perfil",
   **Then** no ve información de aprobador (aún no existe), y en su lugar ve claramente que su
   cuenta está pendiente.

---

### Edge Cases

- ¿Qué ocurre si un usuario intenta cambiar su correo a una dirección que no puede recibir el
  email de verificación (dirección inválida detectada solo por rebote)? El cambio queda pendiente
  indefinidamente hasta que el usuario lo cancele o reintente con otra dirección; no se aplica
  automáticamente.
- ¿Qué ocurre si el usuario cierra sesión antes de confirmar un cambio de correo pendiente? Al
  volver a iniciar sesión con el correo actual (aún vigente), ve el aviso de cambio pendiente en
  su perfil.
- ¿Qué ocurre si el usuario hace clic en el enlace de verificación de cambio de correo después de
  que haya vencido? El cambio no se aplica; ve un mensaje indicando que el enlace expiró y puede
  volver a solicitar el cambio desde su perfil.
- ¿Qué ocurre si un usuario sin miembro de equipo asociado tenía uno en el pasado y fue
  desvinculado? Se trata igual que "no vinculado": no se muestra la sección de empleado/fichajes.
- ¿Qué ocurre con el listado de "actividad reciente" para un superadministrador que actúa sobre
  varios tenants? Solo se muestran los eventos de actividad correspondientes a su propia cuenta,
  respetando el aislamiento por tenant de cada evento registrado.
- ¿Qué ocurre si el usuario intenta cambiar su rol o sus permisos desde el perfil? No es posible:
  el perfil es de solo lectura para rol y permisos; su gestión sigue perteneciendo exclusivamente
  a la sección de administración de usuarios/roles.
- ¿Qué ocurre si falla el envío del correo de verificación de cambio de email? El usuario ve un
  error claro y puede reintentar el envío desde el propio perfil.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE mostrar en el perfil el rol dinámico real del usuario (el
  configurado mediante el sistema de roles por tenant), no el valor heredado que ya no refleja el
  acceso vigente.
- **FR-002**: El sistema DEBE mostrar en el perfil el listado de permisos efectivos que el rol
  del usuario le concede, en un lenguaje comprensible para un usuario no técnico.
- **FR-003**: Para un superadministrador, el sistema DEBE indicar su carácter de acceso total en
  lugar de enumerar permisos individuales.
- **FR-004**: El sistema DEBE permitir a cualquier usuario autenticado cambiar su propia
  contraseña desde su perfil, exigiendo la contraseña actual como confirmación de identidad.
- **FR-005**: El sistema DEBE validar que la nueva contraseña cumple los requisitos mínimos de
  seguridad ya vigentes en el sistema y que coincide con su confirmación, antes de aplicarla.
- **FR-006**: Al cambiar la contraseña exitosamente, el sistema DEBE invalidar las demás sesiones
  activas del usuario en otros dispositivos (eliminando sus registros de sesión), manteniendo
  activa la sesión actual desde la que se hizo el cambio.
- **FR-007**: El sistema DEBE permitir a cualquier usuario autenticado editar su propio nombre
  visible, aplicándose de inmediato sin pedir confirmación adicional de identidad.
- **FR-008**: El sistema DEBE permitir a cualquier usuario autenticado, tras confirmar su
  contraseña actual, solicitar un cambio de su correo electrónico de acceso, el cual NO se aplica
  hasta que el usuario confirme la nueva dirección mediante un enlace de verificación con
  vencimiento enviado a ella.
- **FR-009**: Mientras un cambio de correo esté pendiente de confirmación, el sistema DEBE seguir
  aceptando el correo electrónico actual como identificador de acceso.
- **FR-010**: El sistema DEBE impedir que un usuario cambie su correo a una dirección ya en uso
  por otra cuenta del mismo tenant.
- **FR-011**: El sistema DEBE mostrar en el perfil si existe un cambio de correo pendiente de
  confirmación, con opción de reenviar la verificación o cancelar la solicitud; si el enlace de
  verificación vence sin confirmarse, el sistema DEBE tratar la solicitud como caducada y permitir
  solicitar una nueva.
- **FR-012**: El sistema DEBE mostrar en el perfil los eventos de actividad más recientes del
  propio usuario autenticado (como mínimo, inicios de sesión), incluyendo fecha/hora, resultado
  (éxito/fallo), navegador legible y ubicación aproximada cuando esté disponible.
- **FR-013**: El sistema DEBE limitar por defecto la actividad reciente mostrada en el perfil a
  los 5 eventos más recientes, ofreciendo un enlace al historial completo únicamente a usuarios
  cuyo rol ya tiene permiso sobre la sección de logs de actividad.
- **FR-014**: El sistema DEBE mostrar un estado vacío apropiado cuando el usuario no tiene
  actividad registrada todavía, en vez de una sección rota o vacía sin explicación.
- **FR-015**: Cuando el usuario autenticado esté vinculado a un miembro de equipo activo, el
  sistema DEBE mostrar en el perfil su puesto y su centro de trabajo asignado.
- **FR-016**: Cuando el usuario autenticado esté vinculado a un miembro de equipo activo, el
  sistema DEBE mostrar su estado de fichaje actual (fichado / no fichado / en pausa) y sus
  fichajes más recientes, de forma coherente con lo mostrado en el módulo de fichajes para ese
  mismo miembro.
- **FR-017**: Cuando el usuario autenticado NO esté vinculado a ningún miembro de equipo, o el
  vínculo esté dado de baja, el sistema DEBE omitir la sección de empleado/fichajes (o indicar
  claramente que el vínculo ya no está activo, sin mostrar un estado de fichaje en vivo).
- **FR-018**: Cuando la cuenta del usuario autenticado ya fue aprobada, el sistema DEBE mostrar
  en el perfil quién la aprobó y en qué fecha.
- **FR-019**: Cuando la cuenta del usuario autenticado esté pendiente de aprobación, el sistema
  DEBE mostrar ese estado en vez de datos de aprobador.
- **FR-020**: El perfil DEBE seguir siendo de solo lectura respecto a rol y permisos: ningún
  usuario puede modificar su propio rol o permisos desde esta pantalla.
- **FR-021**: Todas las consultas nuevas de este perfil (actividad, datos de empleado, fichajes,
  aprobación) DEBEN respetar el aislamiento por tenant existente, devolviendo únicamente datos
  del propio usuario autenticado dentro de su propio tenant.

### Key Entities *(include if feature involves data)*

- **Usuario (User)**: entidad ya existente; se le agregan capacidades de autoservicio
  (contraseña, nombre, correo con verificación pendiente) y se corrige la fuente de verdad del
  rol mostrado.
- **Rol / Permiso**: entidades ya existentes del sistema de roles por tenant; el perfil consume
  el rol asignado al usuario y sus permisos efectivos, sin modificarlos.
- **Registro de actividad (log de acceso)**: entidad ya existente; el perfil consume el
  subconjunto de eventos correspondientes al propio usuario.
- **Miembro de equipo**: entidad ya existente que representa el perfil de empleado de un usuario
  (puesto, centro de trabajo, estado de alta/baja); el perfil consume sus datos cuando existe
  vínculo.
- **Fichaje**: entidad ya existente que representa eventos de entrada/salida/pausa de un miembro
  de equipo; el perfil consume el estado derivado más reciente y un resumen de los últimos
  eventos.
- **Solicitud de cambio de correo**: concepto nuevo (pendiente de confirmación) que vincula un
  correo nuevo propuesto a la cuenta del usuario hasta que se verifica.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los usuarios ven en su perfil un rol coherente con el acceso real que
  tienen en el sistema (verificable comparando el rol mostrado con los permisos que efectivamente
  pueden ejercer).
- **SC-002**: Un usuario puede cambiar su propia contraseña sin intervención de un administrador
  en menos de 1 minuto.
- **SC-003**: Un usuario puede actualizar su nombre visible en menos de 30 segundos, y ver el
  cambio reflejado de inmediato en toda la interfaz.
- **SC-004**: Un cambio de correo electrónico nunca se aplica sin que el usuario haya confirmado
  la propiedad de la nueva dirección (0% de cambios de correo aplicados sin verificación).
- **SC-005**: Un usuario puede identificar, sin salir de su perfil, si su última sesión iniciada
  fue exitosa y desde qué ubicación/dispositivo aproximado, reduciendo la necesidad de pedir esa
  información a un administrador.
- **SC-006**: Un usuario vinculado a un miembro de equipo puede confirmar su estado de fichaje
  actual sin necesidad de salir de su perfil y entrar al módulo de fichajes.
- **SC-007**: Los usuarios no vinculados a un miembro de equipo no ven ninguna sección vacía o
  confusa relacionada con fichajes (0% de secciones "vacías" mostradas a usuarios sin vínculo).

## Assumptions

- El sistema de roles dinámicos por tenant (feature 027) es la única fuente de verdad de acceso
  vigente; la columna heredada de rol se conserva en el modelo de datos pero deja de mostrarse en
  el perfil.
- Los requisitos mínimos de seguridad de contraseña ya vigentes en el sistema (`min:8`, sin reglas
  adicionales de complejidad) se reutilizan sin cambios para el cambio de contraseña self-service.
- El envío del enlace de verificación de cambio de email reutiliza el mecanismo de envío de correo
  ya existente en el sistema (el mismo canal usado para otras notificaciones transaccionales); es
  un mecanismo propio y ligero (enlace firmado con vencimiento), no el flujo `MustVerifyEmail` de
  Laravel, que no está implementado hoy y está pensado para verificación inicial de cuenta.
- "Actividad reciente" en el perfil se limita a eventos de acceso (inicio/cierre de sesión,
  éxito/fallo) ya capturados por el sistema de logs existente; no se introduce una nueva categoría
  de evento.
- Un usuario puede estar vinculado a lo sumo a un miembro de equipo, consistente con el modelo de
  datos actual (relación uno-a-uno).
- La invalidación de otras sesiones al cambiar contraseña se apoya en el mecanismo de sesiones ya
  presente en el sistema (tabla de sesiones con driver `database`), eliminando las filas de otros
  dispositivos del mismo usuario, sin requerir infraestructura nueva de gestión de sesiones
  visibles/revocables individualmente por el usuario (eso queda fuera de alcance de esta feature).
- El listado de permisos efectivos se muestra usando las etiquetas legibles ya definidas en el
  catálogo de permisos del sistema, no las claves técnicas internas.
- Fuera de alcance de esta feature: autenticación de dos factores (2FA), gestión visible/revocable
  de sesiones activas por dispositivo, preferencias de notificación, y eliminación de cuenta
  propia.
