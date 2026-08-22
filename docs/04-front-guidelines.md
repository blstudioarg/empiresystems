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

## Input de contraseña: ojo mostrar/ocultar (obligatorio)

**Todo `<input type="password">` nuevo DEBE llevar el ojo mostrar/ocultar.** Sin excepción,
tampoco para campos "secundarios" como confirmación de contraseña, contraseña actual, o secretos
tipo API key/certificado. Markup:

```html
<div class="position-relative">
	<input type="password" class="form-control" name="password" ...>
	<span class="show-pass eye">
		<i class="fa fa-eye-slash"></i>
		<i class="fa fa-eye"></i>
	</span>
</div>
```

El toggle lo resuelve `public/js/plugins-init/password-toggle.init.js` (cargado siempre desde
`layouts/app.blade.php` y `layouts/guest.blade.php`): delegación de eventos sobre `.show-pass`,
busca el primer `input` dentro del `.position-relative` más cercano y alterna su `type`. Funciona
para cualquier cantidad de inputs en la página, incluidos los inyectados dinámicamente (filas de
un modal, formularios AJAX) — **no** usar el `handleshowPass()` de `custom.js` (vendor del
template), que quedó hardcodeado a un único `#dz-password` y solo sirve para el password del
login. El CSS (`.show-pass`, `.eye`, posicionamiento) ya existe global en `style.css` +
`app-overrides.css`, no hace falta escribir nada nuevo ahí.

**Deuda existente (pendiente de retrofit, no bloqueante):** a 2026-07-26 los inputs de
`configuracion/_tab_certificado.blade.php` (`certificado_password`), `_tab_email.blade.php`
(`smtp_password`), `_tab_ia.blade.php` (`api_key`) y `profile/show.blade.php`
(`contrasena_actual`, `contrasena_actual_email`, `password`, `password_confirmation`) todavía no
tienen el ojo. Cualquier feature que toque esas vistas debería agregarlo de paso.

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

## Fondo de la app y elevación de las superficies

El template deja el `body` en `#FCFCFC`, casi blanco: como las `.card` son `#fff`, no se despegan
del fondo y su sombra (`0 5px 15px rgba(17,17,17,.05)`) directamente no se lee. En
`app-overrides.css` el fondo pasa a **`#f3f5f8`** (gris azulado) y se sube la elevación de todo lo
que flota encima: `.card`, `.deznav` (sidebar) y `.header`.

Dos reglas al tocar esto:

- **Las sombras van en dos capas**, no en una: una corta y cerrada para el contacto
  (`0 1px 2px rgba(16,24,40,.04)`) y otra larga y difusa para la elevación
  (`0 8px 24px -6px rgba(16,24,40,.10)`). Una sola capa grande se ve como una mancha gris; dos
  capas se leen como profundidad. La del sidebar además se proyecta hacia la derecha
  (`4px 0 24px -6px`), porque se apoya sobre el contenido, no sobre el usuario.
- **Todo va bajo `body:not([data-theme-version="dark"])`**, para no pisar el theming oscuro del
  template (el atributo lo escribe `dzSettings` en `<body>`). Aunque el toggle de dark mode esté
  oculto hoy, el mecanismo sigue vivo y no hay que romperlo por el camino.

Si se agrega una superficie nueva que flote sobre el fondo (un panel fijo, una barra lateral
secundaria), darle la misma escala de sombra en vez de inventar una: la coherencia de la
elevación es lo que hace que la interfaz se lea como un plano ordenado.

## Badges de estado: `.badge.light.badge-*`

Para marcar estados en un listado se usa la familia `badge light badge-<color>` del template. Todas
siguen el mismo patrón: **fondo pálido teñido + texto en el color saturado** (success `#cefff6`
sobre `#01BD9B`, danger `#fbe7e7` sobre `#E55555`, etc.).

`secondary` venía roto en `style.css`: fondo gris medio (`#868999`) con texto casi negro
(`#1F2025`). Turbio e ilegible dentro de una celda de DataTable. Corregido en `app-overrides.css`
a `#ECEDF0` sobre `#33363F` (contraste ~10:1), alineado con el resto de la familia. Afecta a
"Simple" (facturas simplificadas/POS), "Inactivo" (horarios) y "Baja" (miembros del equipo).

**Regla general: nunca un fondo gris medio con texto oscuro.** Si hace falta un badge de estado
nuevo, usar una de las variantes existentes en vez de inventar colores; y si se inventa, comprobar
el contraste antes (mínimo 4.5:1 para texto normal). Vale lo mismo que para la cabecera de las
DataTables: el contraste no se supone, se calcula.

## Cabecera de las DataTables en color de marca

El `<thead>` de los listados va con fondo `var(--primary)` y texto `var(--primary-contraste)`.
Dos decisiones que hay que respetar:

- **Solo se pinta `table.dataTable`, no `.table` a secas.** Las tablas embebidas en documentos
  (líneas de factura, presupuestos, ticket de POS) tienen que seguir leyéndose como papel; una
  banda de color de marca ahí compite con el documento y lo ensucia. Si una vista nueva necesita
  la cabecera de color, que sea una DataTable de verdad (que es lo que manda la regla de
  "Listados: SIEMPRE DataTable").
- **El color del texto nunca se hardcodea a blanco.** El primario lo elige cada tenant desde
  Configuración → Apariencia, así que puede ser un amarillo, un lima o un celeste claro, y ahí el
  blanco queda ilegible. `AparienciaTenant::contraste()` calcula la luminancia relativa (WCAG,
  sRGB linealizado, umbral 0.179) y emite `--primary-contraste` con `#FFFFFF` o `#1F2937` según
  corresponda. La CSS lo consume con fallback (`var(--primary-contraste, #FFFFFF)`), que cubre el
  primario por defecto del template (`#1D69D6`). Cubierto por
  `ConfiguracionAparienciaTest::test_primary_contraste_se_adapta_a_la_luminancia_del_color_primario`.

Las flechas de orden son iconos de fuente y heredan `currentColor`, así que salen del color de
contraste solas; lo único que se ajusta es la opacidad (pensada originalmente para gris sobre
blanco): 0.65 en reposo, 1 en la columna ordenada y en hover.

**El selector tiene que ser `table.dataTable > thead > tr > th`, con los `>`.** `style.css`
declara esa misma forma (0,1,4) con `background-color: transparent`; una regla
`table.dataTable thead th` (0,1,3) pierde por especificidad aunque `app-overrides.css` cargue
después. El síntoma es engañoso: el `color` sí se aplica (esa propiedad la gana por otro lado) y
el `background-color` no, o sea **letra blanca sobre card blanca — la cabecera desaparece**. Pasó
en la primera versión de esta regla. La solución es igualar la forma del selector, nunca meter
`!important` (que es lo que empieza la guerra de `!important` contra `!important` que ya se
documentó más arriba para `.icon-box`).

**Padding vertical de la cabecera: `0.5rem`, no el `1rem` del template.** `style.css` da
`padding: 1rem 0.9375rem` a `table.dataTable > thead > tr > th`; con la banda de color eso deja
demasiado aire y engorda todos los listados. `app-overrides.css` baja solo `padding-top` y
`padding-bottom` a `0.5rem` y no toca el horizontal (el izquierdo lo fija `style.css` con
`!important`, y el derecho reserva sitio para la flecha de orden).

**Si hace falta otro elemento con fondo primario y texto encima, usar `--primary-contraste`**, no
`#fff`. Es la única forma de que la marca del tenant no rompa la legibilidad.

## Placa de ícono (`.icono-placa`)

Contenedor reutilizable para un `<x-lordicon>`: cuadrado redondeado (4rem, radio 1rem) con el
tinte del color de marca (`--rgba-primary-1`). Definido en `app-overrides.css`.

Se usa cuando el ícono tiene que anclar un bloque de información a su izquierda —cabecera del
perfil de cliente, cards de métricas— en vez de flotar suelto. Para el ícono suelto a la derecha
de una métrica sigue valiendo la regla de la sección siguiente (contenedor sin clases).

**No usar `.icon-box bg-primary-light` del template para esto**: trae 2.5rem fijos con
`!important` y recorta el `<lord-icon>`. Es exactamente el error ya documentado abajo.

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

### Rail de acento y conteo animado (automáticos, no hay que hacer nada)

El tratamiento visual de estas cards vive en `public/css/app-overrides.css` (bloque "Cards
informativas de métricas") y se engancha con `:has()` a **cualquier `.card` que contenga un
elemento `[data-metric]`**. No hay que agregar clases a la vista: basta con seguir el markup de
arriba y poner `data-metric="<clave>"` en el `<h3>` del valor.

Lo que aporta:

- **Rail izquierdo de 5px** con el color del acento, comprimido a `scaleX(.6)` en reposo y a
  `scaleX(1)` en hover (se anima solo `transform`, nunca `width`).
- **Lift de la card** (`translateY(-2px)` + sombra) y un tinte lateral del mismo acento, ambos
  detrás de `@media (hover: hover) and (pointer: fine)` para que el táctil no los dispare al tocar.
- **Entrada escalonada** de la fila (`metricCardIn`, 60ms de delay entre columnas vía
  `.row > *:nth-child(n)`).
- Todo anulado bajo `prefers-reduced-motion: reduce`.

**El color del acento lo decide la clase de color del propio número**, no una clase de la card:
`text-success` → verde, `text-danger` → rojo, sin clase → primario del tenant (`--primary`, así
que respeta la apariencia configurada). Es decir, el rail codifica el estado del dato en vez de
decorar; si una métrica nueva tiene que verse "en alerta", se le pone `text-danger` al `<h3>` y el
rail acompaña solo.

El conteo animado del número lo hace `public/js/metric-cards.js` (cargado siempre desde
`layouts/app.blade.php`). Usa un `MutationObserver` sobre cada `[data-metric]`, así que funciona
igual para valores renderizados en servidor (animan 0 → valor al cargar) y para los que escribe un
`*.init.js` con `$('[data-metric="x"]').text(n)` tras el ajax de totales — **no hay que tocar los
`.init.js`**. Solo anima enteros (con o sin separador de miles); importes, porcentajes y `MB` se
escriben directo, porque animar un formato así muestra cifras intermedias sin sentido.

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

## Acción frecuente y consecuente pero no destructiva: toast con "Deshacer", no modal

El modal de confirmación (sección anterior) es para lo irreversible-destructivo (borrado,
emitir). Hay un caso distinto: una acción que el usuario hace **muchas veces por sesión/día**,
que en el camino feliz es normal y esperable, pero que si se toca por error es molesta de
corregir (no hay "deshacer" real en el modelo de datos, o corregirla implica pasar por soporte/
admin). Meterle un modal de confirmación a ese botón le agrega fricción al 100% de los usos
correctos para prevenir un error que pasa pocas veces — peor trade-off que un modal en una acción
rara (borrar un cliente).

Patrón: dejar el clic tal cual (sin confirmar nada antes), pero la respuesta de éxito muestra un
toast con un botón "Deshacer" embebido en el propio mensaje (unos segundos de ventana, no un
`window.confirm` previo). Si el usuario toca "Deshacer", **no se borra/edita el registro ya
creado** — se ejecuta la acción normal que lo revierte (otro evento nuevo, auditable), exactamente
igual que si el usuario la hubiera disparado a mano. Referencia: `fichaje-app.init.js` (fichar
salida es la acción de mayor consecuencia de la pantalla de fichar — bottom nav mobile, "Salida"
queda a un dedo del FAB de pausa — y "deshacerla" es re-fichar entrada, no un endpoint de borrado
nuevo, para no romper el ledger append-only de `RegistroFichajes`).

```js
var $toast = toastr.success(
	'Fichaje de salida registrado. <button type="button" class="btn btn-sm btn-light ms-2 fichaje-deshacer-salida">Deshacer</button>',
	null,
	{ timeOut: 6000, tapToDismiss: false } // tapToDismiss:false para que solo lo cierre el botón/timeout
);

$toast.on('click', '.fichaje-deshacer-salida', function (e) {
	e.stopPropagation();
	var $btn = $(this);

	window.withButtonLoading($btn, function () { return enviarFichaje('entrada'); })
		.done(function (response) { /* aplicar el nuevo estado */ })
		.always(function () { toastr.clear($toast); });
});
```

Notas: el botón embebido en el toast sigue el mismo patrón de loading que cualquier otro botón
AJAX (`withButtonLoading`, ver sección siguiente) — no es una excepción. `toastr.success/error/...`
devuelve el elemento del toast (`escapeHtml: false` por defecto en la config global, así que HTML
inline en el mensaje se renderiza tal cual). No usar este patrón para lo genuinamente destructivo
(seguir usando `confirmDelete`) ni para acciones poco frecuentes (ahí un modal previo no molesta).

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

**Excepción documentada — bottom nav de fichaje (mobile)**: los 3 botones de
`#fichaje-bottom-nav` (Entrada/Pausa/Salida en `fichajes/index.blade.php`) NO usan
`withButtonLoading` (`fichaje-app.init.js`, handler de click delegado). El spinner que antepone
`setButtonLoading` rompe el layout circular del FAB central de Pausa y, en la card de escritorio,
tapaba el ícono; a pedido explícito del usuario se sacó para esos 3 botones puntuales. El feedback
de que el fichaje se registró sigue existiendo (toast + `aplicarEstado` recoloreando/deshabilitando
según el nuevo estado apenas llega la respuesta) — lo que no hay es el disabled+spinner mientras la
petición está en vuelo. No copiar este patrón a otros botones sin el mismo motivo (ícono FAB
circular); para cualquier botón AJAX nuevo seguir usando `withButtonLoading` por defecto.

**`confirmDelete` ya usa este mecanismo internamente** sobre su propio botón de confirmación (ver
sección anterior) — un botón de fila que dispara `window.confirmDelete(...)` no necesita loading
propio, alcanza con que su `onConfirm` haga `return $.ajax(...)`.

**No aplica** a: selects que cargan opciones dependientes (usar el patrón inline ya existente,
`$select.prop('disabled', true).html('<option>Cargando...</option>')`, ver `loadLocalidades` en
`clientes-modal.init.js`/`proveedores-modal.init.js`/`super-admin-tenants-modal.init.js`); guardado
automático on-change sin botón explícito (`configuracion-apariencia.init.js`); la barra de progreso
propia de DataTables (`processing: true`, ya maneja su propio feedback).

## CSRF en peticiones AJAX sin formulario

**No hay `$.ajaxSetup` global en el proyecto**: cada petición AJAX se hace cargo de su propio token
CSRF. Hay dos formas válidas, y hay que elegir según de dónde salgan los datos:

- **Petición que envía un form serializado** (`data: $form.serialize()`): el token ya viaja en el
  body porque el `<form>` lleva `@csrf`. No hace falta nada más. Es el caso de casi todos los
  `*-modal.init.js`, `calendario.init.js`, `stock-ajuste.init.js`, etc.
- **Petición sin body de formulario** — acciones de botón tipo "confirmar"/"anular"/"cambiar
  estado", que hacen `POST`/`PUT`/`PATCH`/`DELETE` a una URL sin serializar ningún form: **hay que
  mandar el header explícitamente**, leyéndolo del `<meta name="csrf-token">` que `layouts/app.blade.php`
  ya renderiza siempre:

  ```js
  $.ajax({
  	url: url,
  	method: 'POST',
  	dataType: 'json',
  	headers: {
  		Accept: 'application/json',
  		'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
  	},
  });
  ```

Olvidar el header en el segundo caso da un **419 con `{"message": "CSRF token mismatch."}`**, que
al pasar por el `.fail()` se muestra como un toast de error con ese texto literal — parece un
problema de sesión caducada pero no lo es. Si aparece ese toast en una acción de botón, sospechar
primero de esto (ocurrió en `compras-show.init.js`: el handler de estado B2B sí mandaba el header y
la función compartida de Confirmar/Anular no).

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

**Excepción documentada (feature 036, tab Configuración → Menú)**: el editor del menú lateral
personalizable (`configuracion/_tab_menu.blade.php`) **no** usa DataTable. La regla existe para
listados de **registros del negocio** (clientes, facturas, cuentas bancarias…) donde buscador/
paginación/orden por columna aportan valor; el editor de menú es una jerarquía **fija y pequeña**
de 36 elementos de producto (no registros del tenant), y lo que hay que editar es justamente esa
jerarquía (grupo + entradas), algo que DataTables no soporta arrastrar sin la extensión
`RowReorder` (que además solo reordena filas planas de un mismo nivel, no una jerarquía). Patrón
reutilizable para cualquier lista jerárquica arrastrable futura:

- **jQuery UI `sortable`** (vendorizado en `public/vendor/jqueryui/`, cargado solo desde la vista
  que lo usa vía `@push('styles')`/`@push('scripts')`, nunca global) — no `SortableJS` ni otra
  dependencia nueva mientras el banco del template ya traiga una solución.
- **Confinamiento por nivel por construcción, no por validación**: cada `<ul>` de un nivel
  (entradas de un grupo) es un `sortable` **independiente**, y la lista de niveles superiores
  (grupos) es otro `sortable` aparte — **sin `connectWith` entre ellos**. Al no estar conectados,
  jQuery UI devuelve por sí solo cualquier fila soltada fuera de su lista de origen; el
  confinamiento sale gratis, no hace falta comprobarlo en el submit.
- **Asa de arrastre obligatoria** (`handle: '.algo-handle'`): la fila no es arrastrable por toda su
  superficie, porque normalmente lleva un input de texto editable (renombrar) — sin asa, tocar el
  input en una tablet iniciaría un arrastre en vez de enfocar el campo. Sumar una `distance` mínima
  en las opciones del `sortable` para no confundir arrastre con scroll táctil.
- **Grupos con hijos: contraídos por defecto**, con un botón de flecha (`.algo-toggle`, delegado
  sobre el contenedor para sobrevivir a un repintado por JS) que alterna una clase `colapsado` en
  el `<li>` del grupo — el CSS oculta el `<ul>` de hijos con esa clase (`display: none`), nunca con
  JS. Reordenar los grupos entre sí no exige desplegar ninguno; solo se despliega el grupo cuyas
  entradas se van a reordenar. Sin esto, una jerarquía con muchas entradas de segundo nivel
  siempre desplegadas obliga a scrollear una lista larguísima para mover algo del nivel superior.
- **Guardado por botón único, nunca por evento de arrastre**: el arrastre solo mueve el DOM; el
  `orden` (y cualquier otro campo) se lee recién en el submit, iterando los `<li>` en su posición
  actual (`$lista.find('> li').map(...).get()`). Nada se persiste hasta pulsar "Guardar".
- **El servidor no confía en el orden recibido**: reconstruye la lista final a partir de su propio
  catálogo/fuente de verdad, coloca al final los elementos ausentes de la petición y descarta
  claves desconocidas. Ver `App\Support\MenuTenant` (feature 036) como referencia completa del
  patrón catálogo + personalización + fusión server-side.

Referencia completa: `resources/views/configuracion/_tab_menu.blade.php` +
`public/js/plugins-init/configuracion-menu.init.js` + `public/css/configuracion-menu.css`.

**Excepción documentada (feature 037, widgets de resumen de dashboard/panel)**: los bloques de un
**dashboard u home de panel** que muestran un top-N fijo derivado de agregados del servidor —
"Últimas facturas emitidas"/"Top 5 clientes" en `partials/dashboard-contenido.blade.php`, o
"Últimos tenants creados"/"Ranking por tamaño"/"Requieren atención" en
`super_admin/panel/index.blade.php`— usan `<table class="table table-borderless mb-0">` + `@foreach`,
no DataTable. La regla de DataTable existe para **listados de un módulo** (la vista `index` de un
recurso, donde el usuario busca/pagina/ordena su propio conjunto completo de registros); un widget
de dashboard no es eso: es una foto de tamaño fijo (5, 8, top-N) calculada por el servidor en cada
carga, sin paginación ni búsqueda propia, con un enlace de salida al listado real del módulo si hace
falta operar. Aplicar DataTable ahí sería forzar buscador/paginación sobre una lista que nunca va a
tener más de N filas por diseño. Si un widget de este tipo empieza a necesitar buscar/paginar/
ordenar por columna, es señal de que dejó de ser un resumen y pasó a ser un listado — en ese momento
migra a DataTable con su propia vista `index`, no antes.

## "Ver" un documento (factura, presupuesto, albarán, ticket): SIEMPRE en modal, nunca otra pestaña

La acción "Ver" de cualquier documento con PDF (factura, presupuesto, albarán, ticket POS) abre su
vista previa **en un modal con un `<iframe>` dentro de la app**. Nunca `target="_blank"`, nunca una
descarga directa, nunca navegar fuera de la pantalla actual: el usuario está trabajando sobre un
listado o un perfil y sacarlo a otra pestaña le rompe el contexto (pierde filtros, scroll, la tab
en la que estaba) y le deja pestañas huérfanas acumulándose.

Patrón (referencia: `facturas/index.blade.php` + `facturas-datatable.init.js`,
`presupuestos/index.blade.php`, y `clientes/show.blade.php` para el caso "perfil"):

```blade
<div class="modal fade" id="facturaPdfModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Vista previa de la factura</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body p-0" style="height: 80vh;">
				<iframe id="facturaPdfFrame" src="" style="width: 100%; height: 100%; border: 0;"></iframe>
			</div>
		</div>
	</div>
</div>
```

```js
$tabla.on('click', '.btn-ver-factura', function () {
	$('#facturaPdfFrame').attr('src', $(this).data('pdf-url'));
	bootstrap.Modal.getOrCreateInstance($('#facturaPdfModal')[0]).show();
});

// Vaciar el src al cerrar: si no, el PDF sigue cargado en memoria y al reabrir el modal se ve
// un instante el documento anterior antes de cargar el nuevo.
$('#facturaPdfModal').on('hidden.bs.modal', function () {
	$('#facturaPdfFrame').attr('src', '');
});
```

Puntos que no se negocian: `modal-dialog-centered` (ver sección de modales), `modal-xl` +
`height: 80vh` en el body para que el documento se lea sin scroll interno absurdo, `p-0` en el
`modal-body` (el iframe ocupa todo), y el reset del `src` en `hidden.bs.modal`.

**El enlace del "Ver" apunta a la ruta `*.pdf`, no a `*.edit`**: los `edit()` de facturas,
presupuestos y albaranes hacen `abort(403)` cuando el documento ya no es editable (emitido,
aceptado, entregado), así que usar `edit` como "ver" rompe justo en los documentos que más se
consultan. Para albaranes existe además `albaranes.show` (vista propia, no PDF), que sí es una
navegación legítima.

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

## Sub-listado editable embebido en un modal de edición (usuarios de un tenant)

Cuando el modal de edición de una entidad necesita mostrar/editar una **colección de otra entidad
relacionada** (cantidad variable, no son campos fijos de la entidad principal), no entra en el
patrón "CRUD simple" de arriba (que asume que todos los campos viajan en `data-*` del botón
"Editar"): una lista de longitud variable no se puede precargar como atributos de un botón.
Referencia: sección "Usuarios del tenant" en `super_admin/tenants/_form.blade.php` +
`super-admin-tenants-modal.init.js`.

- **Segundo endpoint `GET .../{id}/<subrecurso>`** (aparte, no rompe la regla de arriba): al abrir
  el modal en modo edición, el JS pide ese endpoint y renderiza las filas dinámicamente en un
  `<tbody>` dentro de una sección con clase propia (p. ej. `.usuarios-tenant-field`) que arranca
  `d-none` y se muestra recién en `fillForm()`, igual que `.admin-field` se oculta en modo edición.
- **Guardado por fila, no por el submit del form principal**: cada fila trae sus propios inputs +
  un botón "Guardar" que dispara su propio `$.ajax` hacia una URL de `update` **por fila** (que
  viaja en el JSON de esa fila, patrón `update_url` igual que en cualquier DataTable), con
  `window.withButtonLoading` en ese botón puntual y errores 422 puestos en `.invalid-feedback`
  junto al input de esa fila — no se usa `showErrors()` del form padre porque los `name` de los
  inputs se repiten por fila (no son únicos en el DOM).
- **Campos sensibles que no se pueden precargar** (p. ej. una contraseña hasheada): el input
  correspondiente arranca siempre vacío con un placeholder tipo "Dejar en blanco para no cambiar";
  el backend solo toca ese campo si llega no-vacío.

## Select dinámico con CRUD inline (catálogos: unidades, bancos…)

Cuando un campo de un formulario es un **select sobre un catálogo del tenant** que el usuario debe
poder gestionar sin salir de la pantalla (crear/renombrar/eliminar entradas del catálogo al vuelo),
el patrón es un **componente Blade reutilizable** que envuelve un `<select>` potenciado con Select2
+ tres botones inline (➕ nuevo / ✏️ renombrar / 🗑️ eliminar) + un modal compartido de alta/edición.
Referencias: `<x-unidad-select>` (catálogo `unidades`, valor = nombre), `<x-banco-select>`
(catálogo `bancos`, valor = id) y `<x-categoria-select>` (catálogo `categorias_articulo`, valor =
id, FK `articulos.categoria_id`). **Para crear uno nuevo, clonar uno de esos de punta a punta**
(por nombre → clonar unidad; por id/FK → clonar banco o categoría); las piezas son:

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

## Provincia y localidad: SIEMPRE selects encadenados, nunca inputs de texto

**Cualquier formulario que pida provincia y/o ciudad (localidad) usa el componente
`<x-provincia-localidad>`. Nunca un `<input type="text">`.** El catálogo (`provincias` +
`localidades`, cargado por `ProvinciaLocalidadSeeder` desde los CSV del INE) es la única fuente de
verdad: los inputs libres producen "Madríd", "MADRID", "Madrid (Madrid)" y hacen inservible
cualquier agrupación por zona geográfica en informes.

Uso (el componente renderiza **las dos columnas**, para meterlo dentro de un `.row` existente):

```blade
<x-provincia-localidad
    name-provincia="cliente_provincia"
    name-ciudad="cliente_ciudad"
    :valor-provincia="old('cliente_provincia', $factura?->cliente_provincia)"
    :valor-ciudad="old('cliente_ciudad', $factura?->cliente_ciudad)"
    col="col-md-2 factura-meta-campo"
    label-class="form-label d-block" />
```

Piezas y detalles que importan:

- **Se persiste el NOMBRE, no el id** (`clientes.provincia/ciudad`, `proveedores.*`, `tenants.*`,
  y los campos congelados `facturas.cliente_provincia/cliente_ciudad`). El id de provincia viaja
  solo en `data-provincia-id` para poder pedir sus localidades.
- **JS**: `public/js/components/provincia-localidad.js`, multi-instancia y auto-inicializado sobre
  cada `select[data-provincia-select]` con su `data-localidad-target`. Expone
  `window.ProvinciaLocalidad.get(idDeCualquieraDeLosDosSelects)` con `setValues(provincia, ciudad)`
  y `clear()`. **Para precargar por JS hay que usar `setValues()`**: un `.val()` directo sobre el
  select de ciudad no funciona porque sus `<option>` se cargan por AJAX (`GET /localidades`) — es
  exactamente lo que hace `facturas-form.js` al elegir cliente.
- **Datos heredados**: si el valor guardado no está en el catálogo (importación de Excel, registro
  antiguo), tanto el Blade como el JS lo añaden como `<option>` extra seleccionada, para no
  borrarlo en silencio al guardar.
- **Ruta `localidades.index`** vive en el grupo `tenant.context + auth` **sin permiso de módulo**:
  es un catálogo geográfico público, no dato de tenant, y lo consumen formularios de módulos
  distintos (clientes, proveedores, facturas, tenants del super admin). No volver a encerrarla
  bajo `can:ver-clientes`.
- Formularios anteriores al componente (`clientes/_form`, `proveedores/_form`,
  `super_admin/tenants/_form`) llevan el mismo par de selects escrito a mano, con la carga AJAX
  duplicada en su `*-modal.init.js`. **Ya son selects, así que no hay bug abierto ahí**, pero
  cualquier formulario nuevo —y cualquier retoque grande de esos tres— debe usar el componente.

## Catálogo del POS (TPV): filtros de categoría y badge de stock

El grid del POS (`pos/create.blade.php`, `.pos-grid` de `.pos-articulo`) es **tablet-first, pensado
para el dedo**. Dos convenciones:

- **Filtros de categoría = botones GRANDES, no pills.** Sobre el grid va `.pos-filtros` (fila con
  `overflow-x: auto` para scrollear en horizontal si hay muchas categorías) con `.pos-filtro`
  (min-height 52px, borde 1.5px, radio 1rem, `:active { scale .96 }`, estado `.active` con el
  primario del tenant). Primer botón siempre **"Todos"**; el resto una categoría cada uno con su
  `<span class="badge-count">` (conteo). El controller (`PosController@create`) solo pasa las
  categorías **que tienen al menos un producto** (agrupa los `articulos` cargados por `categoria_id`,
  no una query aparte). Cada `.pos-articulo` lleva `data-categoria="{{ $articulo->categoria_id }}"`.
  El filtrado es **client-side y se combina con la búsqueda** (`pos-form.js` → `aplicarFiltros()`
  cruza texto + `categoriaActiva`; ocultar con la clase `.filtrado-oculto`, no `style.display`, para
  no pisar el filtro con la búsqueda ni al revés).
- **Badge de stock en el card.** Para productos con `gestion_stock`: si `stock_actual <= 0` →
  `<span class="pos-art-badge sin-stock">Sin stock</span>` + clase `.agotado` en el card (lo atenúa
  y desactiva el hover); si `stock_actual <= stock_minimo` → badge ámbar `.bajo-stock` ("Quedan N").
  El badge es `position: absolute` en la esquina superior del `.pos-articulo`. Se sigue pudiendo
  añadir al ticket (el POS permite stock negativo, misma decisión que la emisión de facturas).

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

## Botón "Exportar"/"Importar" en la cabecera de un DataTable (feature 031)

Los listados con exportación a Excel (`clientes`, `articulos`, `facturas`, `albaranes`, `leads`)
llevan un botón `<button id="btn-exportar-{modulo}" class="btn btn-outline-secondary">Exportar</button>`
en la cabecera de la card, junto al resto de acciones. Los que además admiten importación
(`clientes`, `articulos`, `proveedores`) llevan también
`<button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importarModal">Importar</button>`.
Ambos van **antes** del botón primario "+ Agregar …" (acción secundaria, no la principal de la
pantalla).

**El botón "Exportar" nunca hace un `GET`/descarga directa**: exportar respeta los filtros activos
del DataTable (búsqueda, columnas visibles), y estos cinco listados no tienen filtrado server-side
(cargan el dataset completo y DataTables filtra en el navegador). El patrón, implementado una sola
vez en `public/js/plugins-init/excel-export.init.js` y reutilizado por cada vista:

```js
window.initExportacionExcel({
	boton: '#btn-exportar-clientes',
	url: @json(route('clientes.exportar', ['modulo' => 'clientes'])),
	table: function () { return $('#clientes-table').DataTable(); },
});
```

`initExportacionExcel` recoge los IDs de las filas visibles con
`table.rows({ search: 'applied' }).data()`, hace `POST` con `fetch()` (no se puede navegar a un
`GET` con miles de IDs en la query string) y dispara la descarga del blob de respuesta manualmente
(`URL.createObjectURL` + `<a download>`). Si no hay filas visibles, muestra un toast de aviso en
vez de mandar la petición.

**Variante para tablas server-side** (p. ej. `logs`, feature "logs de actividad"): cuando el
DataTable pagina en servidor (dataset potencialmente grande, no cargado entero en el navegador),
la opción `table` no sirve — el navegador solo tiene la página actual. `initExportacionExcel`
acepta en su lugar `ids: function ()`, que debe devolver (o resolver, vía `Promise`) el array de
IDs a exportar; internamente se le pide al mismo endpoint del listado (el `ajax.url` de la propia
tabla) todas las filas que matchean la búsqueda activa, con `length` alto y sin paginar, y de ahí
se extraen los `id`. Exactamente una de las dos opciones (`table` o `ids`), nunca ambas. Ver
`resources/views/logs/index.blade.php` y `LogActividadController::index()` (que expone `id` en
cada fila del JSON justamente para esto).

**"Importar" abre un modal, nunca navega a otra página** (consistente con "CRUD simple: alta/edición
en modal + AJAX" más abajo, aunque acá no hay alta/edición sino un flujo de tres pasos: subir →
previsualizar → confirmar). El modal es un partial único y reutilizable,
`resources/views/excel/_importar_modal.blade.php`, incluido una vez por vista con
`@include('excel._importar_modal', ['modulo' => 'clientes', 'etiqueta' => 'clientes'])`, y
manejado por `public/js/plugins-init/excel-importar-modal.init.js`:

```js
window.initImportacionModal({
	previsualizarUrl: @json(route('clientes.importar.previsualizar', ['modulo' => 'clientes'])),
	confirmarUrl: @json(route('clientes.importar.confirmar', ['modulo' => 'clientes'])),
	tabla: '#clientes-table',
});
```

Los tres pasos viven en el mismo modal (`#importar-paso-subir` / `#importar-paso-previsualizacion`
/ `#importar-paso-resultado`, alternados con `d-none`), sin volver a pedir el fichero: el `token`
que devuelve la previsualización viaja al confirmar. Para que esto funcione,
`ImportacionController@previsualizar` **y** `@confirmar` responden JSON cuando
`$request->wantsJson()` (el flujo con redirect + `session('resumen_importacion')` sigue existiendo
como fallback de `GET /importar/{modulo}`, pero ya no lo enlaza ningún botón de la UI). Al confirmar
con éxito, recarga la tabla con `table.ajax.reload(null, false)` — mismo patrón que cualquier alta
por modal.

Cada módulo exportable/importable necesita su propia ruta literal (`/exportar/clientes`,
`/importar/articulos/previsualizar`, …) dentro de su grupo `can:ver-{modulo}` — **nunca**
`Route::post('/exportar/{modulo}')` con `where()`/`whereIn()` repetido por grupo: Laravel no
permite registrar dos rutas con el mismo método+URI literal aunque tengan constraints distintos (la
segunda pisa a la primera en el `RouteCollection`, silenciosamente, sin error). El patrón correcto
para inyectar igualmente el nombre del módulo en el controller es `->defaults('modulo', 'clientes')`
sobre la ruta literal. Ver `routes/web.php` y `specs/031-import-export-excel/` para el patrón
completo.

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

## Ayuda contextual (mini-tutoriales por vista/proceso)

Documentación in-app para el usuario final (no para devs). **Un único punto de entrada global**:
el botón **"Ayuda de esta pantalla"** del `help-desk` en el pie del sidebar
(`partials/sidebar.blade.php`), que abre **un solo modal global** cuyo contenido cambia según la
vista actual. No se pone un botón `?` por vista ni por modal — el usuario aprende un solo lugar
donde está la ayuda y siempre es contextual.

Piezas del mecanismo:

- **Modal global**: `partials/ayuda-modal.blade.php`, incluido **una sola vez** en
  `layouts/app.blade.php` (id `#ayudaContextualModal`, clase `.ayuda-modal`). Trae el chrome
  (cabecera con lordicon + eyebrow "Guía rápida" + título, botón de cierre, cuerpo scrolleable);
  el contenido lo inyecta la vista.
- **Contenido por vista**: cada vista que tenga ayuda declara dos secciones Blade —
  `@section('ayuda-titulo', 'Clientes')` y un `@section('ayuda') @include('ayuda.<slug>') @endsection`
  al final del archivo (fuera de `@section('content')`). El modal las lee con
  `@yield('ayuda-titulo')` y `@hasSection('ayuda')`/`@yield('ayuda')`. Funciona porque con
  `@extends` las `@section` del hijo se registran antes de que el layout renderice sus `@yield`.
- **Contenido modular**: el texto vive en su propio archivo `resources/views/ayuda/<slug>.blade.php`
  — nunca inline en la vista. Un archivo por pantalla/proceso.
- **Fallback**: si la vista actual no define `@section('ayuda')`, el modal muestra un estado vacío
  ("Todavía no hay una guía para esta pantalla"); el botón del sidebar es siempre visible.

**Convención de markup del contenido de ayuda** (para que el CSS lo estilice sin clases extra):
un `<p>` de intro, un `<ol>` de pasos (los números salen en círculos con el color del tenant, vía
`counter`), `<strong>` para los términos, y un `<p class="ayuda-nota">` final para el error común a
evitar (se renderiza como callout con ícono de info). Estilos en `app-overrides.css`, bloque
"Ayuda contextual"; light/dark cubiertos.

**Qué tan largo debe ser**: la prueba práctica es que casi no debería hacer falta scrollear (el
cuerpo tiene `max-height` con scroll como red de seguridad, no como norma). Responder solo tres
cosas — qué hace esta pantalla, cómo se usa (pasos), qué error común evitar — nunca contexto de
negocio/normativa (eso vive en `docs/`). Referencia completa: `resources/views/ayuda/clientes.blade.php`
+ las secciones `@section('ayuda*')` al final de `clientes/index.blade.php`.

## Nueva entrada de menú ⇒ nuevo permiso (obligatorio)

**Desde la feature 036, el sidebar del tenant ya no está escrito a mano en
`sidebar.blade.php`: se pinta iterando `App\Support\CatalogoMenu` (fusionado con la
personalización del tenant por `App\Support\MenuTenant::estructura()`).** Una entrada nueva del
menú se añade al catálogo (`CatalogoMenu::CATALOGO`, con `clave`/`etiqueta`/`icono`/`ruta`/
`permiso`/`hijos`), **no** editando el `<li>`/`<ul>` de `sidebar.blade.php` a mano — el bucle del
partial ya sabe pintar cualquier elemento del catálogo (grupo con o sin hijos, entrada de segundo
nivel) sin tocar el Blade. El paso 3 de la lista de abajo queda actualizado en consecuencia. El
Super Admin, la tarjeta de usuario, el botón de ayuda y el toggle de Dark Mode siguen fuera del
catálogo, sin cambios.

Toda sección nueva del sidebar (feature 027) necesita un **permiso propio en el catálogo global**,
nunca reutilizar uno existente "porque ya alcanza" ni dejar la sección sin permiso (salvo el
**perfil**, la única sección genuinamente universal, sin permiso). **Granularidad = una vista o
subvista del menú**: si un módulo tiene una subvista de "crear" con entrada propia en el menú
(p. ej. "Crear factura", "Crear ticket", "Nueva campaña"), esa subvista lleva su **propio** permiso
`ver-{seccion}-crear` — no se agrupa bajo el permiso del listado (doc 09, Cambios 2 y 3). Del mismo
modo, un módulo con varias subvistas de gestión se desglosa en un permiso por subvista (el bloque
"Control de fichaje" → `ver-jornada`/`ver-calendario`/`ver-miembros`/`ver-horarios`/`ver-alertas`,
doc 09, Cambio 1). Nota: **fichar** y **mi jornada** SÍ tienen permiso (`ver-fichar`,
`ver-mi-jornada`) pero por defecto lo recibe todo rol (no se excluyen del rol base), así que siguen
siendo de facto universales salvo que un admin los acote (doc 09, Cambio 5). Cuando conviertas una
sección universal en gateada, revisá el aterrizaje de `/` (`App\Support\ResolvedorLanding`). Pasos,
en este orden:

1. **Permiso en el catálogo**: añadir la entrada (`clave`, `etiqueta`, `modulo`) en
   `App\Support\CatalogoPermisos::PERMISOS`. La clave sigue el patrón `ver-{seccion}` kebab-case.
2. **Re-correr el seeder**: `php artisan db:seed --class=PermisosSeeder` en cada entorno (deploy) —
   siembra la clave nueva y sincroniza el rol "Administrador" de **cada tenant** con el catálogo
   completo. Es el único punto donde un permiso nuevo se auto-asigna a un rol existente.
3. **Entrada del catálogo del menú** (`App\Support\CatalogoMenu`): añadir el elemento (`clave`,
   `etiqueta`, `icono` solo si es de primer nivel, `ruta`, `permiso`, `hijos`). Si es una entrada
   nueva de un grupo ya existente, sumarla a la lista `hijos` de ese grupo — el grupo no declara
   permiso propio, su visibilidad se deriva de que al menos un hijo sea visible (no hace falta
   tocarlo). Si es un grupo nuevo de primer nivel, agregar el elemento completo al catálogo. El
   bucle de `sidebar.blade.php` pinta cualquier elemento del catálogo solo; no se edita ese Blade.
4. **Rutas** (`routes/web.php`): el grupo de rutas de esa sección lleva
   `->middleware('can:ver-{seccion}')`. Nunca dejar una ruta de gestión sin `can:` confiando en que
   el sidebar ya la esconde — el enforcement real es el middleware, ocultar en el menú es solo UX
   (Principio III).
5. **Difusión a roles**: **solo** el rol "Administrador" recibe el permiso nuevo automáticamente
   (paso 2); el resto de roles de cada tenant lo reciben opt-in, editándolos desde `/roles`. Es un
   comportamiento esperado, no un bug — evita que una sección nueva aparezca sin aviso en roles que
   el tenant configuró a propósito con acceso acotado.

**Dividir, mover o quitar un permiso ya existente** (no dar de alta uno nuevo) necesita además una
**migración de datos** para no quitarle acceso a los roles personalizados de los tenants ya en
producción (el seeder solo resincroniza "Administrador" y "Usuario" base, no los roles a medida).
Patrón (ver `database/migrations/2026_07_23_12*` como referencia, doc 09):

1. Correr `PermisosSeeder` (crea los permisos nuevos, resincroniza los roles base).
2. Iterar tenants y, por cada rol **personalizado** (≠ Administrador/Usuario) que tenía el permiso
   viejo, concederle el/los nuevo(s) con `givePermissionTo(...)`, fijando el team de spatie
   (`setPermissionsTeamId($tenant->getTenantKey())`) antes y `forgetCachedPermissions()` al terminar.
3. Al **quitar** un permiso fantasma, borrarlo con `Permission::where('name', ...)->delete()` (cascada
   a `role_has_permissions`) y regatear sus rutas con el permiso correcto (p. ej. `ver-bancos` →
   `ver-configuracion`, doc 09, Cambio 4).

**Contabilidad de tests** al tocar el catálogo: `CatalogoPermisosTest` cuenta `claves()` (total) y
`clavesUsuarioBase()` (total − excluidos). Agregar un permiso **no excluido** sube ambos; uno
**excluido** solo el total. `RutasPermisosTest::mapaRutas()` debe reflejar altas/bajas de rutas con
permiso propio (excepto rutas con requisito de negocio extra como `/mi-jornada`, que exige perfil de
miembro y se prueba aparte).

Referencia de implementación completa: `specs/027-roles-permisos-tenant/`. Ajustes posteriores de
granularidad del catálogo: doc 09 (`docs/09-ajustes-catalogo-permisos.md` en la copia de origen).

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

## Widget del asistente IA (feature 030)

El chat flotante vive en `resources/views/partials/asistente-chat.blade.php` (incluido en
`layouts/app.blade.php`, nunca en superadmin/fullwidth) + `public/js/asistente-chat.js`. Convenciones
reutilizables que introdujo:

- **Render condicional en servidor** (nunca ocultar por CSS): con clave IA configurada el widget se
  emite para todos; sin clave, solo para `ver-configuracion` en modo "activar"; el resto no recibe
  markup (no filtra la existencia de la función).
- **Streaming por `fetch` + `ReadableStream`** (no `EventSource`, porque el endpoint es POST con
  CSRF): se parsean a mano los bloques SSE `event:`/`data:` separados por `\n\n`. Patrón replicable
  para cualquier endpoint que emita progreso con `response()->stream()`.
- **Tarjetas de confirmación en el chat**: las escrituras del asistente se muestran como una tarjeta
  con resumen + botones Confirmar/Cancelar que llaman a endpoints AJAX separados; feedback con
  `window.showToast`. La acción se deshabilita al resolverse.
- CSS scoped bajo `.asistente-chat__*` en un `@push('styles')` dentro del propio partial; usa
  `var(--primary)` para respetar el color de marca del tenant.
- **Exclusión por vista, también en servidor**: el `@include('partials.asistente-chat')` en
  `layouts/app.blade.php` está envuelto en `@unless (request()->routeIs('fichajes.index'))` — en
  la vista de fichar (`/fichajes`) el botón flotante tapa el hero de fichaje en pantallas chicas,
  justo la pantalla de uso diario y rápido. Mismo criterio que el resto del widget: la exclusión
  se resuelve en el layout (servidor), nunca con CSS (`display:none`) sobre el widget ya emitido.
  Si aparece otra vista donde el widget estorbe, sumar su `routeIs(...)` a la misma condición.

## Bloque QR normativo en documentos PDF (Verifactu, feature 032)

Cuando un PDF (dompdf) debe llevar un código QR con un tamaño mínimo/máximo exigido por normativa
(no solo estético), usar **unidades `mm` directamente en el CSS** (`width: 35mm; height: 35mm;`) en
vez de convertir a `px`/`pt` a mano — dompdf las respeta de forma nativa y es la única forma de
garantizar el rango exacto (30–40 mm en este caso) en A4 y en un ticket de 80 mm a la vez, donde el
espacio disponible cambia mucho. El QR se genera server-side como **SVG embebido en un data URI**
(`chillerlan/php-qrcode`, sin `ext-gd`/`imagick`, Principio V) y se comparte entre formatos en un
único partial (`resources/views/partials/verifactu-qr.blade.php`) incluido con `@include(...,
['factura' => $factura])` al principio del `<body>` de cada plantilla — nunca duplicado por formato.
Se renderiza a partir de una URL ya persistida en BD (`facturas.qr_contenido`), no recomponiéndola en
cada render, y el partial decide por sí mismo si mostrarse (factura con registro sellado) para no
repetir esa condición en cada vista que lo incluye.

## Etiqueta de criterio de fecha en indicadores agregados (informes)

Cuando un indicador agregado (recuento/importe) se adscribe a un periodo por un criterio de fecha
que no es obvio a simple vista (cohorte de alta vs. evento de cierre vs. instantánea a fecha de
corte — ver `informes-comerciales`), mostrar ese criterio como una pequeña etiqueta de color junto
al título del indicador (`.criterio-badge`, `resources/views/informes-comerciales/index.blade.php`),
no solo en un tooltip o en la documentación aparte. Tres criterios ya tienen su clase de color
(`.criterio-cohorte`, `.criterio-evento`, `.criterio-instantanea`); si un informe nuevo necesita un
cuarto criterio, sumar su propia clase con el mismo patrón (fondo translúcido + texto del mismo
tono) en vez de reusar uno existente con un significado distinto.

## Cards de resumen sobre un listado server-side (feature 043, Cobros)

Cuando una pantalla combina una tira de cards de métricas con un DataTable **server-side**
(`cobros/index.blade.php`, patrón `LogActividadController`), se aplican dos reglas juntas:

- **Cards y DataTable comparten un único juego de filtros y se recargan a la vez.** Cualquier
  cambio de filtro (estado, cliente, rango de fechas, etc.) dispara tanto `table.ajax.reload()`
  como la recarga del endpoint de resumen, con los mismos parámetros. Si no se hace así, cards y
  tabla pueden mostrar periodos o subconjuntos distintos sin que el usuario lo note — el error de
  comunicación más peligroso de un dashboard. Ver `cobros-datatable.init.js` (`recargarTodo()`).
- **Cada card declara su propio criterio de fecha** con `.criterio-badge` (ver sección anterior)
  cuando alguna cifra es instantánea (ignora el rango, ej. "pendiente de cobro a hoy") y otra
  depende del rango (ej. "cobrado en el periodo"). Mezclarlas bajo el mismo rango sin avisar hace
  que una cifra "baje" al estrechar el periodo cuando en realidad no debería (una deuda pendiente
  no depende de qué rango mires).

## Badge secundario bajo el estado principal (DataTable)

Cuando una fila necesita mostrar un segundo estado independiente del "estado" principal de la columna
(p. ej. estado Verifactu de una factura, o el badge "Enviada" ya existente), no se añade una columna
nueva: se apila un `<div class="mt-1">` con su propio `<span class="badge light ...">` dentro del
mismo `render()` de la columna de estado, condicionado a que el dato exista (`if (row.algo) { ... }`).
Mantiene la tabla legible sin ensanchar el layout con columnas casi siempre vacías. Ver
`renderEstado()` en `public/js/plugins-init/facturas-datatable.init.js`.

## Partición de un archivo JS grande en módulos con estado compartido (feature 038)

Cuando un archivo JS de una pantalla concreta (no un componente reutilizable) crece hasta el punto
de que conflictos y bugs de estado compartido se disparan (referencia: `pos-form.js` llegó a 735
líneas antes de partirse), el patrón es un **orquestador ligero + módulos registrados**, no un
bundler ni un sistema de módulos ES nuevo (Principio V: sin build step).

- El archivo original queda como **orquestador**: crea un objeto de estado compartido en
  `window` (p. ej. `window.PosApp`), expone helpers comunes (formateo, escapado HTML) y un
  `registrar(nombre, factory)` que los módulos usan para inscribirse.
- Cada módulo nuevo es un archivo aparte que llama a `PosApp.registrar('nombre', function
  (PosApp) { ... return { init, ...api-pública }; })`. El `factory` **construye** el módulo pero
  no lo inicializa todavía.
- El orquestador arranca en dos pasadas tras `DOMContentLoaded`: primero construye todos los
  módulos registrados (deja su API disponible en `PosApp.modulos.<nombre>`), después llama a
  `init()` de cada uno. Así un módulo puede usar la API de otro (`PosApp.modulos.ticket.render()`)
  sin que el orden de los `<script>` en la vista importe.
- El estado que varios módulos necesitan mutar (p. ej. el array de líneas del ticket) vive en el
  orquestador y se **muta en el sitio** (`push`/`splice`/`length = 0`), nunca se reasigna: si un
  módulo hiciera `PosApp.lineas = []`, los demás módulos seguirían apuntando al array viejo.
- Los `<script>` se registran en la vista en el orden: orquestador primero, módulos después, en
  cualquier orden entre ellos (ver `resources/views/pos/create.blade.php`). Ningún módulo asume
  que otro ya se inicializó — si necesita algo de otro módulo, lo pide en su propio `init()`, no
  en tiempo de carga del archivo.

Referencia completa: `public/js/pos-form.js` (orquestador) + `pos-catalogo.js`, `pos-ticket.js`,
`pos-cobro.js`, `pos-cuenta.js`, `pos-opciones.js` (módulos).

## Tarjeta de mesa y sus tres estados (Sala del POS, feature 038)

Grid de tarjetas (`.pos-mesa`, `resources/views/pos/sala.blade.php`) donde el **borde** comunica
el estado de un vistazo, sin tener que leer el texto interior: gris = libre, verde = ocupada,
ámbar = "olvidada" (lleva tiempo sin que nadie añada nada). El servidor decide `olvidada`
comparando con el umbral configurado (`ConfigPos::mesaOlvidadaMin`); **la vista nunca hace
aritmética de fechas** — si lo hiciera, dependería del reloj de cada tablet y dos dispositivos
mostrarían cosas distintas para la misma mesa.

Las pestañas de filtro por zona de la Sala **reutilizan `.pos-filtro`** del catálogo del TPV (52px
de alto, badge de conteo, activo con el primario del tenant) — no se diseña un selector nuevo para
lo mismo; ver "Catálogo del POS (TPV)" más abajo.

## Modal de selección de opciones de artículo (feature 038)

Al tocar un artículo del catálogo del POS marcado con opciones (`data-tiene-opciones="1"`), se
abre un modal de selección en vez de añadirse directo al ticket — un artículo **sin** opciones
sigue añadiéndose en un solo toque, sin modal (criterio de rendimiento SC-004, para no penalizar
al grueso del catálogo que no usa modificadores). El modal reutiliza la familia visual `.pos-metodo`
del modal de cobro (tarjetas grandes táctiles, radio si el grupo permite 1 selección, checkbox si
permite varias) y sigue "Modales: siempre centrados verticalmente". Las reglas de grupo
(obligatorio, mín./máx.) se validan en JS para feedback inmediato y **se revalidan siempre en
servidor** al guardar la cuenta (Principio III) — el cliente nunca es la única barrera.

## Franja central de la botonera del POS: mini-grid con el módulo de hostelería (feature 038)

La botonera de tres franjas del POS (`Total` · franja central · `Cobrar`) no cambia de estructura
al activar el módulo de hostelería: la franja central pasa de un único botón "Cliente" a un
mini-grid de 3 acciones (`.pos-bkey-grid`, grid `1fr 1fr 1fr`) — Cliente / Guardar / Aparcadas —
conservando el mismo `min-height` táctil que `.pos-bkey` y ocupando el mismo hueco flex de la
botonera. El contexto de mesa (chip con el pendiente y accesos a anular/transferir) vive aparte,
en el `card-header` del ticket, no en la botonera.

## Suplemento de zona y contexto de cuenta: nunca un aumento silencioso (feature 038)

Cualquier importe que suba el precio sin que el artículo lo explique por sí solo (el suplemento de
zona, en este caso) tiene que verse **antes** de cobrar, no solo en el total final: en el `.pos-foot`
del ticket (`+X% zona`) y en la cabecera del modal de cobro junto con la mesa (FR-064), para que
nunca sea una sorpresa al pagar.

## Feedback de bloqueo cuando el borde ya comunica estado (feature 040)

Si el **color del borde** de un elemento ya está reservado para comunicar su estado —como en
"Tarjeta de mesa y sus tres estados" (gris libre / verde ocupada / ámbar olvidada)—, el feedback de
un intento **rechazado** (una acción que no se puede completar: agrandar contra una vecina, llegar
al límite de la rejilla) **nunca** se da tiñendo ese borde: haría parecer que el elemento cambió de
estado. Se da con **sombra exterior** (`box-shadow`) más un **micro-desplazamiento** de rechazo
(~3px, ~200ms), respetando `@media (prefers-reduced-motion: reduce)`.

Tampoco se usa un toast para esto: durante un arrastre el rechazo se dispara decenas de veces y
llenaría la pantalla de avisos. El toast queda para el resultado de una acción puntual (por ejemplo,
un movimiento cancelado al soltar).

Ejemplo vivo: `.plano-mesa.plano-mesa-bloqueada` en `resources/views/pos/sala.blade.php`.

## Alta inline en un listado: confirmación explícita, nunca por `blur`

Cuando una lista permite crear un registro con una fila nueva editable (el patrón del panel de
zonas/mesas de la Sala), el alta **no** se dispara al perder el foco del input. Perder el foco es
un accidente —un Tab, un clic en cualquier sitio, el navegador autocompletando—, no una decisión, y
usarlo como confirmación crea registros que nadie pidió. Además es un gesto invisible: nadie adivina
que hay que "escribir y salir del campo" para que algo se guarde.

La fila de alta lleva **un check para confirmar y una X para descartar**:

- El check nace `disabled` y se habilita en cuanto hay contenido, para que no se confirme en vacío.
- **Enter** confirma y **Escape** descarta, porque las manos ya están en el teclado.
- Perder el foco no hace nada: la fila sigue ahí hasta que se resuelva.
- Si el alta falla en servidor (un nombre repetido, por ejemplo), la fila **conserva lo escrito** y
  vuelve a ser editable, en vez de desaparecer y obligar a teclearlo todo otra vez.
- Un guard de "enviando" evita el alta doble cuando Enter y el clic en el check llegan casi a la vez.

**Renombrar sí puede guardarse al salir del campo**: ahí el registro ya existe, el valor anterior es
conocido y el cambio es reversible escribiendo de nuevo. Crear y editar no corren el mismo riesgo, y
por eso no siguen la misma regla.

Ejemplo vivo: `filaDeAlta()` en `public/js/plugins-init/pos-sala-plano-gestion.init.js`.

## Dos vistas de los mismos datos: el estado y el destino se comparten, no se repiten (feature 041)

Cuando una pantalla ofrece **el mismo dato en dos envases distintos** (en la Sala del POS: la
rejilla de tarjetas y el plano de mesas), el reflejo natural es escribir cada vista por separado.
Es exactamente lo que hace que acaben contradiciéndose: dos copias de "¿esta mesa está olvidada?"
divergen a la primera vez que alguien toca una y no la otra, y entonces la app le miente al usuario
sobre el estado de su propia sala.

La regla es que **la decisión vive en un solo sitio y las vistas la consumen**:

- **El estado visual** (`libre` / `ocupada` / `olvidada`) se calcula una vez. Ejemplo vivo:
  `PosPlanoDibujo.claseEstado(mesa)` en `public/js/plugins-init/pos-plano-dibujo.js`, consumido por
  la tarjeta, por la mesa del plano y por el conteo de las métricas de cabecera.
- **El destino de la interacción** también. Ejemplo vivo: `window.posSalaDestinoMesa(mesa)` en
  `pos-sala.init.js` — tocar una mesa lleva al mismo sitio se toque donde se toque.
- **El dibujo compartido no puede vivir detrás del guard de permiso de una de las vistas.** El
  módulo de dibujo estaba dentro del init del editor, después de `if (!state.puedeEditar) return;`:
  el usuario sin permiso de configuración se quedaba literalmente sin código para dibujar. Si un
  módulo lo van a usar dos vistas con permisos distintos, es un archivo aparte.
- Lo que **sí** puede cambiar entre modos es el contenido interior y los controles (el editor añade
  el asa de arrastre; la vista de servicio, el importe y el tiempo). El **contorno** —posición,
  tamaño, forma, color de estado— es el invariante: si se calculara por separado, el plano que ve
  el camarero podría dejar de coincidir con el que colocó el encargado.

Corolario de contrato: cuando una vista nueva pasa a depender de campos del payload que hasta
entonces solo usaba otra, conviene un test de backend que **fije esos campos** aunque la feature no
cambie el servidor (`tests/Feature/Pos/SalaPayloadPlanoTest.php`). Si no, un refactor del controller
rompe la vista nueva en silencio.

### Corolario: quien decide la visibilidad de unos hermanos tiene que conocer al tercero en discordia

Si una función centraliza "cuál de estos contenedores se ve" (en la Sala: `aplicarVista()`, que
alterna tarjetas y plano de servicio) y **otro** contenedor los oculta a los dos al abrirse (el
editor de plano), la función central **no puede limitarse a mirar su propia variable de estado**.
Ocultar al entrar no basta: cualquier repintado posterior vuelve a pasar por ella y les devuelve la
visibilidad.

El síntoma es feo y desconcertante —dos planos en pantalla, el de lectura encima del editor, con el
usuario creyendo que perdió los cambios que en realidad seguían en memoria— y el disparador es
lejano: crear una zona o una mesa desde el panel de gestión refresca la sala, y ese refresco es el
que reabre lo que el editor había cerrado.

La regla: **la función que reparte visibilidad consulta el estado del modo excluyente**, no al
revés. Si el modo excluyente vive en otro módulo que puede no estar cargado (el editor sale por su
guard de permiso antes de publicar su API), la consulta se hace defensiva:

```js
var editando = !!(window.PosPlano && window.PosPlano.estaEditando && window.PosPlano.estaEditando());
$mesas.classList.toggle('d-none', editando || vistaActiva !== 'tarjetas');
```

Aplica a todo lo que pertenezca a la vista de lectura, no solo al contenedor principal: el cartel de
estado vacío cayó en el mismo agujero y asomaba por encima del editor al crear una zona sin mesas.

## Pintar sobre un lienzo que ya tiene arrastre exige un modo de gesto explícito (feature 042)

Cuando una superficie ya interpreta el arrastre como *mover cosas* (el plano de sala: arrastrar
mesas por su asa, estirarlas por el borde) y se le quiere añadir un gesto que también es arrastrar
—pintar celdas para recortar la planta—, **el mismo dedo sobre el mismo píxel pasa a querer decir
dos cosas distintas**. No hay heurística que resuelva eso bien: distinguir por dónde empezó el
gesto, o por si tocó una mesa, produce un plano donde el usuario nunca sabe qué va a pasar antes de
soltar.

La regla: **un modo explícito, con un toggle visible, que reconfigura la superficie entera.**

- El toggle es un `aria-pressed` real y se ve encendido mientras el modo está activo. El texto de
  ayuda de la barra cambia con él: el usuario tiene que poder leer qué hace su dedo ahora mismo.
- Al entrar en el modo, lo que competía por el gesto se **desactiva de verdad** (en el plano:
  `draggable` y `resizable` de todas las mesas), no se "deja ganar" por z-index o por orden de
  listeners. Al salir, se reactiva.
- La capa de pintado se monta y se desmonta con el modo. Lleva `touch-action: none`, sin lo cual el
  navegador se queda el gesto como scroll de la página (mismo motivo que las asas de
  redimensionado).
- El pintado va con **Pointer Events**: `pointerdown` + `setPointerCapture` en el contenedor, y el
  destino de cada `pointermove` se resuelve por coordenadas (`elementFromPoint`) y no por el
  elemento capturado. Con el dedo no hay eventos de hover sobre los elementos por los que se pasa,
  así que `pointerenter` por celda no sirve.
- El **sentido** del gesto lo decide la primera celda tocada (si era suelo, todo el arrastre
  recorta; si no lo era, todo el arrastre devuelve suelo). Si cada celda decidiera por su cuenta,
  volver a pasar por una ya pintada la desharía y el trazo saldría a franjas.
- Lo que el gesto **no puede** hacer (recortar una celda con una mesa encima) se rechaza sin mover
  nada, con el feedback de bloqueo que ya existe, y se avisa **una sola vez al soltar** con el
  conteo agregado. Un toast por celda recorrida son decenas de toasts en un arrastre.

Ejemplo vivo: modo "Recortar sala" del editor del plano
(`public/js/plugins-init/pos-sala-plano.init.js`, `.pos-plano-celdas` en `pos/sala.blade.php`).

### Corolario: la geometría de un lienzo se escribe en el lienzo, no en `documentElement`

Mientras la rejilla del plano fue una constante global, escribir `--plano-cols`/`--plano-rows` en
`documentElement` al cargar el módulo era inocuo. En cuanto pasó a ser un dato por zona (feature
042) dejó de serlo: en la Sala coexisten **dos lienzos** en la misma página —el del editor y el de
la vista de servicio—, que pueden estar mostrando zonas distintas, y la última en dibujarse le
imponía su tamaño a la otra.

La regla: **una variable CSS que describe una instancia se declara en el elemento de esa
instancia**; en la raíz solo van las que describen el sistema de diseño (ahí siguen `--plano-cell`
y `--plano-gap`, que son el tamaño de celda y valen para toda la app). Conviene además dejar el
valor por defecto declarado en la propia regla CSS del componente, para que el elemento se vea bien
antes de que el JS lo toque. Ejemplo vivo: `PosPlanoDibujo.aplicarGeometria(el, geometria)`.

## Tiras de métricas plegables: `grid-template-rows`, no `max-height`

Las tarjetas de métricas que encabezan casi todos los listados son útiles en escritorio y estorban
en una tablet de servicio, donde empujan el contenido real fuera de la primera pantalla. Cuando una
pantalla necesite poder plegarlas, el patrón es este (ejemplo vivo: el botón **Resumen** de
`pos/sala.blade.php`).

**El plegado va con `grid-template-rows: 0fr → 1fr`, no con `max-height`.** Un `max-height` obliga a
inventar un número mayor que el contenido; si se queda corto recorta, y si se pasa la animación
arranca con un tramo muerto en el que no se ve nada moverse. Las tarjetas pasan de una fila a dos al
estrechar la ventana, así que ese número no existe. El contenedor de dentro lleva `overflow: hidden`
y `min-height: 0` (sin esto último la fila del grid no baja de la altura del contenido).

```css
.strip { display: grid; grid-template-rows: 0fr; transition: grid-template-rows 200ms var(--ease-out); }
.strip-inner { overflow: hidden; min-height: 0; }
.strip.abierto { grid-template-rows: 1fr; transition-duration: 260ms; }
```

El resto de decisiones, que se repiten en cualquier plegable:

- **Curva propia, nunca `ease-in`.** Las easings de serie son flojas; `cubic-bezier(.23, 1, .32, 1)`
  para entrar y salir. `ease-in` arranca lento justo en el instante en que el usuario mira, y hace
  que la misma duración se *perciba* más lenta.
- **Abrir un poco más lento que cerrar** (260 vs 200 ms): al abrir hay algo que mirar; al cerrar el
  usuario ya decidió y solo quiere que se quite de en medio.
- **Escalonado solo al abrir** (40/85/130/175 ms entre tarjetas). Al cerrar salen todas a la vez: un
  cierre escalonado se siente como que la interfaz tarda en obedecer.
- **Nunca entrar desde `scale(0)`**: nada en el mundo real aparece de la nada. `translateY(-10px)` +
  `scale(.985)` + opacidad.
- **`visibility` para la accesibilidad**: `visibility: hidden` con `transition: visibility 0s linear
  <duración>` saca el contenido plegado del árbol de accesibilidad al terminar de cerrarse, para que
  un lector de pantalla no lea métricas invisibles. Con solo `overflow: hidden` seguiría leyéndolas.
- **`aria-expanded` + `aria-controls`** en el botón, que además es el que lleva el estado visual
  (`[aria-expanded="true"]` como selector, en vez de una clase paralela que pueda desincronizarse).
- **`:active { transform: scale(.97) }`** en el botón, y el `:hover` detrás de
  `@media (hover: hover) and (pointer: fine)`: en tablet el toque dispara `:hover` y deja el botón
  encendido después de soltarlo.
- **Movimiento reducido**: se conserva el fundido (ayuda a entender que algo cambió) y se quitan
  desplazamiento y escalonado, que es lo que marea.

**El botón va con el resto de acciones de la pantalla, nunca en una barra propia.** Darle una
franja para él solo gasta exactamente el alto vertical que el plegado venía a recuperar, así que el
plegable no llega a rendir. En la Sala vive en la cabecera de la card, junto a Tarjetas/Plano y
Actualizar. Y como la tira aparece **encima** de esa cabecera, el chevron apunta hacia arriba cuando
está plegado: hacia abajo estaría diciendo que el contenido sale por debajo del botón, que no es
donde va a aparecer.

**La preferencia se persiste por usuario**, con la misma forma de clave que la vista de la Sala
(`'<pantalla>-<cosa>:' + userId`): en hostelería varias personas comparten la misma tablet y no
deben pisarse los ajustes. Es preferencia de interfaz, así que vive en `localStorage` y no viaja al
servidor. El defecto se elige por lo que sirve a la pantalla, no por lo que es más fácil: aquí,
plegado.

## Entrada numérica en pantallas táctiles: teclado propio, nunca el del sistema

En una vista pensada para tablet (el TPV, la Sala), un `<input type="number">` o
`inputmode="decimal"` es una trampa: al enfocarlo, Android/iOS levantan su teclado, que ocupa
media pantalla y **tapa justo el dato que el usuario necesita ver** para decidir qué teclea (el
importe a cobrar, el restante, el vuelto). Encima de un modal, además, empuja el layout y deja
botones fuera de alcance.

La regla: **el campo es un disparador, no una caja de texto.**

- `readonly` + `inputmode="none"` en el `<input>`. `readonly` es lo que realmente impide que el
  teclado del sistema aparezca al enfocarlo; sin él, cualquier foco lo levanta.
- El `click` (y el `focus`, con un `blur()` defensivo para la llegada por tabulador) abre un
  **teclado propio** de la app: la misma familia visual `.pos-key` / `.pos-keypad-grid` que ya usa
  el teclado de importe del modal de cobro. No se diseña un teclado nuevo por campo.
- Ese teclado se superpone **dentro del modal** que ya está abierto (`position: absolute; inset: 0`
  sobre el contenedor), **no** como un modal de Bootstrap anidado: apilar backdrops sobre un modal
  abierto trae bloqueo de scroll, cierres en cadena y z-index peleado, y al usuario se le lee
  exactamente igual.
- El panel muestra en vivo el dato derivado (el vuelto, en el caso de "Entregado") y se confirma
  con un botón explícito. **Cancelar restaura el valor anterior**, no lo pone a cero: cancelar es
  descartar la edición en curso, no borrar lo que ya había.
- La regla de tecleo (coma decimal, máximo 2 decimales, la primera pulsación tras prellenar
  reemplaza) se comparte entre todos los teclados de la pantalla en una sola función
  (`aplicarTecla()` en `public/js/pos-cobro.js`). Dos teclados que se comportan distinto al teclear
  son un error de bulto en una pantalla de servicio.

Ejemplo vivo: campo "Entregado" del modal de cobro (`#pos-keypad-entregado` +
`#pos-entregado-panel` en `resources/views/pos/create.blade.php`).

### Corolario: un `.btn` de color propio necesita las variables `--bs-btn-*`, no `background`

Bootstrap 5 no pinta los botones con un color literal: `.btn` declara
`background-color: var(--bs-btn-bg)` y `color: var(--bs-btn-color)`. Como `css/style.css` carga
**después** de `@stack('styles')`, una regla propia de **una sola clase** (`.pos-keypad-anadir { background: var(--pos-primary) }`)
empata en especificidad con `.btn` y **pierde por orden de cascada**: el botón queda transparente
con texto gris y parece deshabilitado aunque no lo esté. Pasó exactamente eso con "Añadir pago" del
modal de cobro, y estuvo así sin que nadie lo notara hasta que un botón nuevo heredó el mismo fallo.

La forma correcta, y la que mantiene coherentes hover/active/disabled sin repetir colores:

```css
.pos-cobro-modal .pos-keypad-anadir {   /* doble clase: gana a `.btn` en especificidad */
    --bs-btn-bg: var(--pos-primary);
    --bs-btn-color: #fff;
    --bs-btn-hover-bg: color-mix(in srgb, var(--pos-primary) 86%, #000);
    --bs-btn-disabled-bg: #c9ccd1;
}
```

Si un botón con color de marca se ve apagado y en el inspector la variable del tenant **sí** tiene
valor, es esto: no es la variable, es `.btn` pisando el `background-color`.

## Assets propios: siempre `@assetv`, nunca `asset()` a secas

El hosting sirve los estáticos con `Cache-Control` de una semana y los `<script>`/`<link>` no
llevaban versión en la URL. Consecuencia práctica: cada despliegue de un JS o un CSS **no llegaba
al usuario** hasta que hiciera un refresco duro, y había que pedírselo por chat. Un cambio
desplegado que el usuario no ve es un cambio no desplegado.

```blade
{{-- mal: el navegador se queda con la copia vieja --}}
<script src="{{ asset('js/pos-cobro.js') }}"></script>

{{-- bien: ?v=<mtime>, cada despliegue invalida solo lo que cambió --}}
<script src="@assetv('js/pos-cobro.js')"></script>
```

La directiva vive en `AppServiceProvider::registrarAssetVersionado()` y añade `?v=<filemtime>`.
Notas de por qué está hecha así:

- **Es una directiva de Blade y no una función global.** Una función global habría que declararla
  en el `files` del autoload de Composer, y el despliegue a este hosting es **por FTP** —no corre
  `composer install`—, así que el autoload del servidor no se enteraría y reventaría la app entera.
  Una directiva se compila dentro de la propia vista, que sí se despliega.
- **`is_file` de guarda**: si el archivo no está en disco, devuelve la URL sin versionar en vez de
  romper la página con un warning de `filemtime`.
- **Alcance**: los assets **propios** (`public/js`, `public/css`). Los de `public/vendor` e
  `public/icons` siguen con `asset()`: son de terceros, no se editan en el día a día, y versionarlos
  solo añadiría ruido. Si alguna vez se parchea uno vendorizado, pasarlo también a `@assetv`.

No hace falta acordarse de esto al desplegar: hace falta acordarse **al escribir la vista**.

## Cards de resumen + DataTable server-side con filtros compartidos (feature 043, Cobros)

Patrón para una pantalla que combina una tira de cards de métricas agregadas con un listado
paginado grande (miles de filas por tenant, ver `cobros/index.blade.php`):

- **Un único juego de filtros para las dos piezas.** Las cards y el DataTable de la misma pantalla
  **comparten los mismos parámetros de filtro/rango** (estado, cliente, serie, fechas…) y se
  recargan **juntos** ante cualquier cambio: el DataTable vía `table.ajax.reload()` y las cards vía
  un segundo `$.getJSON` al endpoint de resumen con los mismos parámetros (`ajax.data`, nunca una
  función en `ajax.url` — memoria `feedback_datatables_ajax_url_function`). Nunca dos formularios
  de filtro independientes para cards y tabla: mostrarían periodos distintos sin que el usuario lo
  note, que es exactamente el tipo de discrepancia silenciosa que rompe la confianza en las cifras.
- **Criterio de fecha explícito por card cuando no es obvio.** Si una métrica agregada es una foto
  instantánea a hoy (p. ej. "pendiente de cobro") y otra depende del rango seleccionado (p. ej.
  "cobrado en el periodo"), cada una lleva su `.criterio-badge` (`.criterio-instantanea` /
  `.criterio-evento`, ver sección "Etiqueta de criterio de fecha en indicadores agregados" más
  arriba) junto al título. Mezclar los dos criterios bajo un único selector de rango sin
  distinguirlos hace que una cifra "baje" al estrechar el periodo cuando en realidad es una deuda
  acumulada — lo contrario de lo que espera el usuario.
- **DataTable server-side, no client-side**, en cuanto el dataset puede crecer a miles de filas por
  tenant y cada fila necesita un cálculo derivado (saldo, estado, días de retraso) que no es una
  columna directa de la tabla: cargar todo y calcular en PHP por fila no escala. El cálculo derivado
  se concentra en una única clase de `App\Support` (patrón `ConsultaCobros`) con un test de paridad
  contra el método de modelo equivalente, para que un cambio de regla en un solo sitio no
  desincronice el otro.

## Extracción de UI compartida entre dos pantallas ya existentes (feature 043)

Cuando dos pantallas necesitan exactamente el mismo bloque de UI con comportamiento (p. ej. el
modal de cobros de una factura, usado tanto desde `facturas/index.blade.php` como desde
`cobros/index.blade.php`), extraer **a comportamiento constante**, no duplicar:

- El markup va a un partial Blade compartido (`resources/views/partials/_cobros_modal.blade.php`),
  incluido desde ambas vistas con `@include(...)`.
- La lógica JS va a un módulo compartido (`public/js/plugins-init/cobros-modal.js`) que expone una
  función de inicialización con un único punto de variación explícito como parámetro (aquí,
  `onCambio`: qué recargar tras un cambio — en Facturas su propia tabla, en Cobros tabla + cards).
  La vista original pasa a delegar en ese módulo en vez de mantener su propia copia de la lógica.
- **Verificación de "a comportamiento constante"**: la suite de tests ya existente de la pantalla
  origen debe seguir en verde **sin modificar ni un test**. Si hay que tocar un test para que pase,
  la extracción cambió comportamiento y hay que revertirla y rehacerla.
