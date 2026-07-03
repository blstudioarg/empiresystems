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
	// ...ajax de borrado...
});
```

`confirmDelete(mensaje, onConfirm, opciones?)` reescribe el texto del modal y el handler del botón
en cada llamada (soporta múltiples filas de una misma tabla sin acumular handlers), así que no hace
falta instanciar nada por vista.

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

## Notificaciones

Siempre toastr (`window.showToast(type, message)` / flash de sesión), nunca alerts Bootstrap
ad-hoc. Detalle completo en `CLAUDE.md`.

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

Ya aplicado en `clientes/index.blade.php`, `articulos/index.blade.php` y
`facturas/index.blade.php`. Replicarlo en cualquier listado nuevo con DataTable.

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
