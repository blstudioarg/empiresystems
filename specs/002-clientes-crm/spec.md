# Feature Specification: Gestión de Clientes (CRM)

**Feature Branch**: `002-clientes-crm`

**Created**: 2026-07-02

**Status**: Draft

**Input**: User description: "Gestión de clientes (CRM) para cada tenant. Alta, listado, edición y borrado (soft delete) de clientes dentro del tenant activo, respetando el aislamiento multi-tenant por tenant_id (global scope). Estructura de datos según docs/03-modelo-datos.md. Vista 'Clientes' con enlace en el sidebar, cards informativas del template arriba y tabla DataTables responsive con acciones agregar/editar/eliminar."

## Clarifications

### Session 2026-07-02

- Q: ¿Cuántas cartas informativas arriba de la tabla y con qué métricas? → A: 3 cartas — total de clientes, clientes empresa, clientes particular.
- Q: ¿Cómo tratar un NIF repetido dentro del mismo tenant al crear/editar un cliente? → A: Bloquear — NIF único por tenant (para NIF no vacío); se rechaza el guardado si ya existe.
- Q: ¿Validar el formato del NIF/CIF/NIE español en backend? → A: Sí — rechazar NIF/CIF/NIE con dígito de control o estructura inválidos.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver el listado de clientes del tenant (Priority: P1)

Un usuario autenticado de una empresa (tenant) abre la pantalla "Clientes" desde el menú lateral y ve, de un vistazo, un resumen de su cartera de clientes (cartas informativas con métricas) y una tabla con todos los clientes de **su** empresa. La tabla permite buscar, ordenar y paginar, y es usable en móvil (responsive).

**Why this priority**: Es el núcleo consultable de la feature y la base sobre la que se apoyan el alta/edición/borrado. Sin el listado aislado por tenant, nada del resto tiene valor. Entrega valor por sí solo: un usuario puede consultar su cartera.

**Independent Test**: Con dos tenants con clientes distintos, iniciar sesión como usuario del tenant A y verificar que la pantalla muestra solo los clientes de A (nunca los de B), que las métricas de las cartas reflejan la cartera de A, y que la tabla busca/ordena/pagina correctamente en escritorio y móvil.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado del tenant A con N clientes, **When** abre la pantalla "Clientes", **Then** ve una tabla con exactamente esos N clientes y ninguno de otro tenant.
2. **Given** que existe el enlace "Clientes" en el menú lateral, **When** el usuario hace clic, **Then** navega a la pantalla de clientes.
3. **Given** la pantalla de clientes, **When** el usuario escribe en el buscador o cambia de página, **Then** la tabla filtra/pagina sin recargar toda la aplicación.
4. **Given** la pantalla en un dispositivo móvil (viewport estrecho), **When** se muestra la tabla, **Then** las columnas se adaptan/colapsan de forma legible (modo responsive) sin desbordar horizontalmente.
5. **Given** un tenant sin clientes, **When** el usuario abre la pantalla, **Then** ve un estado vacío claro y las cartas muestran cero.

---

### User Story 2 - Alta de un nuevo cliente (Priority: P1)

Un usuario del tenant crea un cliente nuevo rellenando un formulario (empresa o particular) con sus datos fiscales y de contacto. Al guardar, el cliente queda asociado automáticamente a su tenant y aparece en el listado.

**Why this priority**: Poblar la cartera es imprescindible para que el CRM y, más adelante, la facturación tengan datos con los que operar. Junto con el listado forma el MVP mínimo.

**Independent Test**: Como usuario del tenant A, crear un cliente con datos válidos y verificar que aparece en el listado de A, que quedó asignado a A, y que enviar datos inválidos muestra errores de validación sin crear el registro.

**Acceptance Scenarios**:

1. **Given** el formulario de alta, **When** el usuario envía datos válidos, **Then** el cliente se crea asociado a su tenant y se muestra en el listado con un mensaje de éxito.
2. **Given** el formulario de alta, **When** el usuario envía datos inválidos (p. ej. email mal formado o falta un campo obligatorio), **Then** no se crea el cliente y se muestran mensajes de validación por campo.
3. **Given** un cliente de tipo "particular" en factura simplificada, **When** se deja el NIF vacío, **Then** el sistema lo permite (el NIF no es obligatorio para particular).
4. **Given** un cliente de tipo "empresa", **When** se intenta guardar sin NIF/razón social, **Then** el sistema exige esos campos.

---

### User Story 3 - Edición de un cliente existente (Priority: P2)

Un usuario del tenant abre un cliente existente de su cartera, modifica sus datos y guarda los cambios.

**Why this priority**: Los datos de cliente cambian con el tiempo (dirección, contacto, retención). Importante, pero el sistema ya aporta valor con listado + alta.

**Independent Test**: Como usuario del tenant A, editar un cliente de A, guardar y verificar que los cambios persisten; verificar que no se puede editar (ni siquiera acceder a) un cliente de otro tenant.

**Acceptance Scenarios**:

1. **Given** un cliente del tenant A, **When** el usuario edita sus datos y guarda con valores válidos, **Then** los cambios persisten y se reflejan en el listado.
2. **Given** un intento de editar un cliente que pertenece al tenant B, **When** el usuario del tenant A accede a esa acción, **Then** el sistema lo impide (no encontrado / sin acceso).
3. **Given** el formulario de edición, **When** se envían datos inválidos, **Then** no se guardan los cambios y se muestran errores de validación.

---

### User Story 4 - Eliminación (soft delete) de un cliente (Priority: P2)

Un usuario del tenant elimina un cliente de su cartera. El borrado es lógico (soft delete): el registro deja de aparecer en el listado pero se conserva en la base de datos por trazabilidad.

**Why this priority**: Mantener la cartera limpia es útil, pero es la operación menos frecuente y menos crítica del CRUD.

**Independent Test**: Como usuario del tenant A, eliminar un cliente y verificar que desaparece del listado, que el conteo de las cartas baja, y que el registro sigue existiendo (soft-deleted) y no es accesible desde otro tenant.

**Acceptance Scenarios**:

1. **Given** un cliente en el listado, **When** el usuario confirma su eliminación, **Then** el cliente desaparece del listado y las métricas se actualizan.
2. **Given** una acción de eliminar, **When** se dispara, **Then** el sistema pide confirmación antes de borrar.
3. **Given** un cliente eliminado, **When** se consulta el listado, **Then** no aparece, pero el dato permanece almacenado (borrado lógico, no físico).

---

### Edge Cases

- **Aislamiento en URL manipulada**: si un usuario del tenant A intenta acceder por URL directa a editar/eliminar un cliente del tenant B, el sistema responde como "no encontrado" (nunca revela ni opera sobre datos de otro tenant).
- **NIF duplicado dentro del tenant**: intentar guardar un cliente con un NIF ya usado por otro cliente activo del mismo tenant se rechaza con error por campo (FR-007b); NIF iguales en tenants distintos son independientes.
- **NIF con formato inválido**: un NIF/CIF/NIE con estructura o dígito de control incorrecto se rechaza en validación (FR-007a).
- **Campos numéricos fuera de rango**: porcentajes de IRPF / tipo impositivo fuera de 0–100 se rechazan en validación.
- **Estado vacío**: tenant sin clientes → tabla con mensaje de vacío y cartas en cero.
- **Cliente con muchos datos opcionales vacíos**: particular sin NIF, sin dirección completa — debe listarse y editarse sin errores.
- **Volumen alto**: un tenant con miles de clientes — la tabla debe seguir siendo usable (búsqueda/paginación/orden).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST persistir clientes con los atributos definidos en `docs/03-modelo-datos.md` para la tabla `clientes`: tipo (`empresa`/`particular`), nombre / razón social, NIF, dirección, código postal, ciudad, provincia, país (por defecto `ES`), email, teléfono, indicador de recargo de equivalencia, IRPF por defecto, tipo impositivo por defecto, y notas.
- **FR-002**: Cada cliente MUST estar asociado a un único tenant mediante `tenant_id`, y toda consulta de clientes MUST filtrarse automáticamente por el tenant activo a través del global scope de tenant (Principio I de la constitución). Ningún usuario puede ver ni operar sobre clientes de otro tenant.
- **FR-003**: El sistema MUST soportar borrado lógico (soft delete) de clientes: un cliente eliminado deja de listarse pero su registro se conserva.
- **FR-004**: Los usuarios MUST poder crear un cliente nuevo desde un formulario, indicando si es empresa o particular.
- **FR-005**: Los usuarios MUST poder editar los datos de un cliente existente de su tenant.
- **FR-006**: Los usuarios MUST poder eliminar (soft delete) un cliente de su tenant, con confirmación previa.
- **FR-007**: El sistema MUST validar en el backend los datos de alta y edición (campos obligatorios según tipo, formato de email, rangos de porcentajes) y rechazar los inválidos mostrando errores comprensibles por campo.
- **FR-007a**: Cuando se informe un NIF, el sistema MUST validar que tenga formato válido de NIF/CIF/NIE español (estructura y dígito de control) y rechazar los inválidos con error por campo.
- **FR-007b**: El sistema MUST impedir crear o editar un cliente con un NIF (no vacío) que ya exista para otro cliente activo del mismo tenant; es decir, el NIF es único por tenant. NIF vacío (permitido en particulares) no está sujeto a esta restricción, y un mismo NIF puede existir en tenants distintos de forma independiente.
- **FR-008**: El sistema MUST exponer una pantalla "Clientes" accesible desde un enlace en el menú lateral (sidebar) de la aplicación.
- **FR-009**: La pantalla de clientes MUST mostrar, en la parte superior del contenido, exactamente 3 cartas informativas con métricas reales de la cartera del tenant activo: total de clientes, clientes de tipo empresa y clientes de tipo particular.
- **FR-010**: La pantalla de clientes MUST listar los clientes del tenant en una tabla con búsqueda, ordenación y paginación, y MUST comportarse en modo responsive (adaptación/colapso de columnas) en viewports estrechos.
- **FR-011**: Cada fila de la tabla MUST ofrecer acciones para editar y eliminar ese cliente, y la pantalla MUST ofrecer una acción para agregar un cliente nuevo.
- **FR-012**: Las métricas de las cartas MUST reflejar el estado actual de la cartera (se actualizan tras alta/edición/eliminación).
- **FR-013**: El sistema MUST requerir NIF y razón social para clientes de tipo empresa, y MUST permitir NIF vacío para clientes de tipo particular (coherente con la factura simplificada).
- **FR-014**: Solo usuarios autenticados con un tenant activo MUST poder acceder a la gestión de clientes. (El comportamiento del super admin, que no pertenece a un tenant, se trata en Assumptions.)

### Key Entities *(include if feature involves data)*

- **Cliente**: receptor de las facturas del tenant. Pertenece a un tenant (`tenant_id`). Puede ser empresa o particular. Atributos: identificación fiscal (NIF), nombre/razón social, domicilio (dirección, CP, ciudad, provincia, país), contacto (email, teléfono), parámetros fiscales por defecto (recargo de equivalencia, IRPF por defecto, tipo impositivo por defecto) y notas libres. Soporta borrado lógico. Relación: un tenant tiene muchos clientes; (más adelante) un cliente tendrá muchas facturas.
- **Tenant**: empresa cliente del SaaS que posee su propia cartera de clientes. Ya existe en el sistema; aquí solo se referencia como propietario del aislamiento.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las consultas, altas, ediciones y borrados de clientes quedan restringidos al tenant activo: en pruebas con múltiples tenants, ningún usuario accede a datos de otro tenant (cero fugas).
- **SC-002**: Un usuario puede dar de alta un cliente nuevo completo en menos de 2 minutos desde que abre la pantalla.
- **SC-003**: La pantalla de clientes es plenamente utilizable (buscar, ordenar, paginar, editar, eliminar) tanto en escritorio como en móvil, sin desbordamiento horizontal ni pérdida de acciones.
- **SC-004**: Las cartas informativas muestran cifras que coinciden exactamente con el contenido real de la tabla del tenant en todo momento (tras cada operación CRUD).
- **SC-005**: El 100% de los envíos con datos inválidos se rechazan con mensajes por campo y sin crear/modificar registros.
- **SC-006**: Un cliente eliminado desaparece del listado en el 100% de los casos y su registro permanece recuperable en base de datos (borrado lógico verificable).

## Assumptions

- **Número de cartas informativas**: resuelto en Clarifications → **3 cartas** (total, empresas, particulares). Las cifras de ejemplo del template (importes en dólares, proyectos) se reemplazan por datos reales del tenant.
- **Componente de tabla**: se reutiliza el componente DataTables del template NexaDash en su variante responsive (`display responsive nowrap`), trasplantando sus assets (`public/vendor/datatables` y el init de plugins) desde `template/` según indica CLAUDE.md. La elección concreta de assets se detalla en el plan; la spec solo exige el comportamiento (búsqueda/orden/paginación/responsive).
- **Roles**: cualquier usuario autenticado asociado a un tenant (`admin` o `usuario`) puede gestionar clientes; no se define en esta feature una diferencia de permisos más fina entre esos dos roles. El `super_admin` (sin tenant propio) no gestiona clientes de negocio desde esta pantalla; su alcance es la administración del SaaS y queda fuera del scope de esta feature.
- **Alcance del CRUD**: la feature cubre únicamente la entidad `clientes`. No incluye facturas, series, artículos ni ninguna otra tabla del modelo de datos (se abordarán en features posteriores).
- **NIF**: resuelto en Clarifications → NIF único por tenant (FR-007b) y validación de formato NIF/CIF/NIE español (FR-007a).
- **Importación/exportación masiva** de clientes queda fuera de alcance de esta feature.
- **Idioma**: la interfaz de la pantalla está en español (coherente con el producto España-first).
