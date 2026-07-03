# Feature Specification: Catálogo de Productos/Servicios

**Feature Branch**: `004-productos-servicios`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: "Catálogo de productos/servicios (feature 004-productos-servicios). CRUD completo siguiendo el mismo patrón que la feature de clientes (002-clientes-crm): vista tipo DataTable, tenant scope, tests de aislamiento multi-tenant, formularios con validación. Un único modelo Producto con un campo tipo (producto | servicio). Cada producto guarda: código/referencia, nombre, descripción, precio unitario sin IVA, y su tipo impositivo por defecto. El impuesto indirecto NO es solo IVA: según el régimen fiscal del tenant (domicilio de la empresa emisora) aplica IVA (península/Baleares: 21/10/4/0/exento), IGIC (Canarias: 0/3/7/9,5/15/20) o IPSI (Ceuta/Melilla). El producto debe guardar el tipo impositivo aplicable de forma genérica (porcentaje + régimen resuelto según el tenant), no un IVA hardcodeado. Opcionalmente el producto puede llevar IRPF y recargo de equivalencia para precargar las líneas de factura más adelante. Esta feature es previa a la de facturación (005), que compondrá clientes + productos. Precios se guardan sin impuesto (base imponible), el impuesto se calcula en backend."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver el catálogo de productos/servicios del tenant (Priority: P1)

Un usuario autenticado de una empresa (tenant) abre la pantalla "Productos/Servicios" desde el menú lateral y ve, de un vistazo, un resumen de su catálogo (cartas informativas con métricas) y una tabla con todos sus artículos. La tabla permite buscar, ordenar y paginar, y es usable en móvil (responsive).

**Why this priority**: Es el núcleo consultable de la feature y la base sobre la que se apoyan el alta/edición/borrado. Sin el listado aislado por tenant, nada del resto tiene valor.

**Independent Test**: Con dos tenants con catálogos distintos, iniciar sesión como usuario del tenant A y verificar que la pantalla muestra solo los artículos de A (nunca los de B), que las métricas de las cartas reflejan el catálogo de A, y que la tabla busca/ordena/pagina correctamente en escritorio y móvil.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado del tenant A con N artículos, **When** abre la pantalla "Productos/Servicios", **Then** ve una tabla con exactamente esos N artículos y ninguno de otro tenant.
2. **Given** que existe el enlace "Productos/Servicios" en el menú lateral, **When** el usuario hace clic, **Then** navega a la pantalla del catálogo.
3. **Given** la pantalla del catálogo, **When** el usuario escribe en el buscador o cambia de página, **Then** la tabla filtra/pagina sin recargar toda la aplicación.
4. **Given** la pantalla en un dispositivo móvil (viewport estrecho), **When** se muestra la tabla, **Then** las columnas se adaptan/colapsan de forma legible sin desbordar horizontalmente.
5. **Given** un tenant sin artículos, **When** el usuario abre la pantalla, **Then** ve un estado vacío claro y las cartas muestran cero.

---

### User Story 2 - Alta de un producto o servicio nuevo (Priority: P1)

Un usuario del tenant crea un artículo nuevo (producto o servicio) indicando código, nombre, descripción, precio unitario sin impuesto, y su tipo impositivo. El sistema le ofrece únicamente los tipos impositivos válidos según el régimen fiscal de su tenant (IVA, IGIC o IPSI), nunca los de otro régimen. Al guardar, el artículo queda asociado automáticamente a su tenant.

**Why this priority**: Poblar el catálogo es imprescindible para que la futura facturación tenga artículos con los que componer líneas. Junto con el listado forma el MVP mínimo.

**Independent Test**: Como usuario de un tenant con régimen IVA, crear un artículo y verificar que solo puede elegir tipos de IVA (21/10/4/0/exento); como usuario de un tenant con régimen IGIC, verificar que solo ve tipos de IGIC (0/3/7/9,5/15/20). Enviar datos inválidos debe mostrar errores sin crear el registro.

**Acceptance Scenarios**:

1. **Given** el formulario de alta, **When** el usuario envía datos válidos (tipo, nombre, precio, tipo impositivo válido para su régimen), **Then** el artículo se crea asociado a su tenant y se muestra en el listado con un mensaje de éxito.
2. **Given** el formulario de alta, **When** el usuario envía datos inválidos (p. ej. precio negativo, falta el nombre, o un tipo impositivo que no pertenece al régimen de su tenant), **Then** no se crea el artículo y se muestran mensajes de validación por campo.
3. **Given** un tenant con régimen IGIC, **When** el usuario abre el selector de tipo impositivo, **Then** solo ve las opciones válidas de IGIC (0/3/7/9,5/15/20), nunca las de IVA o IPSI.
4. **Given** un artículo de tipo "producto", **When** se marca "gestionar stock", **Then** el sistema permite indicar stock actual y stock mínimo; **When** el artículo es de tipo "servicio", **Then** esos campos no aplican.
5. **Given** el formulario de alta, **When** el usuario indica opcionalmente IRPF y/o recargo de equivalencia por defecto, **Then** esos valores se guardan junto con el artículo para uso futuro en líneas de factura.

---

### User Story 3 - Edición de un producto/servicio existente (Priority: P2)

Un usuario del tenant abre un artículo existente de su catálogo, modifica sus datos y guarda los cambios.

**Why this priority**: Los precios y datos del catálogo cambian con el tiempo. Importante, pero el sistema ya aporta valor con listado + alta.

**Independent Test**: Como usuario del tenant A, editar un artículo de A, guardar y verificar que los cambios persisten; verificar que no se puede editar (ni siquiera acceder a) un artículo de otro tenant.

**Acceptance Scenarios**:

1. **Given** un artículo del tenant A, **When** el usuario edita sus datos y guarda con valores válidos, **Then** los cambios persisten y se reflejan en el listado.
2. **Given** un intento de editar un artículo que pertenece al tenant B, **When** el usuario del tenant A accede a esa acción, **Then** el sistema lo impide (no encontrado / sin acceso).
3. **Given** el formulario de edición, **When** se envían datos inválidos, **Then** no se guardan los cambios y se muestran errores de validación.

---

### User Story 4 - Eliminación (soft delete) de un producto/servicio (Priority: P3)

Un usuario del tenant elimina un artículo de su catálogo. El borrado es lógico (soft delete): el registro deja de aparecer en el listado pero se conserva por trazabilidad (p. ej. artículos ya usados en facturas históricas).

**Why this priority**: Mantener el catálogo limpio es útil, pero es la operación menos frecuente y menos crítica del CRUD.

**Independent Test**: Como usuario del tenant A, eliminar un artículo y verificar que desaparece del listado, que el conteo de las cartas baja, y que el registro sigue existiendo (soft-deleted) y no es accesible desde otro tenant.

**Acceptance Scenarios**:

1. **Given** un artículo en el listado, **When** el usuario confirma su eliminación, **Then** el artículo desaparece del listado y las métricas se actualizan.
2. **Given** una acción de eliminar, **When** se dispara, **Then** el sistema pide confirmación antes de borrar.
3. **Given** un artículo eliminado, **When** se consulta el listado, **Then** no aparece, pero el dato permanece almacenado (borrado lógico, no físico).

---

### Edge Cases

- **Aislamiento en URL manipulada**: si un usuario del tenant A intenta acceder por URL directa a editar/eliminar un artículo del tenant B, el sistema responde como "no encontrado" (nunca revela ni opera sobre datos de otro tenant).
- **Cambio de régimen impositivo del tenant**: si el régimen fiscal del tenant cambiara después de crear artículos, los tipos impositivos ya guardados en artículos existentes no se revalidan retroactivamente (fuera de alcance de esta feature); solo se valida en el momento de crear/editar.
- **Código/referencia duplicado**: dos artículos del mismo tenant pueden compartir o no código — ver Clarifications.
- **Precio con muchos decimales**: el precio unitario admite decimales suficientes para céntimos y fracciones (p. ej. tarifas por hora); se rechazan valores negativos.
- **Producto vs. servicio y stock**: un servicio nunca gestiona stock; un producto puede o no gestionar stock (campo opcional `gestion_stock`).
- **Estado vacío**: tenant sin artículos → tabla con mensaje de vacío y cartas en cero.
- **Volumen alto**: un tenant con miles de artículos — la tabla debe seguir siendo usable (búsqueda/paginación/orden).
- **Tipo impositivo fuera de rango o de otro régimen**: se rechaza en validación de backend, no solo en el selector del front.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST persistir artículos con los atributos definidos en `docs/03-modelo-datos.md` para la tabla `articulos`: tipo (`producto`/`servicio`), código/SKU (opcional), nombre, descripción, unidad, precio unitario (base imponible, sin impuesto), tipo impositivo, gestión de stock y sus campos asociados (solo relevantes para producto), estado activo, e IRPF y recargo de equivalencia por defecto (opcionales, para precargar líneas de factura).
- **FR-002**: Cada artículo MUST estar asociado a un único tenant mediante `tenant_id`, y toda consulta de artículos MUST filtrarse automáticamente por el tenant activo a través del global scope de tenant (Principio I de la constitución). Ningún usuario puede ver ni operar sobre artículos de otro tenant.
- **FR-003**: El sistema MUST soportar borrado lógico (soft delete) de artículos: un artículo eliminado deja de listarse pero su registro se conserva.
- **FR-004**: Los usuarios MUST poder crear un artículo nuevo desde un formulario, indicando si es producto o servicio.
- **FR-005**: Los usuarios MUST poder editar los datos de un artículo existente de su tenant.
- **FR-006**: Los usuarios MUST poder eliminar (soft delete) un artículo de su tenant, con confirmación previa.
- **FR-007**: El sistema MUST validar en el backend los datos de alta y edición (campos obligatorios, precio no negativo, formato numérico) y rechazar los inválidos mostrando errores comprensibles por campo.
- **FR-008**: El sistema MUST resolver el conjunto de tipos impositivos válidos (impuesto indirecto y sus porcentajes) según el `regimen_impositivo` del tenant activo (`iva`: 21/10/4/0/exento; `igic`: 0/3/7/9,5/15/20; `ipsi`: tipos propios de la ciudad autónoma) y MUST rechazar en backend cualquier alta/edición que use un tipo impositivo que no pertenezca al régimen del tenant.
- **FR-009**: El sistema MUST guardar el tipo impositivo del artículo como un porcentaje válido dentro del régimen resuelto, no como un valor de IVA hardcodeado, de forma que el mismo modelo sirva para tenants con distinto régimen fiscal.
- **FR-010**: El sistema MUST permitir opcionalmente indicar un IRPF por defecto y/o marcar recargo de equivalencia por defecto en el artículo, para su uso posterior al precargar líneas de factura (fuera de alcance de esta feature calcular ese precargado).
- **FR-011**: El sistema MUST exigir stock actual/mínimo únicamente cuando el artículo es de tipo `producto` y tiene activada la gestión de stock; para `servicio` o producto sin gestión de stock, esos campos MUST permanecer vacíos/no aplicables.
- **FR-012**: El sistema MUST exponer una pantalla "Productos/Servicios" accesible desde un enlace en el menú lateral (sidebar) de la aplicación.
- **FR-013**: La pantalla del catálogo MUST mostrar, en la parte superior del contenido, cartas informativas con métricas reales del catálogo del tenant activo: total de artículos, total de productos y total de servicios.
- **FR-014**: La pantalla del catálogo MUST listar los artículos del tenant en una tabla con búsqueda, ordenación y paginación, y MUST comportarse en modo responsive (adaptación/colapso de columnas) en viewports estrechos.
- **FR-015**: Cada fila de la tabla MUST ofrecer acciones para editar y eliminar ese artículo, y la pantalla MUST ofrecer una acción para agregar un artículo nuevo.
- **FR-016**: Las métricas de las cartas MUST reflejar el estado actual del catálogo (se actualizan tras alta/edición/eliminación).
- **FR-017**: Solo usuarios autenticados con un tenant activo MUST poder acceder a la gestión del catálogo. (El super admin, sin tenant propio, queda fuera del alcance de esta pantalla.)

### Key Entities *(include if feature involves data)*

- **Artículo** (`articulos`): elemento del catálogo del tenant del que se pueden traer líneas de factura. Puede ser `producto` (bien físico, puede llevar stock) o `servicio` (nunca lleva stock). Pertenece a un tenant (`tenant_id`). Atributos: tipo, código/SKU opcional, nombre, descripción, unidad, precio unitario (base imponible), tipo impositivo (porcentaje válido según el régimen del tenant), gestión de stock y sus campos (solo producto), IRPF por defecto y recargo de equivalencia por defecto (opcionales), estado activo. Soporta borrado lógico. Relación: un tenant tiene muchos artículos; (más adelante) un artículo podrá aparecer en muchas líneas de factura, opcionalmente.
- **Tenant**: empresa cliente del SaaS. Ya existe en el sistema; aquí se referencia como propietario del aislamiento y como fuente del `regimen_impositivo` que determina los tipos impositivos válidos para sus artículos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las consultas, altas, ediciones y borrados de artículos quedan restringidos al tenant activo: en pruebas con múltiples tenants, ningún usuario accede a datos de otro tenant (cero fugas).
- **SC-002**: El 100% de los artículos creados o editados tienen un tipo impositivo que pertenece al régimen fiscal (IVA/IGIC/IPSI) del tenant activo; el 100% de los intentos de usar un tipo de otro régimen se rechazan.
- **SC-003**: Un usuario puede dar de alta un artículo nuevo completo en menos de 1 minuto desde que abre la pantalla.
- **SC-004**: La pantalla del catálogo es plenamente utilizable (buscar, ordenar, paginar, editar, eliminar) tanto en escritorio como en móvil, sin desbordamiento horizontal ni pérdida de acciones.
- **SC-005**: Las cartas informativas muestran cifras que coinciden exactamente con el contenido real de la tabla del tenant en todo momento (tras cada operación CRUD).
- **SC-006**: El 100% de los envíos con datos inválidos (incluyendo precio negativo o tipo impositivo fuera del régimen) se rechazan con mensajes por campo y sin crear/modificar registros.
- **SC-007**: Un artículo eliminado desaparece del listado en el 100% de los casos y su registro permanece recuperable en base de datos (borrado lógico verificable).

## Assumptions

- **Modelo y tabla**: se usa la entidad `articulos` ya definida en `docs/03-modelo-datos.md` (catálogo unificado producto/servicio), no una tabla nueva "productos" separada.
- **Régimen fiscal del tenant**: esta feature asume que el tenant ya tiene resuelto su `regimen_impositivo` (`iva`/`igic`/`ipsi`) según lo documentado en `03-modelo-datos.md` y `02-facturacion-espana.md`. Si ese campo aún no existe en la tabla `tenants` implementada, esta feature lo añade como prerrequisito técnico (se detalla en plan), reutilizando `regimen_impositivo` tal como está documentado, con `iva` como valor por defecto para tenants existentes (mayoría península/Baleares).
- **Tipos impositivos por régimen**: se codifican como catálogo fijo en el sistema (no editable por el usuario) según los valores vigentes documentados en `02-facturacion-espana.md`: IVA (21/10/4/0/exento), IGIC (0/3/7/9,5/15/20). IPSI usa tipos propios de cada ciudad autónoma (Ceuta/Melilla); dado que no hay un catálogo único nacional, para tenants con régimen `ipsi` se permite introducir el porcentaje manualmente en vez de elegir de una lista cerrada.
- **Código/SKU**: es opcional y no se exige unicidad por tenant en esta feature (a diferencia del NIF de clientes); si en el futuro se detecta necesidad de unicidad, se abordará como ajuste posterior.
- **Componente de tabla**: se reutiliza el mismo patrón DataTables responsive ya trasplantado para la feature de clientes (`public/vendor/datatables`), sin necesidad de nuevos assets.
- **Roles**: cualquier usuario autenticado asociado a un tenant (`admin` o `usuario`) puede gestionar el catálogo; no se define en esta feature una diferencia de permisos más fina entre esos dos roles.
- **Alcance del CRUD**: la feature cubre únicamente la entidad `articulos`. No incluye facturas, líneas de factura ni movimientos de stock (kardex); estos se abordarán en features posteriores (005-facturacion y una futura de inventario).
- **Precargado de líneas de factura**: el IRPF y recargo de equivalencia por defecto del artículo se guardan para uso futuro; la lógica de precargar líneas de factura con estos valores es responsabilidad de la feature 005 y queda fuera de alcance aquí.
- **Importación/exportación masiva** de artículos queda fuera de alcance de esta feature.
- **Idioma**: la interfaz de la pantalla está en español (coherente con el producto España-first).
