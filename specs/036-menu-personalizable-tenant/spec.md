# Feature Specification: Menú lateral personalizable por tenant

**Feature Branch**: `036-menu-personalizable-tenant`

**Created**: 2026-07-26

**Status**: Draft

**Input**: User description: "me gustaria un spec para crear una tab nueva de configuracion (vista configuracion) que me permitiese por cada tenant configurar el nombre visible de los elementos del menu y su orden"

## Clarifications

### Session 2026-07-26

- Q: Además de renombrar y reordenar, ¿la tab debe permitir ocultar entradas o mover una entrada de un grupo a otro? → A: Solo nombre y orden. La visibilidad la siguen gobernando exclusivamente los permisos `ver-*` (una única fuente de verdad); no se mueven entradas entre grupos.
- Q: ¿Cómo se reordena en pantalla? → A: Arrastrar y soltar (lista anidada: grupos entre sí, entradas dentro de su grupo). Es una excepción explícita y documentada a la regla «Listados: SIEMPRE DataTable» de `docs/04-front-guidelines.md`, por no ser un listado de registros sino un editor de estructura.
- Q: ¿Cómo se guardan los cambios? → A: Un único botón «Guardar» para toda la tab; recargar sin guardar descarta los cambios.
- Q: ¿Qué alcance tiene «Restaurar valores por defecto»? → A: Solo global (todo el menú), con confirmación previa; no hay reset por elemento.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Renombrar elementos del menú (Priority: P1)

La persona que administra un tenant entra en **Configuración → Menú**, ve la lista completa de
elementos del menú lateral (grupos y entradas), y cambia el texto visible de los que quiera para
que hablen el idioma de su negocio: "Clientes" → "Pacientes", "Artículos/Catálogo" → "Tratamientos",
"Facturas simplificadas" → "Tickets". Guarda y el menú lateral pasa a mostrar esos nombres para
todas las personas de ese tenant.

**Why this priority**: Es el motivo principal de la feature y aporta valor por sí solo: sin tocar
el orden, poder llamar a cada sección como la llama el negocio ya elimina la fricción de "esto que
pone Clientes en realidad son mis pacientes". Es también la parte que no se puede conseguir hoy por
ningún otro medio.

**Independent Test**: Se puede probar entero renombrando un único elemento y recargando cualquier
pantalla de la app: el sidebar muestra el nombre nuevo, y otro tenant sigue viendo el nombre por
defecto.

**Acceptance Scenarios**:

1. **Given** un tenant con el menú sin personalizar, **When** la persona administradora cambia el
   nombre de la entrada "Cartera de clientes" a "Mis pacientes" y guarda, **Then** el sidebar de
   cualquier pantalla de ese tenant muestra "Mis pacientes" en esa entrada y el resto del menú
   permanece igual.
2. **Given** el nombre de un grupo ya personalizado, **When** la persona vuelve a la tab Menú,
   **Then** el campo de ese grupo aparece precargado con el nombre personalizado, no con el nombre
   por defecto.
3. **Given** un tenant A que renombró "Facturas" a "Ventas", **When** una persona del tenant B abre
   la aplicación, **Then** ve "Facturas" (el valor por defecto), sin rastro de la personalización
   de A.
4. **Given** el campo de nombre de un elemento, **When** la persona lo deja vacío o escribe un
   nombre más largo del permitido y guarda, **Then** el sistema rechaza el cambio con un mensaje de
   error junto al campo y no altera el menú.
5. **Given** una persona sin permiso de configuración, **When** intenta acceder a la tab Menú o a
   la operación de guardado, **Then** el sistema deniega el acceso.

---

### User Story 2 - Reordenar el menú (Priority: P2)

La misma persona reordena **arrastrando y soltando**: mueve los grupos del menú entre sí (subir
"Facturas" por encima de "Control de fichaje", por ejemplo) y las entradas dentro de cada grupo, de
modo que lo que su equipo usa a diario quede arriba. Pulsa "Guardar" y el menú lateral respeta ese
orden para todo el tenant.

**Why this priority**: Aporta valor real pero es secundario respecto a renombrar: el orden actual
es utilizable, los nombres equivocados no. Además depende de tener ya la pantalla y el catálogo de
elementos que introduce la P1.

**Independent Test**: Se puede probar arrastrando un único grupo a la primera posición, guardando y
comprobando que el sidebar lo pinta primero, sin haber renombrado nada.

**Acceptance Scenarios**:

1. **Given** el orden por defecto del menú, **When** la persona arrastra el grupo "Facturas" a la
   primera posición y guarda, **Then** el sidebar del tenant muestra "Facturas" como primer grupo y
   el resto conserva su orden relativo.
2. **Given** el grupo "Control de fichaje" con sus entradas en orden por defecto, **When** la
   persona arrastra "Alertas" al primer lugar dentro de ese grupo y guarda, **Then** el sidebar
   muestra "Alertas" como primera entrada de ese grupo y no cambia el orden de los demás grupos.
3. **Given** un rol que solo tiene permiso sobre algunas entradas de un grupo reordenado, **When**
   una persona con ese rol abre la aplicación, **Then** ve únicamente las entradas permitidas, en el
   orden personalizado del tenant (las no permitidas simplemente no aparecen, sin dejar huecos).
4. **Given** un grupo del que el rol no tiene permiso sobre ninguna de sus entradas, **When** la
   persona con ese rol abre la aplicación, **Then** el grupo no aparece, igual que hoy.
5. **Given** la persona arrastrando una entrada, **When** intenta soltarla fuera de su grupo de
   origen, **Then** el sistema no acepta el movimiento y la entrada vuelve a su posición dentro de
   su grupo.
6. **Given** cambios de orden y de nombre sin guardar, **When** la persona recarga la pantalla sin
   pulsar "Guardar", **Then** se descartan y el menú conserva el estado anterior.

---

### User Story 3 - Restaurar los valores por defecto (Priority: P3)

Tras varias pruebas, la persona quiere volver al menú original. Desde la misma tab puede restaurar
los valores por defecto (nombres y orden) del menú completo, confirmando la acción.

**Why this priority**: Es la red de seguridad que hace que las dos historias anteriores se puedan
usar sin miedo, pero no aporta valor si no existe antes la personalización.

**Independent Test**: Personalizar un nombre y un orden, pulsar "Restaurar valores por defecto",
confirmar, y comprobar que el sidebar vuelve exactamente al menú original.

**Acceptance Scenarios**:

1. **Given** un menú con nombres y orden personalizados, **When** la persona restaura los valores
   por defecto y confirma, **Then** la tab y el sidebar vuelven a los nombres y al orden originales.
2. **Given** el diálogo de confirmación de la restauración, **When** la persona lo cancela,
   **Then** la personalización se mantiene intacta.

---

### Edge Cases

- **Elemento nuevo publicado en una versión posterior**: cuando una futura feature añade una
  sección al menú, un tenant que ya personalizó su menú debe ver la sección nueva (no desaparece),
  con su nombre por defecto y colocada al final del nivel al que pertenece, sin que ello borre ni
  desordene su personalización previa.
- **Elemento retirado del producto**: si una sección deja de existir, su personalización guardada se
  ignora silenciosamente y no rompe el pintado del menú ni la tab de configuración.
- **Nombre duplicado**: dos elementos pueden acabar con el mismo nombre visible (p. ej. dos entradas
  llamadas "Ventas"). El sistema **lo permite sin avisar**: no hay regla de unicidad, porque puede
  ser deliberado (una entrada de listado y otra de creación llamadas igual en grupos distintos) y
  cada elemento conserva su destino propio.
- **Nombre con caracteres especiales o marcado HTML**: el nombre se muestra siempre como texto
  plano; nunca se interpreta como marcado.
- **Menú del Super Admin**: el Super Admin no pertenece a ningún tenant; su menú nunca se ve
  afectado por esta personalización y no dispone de esta tab.
- **Sesión abierta mientras se guarda**: una persona que ya tenía la app abierta ve los nombres
  nuevos en la siguiente pantalla que cargue; no se exige propagación en caliente.
- **Personalización vacía**: un tenant que nunca abrió la tab se comporta exactamente como hoy.
- **Arrastre en pantalla táctil o estrecha**: la reordenación debe poder realizarse también desde
  una tablet/móvil; si el gesto no es viable en un dispositivo concreto, la persona debe seguir
  pudiendo al menos renombrar sin quedarse bloqueada.
- **Orden recibido incompleto o con identificadores desconocidos**: al guardar, el servidor
  reconstruye el orden a partir del catálogo, ignorando identificadores que no reconoce y
  colocando al final los elementos del catálogo que no vinieran en la petición; nunca confía en que
  la lista recibida esté completa ni bien formada.

## Requirements *(mandatory)*

### Functional Requirements

**Catálogo y alcance**

- **FR-001**: El sistema DEBE mantener un catálogo del menú lateral del tenant como fuente de
  verdad, con un identificador estable por elemento, su nombre por defecto, su nivel (grupo o
  entrada), el grupo al que pertenece cada entrada, y su orden por defecto. El catálogo cubre los
  10 elementos de primer nivel actuales (Inicio, Control de fichaje, Clientes, CRM, Stock,
  Facturas, POS, Archivos, Marketing, Usuarios) y sus 26 entradas de segundo nivel.
- **FR-002**: La personalización DEBE ser una capa por tenant sobre ese catálogo, referenciada por
  el identificador estable de cada elemento; el catálogo nunca se edita desde la aplicación.
- **FR-003**: El sistema NO DEBE permitir crear elementos de menú nuevos, eliminar elementos
  existentes, mover una entrada de un grupo a otro, ni cambiar los iconos o los destinos de los
  elementos. El alcance es exclusivamente nombre visible y orden.

**Renombrar**

- **FR-004**: Las personas con permiso de configuración DEBEN poder editar el nombre visible de
  cualquier elemento del menú de su tenant, tanto grupos como entradas.
- **FR-005**: El sistema DEBE validar cada nombre: obligatorio (no vacío ni solo espacios) y de un
  máximo de 40 caracteres, mostrando el error junto al campo correspondiente sin perder el resto de
  los cambios en pantalla.
- **FR-006**: El sistema DEBE mostrar todo nombre personalizado como texto plano, sin interpretar
  marcado.
- **FR-007**: Un elemento sin nombre personalizado DEBE mostrar su nombre por defecto.

**Reordenar**

- **FR-008**: Las personas con permiso de configuración DEBEN poder reordenar los grupos entre sí y
  las entradas dentro de su propio grupo **arrastrando y soltando** los elementos en la lista.
- **FR-008a**: El sistema NO DEBE aceptar que una entrada se suelte fuera de su grupo de origen: el
  arrastre está confinado a su propio nivel, y un intento de sacarla la devuelve a su posición.
- **FR-009**: El sistema DEBE reflejar el orden personalizado en el menú lateral de todas las
  personas de ese tenant.
- **FR-010**: Un nivel sin orden personalizado DEBE conservar su orden por defecto.

**Permisos y aislamiento**

- **FR-011**: La personalización NO DEBE alterar en absoluto la visibilidad por permisos: una
  entrada renombrada o reordenada sigue oculta para quien no tiene su permiso `ver-*`, y un grupo
  sigue oculto cuando la persona no tiene permiso sobre ninguna de sus entradas.
- **FR-012**: La personalización DEBE estar aislada por tenant (Principio I): ningún tenant ve ni
  puede modificar la personalización de otro.
- **FR-013**: El acceso a la tab y a las operaciones de lectura/guardado/restauración DEBE exigir el
  permiso de configuración existente, comprobado en el servidor y no solo ocultando la tab.
- **FR-014**: El menú del Super Admin NO DEBE verse afectado por esta feature.

**Restaurar**

- **FR-015**: Las personas con permiso de configuración DEBEN poder restaurar **el menú completo**
  a sus nombres y orden por defecto, en una sola acción y previa confirmación explícita. NO se
  ofrece restauración por elemento individual.
- **FR-016**: Tras restaurar, el tenant DEBE quedar en el mismo estado que un tenant que nunca
  personalizó su menú.

**Evolución del catálogo**

- **FR-017**: Cuando el catálogo incorpore un elemento nuevo, los tenants con personalización previa
  DEBEN verlo con su nombre por defecto, al final del nivel al que pertenece, conservando intacta su
  personalización del resto.
- **FR-018**: Una personalización guardada que apunte a un elemento que ya no existe en el catálogo
  DEBE ignorarse sin provocar errores.

**Experiencia de uso**

- **FR-019**: La personalización DEBE editarse desde una tab nueva llamada "Menú" dentro de la vista
  de Configuración existente, sin sacar a la persona de esa pantalla.
- **FR-020**: La pantalla DEBE mostrar, junto a cada campo editable, el nombre por defecto del
  elemento, para que la persona sepa qué está renombrando aunque el nombre actual ya no se parezca
  al original.
- **FR-020a**: La pantalla DEBE presentar los elementos como una lista jerárquica que refleje la
  estructura real del menú (grupos con sus entradas anidadas), de modo que la persona reconozca lo
  que está editando sin traducir mentalmente una tabla plana.
- **FR-020b**: Los cambios de nombre y de orden DEBEN confirmarse con un **único botón "Guardar"**
  para toda la tab; nada se persiste hasta pulsarlo, y abandonar o recargar la pantalla sin guardar
  descarta los cambios pendientes.
- **FR-021**: El resultado de guardar y de restaurar DEBE comunicarse con el mecanismo de
  notificación estándar de la aplicación (toast), y toda acción que implique esperar debe dar
  feedback de carga en su botón.
- **FR-022**: El sistema DEBE registrar el cambio de configuración del menú en el log de actividad,
  igual que el resto de cambios de configuración del tenant.

### Key Entities

- **Elemento de menú (catálogo)**: definición fija de una pieza del menú lateral. Atributos:
  identificador estable, nombre por defecto, nivel (grupo / entrada de primer nivel / entrada de
  grupo), grupo padre cuando aplica, orden por defecto, y el permiso `ver-*` que la gobierna. Vive
  en el producto, no en los datos del tenant.
- **Personalización de menú del tenant**: conjunto de pares "identificador de elemento → nombre
  visible y/o posición" pertenecientes a un tenant. Es opcional y parcial: solo contiene lo que la
  persona cambió. Se guarda con el resto de la configuración del tenant.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una persona administradora puede renombrar una sección del menú y verla reflejada en
  su barra lateral en menos de 1 minuto, sin ayuda externa y sin salir de la pantalla de
  Configuración.
- **SC-002**: El 100 % de los elementos del menú del tenant (los 10 de primer nivel y sus 26
  entradas de segundo nivel actuales, 36 en total) son renombrables y reordenables dentro de su
  nivel.
- **SC-003**: Las reglas de visibilidad por rol no cambian: para cualquier rol, el conjunto de
  entradas visibles antes y después de personalizar el menú es idéntico; solo cambian su texto y su
  orden.
- **SC-004**: Ningún tenant puede leer ni modificar la personalización de otro (verificado con al
  menos dos tenants de prueba).
- **SC-005**: Un tenant que nunca abre la tab "Menú" ve exactamente el mismo menú que antes de esta
  feature.
- **SC-006**: Restaurar los valores por defecto devuelve el menú a su estado original en una sola
  acción confirmada.
- **SC-007**: Añadir una sección nueva al menú en una feature futura no obliga a tocar la
  personalización de ningún tenant ni a migrar datos.
- **SC-008**: Una persona puede reordenar el menú completo (nombres aparte) y guardarlo en una sola
  confirmación, sin que ningún movimiento intermedio quede persistido antes de guardar.

## Assumptions

- La personalización es **a nivel de tenant, no por usuario ni por rol**: todas las personas del
  tenant ven el mismo menú personalizado (filtrado por sus permisos). Una personalización por rol o
  por persona queda fuera de alcance.
- Se reutiliza el **permiso de configuración ya existente** (`ver-configuracion`) para gobernar la
  tab; no se crea un permiso nuevo, porque no se añade una sección nueva al menú lateral sino una
  tab dentro de una vista ya gateada (la regla "nueva entrada de menú ⇒ nuevo permiso" de
  `docs/04-front-guidelines.md` aplica a entradas del sidebar, no a tabs internas de una vista).
- La personalización se guarda con el **mismo mecanismo de configuración por tenant** que ya usan
  las demás tabs de Configuración; no se introduce infraestructura nueva (Principio V).
- El cambio se ve en la **siguiente carga de página**; no se exige actualizar en caliente el sidebar
  de las sesiones ya abiertas.
- El nombre visible se personaliza **solo en español** (la aplicación es monolingüe hoy); no hay
  traducciones por idioma.
- La personalización **no afecta** a títulos de página, migas de pan, textos de las propias
  pantallas, la ayuda contextual ni la base de conocimiento del asistente IA: solo al menú lateral.
  Las guías siguen refiriéndose a los nombres por defecto.
- Los datos personalizados (nombres de menú) **no son datos personales**, por lo que no aplican
  plazos de retención/purga del Principio II.
- **Excepción documentada a "Listados: SIEMPRE DataTable"** (`docs/04-front-guidelines.md`): el
  editor de menú es una lista jerárquica arrastrable, no un listado de registros con búsqueda,
  paginación y orden por columna. La regla existe para los `index` de entidades del negocio; forzar
  aquí una DataTable impediría el arrastre anidado y no aportaría ninguna de sus ventajas. La
  excepción y su motivo se anotarán en la propia guía de front como parte de esta feature.
- El mecanismo de arrastrar y soltar se resolverá con una librería del banco del template ya
  disponible (`template/.../public/vendor/jqueryui/`), vendorizándola como cualquier otro plugin,
  en lugar de introducir una dependencia externa nueva (Principio V).
- Se asume que el catálogo de elementos se deriva del sidebar actual documentado en
  `resources/views/partials/sidebar.blade.php` y que esta feature convierte ese marcado en algo
  dirigido por el catálogo, sin cambiar iconos, destinos ni permisos existentes.
</content>
</invoke>
