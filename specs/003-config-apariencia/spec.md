# Feature Specification: Configuración del tenant — Apariencia / Marca

**Feature Branch**: `003-config-apariencia`

**Created**: 2026-07-02

**Status**: Draft

**Input**: User description: "Pantalla de Configuración del tenant (apariencia / branding), accesible desde el dropdown del usuario en la topbar, organizada en tabs, primera tab 'Apariencia / Marca' con logo del menú (con preview), color primario, color secundario y color de fondo de la topbar; valores por tenant aplicados a la interfaz."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Personalizar los colores de marca del tenant (Priority: P1)

Un usuario del tenant entra a la pantalla de Configuración desde el menú desplegable de su foto de
perfil (topbar), abre la tab "Apariencia / Marca", elige el color primario, el color secundario y
el color de fondo de la barra superior con sendos selectores de color, y guarda. Al recargar el CRM,
toda la interfaz de ese tenant refleja los colores elegidos.

**Why this priority**: Es el núcleo de valor de la feature (personalización de marca) y el que da
sentido a la pantalla de configuración; se puede entregar y demostrar sin la subida de logo.

**Independent Test**: Iniciar sesión como usuario de un tenant, cambiar los tres colores, guardar,
recargar y comprobar que la interfaz usa los nuevos colores; comprobar que otro tenant no ve esos
colores.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado de un tenant, **When** abre el dropdown de su perfil en la
   topbar y pulsa "Configuración", **Then** ve la pantalla de Configuración con tabs y la tab
   "Apariencia / Marca" activa por defecto.
2. **Given** la tab "Apariencia / Marca", **When** el usuario cambia el color primario, secundario
   y de fondo de la topbar y guarda, **Then** el sistema persiste los tres valores para su tenant y
   muestra confirmación de guardado.
3. **Given** colores ya guardados para un tenant, **When** cualquier usuario de ese tenant carga
   cualquier pantalla del CRM, **Then** la interfaz aplica el color primario, secundario y de fondo
   de topbar guardados.
4. **Given** dos tenants con configuraciones de color distintas, **When** un usuario de cada tenant
   navega el CRM, **Then** cada uno ve únicamente los colores de su propio tenant.

---

### User Story 2 - Subir el logo del tenant con vista previa (Priority: P2)

El usuario, en la misma tab "Apariencia / Marca", selecciona un archivo de imagen como logo. Antes
de guardar ve una vista previa de la imagen elegida. Al guardar, el logo queda asociado a su tenant
y se muestra en la cabecera/menú del CRM en lugar del logo por defecto.

**Why this priority**: Completa la identidad visual del tenant, pero la personalización de colores
(P1) ya aporta valor por sí sola; el logo puede llegar después.

**Independent Test**: Seleccionar una imagen válida, verificar que aparece la vista previa, guardar,
recargar y comprobar que el logo del menú es el subido; verificar que otro tenant conserva el suyo/
el por defecto.

**Acceptance Scenarios**:

1. **Given** la tab "Apariencia / Marca", **When** el usuario selecciona un archivo de imagen en el
   campo de logo, **Then** se muestra una vista previa de la imagen antes de guardar.
2. **Given** una imagen válida seleccionada, **When** el usuario guarda, **Then** el logo se almacena
   asociado a su tenant y pasa a mostrarse en la cabecera/menú del CRM.
3. **Given** un archivo que no es una imagen admitida o supera el tamaño permitido, **When** el
   usuario intenta guardar, **Then** el sistema rechaza el archivo con un mensaje de error claro y no
   modifica el logo actual.
4. **Given** un tenant con logo propio y otro sin logo, **When** usuarios de cada uno cargan el CRM,
   **Then** el primero ve su logo y el segundo el logo por defecto, sin mezclarse.

---

### User Story 3 - Estructura de configuración preparada para crecer (Priority: P3)

La pantalla de Configuración presenta varias tabs claramente diferenciadas. En esta entrega solo la
tab "Apariencia / Marca" es funcional; las demás quedan como marcadores de secciones futuras
(facturación, verifactu, email, etc.) sin funcionalidad activa, de modo que añadir nuevas secciones
después no requiera rediseñar la pantalla.

**Why this priority**: Es andamiaje de UX; no aporta valor funcional inmediato pero evita retrabajo.
Puede recortarse sin afectar P1/P2.

**Independent Test**: Abrir la pantalla de Configuración y comprobar que existe una navegación por
tabs con al menos la tab funcional "Apariencia / Marca" y los marcadores de futuras secciones
visibles pero inertes.

**Acceptance Scenarios**:

1. **Given** la pantalla de Configuración, **When** el usuario la abre, **Then** ve una barra de tabs
   con "Apariencia / Marca" y otras secciones previstas señalizadas como próximas.
2. **Given** las tabs de secciones futuras, **When** el usuario las selecciona, **Then** no producen
   error y comunican que aún no están disponibles.

---

### Edge Cases

- **Usuario no autenticado** navega a la URL de configuración: es redirigido al login.
- **Usuario de otro tenant** intenta acceder/editar la configuración: solo puede afectar a la de su
  propio tenant; el aislamiento (FR-009) lo impide.
- **Guardar sin cambiar nada**: se acepta sin error (idempotente) o se informa que no hubo cambios.
- **Color inválido** (valor que no es un color válido): se rechaza con error de validación y no se
  persiste.
- **Logo con formato correcto pero corrupto / dimensiones extremas**: se valida tipo y tamaño; un
  archivo que no supere las validaciones no reemplaza el logo existente.
- **Restablecer a valores por defecto**: el usuario debe poder volver al aspecto por defecto (colores
  del template y sin logo propio) — ver FR-010.
- **Reemplazo de logo**: subir un logo nuevo debe dejar el tenant con un único logo vigente (el
  anterior deja de mostrarse).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST ofrecer una pantalla de "Configuración" accesible únicamente para
  usuarios autenticados, enlazada desde el menú desplegable del perfil de usuario en la barra
  superior (no desde el menú lateral).
- **FR-002**: La pantalla de Configuración MUST organizarse en tabs diferenciadas, con la tab
  "Apariencia / Marca" como sección funcional de esta entrega y el resto como marcadores de secciones
  futuras sin funcionalidad activa.
- **FR-003**: En la tab "Apariencia / Marca" el usuario MUST poder definir un color primario, un
  color secundario y un color de fondo de la barra superior mediante selectores de color.
- **FR-004**: En la tab "Apariencia / Marca" el usuario MUST poder seleccionar un archivo de imagen
  como logo del tenant y ver una vista previa de la imagen elegida antes de guardar.
- **FR-005**: El sistema MUST validar el logo antes de aceptarlo, restringiendo el tipo de archivo a
  formatos de imagen admitidos y limitando el tamaño máximo; un archivo que no cumpla se rechaza con
  mensaje claro y no altera el logo vigente.
- **FR-006**: El sistema MUST validar que los colores recibidos son valores de color válidos antes de
  persistirlos; un valor inválido se rechaza sin persistir.
- **FR-007**: El sistema MUST persistir los valores de apariencia (colores y logo) asociados al tenant
  del usuario que los guarda, de forma que cada tenant tenga su propia configuración independiente.
- **FR-008**: El sistema MUST aplicar los colores y el logo guardados a la interfaz del CRM para todos
  los usuarios del mismo tenant; si un tenant no ha configurado un valor, se usa el valor por defecto
  del template.
- **FR-009**: El sistema MUST garantizar aislamiento entre tenants: ningún usuario puede ver ni
  modificar la configuración de apariencia de otro tenant.
- **FR-010**: El usuario MUST poder restablecer la apariencia a los valores por defecto (colores del
  template y logo por defecto).
- **FR-011**: Cualquier usuario autenticado del tenant MUST poder ver y modificar la configuración de
  apariencia de su propio tenant (sin distinción de rol); el aislamiento entre tenants (FR-009) sigue
  aplicando en todos los casos.
- **FR-012**: Tras un guardado correcto, el sistema MUST confirmar visualmente el resultado al usuario.

### Key Entities *(include if feature involves data)*

- **Configuración de apariencia del tenant**: conjunto de valores de marca pertenecientes a un tenant
  — color primario, color secundario, color de fondo de la topbar y referencia al logo. Cada tenant
  tiene como máximo un conjunto vigente; en ausencia de valores se usan los por defecto del template.
  Se apoya en el almacén de configuración clave-valor por tenant ya previsto en el modelo de datos
  (`configuraciones`, con índice único por tenant+clave) y en la referencia al fichero de logo del
  tenant (`tenants.logo_path`), respetando lo documentado en `docs/03-modelo-datos.md`.
- **Archivo de logo**: imagen subida por el tenant, almacenada de forma segura y referenciada por la
  configuración del tenant; sustituible y restablecible al valor por defecto.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede cambiar los tres colores de marca y ver el cambio reflejado en la
  interfaz tras guardar y recargar, en menos de 1 minuto y sin ayuda técnica.
- **SC-002**: Un usuario puede subir un logo, ver su vista previa antes de guardar y comprobar que
  aparece en el menú del CRM tras guardar.
- **SC-003**: En un entorno con al menos 2 tenants, el 100% de las veces cada tenant ve solo su propia
  configuración de apariencia; nunca la de otro tenant.
- **SC-004**: El 100% de los intentos de subir un archivo de logo con formato no admitido o tamaño
  excesivo son rechazados con un mensaje comprensible, sin corromper ni cambiar el logo vigente.
- **SC-005**: Un usuario puede restablecer la apariencia por defecto y confirmar visualmente que la
  interfaz vuelve al aspecto original.

## Assumptions

- La configuración de apariencia aplica a nivel de **tenant** (afecta a todos sus usuarios), no es una
  preferencia por usuario individual.
- Los valores de color por defecto son los que ya trae el template actual del CRM; "restablecer" vuelve
  a esos valores.
- El almacenamiento se apoya en las estructuras ya documentadas en `docs/03-modelo-datos.md` (tabla
  `configuraciones` para los ajustes y `tenants.logo_path` para el logo); cualquier ajuste al modelo se
  reflejará también en esa documentación al cerrar la feature (según CLAUDE.md).
- Los componentes de UI (selector de color y campo de archivo con vista previa) se trasplantan del
  banco de piezas del template ya presente en el repositorio.
- El alcance de esta entrega se limita a apariencia/marca; las demás secciones de configuración
  (facturación, verifactu, email, etc.) quedan fuera de alcance salvo como marcadores de tabs.
- No se cubre versionado ni historial de cambios de configuración; solo el valor vigente por tenant.
