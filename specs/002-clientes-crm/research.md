# Research: Gestión de Clientes (CRM)

**Feature**: 002-clientes-crm · **Date**: 2026-07-02

Decisiones técnicas para resolver los puntos abiertos del Technical Context. Verificado contra el
código instalado (feature 001: `stancl/tenancy` v3.10, `SetTenantContext`, layout `app.blade.php`
con `@stack('styles')`/`@stack('scripts')`, jQuery bundled en `vendor/global/global.min.js`) y los
assets reales del template en `template/Laravel-NexaDash-v1.0-28_May_2025/package/`.

---

## D1a. Corrección descubierta en test-first: sin binding implícito de ruta para `Cliente`

**Decision**: `ClienteController@update`/`@destroy` reciben el id de `{cliente}` como `string`
(NO como `Cliente $cliente` con binding implícito) y lo resuelven manualmente con
`Cliente::findOrFail($cliente)` dentro del método.

**Rationale**: el test crítico de aislamiento (T010) detectó que `SubstituteBindings` (la
resolución implícita de `{cliente}` a un modelo) se ejecuta, en este proyecto, **antes** que el
middleware `tenant.context` complete su trabajo dentro del pipeline real de Laravel — verificado
con `$kernel->middlewarePriority` (`SubstituteBindings` está fijado en una posición del array de
prioridad global de Laravel; `tenant.context`, al ser un middleware propio no listado en ese
array, no tiene garantía de ejecutar antes). El resultado observado: el binding implícito
encontraba clientes de **otro tenant** aunque `tenancy()->initialized` fuera `true` en el
controller. Resolver el modelo manualmente dentro del método garantiza que el `TenantScope` esté
activo, porque el controller siempre corre al final del pipeline de middleware, sin depender del
orden relativo de `SubstituteBindings`.

**Alternatives considered**: reordenar/forzar prioridad de `tenant.context` en
`bootstrap/app.php` (más fràgil, depende de detalles internos de Laravel que pueden cambiar entre
versiones); usar `Route::model()`/binding explícito con `resolveRouteBinding` sobrescrito en
`Cliente` (más código para el mismo resultado). La resolución manual es la más simple y explícita
(Principio V) y el patrón recomendado por la comunidad Laravel para modelos con scope de tenant.

## D1. Aislamiento de tenant: trait `BelongsToTenant`

**Decision**: `Cliente` usa el trait `Stancl\Tenancy\Database\Concerns\BelongsToTenant` (verificado
en `vendor/`). Aplica un `TenantScope` global que filtra por `tenant_id` y autocompleta `tenant_id`
al crear cuando tenancy está inicializado. Es exactamente el mecanismo que la feature 001 dejó
previsto (research 001 · D3) y que exige el Principio I.

**Rationale**: no reinventar el scope; el paquete ya resuelve scope + autofill + contexto. El
middleware `SetTenantContext` (ya existente) inicializa tenancy con el tenant del usuario en cada
request autenticado, así que el scope está activo dentro del grupo `['auth','tenant.context']`.

**Alternatives considered**: global scope artesanal (rechazado en 001, duplica el paquete).

**Implicación de tests (Principio IV)**: hay que verificar que (a) crear un cliente autocompleta el
`tenant_id` del tenant activo, (b) listar solo devuelve clientes del tenant activo, (c) acceder por
id a un cliente de otro tenant devuelve 404 (el scope lo oculta del `findOrFail`).

## D2. Estructura de la tabla `clientes`

**Decision**: migración con exactamente los campos de `docs/03-modelo-datos.md`:
`tenant_id` (fk indexado), `tipo` (string, enum PHP), `nombre`, `razon_social` (nullable),
`nif` (varchar(15), nullable), `direccion`, `cp`, `ciudad`, `provincia`, `pais` (default `ES`),
`email`, `telefono`, `aplica_recargo_equivalencia` (bool, default false), `irpf_defecto`
(decimal(5,2) nullable), `tipo_impositivo_defecto` (decimal(5,2) nullable), `notas` (text nullable),
`softDeletes`, `timestamps`. Índices: `(tenant_id, nif)` y `(tenant_id, nombre)`.

**Rationale**: el modelo de datos es la fuente de verdad (constitución II). Se respetan
convenciones: `DECIMAL(5,2)` para porcentajes, `softDeletes`.

**Nota sobre unicidad de NIF**: NO se usa un índice único en DB para `(tenant_id, nif)` porque el
soft-delete y el NIF nullable lo complican (múltiples NULL y clientes borrados chocarían). La
unicidad se valida en la capa de request (D5) con `Rule::unique` acotada a `tenant_id`, `nif` no
nulo y `deleted_at` null. El índice `(tenant_id, nif)` queda como índice normal (búsqueda), no
único.

## D3. Tipo de cliente: enum PHP

**Decision**: `App\Enums\TipoCliente` (string-backed): `Empresa = 'empresa'`, `Particular =
'particular'`. Columna `tipo` varchar con cast a enum en el modelo. Sigue el patrón de
`App\Enums\UserRole` de la feature 001.

**Rationale**: consistencia con el código existente; string en DB para no requerir ALTER si se
añaden tipos.

## D4. Tabla en el frontend: DataTables responsive, client-side

**Decision**:
- Trasplantar desde `template/.../public/vendor/datatables/` a `public/vendor/datatables/`: `css/`,
  `images/`, `js/jquery.dataTables.min.js` y `responsive/responsive.css` + `responsive/responsive.js`.
  (No se necesitan buttons/jszip para esta feature.)
- El backend renderiza el `<tbody>` con **todos** los clientes del tenant (Blade), y DataTables
  inicializa sobre esa tabla ya poblada en modo **client-side** con `responsive: true`.
- Init propio en `public/js/plugins-init/clientes-datatable.init.js` (NO reutilizar
  `datatables.init.js` del template, que construye una tabla desde un `dataSet` demo hardcodeado).
  El init selecciona `#clientes-table` y activa `responsive`, búsqueda, orden y paginación, con
  textos en español (`language`).
- La tabla usa `class="display responsive nowrap"` como pide la spec.
- Assets cargados solo en `clientes/index.blade.php` vía `@push('styles')` y `@push('scripts')`.

**Rationale**: para el volumen esperado (miles de filas por tenant en el peor caso) el render
client-side es suficiente y es lo más simple (Principio V); evita montar el endpoint AJAX
server-side de DataTables. jQuery ya está disponible (bundled en global.min.js, verificado). Si en
el futuro el volumen lo exige, se migra a server-side sin cambiar el modelo.

**Alternatives considered**: DataTables server-side processing (AJAX) — más complejo, innecesario
ahora. Paginación nativa de Laravel — no da búsqueda/orden client-side ni el look del template.

## D5. Validación server-side (Form Requests + regla NIF)

**Decision**: `StoreClienteRequest` y `UpdateClienteRequest`.
- Reglas comunes: `tipo` in enum; `nombre` required; `email` nullable email; `pais` default `ES`;
  `cp`/`telefono` string; `irpf_defecto` y `tipo_impositivo_defecto` nullable numeric entre 0 y 100;
  `aplica_recargo_equivalencia` boolean.
- **Empresa** (`tipo=empresa`): `razon_social` required, `nif` required (FR-013).
- **Particular**: `razon_social`/`nif` nullable (FR-013).
- **Formato NIF** (FR-007a): regla `App\Rules\NifEspanol` que valida DNI/NIF (8 dígitos + letra de
  control), NIE (X/Y/Z + 7 dígitos + letra) y CIF (letra + 7 dígitos + dígito/letra de control).
  Solo se aplica si el NIF no está vacío.
- **Unicidad NIF por tenant** (FR-007b): `Rule::unique('clientes','nif')` acotada con
  `->where('tenant_id', <tenant activo>)` e ignorando soft-deleted; en update, `->ignore($cliente)`.
  Solo cuando `nif` no es vacío.

**Rationale**: Form Requests centralizan validación server-side (Principio III) y devuelven errores
por campo (SC-005). La regla de NIF encapsula la lógica de dígito de control (coherente con producto
España-first, Principio II) y es unit-testeable.

**Alternatives considered**: validación inline en el controller (menos reutilizable); paquete
externo de validación de NIF (innecesario, YAGNI — la regla es corta y conocida).

## D6. Métricas de las 3 cartas

**Decision**: en `ClienteController@index`, calcular en backend (dentro del scope de tenant):
`total = Cliente::count()`, `empresas = Cliente::where('tipo','empresa')->count()`,
`particulares = Cliente::where('tipo','particular')->count()`. Pasar a la vista y renderizar en 3
cartas adaptadas del bloque `depostit-card`/`same-card` de `crm.blade.php` (títulos en español,
sin las cifras demo en dólares).

**Rationale**: FR-009/FR-012/SC-004. Cálculo server-side, cifras siempre consistentes con la tabla.
Tres queries `count()` triviales; si se quisiera optimizar, un solo `groupBy('tipo')`, pero YAGNI.

## D7. Rutas y navegación

**Decision**: `Route::resource('clientes', ClienteController::class)->only(['index','store','update','destroy'])`
dentro del grupo `['auth','tenant.context']` de `routes/web.php` (mismo grupo que el dashboard).
**No hay rutas `create`/`edit`** (ver D10 — alta y edición usan un modal sobre `index`, no páginas
propias). Enlace "Clientes" en `partials/sidebar.blade.php` como ítem de primer nivel con su icono
(SVG del set del template).

**Rationale**: el resource controller es el patrón Laravel estándar (Principio V). El grupo ya
aplica auth + contexto de tenant, así que el aislamiento queda garantizado por construcción.

## D10. Alta y edición vía modal Bootstrap único (no páginas separadas)

**Decision**: un único modal Bootstrap `#clienteModal` vive en `clientes/index.blade.php`,
conteniendo el parcial de campos `_form.blade.php`. Se reutiliza para alta y edición:

- **Alta**: el botón "Agregar cliente" limpia el modal (JS) y deja el `<form>` apuntando a
  `route('clientes.store')` con método `POST`.
- **Edición**: cada fila de la tabla lleva los datos del cliente como atributos `data-*` en el
  botón "Editar" (id, tipo, nombre, razón social, NIF, dirección, cp, ciudad, provincia, país,
  email, teléfono, recargo, IRPF, tipo impositivo, notas). Un script
  (`public/js/plugins-init/clientes-modal.init.js`) lee esos atributos al hacer clic, rellena los
  campos del modal, cambia el `action` del formulario a `route('clientes.update', id)` con
  `@method('PUT')`, y abre el modal (`bootstrap.Modal`). **No hay endpoint AJAX ni petición GET
  adicional**: los datos ya están en el DOM porque la tabla se renderiza completa (D4).
- El modal reutiliza el markup base de `ui-modal.blade.php` del template (`modal fade`,
  `modal-dialog`, `modal-content`, `modal-header/body/footer`).

**Rationale**: decisión explícita del usuario (más "app-like", sin salir de `/clientes`), reforzada
después por un segundo requisito explícito: **ninguna acción (alta/edición/borrado) debe recargar
la página** (ver D11 — corrige el rechazo inicial de AJAX que había en esta decisión).

**Alternatives considered**:
- *Páginas separadas `create.blade.php`/`edit.blade.php`* (descartado a pedido del usuario): más
  simple de testear pero menos fluido.
- *Un modal por cliente* (uno por fila): duplica markup N veces innecesariamente; un modal
  reutilizado es más simple y liviano. Rechazado.

## D11. Alta, edición y borrado por AJAX (sin recargar la página)

**Decision**: el usuario pidió explícitamente que ninguna de las tres acciones recargue la
página — corrige D10, que había descartado AJAX por YAGNI. Implementación:

- `ClienteController@store/@update/@destroy` detectan `$request->wantsJson()` (verdadero cuando el
  cliente envía `Accept: application/json`, como hace jQuery con `dataType: 'json'`) y responden
  `response()->json(['message' => ...])` (201/200) en vez de `redirect()`. Si la petición NO pide
  JSON (form nativo sin JS), se mantiene el flujo clásico de redirect — no se rompe progressive
  enhancement.
- Los errores de validación **ya los maneja Laravel automáticamente**: cuando la petición espera
  JSON, el `ValidationException` se convierte en una respuesta 422 con `{message, errors}` sin
  tocar código propio — no hace falta sobrescribir `failedValidation()` en los Form Requests.
- `public/js/plugins-init/clientes-modal.init.js` intercepta el `submit` del `#cliente-form` con
  `$.ajax` (`dataType: 'json'`), y el `click` de cada botón "Eliminar" con otra llamada `$.ajax`
  (`DELETE` vía `_method` spoofing). En éxito: cierra el modal, muestra un alert de éxito, y llama
  `refreshListado()`.
- `refreshListado()` hace un `GET` (no-JSON) a la propia URL de `clientes.index`, parsea el HTML
  de respuesta con `DOMParser`, y reemplaza únicamente `#clientes-cards` y `#clientes-table-body`
  por sus versiones frescas (destruyendo y reinicializando el DataTable). Reutiliza el mismo Blade
  que ya renderiza la lista — sin duplicar lógica de conteo/formato en JS ni crear un endpoint JSON
  aparte para los datos de la tabla.
- Se extrajo `resources/views/clientes/_row.blade.php` (una fila) para que `index.blade.php` lo
  reutilice en el render inicial, manteniendo una única fuente de verdad del markup de fila.
- Los errores de validación se muestran inyectando el mensaje en el `data-error-for="<campo>"`
  correspondiente dentro del modal (sin cerrarlo), en vez de depender de `old()`/`@error` de Blade
  (que ya no aplican porque nunca hay recarga de página en el camino feliz de JS).

**Rationale**: cumple el requisito explícito del usuario. Evita construir una API JSON completa
(GET de un cliente individual, serialización dedicada) reutilizando el HTML ya renderizado por
`index` vía un `GET` normal — la única "capa AJAX" nueva es el POST/PUT/DELETE de la propia acción,
no una API paralela. Compatible con Principio V (sin dependencias nuevas: jQuery y Bootstrap ya
estaban cargados).

**Alternatives considered**:
- *Devolver HTML parcial (fragmento de fila) desde `store`/`update`* y actualizar solo esa fila con
  JS: evita el roundtrip extra de `refreshListado()`, pero obliga a reimplementar en el controller
  el cálculo de métricas + renderizar la fila en cada acción, duplicando lo que `index` ya hace.
  Rechazado por simplicidad (una sola fuente de verdad del render).
- *API JSON dedicada* (devolver el modelo serializado y renderizar la fila en JS con una plantilla
  de cliente): más "SPA-like" pero introduce una segunda representación de los datos (JS) que
  mantener en paralelo al Blade. Rechazado (YAGNI/Principio V).

## D8. Borrado lógico con confirmación

**Decision**: `destroy` hace `$cliente->delete()` (SoftDeletes → set `deleted_at`). La confirmación
previa (FR-006) se resuelve en el frontend con SweetAlert (el template ya trae el vendor
`uc-sweetalert`/`sweetalert2`) o un `confirm()` nativo como fallback; el formulario de borrado envía
`DELETE` vía method spoofing. No se expone restauración de borrados en esta feature (fuera de
alcance).

**Rationale**: SoftDeletes cumple FR-003/SC-006 (registro conservado). Confirmación evita borrados
accidentales.

## D9. Estrategia de tests (Principio IV — test-first)

**Decision**: escribir **antes** de implementar:
- `ClienteTenantIsolationTest` (crítico, Red-Green-Refactor): con 2 tenants + sus usuarios, afirmar
  que el listado de A no incluye clientes de B; que crear como A asigna `tenant_id` de A; que
  `edit`/`update`/`destroy` sobre un id de B responde 404 para el usuario de A.
- `ClienteCrudTest`: alta OK, alta inválida (falta nombre / email mal / empresa sin NIF), NIF con
  formato inválido rechazado, NIF duplicado en el mismo tenant rechazado, mismo NIF permitido en
  tenant distinto, edición OK, borrado lógico (desaparece del listado, `deleted_at` no nulo).
- Unit test de `NifEspanol` con casos válidos/ inválidos de DNI/NIE/CIF.
- Factory `ClienteFactory` con estados `empresa()` y `particular()`.

**Rationale**: la constitución exige test-first en aislamiento multi-tenant; SQLite in-memory
(ya en phpunit.xml) mantiene la suite rápida.
