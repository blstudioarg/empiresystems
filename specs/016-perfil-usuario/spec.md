# Feature Specification: Vista de perfil de usuario (Mi perfil)

**Feature Branch**: `016-perfil-usuario`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Vista de perfil de usuario (Mi perfil). Crear la vista que ya espera el ProfileController@show existente, reutilizando la cabecera del template NexaDash overview-profile, con avatar editable, datos reales del usuario y descartando todo el contenido demo del template."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver mi perfil (Priority: P1)

Un usuario autenticado del CRM accede a "Mi perfil" desde la barra lateral / menú de usuario
y ve una página con sus datos reales: nombre, foto de perfil, rol dentro de la empresa, correo
electrónico, empresa (tenant) a la que pertenece, estado de aprobación de su cuenta y la fecha
en que se dio de alta. La página vive dentro del layout habitual con sidebar y respeta el tema
claro/oscuro del CRM.

**Why this priority**: Es el núcleo de la feature y el MVP. Sin la vista de solo lectura, la
ruta `profile.show` (ya cableada en el backend) devuelve un error porque la vista no existe.
Entrega valor inmediato: el usuario puede consultar y verificar sus propios datos de cuenta.

**Independent Test**: Iniciar sesión como un usuario cualquiera, navegar a `/perfil` y comprobar
que se muestran sus datos reales (no datos demo del template) sin errores.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado y aprobado, **When** entra a "Mi perfil", **Then** ve su
   nombre, avatar, rol, email, empresa, estado "Aprobado" y su fecha de alta.
2. **Given** un usuario con cuenta aún pendiente de aprobación, **When** entra a "Mi perfil",
   **Then** ve claramente indicado que su estado es "Pendiente".
3. **Given** un usuario sin foto de perfil subida, **When** entra a "Mi perfil", **Then** ve
   un avatar por defecto en lugar de una imagen rota.

---

### User Story 2 - Cambiar mi foto de perfil (Priority: P2)

Desde la propia vista de perfil, el usuario puede subir o reemplazar su foto de perfil. Tras
subirla, la imagen se actualiza y recibe una confirmación visual (notificación).

**Why this priority**: Es la única acción de escritura de la vista y el endpoint
(`profile.avatar.update`) ya existe en el backend; conecta la UI con una capacidad ya
implementada. Es secundaria respecto a la consulta de datos pero aporta personalización.

**Independent Test**: Desde "Mi perfil", subir una imagen válida y verificar que el avatar
mostrado cambia y aparece una notificación de éxito.

**Acceptance Scenarios**:

1. **Given** un usuario en su perfil, **When** sube una imagen válida (formato de imagen,
   dentro del tamaño permitido), **Then** su avatar se actualiza y ve una notificación de éxito.
2. **Given** un usuario en su perfil, **When** intenta subir un archivo no válido (no imagen o
   demasiado grande), **Then** ve un mensaje de error y su avatar no cambia.
3. **Given** un usuario que ya tenía foto, **When** sube una nueva, **Then** la anterior se
   reemplaza por la nueva.

---

### Edge Cases

- **Usuario sin empresa/tenant asociado** (p. ej. Super Admin sin tenant): la vista debe mostrar
  un valor neutro ("Sin empresa" o equivalente) en lugar de fallar.
- **Estado Rechazado**: debe representarse de forma distinguible del estado Pendiente/Aprobado.
- **Fecha de alta ausente**: mostrar un valor de reserva en vez de dejar el campo vacío o roto.
- **Aislamiento**: un usuario nunca puede ver ni editar el perfil de otro usuario; "Mi perfil"
  siempre resuelve al usuario autenticado, sin identificadores manipulables en la URL.
- **Subida sin archivo seleccionado**: la acción no debe romper la página.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE mostrar una vista "Mi perfil" para el usuario autenticado dentro
  del layout con sidebar del CRM, respetando el tema claro/oscuro persistido.
- **FR-002**: La vista DEBE mostrar los datos reales del usuario autenticado: nombre, foto de
  perfil (o avatar por defecto si no tiene), rol, correo electrónico, empresa (tenant), estado
  de aprobación de la cuenta y fecha de alta.
- **FR-003**: El rol y el estado de la cuenta DEBEN mostrarse con etiquetas legibles en español
  (no con los valores técnicos internos del enum).
- **FR-004**: La vista DEBE reutilizar la cabecera visual del template NexaDash (card de
  cabecera con avatar, badge de estado, nombre y meta-datos en línea) descartando todo el
  contenido demo no aplicable a un CRM de facturación (feed social, comentarios, galerías,
  quotes, embeds de vídeo/audio, botones "Follow/Offer a Deal", iconos de redes sociales,
  métricas "Total Earnings/New Referrals/New Deals", proyectos, campañas, lista de tareas y
  gráficos de ventas de demostración).
- **FR-005**: Los usuarios DEBEN poder subir o reemplazar su foto de perfil desde la vista,
  usando la capacidad de actualización de avatar ya existente.
- **FR-006**: El sistema DEBE mostrar una notificación de éxito tras cambiar la foto de perfil
  y un mensaje de error si la subida no es válida, siguiendo el patrón de notificaciones del
  proyecto (toastr).
- **FR-007**: El sistema DEBE garantizar que la vista siempre corresponde al usuario autenticado
  y que ningún usuario puede acceder al perfil de otro (sin identificador de usuario en la ruta).
- **FR-008**: Toda la interfaz de la vista DEBE estar en español.
- **FR-009**: La vista DEBE degradar con elegancia cuando falten datos opcionales (sin empresa,
  sin foto, sin fecha), mostrando valores de reserva en lugar de errores o campos rotos.

### Key Entities *(include if feature involves data)*

- **Usuario**: la persona autenticada cuyo perfil se muestra. Atributos relevantes para la vista:
  nombre, correo, rol, estado de aprobación, foto de perfil, fecha de alta y empresa asociada.
- **Empresa (Tenant)**: organización a la que pertenece el usuario; se muestra su nombre como
  contexto del perfil. Puede no existir para ciertos usuarios (p. ej. Super Admin).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario autenticado puede abrir "Mi perfil" y ver todos sus datos de cuenta
  correctos en una sola pantalla, sin errores, en la primera visita.
- **SC-002**: El 100% de los datos mostrados en la vista corresponden al usuario autenticado
  (cero datos de demostración del template y cero datos de otros usuarios).
- **SC-003**: Un usuario puede cambiar su foto de perfil y ver el resultado reflejado en menos
  de 5 segundos, con confirmación visual clara.
- **SC-004**: Un intento de subida inválida siempre produce un mensaje de error comprensible y
  nunca deja la foto en un estado inconsistente.

## Assumptions

- La ruta `GET /perfil` (`profile.show`) y el endpoint de actualización de avatar
  (`profile.avatar.update`) ya existen en el backend y no requieren cambios; esta feature aporta
  principalmente la vista que falta y su cableado con esos endpoints.
- La cabecera del template NexaDash (`overview-profile.blade.php`) se usa solo como banco de
  piezas visuales; se trasplanta la cabecera y se descarta el resto.
- El perfil es de solo lectura salvo por la foto; la edición de otros campos (nombre, email,
  contraseña) queda fuera del alcance de esta feature.
- El estado "online" del badge del avatar es decorativo (siempre presente para el propio
  usuario), no refleja presencia en tiempo real.
- Los assets globales del template (CSS/JS, toastr) ya están cargados desde el layout base.
