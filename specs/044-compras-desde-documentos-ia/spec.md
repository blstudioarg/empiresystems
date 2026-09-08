# Feature Specification: Compras desde documentos (PDF/imagen) interpretados por IA

**Feature Branch**: `044-compras-desde-documentos-ia`

**Created**: 2026-09-06

**Status**: Draft

**Input**: User description: "Generar compras a partir de importarle documentos en PDF o en imagen; darle las imágenes al modelo, que interprete y devuelva una proposición de compra con los datos interpretados; el usuario debe poder dar OK o modificar los valores y luego crearla."

## Documentación consultada (regla de oro del proyecto)

- **`docs/04-front-guidelines.md`, sección "Botón 'Exportar'/'Importar' en la cabecera de un
  DataTable (feature 031)"**: exige que **"Importar" abra un modal, nunca navegue a otra página**,
  con el flujo de tres pasos (subir → previsualizar → confirmar) resuelto en **un único modal** con
  los pasos alternados por `d-none`, **sin volver a pedir el fichero** (un token de previsualización
  viaja al confirmar), y recarga de la tabla con `table.ajax.reload(null, false)` al terminar. El
  botón va **antes** del botón primario "+ Nueva compra" por ser acción secundaria. Esta feature
  adopta ese patrón íntegro (decisión D1).
- **`docs/04-front-guidelines.md`, sección "Listados: SIEMPRE DataTable, nunca una `<table>` plana"**:
  cualquier listado de la propuesta (líneas detectadas, cola de documentos) que se muestre como
  listado independiente en una pantalla propia debe ser DataTable. Se documenta explícitamente en
  Assumptions por qué la tabla de líneas **dentro** del modal de propuesta es la excepción ya
  admitida por la guía (tabla de edición de un documento, mismo caso que las líneas de factura en
  la vista full-page, no un listado navegable).
- **`docs/04-front-guidelines.md`, sección "Estado de carga en botones (AJAX/fetch)"**: todo botón
  que dispare una petición que puede tardar DEBE mostrar deshabilitado + spinner vía
  `window.withButtonLoading` (con `data-loading-text`). Crítico aquí: la interpretación por IA es la
  petición más lenta de toda la app.
- **`docs/04-front-guidelines.md`, sección "CSRF en peticiones AJAX sin formulario"**: las acciones
  de botón sin form serializado deben mandar `X-CSRF-TOKEN` explícito; el precedente de olvido está
  documentado justamente en `compras-show.init.js`.
- **`docs/04-front-guidelines.md`, sección "Notificaciones"** y regla de `CLAUDE.md`: siempre toastr
  vía `window.showToast(...)`, nunca alerts Bootstrap ad-hoc.
- **`docs/04-front-guidelines.md`, sección "Nunca imprimir directo un campo `decimal:N` de Eloquent
  en Blade"**: aplica a los importes de la propuesta y de la compra creada.
- **`docs/04-front-guidelines.md`, sección "Assets propios: siempre `@assetv`, nunca `asset()` a
  secas" (feature 043)**: el hosting sirve los estáticos con caché de una semana y el despliegue es
  por FTP; un JS propio cargado con `asset()` no llega al usuario tras desplegarlo. El JS nuevo de
  esta feature se carga versionado.
- **`docs/04-front-guidelines.md`, sección "Alta inline en un listado: confirmación explícita, nunca
  por `blur`" (feature 041)**: perder el foco no es una decisión, y usarlo como confirmación crea
  registros que nadie pidió. Condiciona directamente FR-019 (alta del proveedor detectado): check
  para confirmar + X para descartar, Enter/Escape, y conservar lo escrito si el alta falla.
- **`docs/04-front-guidelines.md`, sección "Extracción de UI compartida entre dos pantallas ya
  existentes" (feature 043)**: exige extraer a partial+módulo compartido cuando hay **dos**
  consumidores. Aquí hay uno, así que el modal se mantiene específico de compras (YAGNI).
- **`docs/04-front-guidelines.md`, sección "Ayuda contextual (mini-tutoriales por vista/proceso)"**:
  `compras/index.blade.php` ya declara `@section('ayuda')` con `ayuda.compras`; este flujo nuevo
  cambia lo que el usuario hace en esa pantalla, así que la guía in-app entra en el mismo cambio.
- **`docs/04-front-guidelines.md`, sección "Nueva entrada de menú ⇒ nuevo permiso (obligatorio)"**:
  esta feature **no** añade entrada de menú (vive dentro de Compras), por lo que **no** da de alta
  un permiso nuevo; reutiliza `ver-compras`, que es el permiso de la pantalla donde se ejecuta.
- **`docs/04-front-guidelines.md`, sección "Confirmación de acciones irreversibles"**: descartar una
  propuesta no persiste nada, por lo que no requiere modal de confirmación destructiva.
- **`.specify/memory/constitution.md`**: Principio I (aislamiento multi-tenant con `tenant_id` y
  tests de fuga entre tenants), Principio II (impuesto agnóstico al régimen: no asumir IVA; RGPD/
  LOPDGDD sobre el documento subido, que contiene datos personales del proveedor → plazo de
  retención y purga siguiendo el patrón `RetencionLogsTenant` + comando programado), Principio III
  (los importes SIEMPRE los calcula el backend a partir de las líneas; lo que propone el modelo es
  sugerencia de UI, nunca fuente de verdad), Principio IV (test-first en aislamiento y cálculo),
  Principio V (simplicidad: sin colas ni workers, llamada síncrona dentro del request como ya hace
  `AsistenteIa`; debe correr en hosting compartido cPanel).
- **`docs/03-modelo-datos.md`, sección de compras** y **`docs/02-facturacion-espana.md`**: las
  compras registran el impuesto soportado por línea con su tipo impositivo; el régimen impositivo
  del tenant condiciona los tipos válidos (IVA / IGIC / IPSI), por lo que la propuesta no puede
  asumir 21 % por defecto.
- **`docs/00-vision.md` / `docs/01-arquitectura.md`**: el asistente IA (feature 030) ya introdujo el
  proveedor de IA por tenant con clave cifrada; esta feature no introduce un proveedor nuevo.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear una compra a partir de un documento (Priority: P1)

Una persona de administración recibe por email la factura en PDF de un proveedor. En vez de teclear
la cabecera y las líneas a mano, entra en **Compras**, pulsa **Importar documento**, sube el PDF y
espera unos segundos. El sistema le muestra una **propuesta de compra** con proveedor, número de
documento, fecha, líneas (concepto, cantidad, precio unitario, tipo impositivo) e importes ya
rellenos. Revisa, corrige lo que haga falta y pulsa **Crear compra**. La compra queda creada en
estado **borrador**, igual que si la hubiera cargado a mano, lista para confirmarse y mover stock.

**Why this priority**: Es el núcleo de la feature y el único recorrido que, por sí solo, ya entrega
el valor pedido (ahorrar el tecleo manual de una compra). Sin él no hay feature.

**Independent Test**: Subir un documento de compra de una página y verificar que se llega a una
compra en borrador cuyos datos coinciden con el documento, sin haber tecleado ningún campo.

**Acceptance Scenarios**:

1. **Given** un tenant con la IA configurada y un PDF de factura de proveedor legible, **When** el
   usuario lo sube desde el modal de importación, **Then** el sistema muestra una propuesta editable
   con proveedor, número de documento, fecha, líneas e importes extraídos del documento, **y no ha
   creado todavía ninguna compra**.
2. **Given** una propuesta en pantalla, **When** el usuario pulsa **Crear compra** sin modificar
   nada, **Then** se crea una compra en estado **borrador** con esos datos, el listado se recarga
   mostrándola y aparece un toast de éxito.
3. **Given** una propuesta en pantalla, **When** el usuario modifica la fecha, el concepto de una
   línea y una cantidad, y pulsa **Crear compra**, **Then** la compra creada refleja los valores
   **editados**, no los propuestos por el modelo.
4. **Given** una propuesta con líneas cuyos importes propuestos por el modelo no cuadran (el total
   del documento no coincide con la suma de las líneas), **When** se crea la compra, **Then** los
   importes persistidos son los **recalculados por el sistema** a partir de cantidad × precio y tipo
   impositivo de cada línea, y el usuario ve esos importes recalculados antes de confirmar.
5. **Given** el usuario ha llegado a la propuesta, **When** cierra el modal o pulsa **Descartar**,
   **Then** no se crea ninguna compra ni ningún proveedor, y el listado queda como estaba.
6. **Given** una compra creada por esta vía, **When** el usuario abre su detalle, **Then** ve
   indicado que su origen es la importación de un documento y puede **descargar el documento
   original** que la generó.

---

### User Story 2 - Resolver el proveedor sin ensuciar el catálogo (Priority: P1)

El documento identifica a un proveedor. El sistema intenta reconocerlo entre los proveedores ya
dados de alta en el tenant y lo propone preseleccionado. Si no lo reconoce, no lo da de alta por su
cuenta: ofrece crear un proveedor nuevo con los datos leídos del documento (nombre, NIF/CIF,
dirección, etc.), y el usuario decide si lo crea, si elige otro del catálogo, o si corrige los datos
antes de crearlo.

**Why this priority**: Sin proveedor no hay compra: es un dato obligatorio del registro. Y es el
punto donde una lectura errónea del modelo haría más daño permanente (proveedores duplicados o
basura en el catálogo), por eso la creación nunca es automática (decisión D2).

**Independent Test**: Subir dos documentos, uno de un proveedor existente y otro de uno inexistente,
y verificar que el primero se preselecciona y el segundo exige una decisión explícita del usuario.

**Acceptance Scenarios**:

1. **Given** un proveedor ya existente en el tenant con el mismo NIF/CIF que el del documento,
   **When** se genera la propuesta, **Then** ese proveedor aparece **preseleccionado** y se indica
   que se reconoció por su identificación fiscal.
2. **Given** que ningún proveedor coincide por NIF/CIF pero uno coincide por nombre de forma
   aproximada, **When** se genera la propuesta, **Then** el sistema lo propone como **sugerencia a
   confirmar** (no como hecho), dejando claro que el emparejamiento es por nombre y no por
   identificación fiscal.
3. **Given** que no hay ninguna coincidencia, **When** se genera la propuesta, **Then** el sistema
   ofrece **crear un proveedor nuevo** con los datos leídos, mostrándolos editables, y **no permite
   crear la compra** hasta que el usuario elija un proveedor existente o confirme la creación del
   nuevo.
4. **Given** que el usuario confirma la creación del proveedor nuevo, **When** se crea la compra,
   **Then** el proveedor queda dado de alta en el catálogo del tenant con los datos confirmados y la
   compra queda asociada a él, **en una sola operación atómica** (o se crean ambos, o no se crea
   ninguno).
5. **Given** que el usuario descarta la propuesta tras haber visto la oferta de crear el proveedor,
   **When** revisa el catálogo de proveedores, **Then** no se ha dado de alta ningún proveedor.

---

### User Story 3 - Emparejar las líneas con el catálogo para que la compra mueva stock (Priority: P2)

Muchas líneas del documento corresponden a artículos que el tenant ya tiene en su catálogo. Para que
al confirmar la compra entre stock, cada línea necesita estar asociada a un artículo. El sistema
propone, línea a línea, el artículo que cree que corresponde (por referencia/SKU o por nombre), y el
usuario lo acepta, lo cambia por otro, o lo deja como **línea libre** sin artículo.

**Why this priority**: Es lo que conecta esta feature con el control de stock, que es la razón de ser
de las compras en el producto. No es P1 porque una compra sin artículos asociados ya es una compra
válida (solo aporta a los importes), igual que hoy ocurre con las compras importadas de Facturae.

**Independent Test**: Subir un documento cuyas líneas incluyan una referencia que exista en el
catálogo y otra que no, y verificar que la primera llega emparejada y la segunda como línea libre.

**Acceptance Scenarios**:

1. **Given** una línea del documento cuya referencia coincide exactamente con la de un artículo del
   catálogo, **When** se genera la propuesta, **Then** esa línea aparece con el artículo asignado y
   se indica que el emparejamiento fue por referencia.
2. **Given** una línea cuyo texto se parece al nombre de un artículo del catálogo sin coincidencia
   de referencia, **When** se genera la propuesta, **Then** el artículo aparece como **sugerencia**
   claramente distinguible de un emparejamiento por referencia.
3. **Given** una línea sin ninguna coincidencia razonable, **When** se genera la propuesta, **Then**
   queda como **línea libre** (sin artículo), lo cual es un resultado válido y no bloquea la
   creación.
4. **Given** cualquier línea de la propuesta, **When** el usuario cambia el artículo asignado o lo
   quita, **Then** la compra creada respeta esa decisión del usuario.
5. **Given** una compra creada con líneas asociadas a artículos de tipo producto con gestión de
   stock, **When** el usuario la **confirma** desde su detalle, **Then** el stock de esos artículos
   aumenta exactamente igual que en una compra cargada a mano (sin lógica de stock nueva ni
   distinta).
6. **Given** una compra creada por esta vía, **When** aún está en borrador, **Then** se puede editar
   y eliminar con las mismas reglas que cualquier otra compra en borrador.

---

### User Story 4 - Procesar varios documentos de una tacada (Priority: P2)

El usuario tiene el correo con seis facturas de proveedor del mes. Las sube todas juntas y el sistema
las interpreta una por una, presentándole las propuestas **en cola**: revisa y crea la primera, pasa
a la segunda, y así hasta terminar. Puede saltarse alguna. Si un documento resulta ilegible, se lo
dice y sigue con el resto.

**Why this priority**: Multiplica el ahorro de tiempo del caso P1, que es el valor central de la
feature, pero no es imprescindible para entregarlo.

**Independent Test**: Subir tres documentos a la vez (uno de ellos ilegible) y verificar que se
obtienen dos compras creadas y un aviso concreto sobre el tercero.

**Acceptance Scenarios**:

1. **Given** que el usuario sube varios documentos a la vez, **When** termina la interpretación,
   **Then** el sistema indica cuántos documentos se interpretaron correctamente y cuántos no, y
   presenta la primera propuesta con un indicador de posición en la cola (p. ej. "1 de 3").
2. **Given** una propuesta de la cola, **When** el usuario la crea o la descarta, **Then** el sistema
   avanza automáticamente a la siguiente propuesta pendiente sin cerrar el modal.
3. **Given** que uno de los documentos del lote no pudo interpretarse, **When** termina el proceso,
   **Then** el sistema **identifica ese documento por su nombre de archivo** y explica el motivo, y
   los demás documentos se procesan igualmente.
4. **Given** que el usuario ha creado ya algunas compras de la cola, **When** cierra el modal a mitad
   de la cola, **Then** las compras ya creadas se conservan y las propuestas pendientes se descartan
   sin efectos secundarios.
5. **Given** que se ha terminado la cola, **When** se cierra el modal, **Then** el listado de compras
   se recarga mostrando todas las compras creadas y las métricas de la cabecera se actualizan.

---

### User Story 5 - Evitar duplicar una compra ya registrada (Priority: P3)

La misma factura puede subirse dos veces (reenvío del proveedor, error del usuario). Antes de crear,
el sistema avisa si ya existe una compra con el mismo proveedor, número de documento y fecha.

**Why this priority**: Protege la integridad de los datos y del stock (una compra duplicada
confirmada infla el inventario), pero es un caso de borde frente al recorrido principal. El sistema
ya tiene este criterio de duplicado implementado para la importación de Facturae.

**Independent Test**: Subir dos veces el mismo documento y verificar que la segunda vez el sistema
avisa antes de crear.

**Acceptance Scenarios**:

1. **Given** que ya existe una compra con el mismo proveedor, número de documento y fecha, **When**
   se genera la propuesta del documento repetido, **Then** el sistema muestra un **aviso de posible
   duplicado** identificando la compra existente, con enlace a ella.
2. **Given** ese aviso, **When** el usuario decide crearla igualmente, **Then** la compra se crea
   (el aviso no bloquea: puede ser un documento legítimamente repetido de numeración).
3. **Given** ese aviso, **When** el usuario descarta la propuesta, **Then** no se crea nada y, si hay
   cola, se pasa al siguiente documento.

---

### Edge Cases

- **El tenant no tiene configurada la clave de IA**: el botón de importar documento debe explicar
  que la funcionalidad requiere configurar el asistente IA y dónde hacerlo, sin lanzar ningún error
  técnico ni dejar el modal colgado.
- **El servicio de IA falla, agota su cuota o tarda demasiado**: mensaje diferenciado y accionable
  (clave inválida / límite de uso alcanzado / servicio no disponible), coherente con los mensajes que
  ya usa el asistente IA; el usuario nunca se queda sin saber qué pasó ni con un botón bloqueado.
- **El documento no es una compra** (un contrato, una foto de un ticket de párking, una página en
  blanco): el sistema lo informa como "no se pudo interpretar como documento de compra" en vez de
  proponer una compra vacía o inventada.
- **El documento es legible solo en parte**: los campos que no se pudieron leer llegan vacíos y
  señalados como tales; el usuario los completa. Un campo obligatorio vacío bloquea la creación con
  el mismo mensaje de validación que el alta manual.
- **Archivo demasiado grande, con demasiadas páginas, o de un tipo no admitido**: se rechaza antes de
  gastar una llamada al modelo, indicando el límite concreto.
- **Documento con varias facturas dentro del mismo PDF**: fuera de alcance; se interpreta como un
  único documento de compra. Si el resultado no es correcto, el usuario lo corrige o lo descarta.
- **Tipos impositivos ajenos al régimen del tenant** (p. ej. un 21 % leído en un tenant canario bajo
  IGIC): el valor leído se muestra pero se señala como incoherente con el régimen del tenant, para
  que el usuario lo corrija antes de crear.
- **Importes con signo negativo o descuentos en el documento**: se admiten en la propuesta como
  valores editables; las reglas de validación de importes de la compra son las mismas del alta
  manual, sin excepciones nuevas.
- **Dos usuarios del mismo tenant subiendo el mismo documento a la vez**: cada uno ve su propia
  propuesta; el aviso de duplicado se evalúa al crear, no solo al proponer.
- **La sesión caduca entre proponer y crear**: la creación falla con un mensaje claro y el documento
  subido no queda huérfano ocupando espacio indefinidamente.
- **Aislamiento**: una propuesta generada en el tenant A no puede consultarse ni confirmarse desde el
  tenant B bajo ninguna circunstancia, ni siquiera conociendo su identificador.

## Requirements *(mandatory)*

### Functional Requirements

**Entrada y subida**

- **FR-001**: El listado de compras MUST ofrecer una acción **"Importar documento"** en la cabecera
  de la card, junto a "Importar Facturae" y **antes** del botón primario "+ Nueva compra", disponible
  solo para quien tiene permiso sobre la pantalla de compras.
- **FR-002**: La acción MUST abrir un **modal único** que **nunca navega a otra página**, con los
  pasos alternados dentro de él y sin volver a pedir el fichero entre pasos. Los pasos son
  **subir → interpretando (progreso) → revisar propuesta → resumen**; el paso de progreso es un
  estado de espera, no una decisión del usuario, por lo que el flujo sigue siendo el de tres pasos
  del patrón de importación documentado (subir → previsualizar → confirmar).
- **FR-003**: El sistema MUST aceptar documentos en **PDF y en imagen** (formatos de imagen de uso
  común para fotos y escaneos) y MUST permitir **subir varios documentos en una misma operación**.
- **FR-004**: El sistema MUST rechazar, **antes** de invocar al modelo, los archivos que superen el
  límite admitido de tamaño, número de páginas o número de documentos por lote, indicando el límite
  concreto incumplido y el archivo afectado.
- **FR-005**: Todo botón que dispare la interpretación o la creación MUST mostrar estado de carga
  (deshabilitado + indicador de progreso) mientras la petición está en vuelo, y MUST restaurarse al
  terminar, tanto en éxito como en error.

**Interpretación y propuesta**

- **FR-006**: El sistema MUST enviar cada documento al proveedor de IA **ya configurado por el
  tenant** y MUST obtener de él una propuesta estructurada con: datos del proveedor emisor, número de
  documento, fecha, y una lista de líneas con concepto, cantidad, precio unitario y tipo impositivo.
- **FR-007**: Si el tenant **no tiene configurada** la integración de IA, el sistema MUST informarlo
  con un mensaje que indique dónde configurarla, y MUST NOT intentar la interpretación ni mostrar un
  error técnico.
- **FR-008**: Ante un fallo del servicio de IA, el sistema MUST distinguir al menos: **clave
  inválida**, **límite de uso alcanzado**, **servicio no disponible** y **error interno**, con un
  mensaje accionable por caso; el detalle técnico MUST mostrarse únicamente a quien puede ver la
  configuración del tenant.
- **FR-009**: Si un documento no puede interpretarse como documento de compra, el sistema MUST
  informarlo identificando el archivo por su nombre, y MUST continuar con el resto del lote.
- **FR-010**: La propuesta MUST señalar de forma visible qué campos **no pudo leer** del documento,
  en vez de rellenarlos con valores inventados o por defecto.
- **FR-011**: La propuesta MUST NOT persistir ninguna compra, línea ni proveedor. **Nada se crea
  hasta que el usuario confirma explícitamente.**
- **FR-012**: El sistema MUST conservar el documento subido asociado a la propuesta durante el tiempo
  que dure la revisión, y MUST descartarlo si la propuesta se abandona sin crear la compra.

**Revisión y edición**

- **FR-013**: El usuario MUST poder **modificar cualquier valor** de la propuesta antes de crear la
  compra: proveedor, número de documento, fecha, notas, y por cada línea el concepto, la cantidad, el
  precio unitario, el tipo impositivo y el artículo asociado.
- **FR-014**: El usuario MUST poder **añadir y eliminar líneas** de la propuesta.
- **FR-015**: El sistema MUST mostrar los importes (base, cuota de impuesto, total) **recalculados a
  partir de los valores actualmente en pantalla**, actualizándose al editar, para que el usuario vea
  antes de confirmar exactamente lo que se va a guardar.
- **FR-016**: El usuario MUST poder **descartar** una propuesta sin efectos: ninguna compra, línea ni
  proveedor creados.

**Proveedor**

- **FR-017**: El sistema MUST intentar emparejar al emisor del documento con un proveedor existente
  del tenant, primero por **identificación fiscal (NIF/CIF)** y, si no hay coincidencia, por
  **similitud de nombre**.
- **FR-018**: El sistema MUST distinguir en la propuesta un emparejamiento **por identificación
  fiscal** (fiable) de uno **por nombre** (sugerencia a confirmar).
- **FR-019**: El sistema MUST NOT crear un proveedor de forma automática. Cuando no hay
  coincidencia, MUST ofrecer crear uno con los datos leídos, editables, y requerir **confirmación
  explícita** del usuario.
- **FR-020**: El sistema MUST impedir la creación de la compra mientras no haya un proveedor
  resuelto (existente elegido o nuevo confirmado).
- **FR-021**: Cuando la creación implique dar de alta un proveedor nuevo, el alta del proveedor y la
  creación de la compra MUST ser **atómicas**: si algo falla, no queda ni el proveedor ni la compra.

**Líneas y artículos**

- **FR-022**: El sistema MUST proponer, para cada línea, un artículo del catálogo del tenant,
  emparejando por **referencia/SKU** y, en su defecto, por **similitud de nombre**.
- **FR-023**: El sistema MUST distinguir en la propuesta un emparejamiento **por referencia** de uno
  **por nombre**, y MUST dejar la línea como **línea libre** cuando no haya coincidencia razonable.
- **FR-024**: El usuario MUST poder aceptar, cambiar o quitar el artículo propuesto en cada línea, y
  la compra creada MUST respetar esa decisión.
- **FR-025**: El sistema MUST NOT crear artículos nuevos en el catálogo como parte de este flujo.

**Creación**

- **FR-026**: Al confirmar, el sistema MUST crear la compra en estado **borrador**, con las mismas
  reglas, validaciones y comportamiento posterior (editar, eliminar, confirmar, anular) que una
  compra dada de alta manualmente. **Este flujo no introduce reglas de stock nuevas ni distintas.**
- **FR-027**: Los importes persistidos (base por línea, cuota por línea, base total, cuota total,
  total) MUST calcularse **en el servidor** a partir de las cantidades, precios y tipos impositivos
  confirmados. Los importes propuestos por el modelo MUST tratarse como sugerencia de interfaz y
  **nunca** como fuente de verdad.
- **FR-028**: El tipo impositivo de cada línea MUST validarse contra el **régimen impositivo del
  tenant** (IVA/IGIC/IPSI); un tipo incoherente con ese régimen MUST señalarse al usuario antes de
  crear. El sistema MUST NOT asumir IVA.
- **FR-029**: La compra creada MUST quedar marcada con un **origen propio** que la distinga de las
  manuales y de las importadas por Facturae, y MUST conservar la referencia al **documento original**.
- **FR-030**: El usuario MUST poder **descargar el documento original** desde el detalle de una compra
  creada por esta vía, con el mismo control de permisos que el resto de la compra.
- **FR-031**: Antes de crear, el sistema MUST avisar si ya existe una compra con **el mismo
  proveedor, número de documento y fecha**, identificando la compra existente. El aviso **MUST NOT
  bloquear**: el usuario decide si crea igualmente o descarta.
- **FR-032**: Tras crear una compra desde el modal, el listado y sus métricas MUST actualizarse sin
  recargar la página completa, y MUST notificarse el resultado mediante el mecanismo de
  notificaciones estándar de la aplicación.

**Lote**

- **FR-033**: Con varios documentos, el sistema MUST presentar las propuestas **en cola dentro del
  mismo modal**, indicando la posición actual y el total, y MUST avanzar a la siguiente al crear o
  descartar la actual.
- **FR-034**: Un documento que falle MUST NOT invalidar el resto del lote; al terminar, el sistema
  MUST resumir cuántos se crearon, cuántos se descartaron y cuáles fallaron y por qué.
- **FR-035**: Las compras ya creadas dentro de una cola MUST conservarse aunque el usuario abandone
  el modal antes de terminarla.

**Aislamiento, seguridad y datos personales**

- **FR-036**: Toda propuesta, documento subido y compra resultante MUST estar asociada al tenant
  activo y MUST ser inaccesible desde otro tenant, incluso conociendo su identificador.
- **FR-037**: El acceso a este flujo MUST estar restringido por el permiso ya existente de la
  pantalla de compras; el control MUST aplicarse en el servidor, no solo ocultando el botón.
- **FR-038**: Los documentos subidos contienen datos personales (identificación y domicilio de
  proveedores y, en su caso, de personas físicas), por lo que el sistema MUST definir un **plazo de
  retención configurable** para los documentos de propuestas **no confirmadas** y un mecanismo de
  **purga periódica**, reutilizando el patrón de retención/purga ya establecido en el proyecto en
  lugar de inventar uno nuevo.
- **FR-039**: El sistema MUST registrar en el log de actividad la creación de una compra por esta
  vía, igual que el resto de altas de compras.

**Documentación (obligaciones de cierre)**

- **FR-040**: La guía in-app de la pantalla de compras MUST actualizarse para describir este flujo,
  en el mismo cambio que la implementación.
- **FR-041**: La base de conocimiento del asistente IA MUST cubrir el módulo de Compras — hoy **no
  existe** ese archivo, así que se crea — incluyendo este flujo y sus reglas
  (propuesta no persistente, proveedor nunca automático, importes recalculados en servidor), en el
  mismo cambio que la implementación.
- **FR-042**: Cualquier convención de interfaz reutilizable que surja (p. ej. el patrón de "cola de
  propuestas revisables en un modal") MUST anotarse en las guías de front en el mismo cambio.

### Key Entities

- **Documento de compra subido**: el archivo PDF o imagen aportado por el usuario. Atributos
  relevantes: nombre original, tipo, tamaño, tenant al que pertenece, momento de subida, y su vínculo
  con la propuesta generada y, si se confirma, con la compra resultante. Se conserva como
  justificante de la compra creada; se purga si la propuesta nunca se confirma.
- **Propuesta de compra**: resultado **transitorio y no persistido como compra** de interpretar un
  documento. Contiene los datos de cabecera propuestos (proveedor detectado, número de documento,
  fecha), las líneas propuestas, el resultado del emparejamiento de proveedor y de artículos, la
  marca de campos no legibles y el aviso de posible duplicado. Vive solo hasta que el usuario la
  confirma o la descarta.
- **Línea propuesta**: concepto, cantidad, precio unitario y tipo impositivo leídos del documento,
  más el artículo del catálogo sugerido (si lo hay) y el criterio por el que se sugirió (referencia o
  nombre). Todas sus propiedades son editables antes de confirmar.
- **Emparejamiento de proveedor**: relación entre el emisor leído del documento y un proveedor del
  catálogo del tenant, con el criterio empleado (identificación fiscal o nombre) y el estado
  (reconocido / sugerido / sin coincidencia → alta pendiente de confirmación).
- **Compra** *(entidad existente)*: se reutiliza sin cambios de comportamiento; esta feature
  incorpora un **origen** que identifica la procedencia "documento interpretado" y el vínculo al
  documento original.
- **Proveedor** *(entidad existente)*: puede darse de alta desde este flujo, siempre con
  confirmación explícita del usuario.
- **Artículo** *(entidad existente)*: solo se **consulta** para emparejar líneas; nunca se crea ni se
  modifica desde este flujo.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario registra una compra de una factura de proveedor de hasta 10 líneas **en
  menos de 1 minuto** desde que pulsa "Importar documento", frente a los varios minutos que exige el
  alta manual línea a línea.
- **SC-002**: El registro de una compra desde documento requiere **cero pulsaciones de teclado** en
  el caso favorable (proveedor reconocido y datos correctos): basta revisar y confirmar.
- **SC-003**: En documentos de compra legibles y bien estructurados, **al menos el 90 %** de los
  campos de cabecera (proveedor, número de documento, fecha) llegan correctos sin que el usuario
  tenga que corregirlos.
- **SC-004**: En esos mismos documentos, **al menos el 85 %** de las líneas llegan con concepto,
  cantidad y precio unitario correctos.
- **SC-005**: **El 100 %** de las compras creadas por esta vía tienen importes que cuadran con sus
  propias líneas, independientemente de lo que el modelo propusiera como totales.
- **SC-006**: **Ningún** proveedor ni artículo se da de alta sin confirmación explícita del usuario,
  verificable descartando propuestas y comprobando que los catálogos no cambian.
- **SC-007**: **Ninguna** propuesta ni documento de un tenant es accesible desde otro tenant,
  verificado con al menos dos tenants de prueba.
- **SC-008**: Con un documento estándar de una página, el usuario recibe la propuesta **en menos de
  30 segundos**, con indicación de progreso durante toda la espera.
- **SC-009**: En un lote con documentos ilegibles mezclados, **el 100 %** de los documentos legibles
  se procesan igualmente, y cada fallo se reporta identificando su archivo por nombre.
- **SC-010**: Las compras creadas por esta vía se comportan de forma **indistinguible** de las
  manuales en su ciclo posterior (editar/eliminar en borrador, confirmar generando stock, anular
  revirtiéndolo), verificable con los mismos casos de prueba del alta manual.

## Assumptions

- **Proveedor de IA**: se reutiliza la integración de IA por tenant ya existente (feature 030), con
  la clave del tenant almacenada cifrada y el modelo fijado por el operador del SaaS. No se introduce
  un proveedor de IA nuevo ni una configuración nueva por tenant más allá de lo ya existente. El
  modelo configurado debe admitir la lectura de documentos e imágenes; si el operador fija un modelo
  que no lo admite, el fallo se reporta como error de servicio (FR-008).
- **Ejecución síncrona**: la interpretación ocurre dentro de la petición del usuario, sin colas ni
  procesos en segundo plano, por compatibilidad con hosting compartido (Principio V) y por coherencia
  con el asistente IA existente. Esto acota el tamaño de lote razonable; el límite concreto se fija
  en el plan.
- **Coste**: cada documento consume cuota del proveedor de IA del propio tenant. No se añade
  facturación, medición ni límite de gasto por tenant en esta feature.
- **La tabla de líneas dentro del modal de propuesta no es un DataTable**: la guía exige DataTable
  para **listados**; aquí se trata de la tabla de edición de las líneas de un documento, el mismo
  caso que la tabla de líneas del alta de facturas/compras, que tampoco lo es. La cola de documentos
  se representa como navegación entre propuestas, no como listado navegable.
- **Alcance de la lectura**: se interpretan facturas y albaranes de proveedor de estructura
  convencional. Documentos manuscritos, tickets térmicos degradados o PDFs con varias facturas
  distintas quedan fuera de la garantía de precisión (los objetivos SC-003/SC-004 aplican a
  documentos legibles y bien estructurados).
- **Idioma**: los documentos esperados están en español; otros idiomas pueden funcionar pero no se
  garantiza precisión.
- **Sin cambios en el ciclo de vida de la compra**: no se toca la confirmación, la anulación ni la
  generación de movimientos de stock; esta feature solo añade una nueva forma de **llegar** a una
  compra en borrador.
- **Sin entrada de menú nueva**: el flujo vive dentro de la pantalla de Compras, por lo que no se da
  de alta un permiso nuevo y se reutiliza el permiso existente de esa sección.
- **Estado B2B**: es un concepto propio de la recepción por Facturae; las compras creadas desde
  documento no entran en ese ciclo y no fijan estado B2B.
- **Sin aprendizaje ni memoria**: el sistema no memoriza correcciones del usuario para mejorar
  emparejamientos futuros. Es una posible evolución, fuera de alcance aquí.
