# Guías de front

Convenciones de UI recurrentes para no repetir las mismas decisiones (y los mismos errores) en
cada feature. Si una regla de aquí choca con un caso concreto, gana el sentido común, pero hay
que anotar la excepción y por qué.

## Preview de imagen junto a un input file

Cuando un formulario tiene un `<input type="file">` para subir una imagen (logo, avatar, etc.) y
se muestra una preview de la imagen actual/seleccionada, la preview va **pegada encima del
input, dentro de la misma columna** — no en una columna separada al lado. Referencia:
`resources/views/configuracion/_tab_apariencia.blade.php` (campo Logo).

```html
<div class="col-md-6 mb-3">
	<label class="form-label" for="logo">Logo</label>
	<img id="logo-preview" class="d-block mb-2" style="max-height: 80px;" ...>
	<input type="file" class="form-control" id="logo" name="logo" ...>
	<small class="form-text text-muted">...</small>
</div>
```

## Logo del tenant en el nav-header: en mobile siempre el mini, nunca el full

El tenant sube dos logos en `configuracion` (`logo_path` para el sidebar expandido,
`logo_mini_path` para el colapsado — `resources/views/partials/nav-header.blade.php`). En
desktop, `public/css/app-overrides.css` hace la exclusión entre los dos según
`#main-wrapper.menu-toggle` (colapsado → solo mini) o no (expandido → solo full). En **mobile**
el sidebar es siempre off-canvas (nunca "expandido" de verdad), así que esa exclusión por sí sola
dejaría los dos logos ocultos cuando `#main-wrapper` no tiene `.menu-toggle`. Por eso hay una
media query aparte en `app-overrides.css` (mismo breakpoint que usa NexaDash para ocultar
`.brand-title`, `max-width: 47.9375rem`) que fuerza el `img.logo-abbr` (mini) visible en los dos
estados. Si se toca el logo del nav-header, no romper esta regla — el mini es el único que debe
verse en mobile.

## Overrides de color de NexaDash necesitan doblar la clase para ganar especificidad

`style.css` define el color de topbar/nav-header vía selectores de atributo como
`[data-headerbg="color_2"] .header` (especificidad 0,2,0). Una regla `.header { background: ... }`
normal (0,1,0) pierde contra eso pase lo que pase el orden de carga del CSS. Para que el color de
apariencia del tenant (`resources/views/partials/apariencia-tenant.blade.php`, variable
`--topbar-bg`) gane, hay que doblar la clase (`.header.header { ... }`) para igualar o superar esa
especificidad. Si se añade un override de color sobre cualquier elemento que el template también
tematiza vía `[data-algo="..."]`, revisar primero si hace falta el mismo truco.

## Padding del content-body vs padding interno de las cards

`style.css` (NexaDash) trae `.content-body .container-fluid { padding: 1.875rem }` por defecto,
mientras que `.card-body` usa `--bs-card-spacer-x: 1rem`. Esto deja el margen entre el borde de
la página y las cards más ancho que el padding interno de cada card — inconsistente. Ya está
corregido de forma global (no hace falta repetirlo por vista) en `public/css/app-overrides.css`,
que iguala `padding-top`/`padding-right`/`padding-left` a `1rem` y se carga siempre después de
`style.css` en `layouts/app.blade.php`. Cualquier ajuste de espaciados globales del template (no
de una vista puntual) va en ese archivo, nunca editando `style.css` directamente (es vendored).

## Tamaño de formularios: todo "sm" por defecto

Inputs, selects y botones deben verse pequeños (equivalente a `.form-control-sm` /
`.form-select-sm` / `.btn-sm` de Bootstrap), incluyendo el `select` potenciado por
`bootstrap-select` y los `.form-label`. Esto ya está resuelto globalmente en
`public/css/app-overrides.css`: `.form-control`, `.form-select`, `.btn` y `.form-label` heredan
el tamaño "sm" sin tener que añadir la clase `-sm` a mano en cada vista nueva.

- **No agregues `.form-control-sm` / `.btn-sm` explícitamente** en vistas nuevas — ya es el
  comportamiento por defecto. Si en algún caso puntual se necesita un control más grande
  (ej. un CTA principal), usar `.form-control-lg` / `.btn-lg` explícito para el caso excepcional,
  no al revés.
- Si se agrega un nuevo plugin de formulario (date picker, tag input, etc.) que no herede de
  `.form-control`/`.form-select`, revisar su tamaño por defecto y, si hace falta, sumar su regla
  en `app-overrides.css` siguiendo el mismo patrón que `.bootstrap-select .dropdown-toggle`.
- **Ojo con `!important` acá y por qué hace falta**: `style.css` no define `.form-control`/`.btn`
  una sola vez — más abajo en el mismo archivo (buscar `.form-control {` y `.btn {`, hay varias
  ocurrencias) los redefine con `height`/`padding` en **valores literales** (no `var()`), pensados
  para el tamaño grande por defecto del template. Un override sin `!important` en
  `app-overrides.css`, aunque cargue después, pierde contra esas reglas porque siguen dentro de
  `style.css` y ya "ganaron" la propiedad antes de que el navegador llegue a nuestro archivo. Por
  eso el bloque de tamaño `sm` usa `!important` en `height`/`padding`/`font-size`. Si en el futuro
  algo se ve descuadrado (ej. un input `type="file"` cuyo `line-height` alto empuja el texto del
  pseudo-botón fuera y lo solapa con el texto de ayuda de abajo), es señal de que `style.css`
  tiene otra regla puntual pisando el override — buscarla y sumar la contra-regla ahí mismo, no
  sacar el `!important` general.

## Modales: siempre centrados verticalmente

Todo modal se abre **centrado en la pantalla**: el `<div class="modal-dialog ...">` lleva
**siempre** `modal-dialog-centered`, sin excepción. Las clases de tamaño (`modal-lg`, `modal-xl`) o
`modal-dialog-scrollable` se suman después, pero `modal-dialog-centered` no se omite nunca:

```html
<div class="modal-dialog modal-dialog-centered">          {{-- estándar --}}
<div class="modal-dialog modal-dialog-centered modal-lg"> {{-- ancho grande --}}
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
```

Aplica a modales de vistas, de componentes reutilizables (`<x-unidad-select>`, `<x-banco-select>`)
y al modal genérico de confirmación. Al crear un modal nuevo, poner `modal-dialog-centered` de una;
si se ve un modal pegado arriba, es que le falta la clase.

## Padding y gutter de los modales

Mismo problema que arriba: `style.css` redefine `.modal-header`/`.modal-body`/`.modal-footer`
más abajo en el archivo con `padding: 1.875rem` literal. Con controles "sm" adentro ese padding
grande queda desproporcionado — corregido globalmente en `app-overrides.css`:
`.modal-header/.modal-body/.modal-footer` a `1rem`. No hace falta tocar nada por vista nueva.

## Gap entre .row y margin-bottom de .card

Mismo patrón otra vez: `style.css` redefine `.card` más abajo en el archivo con
`margin-bottom: 1.563rem` (25px) y `height: calc(100% - 25px)` (truco para que las cards de una
misma fila queden a igual alto pese al margin-bottom), y `.row` trae `--bs-gutter-x: 25px` /
`--bs-gutter-y: 0`. La combinación se ve como "mucho aire" entre una fila de cards y la
siguiente, y como margen de sobra debajo de la última card de la página.

Corregido globalmente en `app-overrides.css` (no hace falta `g-3`/`mb-3` a mano por vista):
- `.row` → `--bs-gutter-x` y `--bs-gutter-y` a `1rem` (equivalente a `g-3` en todos lados).
- `.card` → `margin-bottom: 1rem` y `height: calc(100% - 1rem)` **juntos** — si se cambia uno hay
  que cambiar el otro, si no las cards de una misma fila quedan de alturas distintas.

**No agregar `mb-3`/`mt-3` a las columnas de un `.row`**: como `--bs-gutter-y: 1rem` ya está seteado
globalmente, Bootstrap le agrega `margin-top: 1rem` a cada columna que envuelve a la fila
siguiente. Si además la columna tiene `mb-3`, ese margen se suma al `margin-top` del gutter de la
fila de abajo → ~2rem de separación en vez de 1rem, doble espacio vertical entre filas de campos.
Corregido en `_form.blade.php` (clientes y artículos): las columnas de formulario ya no llevan
`mb-3`, el espaciado vertical lo da el gutter de `.row` solo. Si una vista nueva se ve con
demasiado aire entre filas, buscar `mb-3`/`mt-3` sueltos en columnas de ese `.row` y sacarlos.

## Icono flotante en cards informativas (métricas)

En las cards de métricas (ej. "Total de clientes", "Clientes empresa" en `clientes/index.blade.php`
y equivalentes en `articulos/index.blade.php`) el `<x-lordicon>` va en un `<div>` sin clases —
**no usar `icon-box bg-primary-light`** en ese contenedor. `.icon-box` (`style.css`) trae
`height`/`width: 2.5rem` fijos con `!important` y `bg-primary-light` un fondo de color; ambos
piensan en un ícono de fuente (`<i class="...">`) chico dentro de una caja, no en un
`<lord-icon>` que ya trae su propio tamaño vía atributo `size`. El resultado con lordicon es un
ícono recortado dentro de una caja demasiado chica con fondo de color de sobra.

Se corrigió puntualmente antes agregando un override CSS por vista para anular `border`/
`background` de `.icon-box` — quedó feo (`!important` peleando contra `!important`) y no arregla
el `height`/`width` fijo. La solución correcta es no poner esas clases desde el principio: dejar
el `<div>` contenedor limpio y que el tamaño del ícono lo controle solo `size="50"` del
`<x-lordicon>`.

## Colores de los lordicon

Todo `<x-lordicon>` debe verse con el color primario + secundario del tenant (nunca los colores
por defecto del JSON del ícono, que vienen fijos de Lordicon). Ya resuelto en
`resources/views/components/lordicon.blade.php`: el componente calcula
`App\Support\AparienciaTenant::coloresEfectivos()` y arma el atributo `colors="primary:...,
secondary:..."` automáticamente si no se pasa `colors` explícito. No hace falta (ni se debe)
pasar `colors` a mano en vistas nuevas — si un ícono puntual necesita otra paleta por algún
motivo excepcional, ahí sí pasar `colors` explícito y anotar el porqué en esa vista.

## Menú lateral: tamaño de ícono y texto

Los `<x-lordicon>` del sidebar (`resources/views/partials/sidebar.blade.php`) van a `size="30"`
(30x30, no el `size="24"` por defecto del componente) y cada `<span class="nav-text">` lleva
`ms-2` para separarse del ícono. El tamaño de fuente de `.nav-text` (14px por defecto del
template) se subió ligeramente a `0.9375rem` en `app-overrides.css`. Si se agrega un ítem nuevo
al sidebar, replicar `size="30"` y `class="nav-text ms-2"` a mano (no hay override CSS que fuerce
el tamaño del ícono porque `<lord-icon>` lo define vía atributo `style`, no clase).

## Confirmación de acciones irreversibles (borrado y otras)

Nunca usar `confirm()` nativo del navegador antes de una acción irreversible (borrado, emitir,
etc.). Usar siempre el modal genérico `resources/views/partials/confirm-delete-modal.blade.php`
(incluido una única vez en `layouts/app.blade.php`, junto con `public/js/confirm-delete.js` cargado
siempre). Por defecto se ve/comporta como confirmación de **borrado**: lordicon
`wired-outline-185-trash-bin-hover-empty` (`trigger="loop"`) + botón rojo "Eliminar".

Desde cualquier `*.init.js`, reemplazar:

```js
if (!confirm('¿Eliminar...?')) { return; }
// ...ajax de borrado...
```

por:

```js
window.confirmDelete('¿Eliminar...?', function () {
	return $.ajax({ /* ...ajax de borrado... */ });
});
```

`confirmDelete(mensaje, onConfirm, opciones?)` reescribe el texto del modal y el handler del botón
en cada llamada (soporta múltiples filas de una misma tabla sin acumular handlers), así que no hace
falta instanciar nada por vista. **`onConfirm` debe hacer `return` del `$.ajax(...)`** (no solo
dispararlo): el modal usa el estado de carga genérico (ver sección siguiente) sobre su propio botón
de confirmación y permanece abierto con spinner hasta que la petición termina, recién ahí se cierra
— si `onConfirm` no devuelve la promesa, el modal se cierra al instante como antes, sin loading
visible durante la espera.

**No reutilizar el modal "tal cual" para una acción que no sea borrado** (el icono de papelera y el
botón "Eliminar" en rojo confunden al usuario, p. ej. "Emitir factura" mostrando "Eliminar"). Pasar
`opciones` para adaptar ícono/texto/color del botón sin duplicar el modal:

```js
window.confirmDelete('¿Emitir esta factura?', onConfirm, {
	confirmLabel: 'Emitir',
	confirmClass: 'btn-primary', // reemplaza btn-danger
	icon: 'invoice',              // debe existir en public/icons/lordicon/
});
```

Referencia de uso: `clientes-modal.init.js`, `articulos-modal.init.js` (borrado, sin opciones),
`facturas-datatable.init.js` (borrado sin opciones + emitir con opciones).

## Estado de carga en botones (AJAX/fetch)

**Todo botón que dispare una petición que puede tardar (`$.ajax`, `fetch`, submit de un form vía
AJAX) DEBE mostrar feedback de carga mientras espera la respuesta**: deshabilitado + spinner, para
que el usuario nunca se quede sin saber si el clic "agarró" o no. Nunca dejar un botón clickeable
(o sin ningún indicio visual) mientras una petición está en vuelo.

Esto ya está resuelto de forma genérica en `public/js/button-loading.js` (cargado siempre desde
`layouts/app.blade.php`, antes que `confirm-delete.js` porque este último depende de él). Dos
funciones:

- **`window.withButtonLoading(button, requestFn)`** — el caso normal. `requestFn` debe devolver el
  jqXHR/Promise/fetch de la petición. Activa el loading, ejecuta `requestFn()`, y restaura el botón
  cuando termina (éxito o error). Devuelve la misma promesa, así que se encadena `.done()/.fail()`
  (o `.then()/.catch()`) exactamente igual que si hubieras llamado `$.ajax(...)` directo:

  ```js
  window.withButtonLoading($submitBtn, function () {
  	return $.ajax({ url: ..., method: 'POST', data: $form.serialize(), ... });
  })
  	.done(function (response) { ... })
  	.fail(function (xhr) { ... });
  ```

  Esto **reemplaza** el patrón manual `$submitBtn.prop('disabled', true)` / `.always(function () {
  $submitBtn.prop('disabled', false); })` que se repetía en cada `*-modal.init.js` — no volver a
  escribirlo a mano en un botón nuevo.

- **`window.setButtonLoading(button, true|false)`** — toggle de bajo nivel, para los pocos casos
  que no encajan en el wrapper de arriba: un submit de **form plano** que navega a otra página (sin
  AJAX, p. ej. `rectificarFacturaForm` en `facturas/index.blade.php`), donde alcanza con activar el
  loading al `submit` y no hace falta restaurarlo (la página navega o recarga). En ese caso, activar
  manualmente y no llamar a `setButtonLoading(..., false)`:

  ```js
  $('#miFormPlano').on('submit', function () {
  	window.setButtonLoading($(this).find('button[type="submit"]'), true);
  });
  ```

**Texto de carga opcional**: si el botón debe cambiar de texto mientras carga (p. ej. "Enviar" →
"Enviando..."), agregar `data-loading-text="Enviando..."` al `<button>` en el Blade — no hace falta
tocar el JS, `withButtonLoading`/`setButtonLoading` lo detectan solos y restauran el texto original
al terminar.

**`confirmDelete` ya usa este mecanismo internamente** sobre su propio botón de confirmación (ver
sección anterior) — un botón de fila que dispara `window.confirmDelete(...)` no necesita loading
propio, alcanza con que su `onConfirm` haga `return $.ajax(...)`.

**No aplica** a: selects que cargan opciones dependientes (usar el patrón inline ya existente,
`$select.prop('disabled', true).html('<option>Cargando...</option>')`, ver `loadLocalidades` en
`clientes-modal.init.js`/`proveedores-modal.init.js`/`super-admin-tenants-modal.init.js`); guardado
automático on-change sin botón explícito (`configuracion-apariencia.init.js`); la barra de progreso
propia de DataTables (`processing: true`, ya maneja su propio feedback).

## Notificaciones

Siempre toastr (`window.showToast(type, message)` / flash de sesión), nunca alerts Bootstrap
ad-hoc. Detalle completo en `CLAUDE.md`.

## Listados: SIEMPRE DataTable, nunca una `<table>` plana

Cualquier listado de registros (cuentas, clientes, artículos, facturas, y cualquier `index` nuevo)
se renderiza **siempre** con nuestra DataTable ya configurada — nunca una `<table>` HTML plana
poblada con un `@foreach` en Blade. Esto vale también para los listados que viven **dentro de una
tab de configuración** (p. ej. "Cuentas bancarias" en la tab Facturación): estar embebido en una
tab no es excusa para saltarse el patrón.

El patrón estándar (referencia: `clientes/index.blade.php`, `configuracion/_tab_facturacion.blade.php`):

- **Markup**: `<table id="<algo>-table" class="display responsive nowrap w-100">` con solo `<thead>`
  y un `<tbody></tbody>` vacío; las filas las pinta DataTables por AJAX, no Blade.
- **Datos por AJAX**: el `index` del controller responde `response()->json(['data' => ...])` cuando
  `$request->wantsJson()`; el init pide esa URL con `headers: { Accept: 'application/json' }` y
  `dataSrc: 'data'`. Si el listado está en otra ruta que la página actual (caso tab de
  configuración), pasar la URL explícita en el `window.<algo>State` (no asumir
  `window.location.href`).
- **Assets**: cargar en `@push('styles')`/`@push('scripts')` el CSS/JS de
  `vendor/datatables/...` + el override de paginación "Anterior/Siguiente" (ver sección más abajo)
  + los init `js/plugins-init/<algo>-datatable.init.js` y `<algo>-modal.init.js`.
- **Acciones**: columna única con dropdown "Acciones" (ver "Columna Acciones"); alta/edición en un
  único modal reutilizado + submit AJAX (ver sección siguiente).

**No** poblar tablas con `@foreach` + `@forelse` en el Blade "porque es un listado chico" o "porque
está dentro de una tab": pierde buscador, paginación, orden, estado persistido y el estilo "sm"
global, y queda inconsistente con el resto de la app. Si aparece una `<table>` con `@foreach` en una
vista nueva, es un bug de front a corregir migrándola a DataTable.

## CRUD simple: alta/edición en modal + AJAX (patrón por defecto)

Para cualquier listado nuevo (DataTable) cuyo formulario de alta/edición sea "plano" (unos pocos
campos de texto/select/checkbox, sin secciones dinámicas), el patrón por defecto es **un único
modal reutilizado para crear y editar**, con submit vía AJAX — no páginas `create.blade.php` /
`edit.blade.php` separadas. Referencia: `clientes/index.blade.php` +
`public/js/plugins-init/clientes-modal.init.js` (y su equivalente `articulos-modal.init.js`,
`super-admin-tenants-modal.init.js`).

- **Un solo modal, un solo `<form>`**: el botón "+ Agregar X" del `card-header` resetea el form y
  apunta su `action` al `store` route; el botón "Editar" del dropdown de acciones (ver "Columna
  Acciones" más abajo) rellena el mismo form con los datos de esa fila y cambia `action` al
  `update` route + un input oculto `_method=PUT`. Nunca dos modales (uno alta, uno edición) para
  la misma entidad.
- **Los datos de cada fila para editar viajan en `data-*` del botón**, no se piden por un segundo
  endpoint `GET .../{id}/editar`: el JSON que ya alimenta la DataTable (`index` con
  `wantsJson()`) trae todos los campos que el form necesita repoblar. El `renderAcciones()` del
  `*-datatable.init.js` los vuelca como atributos `data-*` del botón "Editar"; el
  `*-modal.init.js` los lee con `$(this).data('campo')` y llama a `fillForm(...)`.
  - Consecuencia de rutas: si el CRUD sigue este patrón, **no hace falta** registrar las rutas
    `create`/`edit` de `Route::resource` — solo `index, store, update, destroy`
    (`Route::resource('x', XController::class)->only([...])`), y el controller no necesita
    métodos `create()`/`edit()` que devuelvan una vista de formulario.
- **Submit y errores**: el form se envía por `$.ajax` con `dataType: 'json'` y
  `headers: { Accept: 'application/json' }`; el controller responde `response()->json([...])` en
  vez de `redirect()` cuando `$request->wantsJson()`. Un 422 rellena `.invalid-feedback` por campo
  (ver `data-error-for` en el partial `_form.blade.php`); cualquier otro error usa
  `window.showToast('danger', ...)`. Éxito: `modal.hide()` + toast de éxito +
  `table.ajax.reload(null, false)` (recarga la tabla sin resetear la paginación actual).
- **El partial `_form.blade.php` no lleva `value`/`old()` ni `@error`**: como el mismo form sirve
  para alta y edición y se rellena/limpia siempre por JS (`resetForm()`/`fillForm()`), los campos
  se declaran "vacíos" en el Blade; el estado (valores y errores) lo pone el JS en cada apertura
  del modal, no el server-side rendering de una vista de edición.

**Cuándo NO aplica este patrón** (usar la vista full-page en su lugar, ver sección siguiente):
formularios con varias secciones o líneas dinámicas donde un modal quedaría apretado o exigiría
demasiado scroll interno — el caso ya documentado es `facturas` (emisor + cliente + fechas +
líneas + totales). Si un CRUD nuevo empieza simple pero crece hasta ese punto, migrar a full-page
en vez de forzar el modal.

## Select dinámico con CRUD inline (catálogos: unidades, bancos…)

Cuando un campo de un formulario es un **select sobre un catálogo del tenant** que el usuario debe
poder gestionar sin salir de la pantalla (crear/renombrar/eliminar entradas del catálogo al vuelo),
el patrón es un **componente Blade reutilizable** que envuelve un `<select>` potenciado con Select2
+ tres botones inline (➕ nuevo / ✏️ renombrar / 🗑️ eliminar) + un modal compartido de alta/edición.
Referencias: `<x-unidad-select>` (catálogo `unidades`, valor = nombre) y `<x-banco-select>`
(catálogo `bancos`, valor = id). **Para crear uno nuevo, clonar uno de esos dos de punta a punta**;
las piezas son:

- **Componente Blade** (`resources/views/components/<algo>-select.blade.php`): `@props([name, id])`,
  un `<div class="input-group <algo>-control">` con el `<select class="<algo>-select">` + los tres
  botones, y un `<div class="invalid-feedback d-block" data-error-for="{{ $name }}">`. Los assets
  (CSS de select2 + CSS propio del componente + modal `#<algo>Modal` + config JS + JS del
  componente) van dentro de un **`@once`** para emitirse una sola vez aunque haya varias instancias
  en la página. La config JS (`window.<algo>SelectConfig`) inyecta `indexUrl`, `storeUrl`,
  `updateUrlTemplate` (con `'__ID__'`), `destroyUrlTemplate` y `csrf`.
- **JS del componente** (`public/js/components/<algo>-select.js`): inicializa cada `.<algo>-select`
  como Select2 (`dropdownParent` = modal padre si el select está dentro de un modal, si no
  `document.body`), cablea los tres botones, y mantiene **todas las instancias sincronizadas** con
  el catálogo tras cada alta/baja/edición (`reloadAll`). Expone `window.<Algo>Select.get(idOrEl)`
  con `setValue(valor)/clear()/reload()`. Recuerda restaurar `modal-open` en el `hidden.bs.modal`
  del modal del catálogo cuando hay modales apilados (el modal del catálogo se abre **encima** del
  modal del formulario que contiene el select).
- **Controller** (`<Algo>Controller`): `index` (JSON tenant-scoped), `store`, `update` (resolución
  **manual** del modelo, sin binding implícito, para garantizar el `TenantScope` — ver
  `project_tenant_route_binding`), `destroy`. Validación de unicidad **por tenant** con
  `Rule::unique(tabla, campo)->where('tenant_id', tenant()->id)->ignore($id?)`. Rutas:
  `Route::resource('<algo>', ...)->only(['index','store','update','destroy'])`.
- **Borrado con FK `RESTRICT`**: si otras filas referencian la entrada del catálogo por FK (caso
  `cuentas_bancarias.banco_id`), el `destroy` debe **comprobar el uso antes** (`->withTrashed()`
  incluidas las bajas lógicas, que conservan la fila) y responder `422` con mensaje claro en vez de
  dejar reventar la FK. El JS muestra `xhr.responseJSON.message` en un toast.

**Gotcha de CSS (el que más cuesta ver): el CSS del componente está scoped a SU clase wrapper.**
Cada componente necesita su propio archivo CSS (`public/css/<algo>-select.css`) con las reglas de
Select2 **scoped a `.<algo>-control`** (igualar la caja de `.form-control`: `flex: 1 1 auto;
width: 1% !important` en `.select2-container`, y `height/padding/font-size` en
`.select2-selection--single`). Si al crear `<x-banco-select>` reutilizás el CSS de otro componente
(`css/unidad-select.css`, cuyas reglas están limitadas a `.unidad-control`), el `.select2-container`
del nuevo componente **no recibe `flex:1`** y los tres botones se rompen a la línea de abajo en vez
de quedar en línea con el select — sin ningún error en consola. Copiá también el CSS y renombrá el
scope, no solo el Blade/JS.

## Vista full-page de creación con preview en vivo (facturas)

Para formularios "cargados" (muchas secciones + líneas dinámicas), en vez de modal se usa una
página Blade dedicada servida por el propio controller (`facturas/create.blade.php`), con JS
propio para altas/bajas de líneas y recálculo de preview. Patrón reutilizable:

- **La página ES el documento, no un formulario sobre el documento**: en vez de repartir
  emisor/cliente/fechas/líneas/totales en varias cards genéricas, todo vive dentro de una única
  card "papel" (`.factura-documento`, fondo `#fdfcfa` apenas distinto del blanco del resto del
  panel) con membrete (emisor + identificador/estado), campos meta compactos, tabla de líneas y
  bloque de totales con la misma estructura que `facturas/pdf.blade.php` — para que lo que se
  edita se vea como lo que se va a imprimir, no como un CRUD desconectado del resultado.
- **Líneas editables**: `<template>` HTML con una fila (`<tr>`) de inputs sin `name`; el JS clona
  el template al añadir línea y solo asigna los `name="lineas[N][campo]"` en el submit (evita
  reindexar constantemente mientras se editan/eliminan filas). Los inputs de la fila van sin
  borde hasta hover/focus (`border-color: transparent`) para que la tabla se lea como texto de
  factura y no como un grid de formulario.
- **Números tabulares**: `font-variant-numeric: tabular-nums` en las columnas de importes
  (cantidad/precio/dto/IVA/base/totales) para que alineen en columna como en un documento real,
  en vez de "bailar" según la cantidad de dígitos.
- **Preview de documento**: el cálculo en JS (base/cuota/recargo/IRPF) es una aproximación *solo
  UX*, replicando las fórmulas del servicio de backend; el servidor siempre recalcula y es la
  única fuente de verdad de los importes persistidos.
- **Barra de acción inferior sticky** (`.factura-action-bar`, `position: sticky; bottom: 0;`) en
  vez de una card lateral de "Resumen": muestra el total corriendo + Guardar/Cancelar sin competir
  visualmente con el documento ni obligar a scrollear una columna lateral aparte.
- **Panel de catálogo a la izquierda del detalle** (`.factura-catalogo`, columna `col-lg-3` junto
  a la tabla de líneas en `col-lg-9`), no un `<select>` escondido en el header de la tabla: buscador
  (`#catalogo-buscador`, filtra client-side por nombre/SKU sobre el catálogo ya cargado — sin
  round-trip por tecla), filtro por tipo (Todos/Productos/Servicios, botones toggle) y una lista
  (`#catalogo-lista`) de artículos clicables con nombre + tipo/SKU + precio. La opción "+ Línea
  libre (sin artículo)" es siempre el primer ítem de la lista, con el mismo estilo de fila pero
  borde punteado — "agregar artículo" y "agregar línea libre" son la misma acción conceptual
  (elegir un ítem de una lista), no dos controles distintos en dos lugares distintos.
- El catálogo se trae una sola vez (`articulos.index` con `wantsJson`) y se cachea en JS; buscar/
  filtrar re-renderiza la lista contra esa caché, no vuelve a pedir al servidor.
- Ojo con jQuery `$.getJSON(...).done(fn)`: `.done()` no transforma el valor resuelto, así que si
  `fn` reasigna una variable de caché pero el código que consume la promesa espera que el propio
  valor resuelto sea el array, hay que encadenar con `.then(fn)` (que sí transforma) y devolver
  explícitamente el array desde `fn`.

## DataTable: botones "Anterior"/"Siguiente" verticales

El template estiliza `.paginate_button.previous`/`.next` como flechas cuadradas de 24px
(`width` fijo). Como el idioma de la tabla usa texto en vez de flechas ("Anterior"/"Siguiente",
ver `language.paginate` en el init JS), el texto se parte en vertical, letra por letra, dentro de
ese ancho fijo.

Cada vista con DataTable propia debe traer este override en su `@push('styles')` (cambiando el
id de la tabla), no hay una regla global en `style.css` que lo cubra:

```css
#<id-tabla>_wrapper .dataTables_paginate .paginate_button.previous,
#<id-tabla>_wrapper .dataTables_paginate .paginate_button.next {
	width: auto;
	padding: 0 0.75rem;
	white-space: nowrap;
}
```

Ya aplicado en `clientes/index.blade.php`, `articulos/index.blade.php`, `facturas/index.blade.php`
y `logs/index.blade.php` (entre otros). Replicarlo en cualquier listado nuevo con DataTable —
incluida la excepción del feature 021 (`logs-datatable.init.js`), que además de client-side vs
server-side no cambia nada de este bug: el override es de CSS puro, independiente de `serverSide`.

## Columna "Acciones" de los listados (DataTable)

En todo listado (`clientes`, `articulos`, `facturas`, y cualquier nuevo `index` con DataTable), la
columna de acciones es **siempre un único dropdown** (`btn btn-primary light btn-sm
dropdown-toggle` con texto "Acciones"), nunca varios botones sueltos uno al lado del otro en la
celda. Cada acción (ver, editar, eliminar, etc.) es un `<li>` del `dropdown-menu`, con el
destructivo (eliminar) separado por un `<hr class="dropdown-divider">`. Referencia:
`renderAcciones()` en `public/js/plugins-init/{clientes,articulos,facturas}-datatable.init.js`.

Si una acción necesita separarse en dos (ej. "Ver" vs "Editar" en vez de un único link
"Ver/Editar"), se agregan como dos `<li>` distintos dentro del mismo dropdown — no se sale del
patrón dropdown para eso.

## DataTable: inputs nativos de "Mostrar registros" / "Buscar"

Los controles `dataTables_length` (`<select>`) y `dataTables_filter` (`<input>`) los genera el
propio plugin de DataTables **sin** clase `.form-control`/`.form-select` (no vendorizamos
`datatables.bootstrap5`), así que no heredan el override "sm" global de `app-overrides.css` y se
ven con el tamaño nativo del navegador (más grandes, desalineados del resto de inputs "sm" de la
UI). Ya resuelto globalmente en `app-overrides.css` con selectores dedicados
(`.dataTables_wrapper .dataTables_length select`, `.dataTables_wrapper .dataTables_filter
input`) que replican los mismos valores de tamaño que `.form-control`/`.form-select`. Al ser una
regla global, no hace falta repetirla por vista — cualquier tabla nueva (`<table
class="display responsive">` + `.DataTable(...)`) hereda el estilo "sm" automáticamente.

## Nunca imprimir directo un campo `decimal:N` de Eloquent en Blade

Los casts `decimal:4`/`decimal:2` de Eloquent devuelven un **string de punto fijo** ("100.0000",
"4.2000"), no un número. Escribir `{{ $linea->cantidad }}` en una vista Blade muestra literalmente
esos ceros de relleno al usuario — el bug recurrente a evitar (ya pasó en `facturas/pdf.blade.php`,
`ticket-80mm.blade.php` y `compras/show.blade.php`).

**Usar siempre `App\Support\Formato`** en vez de escribir el `number_format`/`rtrim` a mano:

- `Formato::cantidad($valor)` — cantidades/stock: hasta 4 decimales, recorta ceros sobrantes
  (`100.0000` → `100`, `4.5000` → `4,5`).
- `Formato::moneda($valor)` — importes: siempre 2 decimales, nunca se recortan (`121` → `121,00`).
  No incluye el símbolo `€`; agregarlo aparte en la vista.
- `Formato::porcentaje($valor)` — tipo impositivo/recargo: 2 decimales, recorta ceros sobrantes
  (`21.00` → `21`, `9.50` → `9,5`).

Esto **no aplica** cuando el número sale de un endpoint JSON hacia JS/DataTables: ahí el
controller ya castea a `(float)` antes de `response()->json(...)`, así que JS recibe un número
limpio (sin ceros de relleno) y no hace falta `Formato` del lado del cliente — el problema es
exclusivamente de Blade renderizando directo un atributo del modelo.

## Nueva entrada de menú ⇒ nuevo permiso (obligatorio)

Toda sección nueva del sidebar (feature 027) necesita un **permiso propio en el catálogo global**,
nunca reutilizar uno existente "porque ya alcanza" ni dejar la sección sin permiso (salvo que sea
genuinamente personal, como fichar/mi-jornada/perfil). Pasos, en este orden:

1. **Permiso en el catálogo**: añadir la entrada (`clave`, `etiqueta`, `modulo`) en
   `App\Support\CatalogoPermisos::PERMISOS`. La clave sigue el patrón `ver-{seccion}` kebab-case.
2. **Re-correr el seeder**: `php artisan db:seed --class=PermisosSeeder` en cada entorno (deploy) —
   siembra la clave nueva y sincroniza el rol "Administrador" de **cada tenant** con el catálogo
   completo. Es el único punto donde un permiso nuevo se auto-asigna a un rol existente.
3. **Entrada del sidebar** (`resources/views/partials/sidebar.blade.php`): envolver el `<li>` en
   `@can('ver-{seccion}')`; si la entrada es la única del grupo, envolver también el `<li>` padre
   (o el grupo entero en `@canany([...])` si el grupo mezcla varios permisos, ver el bloque
   "Stock"/"Marketing"/"Usuarios" como referencia).
4. **Rutas** (`routes/web.php`): el grupo de rutas de esa sección lleva
   `->middleware('can:ver-{seccion}')`. Nunca dejar una ruta de gestión sin `can:` confiando en que
   el sidebar ya la esconde — el enforcement real es el middleware, ocultar en el menú es solo UX
   (Principio III).
5. **Difusión a roles**: **solo** el rol "Administrador" recibe el permiso nuevo automáticamente
   (paso 2); el resto de roles de cada tenant lo reciben opt-in, editándolos desde `/roles`. Es un
   comportamiento esperado, no un bug — evita que una sección nueva aparezca sin aviso en roles que
   el tenant configuró a propósito con acceso acotado.

Referencia de implementación completa: `specs/027-roles-permisos-tenant/`.

## Calendario (FullCalendar vendorizado)

FullCalendar 5.11.0 está vendorizado en `public/vendor/fullcalendar/` (`main.min.js`,
`main.min.css`, `locales/es.js` — build UMD global, `FullCalendar.Calendar`). Referencia de uso:
`resources/views/calendario/index.blade.php` + `public/js/plugins-init/calendario.init.js`.
Reglas al reutilizarlo:

- **CSS del plugin en `@push('styles')`** (antes de `style.css`, como todo plugin del banco):
  `style.css` ya trae theming para `.app-fullcalendar` — el contenedor debe llevar esa clase.
- **Init directo** (sin el `setTimeout(...,1000)` de la demo del template) con `locale: 'es'` y
  `firstDay: 1`.
- **Eventos por feed JSON con `events: function`** (FullCalendar manda `start`/`end` exclusivo
  por rango visible); el backend calcula todo, el JS solo pinta. Tras una acción que cambie los
  datos: `calendar.refetchEvents()`, nunca recargar la página.
- **Eventos de fondo (`display: 'background'`) no pintan texto**: si el color codifica algo (los
  veredictos del calendario de fichajes), inyectar una etiqueta accesible en `eventDidMount`
  (patrón `.cal-etiqueta` en `app-overrides.css`) — nunca color solo.
- Las clases de veredicto (`cal-veredicto-*`) las emite el backend
  (`VeredictoCumplimiento::clase()`); leyenda y eventos comparten ese único mapa. Estilos y
  variables light/dark en `app-overrides.css` (bloque "Calendario de fichajes").

## Vistas "self-service" mobile-first (fichar, y cualquier pantalla pensada para el móvil del empleado)

Para pantallas que un empleado usa sobre todo desde su móvil (el caso ya resuelto:
`fichajes/index.blade.php`), no alcanza con que la grid sea responsive — hace falta una jerarquía
mobile-first real y una navegación que se sienta de app, no de panel de escritorio encogido.
Patrón de referencia:

- **Hero con una sola idea protagonista**: un elemento grande y en vivo (en fichar, el reloj +
  badge de estado + botón de acción) va arriba, centrado, antes que cualquier dato secundario
  (mapa, precisión GPS). El resto de tarjetas (resumen del día, ubicación) van debajo, en orden de
  relevancia real para el empleado, no en el orden que sea más fácil de maquetar.
- **Botones contextuales, nunca todos a la vez — pero el mecanismo depende de dónde viven**: si una
  acción tiene una máquina de estados (aquí, `cerrada`/`abierta`/`en_pausa` con reglas de transición
  en `RegistroFichajes::validarSecuencia`), nunca hay que dejar todos los botones visibles siempre
  confiando en que el usuario adivine cuál aplica. Dos variantes válidas según el contenedor:
  - **Stack de botones dentro de una card** (el caso desktop de fichar): mostrar/ocultar con
    `d-none` alternado por JS — el contenedor puede crecer o encogerse sin problema.
  - **Barra fija de acciones** (bottom nav mobile, ver punto siguiente): los slots son de posición
    fija, así que en vez de ocultar un botón (lo que "saltaría" el layout) se **deshabilita**
    (`disabled` nativo de `<button>` + una regla `:disabled` que baja opacidad/color) manteniendo
    siempre la misma cantidad de ítems en el mismo lugar.
- **Bottom nav fijo tipo app, solo en mobile** (`d-md-none`, mismo breakpoint 767.98px que ya usa
  el propio template para su sidebar): en fichar dejó de ser navegación (Inicio/Fichar/Mi jornada)
  para convertirse en la **barra de acciones** de la pantalla — 3 slots fijos (entrada / pausa /
  salida), porque en una pantalla "self-service" la acción principal importa más que la navegación
  genérica (que sigue disponible por el menú/hamburger propio del template). El slot central va
  elevado en un círculo (`margin-top` negativo sobre la barra) a modo de FAB, y en fichar ese
  círculo además **cambia de color e ícono según el estado** (azul `--primary` de base, verde
  success cuando la acción pasa a "Reanudar" tras una pausa, gris cuando está deshabilitado) — el
  FAB no es solo decorativo, comunica en qué estado está la acción. El `content-body` necesita
  `padding-bottom` de compensación en ese mismo breakpoint para que el nav no tape el final del
  contenido, y la barra debe sumar `env(safe-area-inset-bottom)` al padding inferior (notch/home
  indicator de iOS). Esta barra es específica de la vista (vive en su propio
  `@push('styles')`/markup), no un componente compartido todavía — si se repite en una tercera
  pantalla "self-service", ahí sí vale la pena extraerla a un partial en vez de copiarla a mano.
- **Ícono por acción, no un solo ícono de marca repetido**: cuando la barra representa acciones
  distintas (entrada/pausa/salida), cada una lleva su propio lordicon semántico (p. ej.
  `wired-outline-983-smart-lock-card-hover-pinch` para entrada, `wired-outline-2185-logout-hover-pinch`
  para salida, el par `wired-outline-3097-pause-circle-hover-pinch` /
  `wired-outline-29-play-pause-circle-hover-pinch` para pausa/reanudar) — no fuerces el ícono "de
  marca" de la sección (el que usa el sidebar, `wired-outline-1846-employee-working-hover-working`)
  en cada botón solo por consistencia; ahí sí sigue siendo el más adecuado para un ítem de
  **navegación** hacia esta sección (ver icono disponibles en `public/icons/lordicon/`).
- **Ícono que cambia según el estado: dos `<lord-icon>` fijos + `d-none`, nunca mutar `colors`/`src`
  en caliente**: para el botón de pausa/reanudar, ambos íconos (pausa y reanudar) están **siempre
  en el DOM**, cada uno ya renderizado con sus `colors` finales, y el JS solo alterna `d-none` entre
  los dos — no se reasigna `src`/`colors` de una única instancia de `<lord-icon>` en runtime (el
  player de Lordicon no está pensado para eso y puede no re-renderizar). Mismo patrón para el
  label de texto del botón (`.text('Pausa' | 'Reanudar')`, no dos botones separados).
- **Excepción de color: ícono sobre fondo sólido sí lleva blanco fijo**: todo lordicon que quede
  **dentro de un botón/elemento con fondo sólido de color** (el FAB del bottom nav, o el propio
  botón `.btn-primary`/`.btn-danger` de la acción principal) lleva `colors="primary:#ffffff,
  secondary:#ffffff"` explícito en vez de la paleta del tenant — si no, el ícono queda del mismo
  tono que el fondo (azul sobre azul) y pierde contraste, igual que el texto blanco del propio
  botón. Es la excepción puntual que ya prevé la sección "Colores de los lordicon" de esta
  misma guía; anotarla en la vista igual que aquí.
- **Centrado de un ícono dentro de un círculo flex**: si el contenedor (`.fab { display:flex;
  align-items:center; justify-content:center }`) envuelve el `<lord-icon>` en un `<span>`
  intermedio (para poder togglear `d-none` entre dos íconos, ver punto anterior), ese `<span>`
  también necesita `display:flex` + `line-height:0` — si queda como `inline` por defecto, el
  espacio de línea propio del texto lo corre un par de píxeles hacia abajo y el ícono se ve
  descentrado dentro del círculo aunque el padre ya sea flex.
- **Reloj/contadores en vivo son solo `textContent`, nunca reflow del DOM**: el tick de segundo a
  segundo (reloj de pared, horas trabajadas hoy) actualiza únicamente el texto de un nodo ya
  existente vía `setInterval`, sin reconstruir markup — el server sigue siendo la única fuente de
  verdad de lo que se persiste; el contador en el cliente es solo percepción de vida en la
  pantalla.
