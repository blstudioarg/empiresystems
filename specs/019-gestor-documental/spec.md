# Feature Specification: Gestor documental por tenant (Drive)

**Feature Branch**: `019-gestor-documental`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Gestor documental tipo 'Drive' por tenant: espacio de archivos navegable, carpetas anidadas y archivos ligeros, todos los usuarios del tenant ven y gestionan todo, sin permisos por carpeta ni versionado."

## Clarifications

### Session 2026-07-04

- Q: ¿Qué tipos de archivo se permiten subir? → A: Lista blanca de documentos (PDF, imágenes
  jpg/png/webp/gif, ofimática docx/xlsx/pptx + odt/ods/odp, texto plano/csv); todo lo demás se
  rechaza.
- Q: ¿Valor por defecto del límite de tamaño por archivo? → A: 10 MB por archivo, configurable por
  el tenant.
- Q: ¿Prioridad de la experiencia de usuario del módulo? → A: UX de primer nivel — front atractivo
  y dinámico; el diseño se aborda con las skills de diseño (`frontend-design`, `ui-ux-pro-max`,
  `emil-design-eng`) antes de implementar la vista.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Subir y descargar archivos (Priority: P1)

Un usuario del tenant entra al gestor de archivos, sube uno o varios documentos (PDF, imagen,
ofimática) y más tarde los descarga cuando los necesita. Es el valor mínimo: un lugar seguro y
propio de la empresa donde guardar y recuperar documentos.

**Why this priority**: Sin subir y descargar no hay gestor documental. Todo lo demás (carpetas,
preview, mover) es organización sobre esta capacidad base.

**Independent Test**: Con el módulo desplegado, un usuario sube un archivo a la raíz, lo ve
listado con su nombre/tamaño/fecha, y lo descarga obteniendo el mismo fichero. Entregable por sí
solo aunque no existan carpetas.

**Acceptance Scenarios**:

1. **Given** un usuario aprobado del tenant en el gestor de archivos, **When** sube un documento
   dentro del límite de tamaño, **Then** el archivo queda listado con su nombre, tamaño, tipo y
   fecha de subida, y aparece quién lo subió.
2. **Given** un archivo ya subido, **When** el usuario pulsa descargar, **Then** el sistema le
   devuelve el fichero original con su nombre visible.
3. **Given** un usuario que intenta subir un archivo que supera el límite de tamaño configurado,
   **When** confirma la subida, **Then** el sistema la rechaza con un mensaje claro y no guarda
   nada.

---

### User Story 2 - Organizar en carpetas y navegar (Priority: P2)

El usuario crea carpetas anidadas para organizar los documentos (p. ej. "Contratos", "Proveedores
/ 2026"), navega entrando y saliendo con una ruta de migas, y sube archivos dentro de la carpeta
en la que está.

**Why this priority**: Aporta el modelo mental de "Drive" y hace usable el módulo cuando hay
volumen; pero el sistema ya entrega valor con solo la raíz (US1).

**Independent Test**: Un usuario crea una carpeta, entra en ella, sube un archivo dentro, vuelve
a la raíz por las migas y comprueba que el archivo vive dentro de la carpeta, no en la raíz.

**Acceptance Scenarios**:

1. **Given** el usuario en cualquier nivel, **When** crea una carpeta con un nombre no repetido en
   ese nivel, **Then** la carpeta aparece en el listado de ese nivel.
2. **Given** el usuario intenta crear una carpeta con un nombre que ya existe en el mismo nivel,
   **When** confirma, **Then** el sistema lo rechaza con un mensaje y no crea duplicado.
3. **Given** el usuario dentro de una carpeta anidada, **When** usa la ruta de migas, **Then**
   puede volver a cualquier nivel superior y a la raíz.
4. **Given** el usuario dentro de una carpeta, **When** sube un archivo, **Then** el archivo queda
   asociado a esa carpeta y no a la raíz.

---

### User Story 3 - Renombrar, mover y borrar (Priority: P2)

El usuario mantiene ordenado el espacio: renombra archivos y carpetas, mueve archivos a otra
carpeta, y borra lo que ya no necesita.

**Why this priority**: Imprescindible para que el espacio no se degrade con el tiempo, pero
posterior a poder subir/organizar.

**Independent Test**: Un usuario renombra un archivo (el fichero descargado sigue siendo el
mismo), lo mueve a otra carpeta, y borra una carpeta comprobando qué pasa con su contenido.

**Acceptance Scenarios**:

1. **Given** un archivo existente, **When** el usuario lo renombra, **Then** el nombre visible
   cambia y el contenido descargado no se altera.
2. **Given** un archivo en la carpeta A, **When** el usuario lo mueve a la carpeta B, **Then** el
   archivo deja de aparecer en A y aparece en B.
3. **Given** una carpeta con contenido, **When** el usuario intenta borrarla, **Then** el sistema
   pide confirmación y aplica la política de borrado definida (ver FR-018).
4. **Given** un archivo, **When** el usuario lo borra, **Then** deja de aparecer en el listado.

---

### User Story 4 - Previsualizar sin descargar (Priority: P3)

El usuario abre una vista previa de PDFs e imágenes directamente en el navegador para consultar el
contenido sin descargar el fichero.

**Why this priority**: Mejora fuerte de experiencia, pero prescindible: siempre se puede descargar
(US1).

**Independent Test**: Un usuario abre un PDF y una imagen subidos y ve su contenido en una vista
previa dentro de la app, sin que se guarde el fichero en su equipo.

**Acceptance Scenarios**:

1. **Given** un PDF o imagen subidos, **When** el usuario pulsa previsualizar, **Then** ve el
   contenido embebido en la app.
2. **Given** un archivo cuyo tipo no admite preview (p. ej. un .docx), **When** el usuario lo
   selecciona, **Then** la opción ofrecida es descargar, no previsualizar.

---

### Edge Cases

- **Aislamiento entre tenants**: un usuario de un tenant no puede ver, descargar ni previsualizar
  un archivo o carpeta de otro tenant, ni siquiera pidiendo su identificador directamente.
- **Nombre duplicado de archivo en la misma carpeta**: se permite (se distinguen por fecha/quién
  lo subió) — solo los nombres de **carpeta** son únicos por nivel.
- **Mover una carpeta dentro de sí misma o de un descendiente**: el sistema DEBE impedirlo (rechazo
  con mensaje claro, sin mover nada). Mover carpetas por drag&drop (o cambiando `parent_id`) sí está
  soportado (ver Assumptions); la restricción es específicamente evitar el ciclo en el árbol.
- **Subida interrumpida / archivo corrupto**: si la subida no se completa, no debe quedar un
  registro huérfano ni un fichero a medias visible en el listado.
- **Extensión/tipo no permitido**: si se define una lista de tipos permitidos, un tipo fuera de
  ella se rechaza con mensaje claro.
- **Borrar una carpeta con subcarpetas y archivos**: se borra en cascada tras una confirmación
  reforzada que informa del número de elementos afectados (FR-018).
- **Caracteres especiales en el nombre visible**: el nombre visible admite caracteres normales de
  documento sin que afecte al almacenamiento físico del fichero.
- **Disco lleno del hosting**: si no hay espacio, la subida falla con mensaje claro sin corromper
  el listado.

## Requirements *(mandatory)*

### Functional Requirements

**Espacio y aislamiento**

- **FR-001**: El sistema DEBE ofrecer a cada tenant un espacio de archivos propio, aislado del de
  cualquier otro tenant, accesible solo por los usuarios de ese tenant.
- **FR-002**: El sistema DEBE impedir que un usuario acceda (listar, descargar, previsualizar,
  renombrar, mover, borrar) a archivos o carpetas que no pertenezcan a su tenant, incluso ante una
  petición directa por identificador.
- **FR-003**: Todos los usuarios **aprobados y activos** del tenant DEBEN poder ver y gestionar
  todo el espacio de archivos del tenant (sin permisos por carpeta ni por rol en el MVP).

**Carpetas**

- **FR-004**: Los usuarios DEBEN poder crear carpetas, incluyendo carpetas anidadas a cualquier
  profundidad.
- **FR-005**: El nombre de una carpeta DEBE ser único dentro de su carpeta contenedora (mismo
  nivel); un nombre repetido en el mismo nivel se rechaza con mensaje claro.
- **FR-006**: Los usuarios DEBEN poder renombrar una carpeta respetando la unicidad de nombre en
  su nivel.
- **FR-007**: Los usuarios DEBEN poder navegar por el árbol de carpetas mediante una ruta de migas
  (breadcrumbs) que permita volver a cualquier nivel superior y a la raíz.

**Archivos**

- **FR-008**: Los usuarios DEBEN poder subir uno o varios archivos, a la raíz o dentro de la
  carpeta en la que se encuentran.
- **FR-009**: El sistema DEBE registrar por cada archivo: nombre visible, nombre original del
  fichero subido, tipo de contenido, tamaño, fecha de subida y qué usuario lo subió.
- **FR-010**: El sistema DEBE rechazar subidas que superen el límite de tamaño por archivo, con un
  mensaje claro, sin dejar rastro del intento fallido.
- **FR-011**: El límite de tamaño por archivo DEBE ser configurable por el tenant sin tocar código,
  con un valor por defecto razonable (ver Assumptions).
- **FR-012**: Los usuarios DEBEN poder descargar cualquier archivo, obteniendo el fichero original
  con su nombre visible.
- **FR-013**: Los usuarios DEBEN poder renombrar un archivo (nombre visible) sin que el contenido
  descargable cambie.
- **FR-014**: Los usuarios DEBEN poder mover un archivo de una carpeta a otra (o a la raíz).
- **FR-015**: Los usuarios DEBEN poder borrar archivos.
- **FR-016**: El sistema DEBE permitir previsualizar dentro de la app los archivos cuyo tipo lo
  admita (al menos PDF e imágenes); para el resto ofrece descarga.
- **FR-016b**: El sistema DEBE aceptar solo una **lista blanca de tipos de documento** (PDF;
  imágenes jpg/png/webp/gif; ofimática docx/xlsx/pptx y odt/ods/odp; texto plano/csv) y rechazar
  cualquier otro tipo con mensaje claro, sin dejar registros ni ficheros huérfanos.

**Listado y borrado**

- **FR-017**: El sistema DEBE mostrar el contenido del nivel actual (carpetas y archivos) con al
  menos dos presentaciones: vista de rejilla y vista de lista.
- **FR-017b**: La experiencia de usuario del módulo es un requisito de primer nivel: la interfaz
  DEBE ser atractiva, dinámica y fluida (feedback inmediato en subir/mover/renombrar/borrar,
  estados de carga y vacío cuidados, interacción ágil). El diseño de la vista DEBE abordarse con
  las skills de diseño del proyecto (`frontend-design`, `ui-ux-pro-max`, `emil-design-eng`) antes
  de implementarla, según `CLAUDE.md` y `docs/04-front-guidelines.md`.
- **FR-018**: Al borrar una carpeta con contenido, el sistema DEBE borrar en cascada la carpeta y
  todo su contenido (subcarpetas y archivos), previa **confirmación reforzada** que informe al
  usuario de cuántos elementos se van a borrar antes de proceder. Dado que no hay papelera de
  restauración en el MVP, el aviso explícito es obligatorio.
- **FR-019**: El almacenamiento físico de los ficheros DEBE quedar separado por tenant, y los
  ficheros NUNCA deben ser accesibles por una URL pública directa: todo acceso pasa por la app,
  que reafirma la pertenencia al tenant.
- **FR-020**: El nombre con el que se almacena físicamente el fichero DEBE ser independiente del
  nombre visible, de modo que renombrar no mueva ni reescriba el fichero y no haya colisiones por
  nombres repetidos.

### Key Entities *(include if feature involves data)*

- **Carpeta**: nodo del árbol de organización del tenant. Pertenece a un tenant, puede tener una
  carpeta contenedora (o ninguna = raíz) y contener otras carpetas y archivos. Atributos: nombre
  (único por nivel), momento de creación. Se puede dar de baja.
- **Archivo**: documento almacenado del tenant. Pertenece a un tenant y opcionalmente a una carpeta
  (o a la raíz). Atributos: nombre visible, nombre original, tipo de contenido, tamaño, referencia
  al fichero físico, usuario que lo subió, momento de subida. Se puede dar de baja.
- **Límite de tamaño por archivo**: parámetro de configuración del tenant que acota el tamaño
  máximo admitido por subida.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario nuevo consigue subir su primer documento y volver a descargarlo sin ayuda
  en menos de 1 minuto.
- **SC-002**: El 100% de los intentos de acceder a un archivo o carpeta de otro tenant (por
  identificador directo) son rechazados; ninguna fuga entre tenants en las pruebas de aislamiento.
- **SC-003**: El 100% de las subidas que superan el límite configurado se rechazan con mensaje
  claro y sin dejar registros ni ficheros huérfanos.
- **SC-004**: Un usuario puede localizar un documento navegando por carpetas y migas en 3 clics o
  menos desde la raíz en una estructura de hasta 3 niveles.
- **SC-005**: Renombrar un archivo nunca altera su contenido descargable (verificable comparando el
  fichero antes y después del renombrado).
- **SC-006**: Cada acción de gestión (crear/renombrar/mover/borrar carpeta o archivo) refleja su
  resultado en la vista con feedback inmediato (confirmación o error visible al usuario sin
  recargas confusas), y el listado del nivel actual se percibe como instantáneo al navegar.

## Assumptions

- **Alcance de organización**: se mueven tanto **archivos** como **carpetas** completas (con sus
  subcarpetas y archivos) entre carpetas, arrastrando y soltando en el explorador (o sobre las
  migas de pan). Mover una carpeta valida que el destino no sea ella misma ni una de sus propias
  subcarpetas (evita ciclos en el árbol).
- **Sin versionado ni papelera con restauración fina**: borrar es baja lógica a efectos de no
  romper referencias, pero el usuario no dispone de una papelera navegable para restaurar en el
  MVP.
- **Sin compartir por enlace público, sin permisos por carpeta, sin cuota total por tenant**: el
  control de espacio se vigila manualmente; solo hay límite por archivo. Todo esto son mejoras
  futuras fuera del MVP.
- **Sin vínculo con entidades de negocio**: los archivos no se asocian a facturas, clientes,
  proveedores, etc. en esta feature (posible "modo híbrido" futuro).
- **Tipos de archivo**: lista blanca de documentos (definida en FR-016b): PDF, imágenes
  (jpg/png/webp/gif), ofimática (docx/xlsx/pptx, odt/ods/odp) y texto plano/csv. Cualquier otro
  tipo (ejecutables, scripts, etc.) se rechaza.
- **Valor por defecto del límite de tamaño**: 10 MB por archivo como default configurable (dentro
  del rango 10–20 MB propuesto), coherente con documentos ligeros en hosting compartido.
- **Actores**: solo usuarios del tenant (rol `admin` o `usuario`) aprobados y activos; el
  `super_admin` no opera este módulo (vive fuera del scope de tenant).
- **Multi-tenant y convenciones**: el módulo sigue el aislamiento por `tenant_id` con global scope
  y las convenciones de datos del proyecto (Principio I de la constitución y `docs/03-modelo-datos.md`).
- **Infraestructura**: almacenamiento en disco local del hosting compartido, particionado por
  tenant (sin almacenamiento de objetos externo en esta fase), coherente con `docs/01-arquitectura.md`.
