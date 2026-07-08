# Empire Systems CRM

Responder siempre en español, en toda interacción con el usuario en este proyecto.

SaaS de facturación para España (multi-tenant). Ver `docs/00-vision.md` para la visión completa
del producto y `docs/01-arquitectura.md`, `docs/02-facturacion-espana.md`, `docs/03-modelo-datos.md`
para las decisiones técnicas, normativa y modelo de datos.

Antes de crear o tocar cualquier vista/componente de front, leer `docs/04-front-guidelines.md`
(convenciones de UI recurrentes: layout de previews de imagen, notificaciones, etc.). Si durante
el trabajo surge una decisión de UI que valga la pena repetir en el futuro, añadirla ahí.

Además, para cualquier tarea de diseño de front (nueva vista, rediseño, decisión estética,
layout, paleta, tipografía, componente visual), usar las skills de diseño instaladas a nivel de
usuario en Windows: `frontend-design` (dirección estética e intencional), `ui-ux-pro-max`
(estilos/paletas/componentes concretos) y `emil-design-eng` (pulido de UI, animaciones, detalles
de interacción). Invocarlas vía la herramienta Skill antes de implementar, no solo cuando el
usuario las pida explícitamente.

Las reglas no negociables del proyecto (aislamiento multi-tenant, cumplimiento normativo,
integridad financiera, test-first, simplicidad) están en `.specify/memory/constitution.md`.
Léela antes de tocar código de negocio; toda spec/plan/PR debe respetarla.

## Stack

- Backend: Laravel 12, MySQL/MariaDB, `stancl/tenancy` (single-database).
- Layout base: `resources/views/layouts/app.blade.php` + `resources/views/partials/*`, con los
  assets globales del template NexaDash ya importados (`public/css`, `public/js`,
  `public/vendor`, `public/icons`). El template completo queda en `template/` (fuera de git,
  no lo borres) como banco de piezas para ir trasplantando vistas puntuales.
- El template solo tiene 2 layouts reales (`default` con sidebar, `fullwidth` sin sidebar para
  login/errores); las ~150 "demos" son páginas de ejemplo sobre esos 2 layouts, no estructuras
  distintas. El motor de estilo real es `dzSettingsOptions` en `public/js/deznav-init.js`
  (sidebar full/mini/compact/modern/overlay/icon-hover, layout vertical/horizontal, 15 esquemas
  de color, light/dark) — ya soportado por `style.css`. Default actual: sidebar `full`, modo
  claro con toggle persistido en `localStorage` (`public/js/theme-persist.js`). **El toggle de
  Dark Mode está oculto a propósito** (`.mode-btn` con `d-none` en `partials/sidebar.blade.php`,
  decisión explícita del 2026-07-08): no se usa dark mode por ahora. El mecanismo sigue
  funcionando (no se borró nada), así que reactivarlo es solo sacar esa clase — no reintroducir
  el toggle "arreglando" un supuesto olvido sin que el usuario lo pida. No volver a
  descartar este motor al simplificar: lo que se sacó a propósito fue el `config/dz.php` que
  cargaba CSS/JS distinto por cada demo page, no el selector de layout.

## Flujo de trabajo (Spec-Driven Development / spec-kit)

Todo feature nuevo entra por el flujo de spec-kit, en este orden:

1. `/speckit-specify` — crear la especificación de la feature.
2. `/speckit-clarify` (opcional pero recomendado si hay ambigüedad) — antes de planificar.
3. `/speckit-plan` — plan de implementación técnico.
4. `/speckit-tasks` — desglose en tareas accionables.
5. `/speckit-analyze` (opcional) — chequeo de consistencia entre spec/plan/tasks antes de implementar.
6. `/speckit-implement` — ejecutar las tareas.

No implementar código de negocio nuevo directo, sin pasar por spec → plan → tasks, salvo que el
usuario pida explícitamente saltarse el flujo para algo trivial (fix menor, config, etc.).

### Al cerrar un spec/feature

Cuando una feature terminó de implementarse (`/speckit-implement` completo y validado):

- Revisar si cambió algo respecto a lo documentado en `docs/00-vision.md`, `01-arquitectura.md`,
  `02-facturacion-espana.md` o `03-modelo-datos.md` (nuevas tablas, decisiones técnicas, alcance).
  Si cambió, **actualizar esos docs** para que sigan siendo la fuente de verdad.
- Si el cambio afecta a un principio de la constitución (nuevo dato no cubierto, contradicción,
  alcance ampliado/reducido de forma material), correr `/speckit-constitution` para enmendarla
  con el bump de versión correspondiente (ver reglas de semver en el propio archivo).
- Si no cambió nada respecto a lo documentado, no hace falta tocar `docs/` — evitar
  actualizaciones innecesarias.

## Documentación al día en TODO cambio (con o sin spec)

**Regla transversal, no atada al flujo de spec-kit.** Cada vez que se cambia algo —una feature
completa por spec, un fix menor, un ajuste de config, un cambio de UI, lo que sea— antes de dar el
trabajo por terminado hay que **razonar explícitamente si queda alguna documentación por
actualizar**, y decirlo (aunque la conclusión sea "no hace falta"). No es un paso opcional ni
reservado a los features grandes: aplica a cualquier cambio, se haya usado spec o no.

Las tres capas de documentación a revisar en cada cambio:

1. **Docs técnicos (`docs/`)** — visión, arquitectura, normativa, modelo de datos. Actualizar si
   el cambio toca tablas, decisiones técnicas o alcance (ver "Al cerrar un spec/feature" arriba).
2. **Guías de front (`docs/04-front-guidelines.md`)** — si el cambio introduce o modifica una
   convención de UI reutilizable, anotarla ahí.
3. **Guías in-app del usuario (`resources/views/ayuda/`)** — la documentación que el usuario final
   ve desde el botón "Ayuda de esta pantalla" del sidebar. **Si un cambio altera lo que el usuario
   hace o ve en una pantalla que ya tiene guía (campos nuevos/renombrados, pasos distintos, reglas
   de negocio que cambian, un flujo que se mueve), hay que actualizar el archivo
   `resources/views/ayuda/<slug>.blade.php` correspondiente.** Y si el cambio agrega una pantalla
   nueva que amerita guía, considerar crearla. Detalle del mecanismo en
   `docs/04-front-guidelines.md`, sección "Ayuda contextual".

Regla de oro: una guía in-app desactualizada es peor que no tenerla (le miente al usuario). Si
tocaste una vista con guía, la guía entra en el mismo cambio, no "después".

## Notas

- **Herramientas de navegador (MCP): confirmación + elección deliberada.** Hay tres disponibles:
  Playwright (`mcp__playwright__*`), Chrome DevTools (`mcp__chrome-devtools__*`) y Oculo
  (`mcp__oculo__*`). Reglas:
  1. **Nunca lanzarlas de forma autónoma.** Pedir confirmación al usuario antes de usar cualquiera
     de ellas (navegar, hacer clic, screenshots, evaluar JS, etc.), aunque sea solo para verificar
     visualmente un cambio.
  2. **Una vez el usuario habilita usar "el navegador", NO ir directo a una cualquiera:** primero
     evaluar cuál de las tres conviene según la tarea y sus ventajas, y decir cuál se elige y por qué:
     - **Oculo** — controla el navegador *vivo* que el usuario está viendo, y describe páginas en
       ~30 tokens (árbol a11y con refs, `page`/`act`/`fill`/`run`). Preferida para flujos
       multi-paso guiados, cuando importa ver lo mismo que el usuario, o para ahorrar tokens en
       navegación/extracción. No graba video a archivo (su único MP4 es generación IA con Veo);
       sí toma screenshots (`C:\Users\fede_\Pictures\Oculo\`). Requiere la app Electron corriendo
       (`env -u ELECTRON_RUN_AS_NODE npm run dev` en `C:\Users\fede_\oculo`; el harness setea
       `ELECTRON_RUN_AS_NODE=1` y hay que desactivarlo o crashea).
     - **Playwright** — abre un Chromium *separado* (no el del usuario). Mejor para pruebas E2E
       reproducibles, ejecución headless en CI, y **grabación de video real** (webm nativo) de un
       flujo.
     - **Chrome DevTools** — inspección profunda de una página: performance traces, análisis de
       red, Lighthouse, heap snapshots, console. Mejor cuando el objetivo es *diagnosticar* (no
       automatizar un flujo).
  3. Oculo pide explícitamente no usar Playwright/puppeteer en paralelo mientras está activo
     (abrirían otro navegador); no mezclar dos motores en la misma verificación.
- Multi-tenant con `tenant_id`: cualquier query o modelo de negocio nuevo debe pasar por el
  global scope de tenant (Principio I de la constitución). No hay excepciones sin justificar.
- Los cálculos de importes/impuestos/Verifactu siempre en backend (Principio III).
- **Notificaciones: siempre toastr, nunca alerts Bootstrap ad-hoc.** El patrón vive en
  `public/vendor/toastr/` (vendorizado del banco del template, ver
  `template/.../resources/views/uc-toastr.blade.php` como referencia visual) + config global en
  `public/js/toastr-config.js`. Ambos se cargan siempre desde `layouts/app.blade.php`, junto con
  `partials/flash-toastr.blade.php` (dispara un toast por cada flash de sesión: `success`, `error`,
  `warning`, `info` — basta con `->with('success', '...')` en el controller, no hace falta tocar
  la vista). Para notificaciones desde JS/AJAX, usar `window.showToast(type, message)` (definido en
  `toastr-config.js`) en vez de construir markup de alerta a mano. No reintroducir divs
  `.alert-success`/`#algo-alert` ad-hoc en vistas nuevas.
  - Cualquier plugin del banco que dependa de otras libs UMD requiere vendorizar también esas
    dependencias, cargadas *antes* que el propio plugin, o recibe `undefined` y lanza
    `TypeError: ... is not a function`. Revisar el `require(...)`/`define([...])` del UMD wrapper
    antes de vendorizar para no dejar dependencias sueltas.
  - **Orden de CSS en `layouts/app.blade.php`**: `@stack('styles')` (CSS de plugins por vista) va
    ANTES de `css/style.css`, no después. `style.css` (37k+ líneas) ya trae su propio theming para
    varios plugins del banco; si el CSS de un plugin nuevo se carga después, su regla base pisa el
    theming del template por orden de cascada aunque tenga la misma especificidad — el plugin se
    ve "roto" sin ningún error en consola. Si un plugin nuevo se ve mal aunque cargue bien,
    sospechar primero de esto antes de tocar JS.
  - **El motor de esquemas de color (`dzSettings`) fija `data-primary`/`data-secondary` en
    `<body>` en cada carga de página**, y `style.css` redefine ahí `--primary`/`--secondary` (y
    derivados) al color por defecto del template. Un override de marca del tenant en `:root`
    (`<html>`) nunca gana esa herencia porque `<body>` queda más cerca del contenido — hay que
    declararlo también en `body` y con `!important` (ver `AparienciaTenant::variablesCss()`).
    Mismo cuidado si se toca el color en vivo por JS: `documentElement.style.setProperty` sin más
    no alcanza, hay que setear también `document.body.style` con prioridad `"important"`.
  - **Color picker: Pickr** (vendorizado en `public/vendor/pickr/`, tema `classic`), no
    `jquery-asColorPicker` (retirado: guardaba en cada movimiento del mouse dentro del picker,
    sin debounce ni orden garantizado entre peticiones, lo que podía persistir un color
    intermedio en vez del elegido). Patrón: un `<div data-color-picker-trigger>` como swatch +
    un `<input readonly>` con el hex, inicializados en
    `public/js/plugins-init/configuracion-apariencia.init.js`; el guardado en servidor va atado
    al evento `save` del picker (confirmación explícita), no a `change` (que sigue disparando en
    cada arrastre, solo para la previsualización en vivo).
