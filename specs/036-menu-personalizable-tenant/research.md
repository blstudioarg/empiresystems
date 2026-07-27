# Research — Menú lateral personalizable por tenant

**Feature**: 036-menu-personalizable-tenant | **Fecha**: 2026-07-26

Todas las incógnitas del Technical Context quedan resueltas aquí. No queda ningún
`NEEDS CLARIFICATION`.

---

## D1 — Dónde y cómo se persiste la personalización

**Decisión**: una **única fila** en la tabla `configuraciones` existente, con
`clave = 'menu.personalizacion'`, `grupo = 'menu'`, `tipo = 'json'`, y en `valor` un JSON con el
mapa de etiquetas y el orden. Ausencia de la fila = tenant sin personalizar.

**Rationale**:

- `configuraciones` ya lleva `tenant_id` y usa `BelongsToTenant` (Principio I resuelto de fábrica),
  y es el mecanismo que usan **todas** las demás tabs de Configuración (`ConfigCrm`,
  `ConfigFichajes`, `ConfigTenant`, `VerifactuTenant`…). Reutilizarlo evita migración, tabla nueva y
  modelo nuevo (Principio V).
- El dato es un documento pequeño (36 elementos: 10 de primer nivel + 26 entradas), se lee
  **entero** en cada carga de página y se
  escribe **entero** al guardar: nunca se consulta ni se filtra por un elemento suelto. Es
  exactamente el caso de uso donde un JSON en una fila gana a una tabla relacional.
- Precedente directo en el propio repo: `ConfigCrm::CLAVE_ASIGNACION_COMERCIALES` ya guarda un array
  como JSON con `tipo => 'json'` en esta misma tabla.

**Alternativas consideradas**:

- *Tabla `menu_personalizaciones` (una fila por elemento)*: rechazada. 36 filas por tenant para un
  dato que siempre se lee y escribe completo, más una migración, un modelo y un factory, sin ninguna
  consulta que se beneficie de la normalización. Complejidad prematura (Principio V, YAGNI).
- *Una clave de `configuraciones` por elemento* (`menu.clientes.etiqueta`, `menu.clientes.orden`…):
  rechazada. Multiplica por ~72 las filas y las escrituras por guardado, y obliga a un `whereIn`
  con todas las claves en cada carga de página.
- *Caché (Redis/archivo) como fuente de verdad*: rechazada, no hay infraestructura garantizada en
  hosting compartido (Principio V) y la BD ya es suficientemente rápida para una fila por request.

**Consecuencia de rendimiento**: 1 consulta extra por carga de página, **memoizada por request** en
`MenuTenant` (propiedad estática indexada por `tenant_id`) para que varios usos en el mismo render
no la repitan. Es el mismo orden de coste que la cuenta de alertas que el sidebar ya ejecuta hoy.

---

## D2 — Cómo se renderiza el sidebar sin cambiar la visibilidad por permisos

**Decisión**: `sidebar.blade.php` deja de tener el menú del tenant escrito a mano y pasa a **iterar
la estructura que devuelve `MenuTenant::estructura($tenantId)`**, que es el catálogo
(`CatalogoMenu`) con las etiquetas y el orden del tenant ya aplicados. La visibilidad se evalúa en
el propio bucle con `@can($elemento['permiso'])`, usando el permiso **declarado en el catálogo** —
los mismos que hoy están escritos a mano.

Reglas del bucle, para que el resultado sea idéntico al actual:

1. Una **entrada** se pinta solo si el usuario tiene su permiso (o si el catálogo la declara sin
   permiso, caso hoy inexistente en el menú de tenant).
2. Un **grupo** se pinta solo si **al menos una** de sus entradas visibles sobrevive al paso 1 —
   equivalente exacto al `@canany([...])` que hoy envuelve cada grupo, pero derivado del catálogo en
   vez de duplicado a mano.
3. El **bloque del Super Admin** (`@if (auth()->user()->isSuperAdmin())`) queda **fuera** del bucle,
   tal cual está hoy: no se toca ni se personaliza (FR-014).

**Rationale**: es la única forma de que renombrar/reordenar aplique sin duplicar la lógica de
permisos en dos sitios. Derivar el `@canany` del grupo desde sus hijos elimina, además, una fuente
de bugs actual (hoy la lista del `@canany` y la de los `@can` internos se mantienen sincronizadas a
mano).

**Riesgo identificado y su mitigación**: es una reescritura de la pieza de navegación de toda la
app; un fallo aquí es muy visible. Mitigación: `MenuSidebarRenderTest` compara, **para varios roles
distintos**, el conjunto de entradas visibles antes y después (SC-003), y se ejecuta antes de dar la
tarea por buena.

**Alternativas consideradas**:

- *Dejar el Blade como está y solo sustituir los textos con un helper* (`{{ MenuTenant::etiqueta('clientes') }}`):
  resolvería renombrar sin tocar la estructura, pero **no permite reordenar** (el orden seguiría
  hardcodeado en el marcado). Rechazada por no cubrir la User Story 2.
- *Reordenar en el cliente con JavaScript tras pintar el sidebar*: rechazada. Provoca un salto
  visible del menú en cada carga, deja el orden a merced de que el JS cargue, y hace inauditable el
  render desde los tests de servidor.

---

## D3 — UI de reordenación: excepción a "Listados: SIEMPRE DataTable" y librería de arrastre

**Decisión**: lista jerárquica arrastrable con **jQuery UI `sortable`**, vendorizada en
`public/vendor/jqueryui/` desde el banco del template
(`template/Laravel-NexaDash-v1.0-28_May_2025/package/public/vendor/jqueryui/`). Se declara y
documenta una **excepción explícita** a la regla "Listados: SIEMPRE DataTable" de
`docs/04-front-guidelines.md`.

**Rationale de la excepción** (la guía misma admite excepciones "anotando cuál y por qué"):

- La regla existe para los **listados de registros del negocio** (`clientes`, `facturas`, y las
  cuentas bancarias dentro de una tab). Lo que aporta —búsqueda, paginación, orden por columna,
  estado persistido— no tiene sentido sobre un conjunto **fijo y pequeño de 36 elementos de
  producto** que no son registros del tenant.
- Es incompatible con el requisito: DataTables no soporta arrastre anidado (grupo + hijos) sin la
  extensión `RowReorder`, que además solo reordena filas planas de un mismo nivel.
- Lo que se edita **es una jerarquía**, y FR-020a exige que se vea como tal para que la persona
  reconozca su menú.

La excepción se anota en `docs/04-front-guidelines.md` en el mismo cambio (regla transversal de
`CLAUDE.md`: "una convención que existe solo en el código y no en la doc se vuelve a violar").

**Rationale de jQuery UI**:

- Ya está en el banco del template, así que vendorizarlo es el procedimiento estándar del proyecto y
  no añade dependencia externa (Principio V).
- jQuery ya es global en la app; `sortable` soporta de forma nativa el anidamiento por **listas
  conectadas** y el confinamiento por lista, que es justo lo que exige FR-008a.

**Cómo se confina el arrastre (FR-008a)**: **no** se usa `connectWith` entre listas. Cada `<ul>` de
entradas es un `sortable` **independiente** (sin conexión con los demás), y la lista de grupos es
otro `sortable` con `items` limitado a los `<li>` de grupo. Al no estar conectadas, jQuery UI
devuelve por sí solo cualquier entrada arrastrada fuera de su lista de origen: el requisito se
cumple por construcción, no por una validación a posteriori.

**Alternativas consideradas**: `SortableJS` (dependencia externa nueva, mejor soporte táctil pero
fuera del banco); botones ▲▼ (descartado por el usuario en `/speckit-clarify`); `RowReorder` de
DataTables (no soporta jerarquía).

---

## D4 — El servidor no confía en la lista que recibe

**Decisión**: al guardar, el servidor **no persiste la estructura recibida**: recorre el
`CatalogoMenu` y, para cada elemento, busca su posición y su etiqueta en la petición. Los
identificadores desconocidos se descartan; los elementos del catálogo ausentes de la petición se
colocan **al final de su nivel** conservando su orden relativo por defecto.

**Rationale**: es el análogo del Principio III (la fuente de verdad es el servidor) aplicado a la
estructura: impide que una petición manipulada invente elementos de menú, resucite uno retirado o
descoloque el catálogo. Resuelve de una vez FR-018 (personalización huérfana), FR-017 (elemento
nuevo aparece al final con su nombre por defecto) y el edge case de "orden recibido incompleto".

**Consecuencia útil**: FR-017 sale **gratis** y sin migración. Cuando una feature futura añada un
elemento al catálogo, los tenants ya personalizados lo verán al final de su nivel simplemente porque
no está en su JSON guardado. Esto satisface SC-007.

**Además, solo se guarda lo que difiere del valor por defecto**: una etiqueta idéntica a la del
catálogo no se persiste. Mantiene el JSON pequeño y hace que "restaurar" (D1: borrar la fila) y
"nunca personalicé" sean literalmente el mismo estado (FR-016).

---

## D5 — Accesibilidad y uso táctil del arrastre

**Decisión**: se acepta la limitación conocida del arrastre y se mitiga así:

- La reordenación por arrastre es la **única** vía de cambiar el orden (decisión del usuario), pero
  **renombrar es totalmente independiente de ella**: son inputs de texto normales, operables con
  teclado y en cualquier dispositivo. Una persona que no pueda arrastrar nunca queda bloqueada para
  la User Story 1, que es la P1 (cubre el edge case "arrastre en pantalla táctil o estrecha").
- Se activa el soporte táctil de jQuery UI en los `sortable` (`distance` mínima para no confundir
  arrastre con scroll en tablet) y cada fila lleva un **asa de arrastre explícita** (icono de
  agarre, opción `handle`) en vez de ser arrastrable por toda su superficie: sin asa, tocar el input
  de texto en una tablet iniciaría un arrastre en lugar de enfocar el campo.
- Cada fila expone su nombre accesible y su posición para lectores de pantalla; el estado de la
  lista tras soltar se anuncia con un mensaje de región activa.

**Rationale**: es la mitigación proporcionada al alcance. Una alternativa accesible completa
(botones ▲▼ además del arrastre) fue descartada explícitamente por el usuario en `/speckit-clarify`;
dejar constancia aquí evita que se reabra el debate en implementación.

---

## D6 — Casos especiales del sidebar actual que el catálogo debe modelar

Auditoría de `resources/views/partials/sidebar.blade.php` (estado a 2026-07-26). El catálogo debe
representar estos cuatro casos o el bucle de D2 no podrá reproducir el menú actual:

1. **Entradas de primer nivel sin hijos**: "Inicio" (`ver-dashboard`) y "Archivos"
   (`ver-archivos`) son `<li>` con `href` directo, sin `has-arrow` ni `<ul>` anidado. El catálogo
   los modela como **grupo sin hijos con ruta propia**; el bucle pinta un enlace directo cuando el
   elemento tiene ruta y no tiene hijos. Son renombrables y reordenables junto al resto de grupos.
2. **Badge de alertas**: el grupo "Control de fichaje" muestra un contador de alertas nuevas sobre
   el icono, y la entrada "Alertas" lo repite en línea. Esa cuenta **no se mueve al catálogo** (es
   un dato en vivo, no estructura): el bucle la calcula como hoy y la pinta condicionada a la
   **clave** del elemento (`control-fichaje` / `alertas`), no a su etiqueta — de lo contrario,
   renombrar "Alertas" haría desaparecer su badge.
3. **Entrada condicionada a la existencia de una ruta**: "Roles" está envuelta en
   `@if (Route::has('roles.index'))` además de su `@can('ver-roles')`. El catálogo declara la ruta
   por su **nombre**, y el bucle omite cualquier elemento cuya ruta no esté registrada — lo que
   generaliza ese `Route::has` a todos los elementos en lugar de dejarlo como caso especial.
4. **Iconos**: cada grupo tiene su `<x-lordicon icon="..." size="30" trigger="hover">`. El icono
   pasa a ser un campo del catálogo (**no personalizable**, FR-003); el `size="30"` y el
   `class="nav-text ms-2"` los emite la plantilla del bucle, cumpliendo la sección "Menú lateral:
   tamaño de ícono y texto" de la guía de front para todos los elementos a la vez.

**Fuera del catálogo y sin tocar**: el bloque del Super Admin, la tarjeta de usuario del sidebar, el
botón de "Ayuda de esta pantalla" y el toggle de Dark Mode oculto.
</content>
