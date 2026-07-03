# Empire Systems CRM

SaaS de facturación para España (multi-tenant). Ver `docs/00-vision.md` para la visión completa
del producto y `docs/01-arquitectura.md`, `docs/02-facturacion-espana.md`, `docs/03-modelo-datos.md`
para las decisiones técnicas, normativa y modelo de datos.

Antes de crear o tocar cualquier vista/componente de front, leer `docs/04-front-guidelines.md`
(convenciones de UI recurrentes: layout de previews de imagen, notificaciones, etc.). Si durante
el trabajo surge una decisión de UI que valga la pena repetir en el futuro, añadirla ahí.

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
  claro con toggle persistido en `localStorage` (`public/js/theme-persist.js`). No volver a
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

## Notas

- **Chrome DevTools (MCP)**: pedir confirmación al usuario antes de usar cualquier herramienta
  `mcp__chrome-devtools__*` (navegar, hacer clic, screenshots, evaluar JS, etc.), aunque sea solo
  para verificar visualmente un cambio. No lanzarlas de forma autónoma.
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
  - `jquery-asColorPicker` (y cualquier otro plugin del banco que dependa de otras libs UMD)
    requiere vendorizar también sus dependencias: `jquery-asColor` y `jquery-asGradient` deben
    cargarse *antes* que `jquery-asColorPicker.min.js`, si no el picker recibe `undefined` y
    lanza `TypeError: (0, h.default) is not a function`. Revisar el `require(...)`/`define([...])`
    del UMD wrapper del plugin antes de vendorizarlo para no dejar dependencias sueltas.
  - **Orden de CSS en `layouts/app.blade.php`**: `@stack('styles')` (CSS de plugins por vista) va
    ANTES de `css/style.css`, no después. `style.css` (37k+ líneas) ya trae su propio theming para
    varios plugins del banco (p. ej. `.asColorPicker-trigger { position: absolute; ... }`); si el
    CSS del plugin se carga después, su regla base (`position: relative`) pisa el theming del
    template por orden de cascada aunque tenga la misma especificidad — el picker se ve "roto"
    (swatch fuera del input) sin ningún error en consola. Si un plugin nuevo se ve mal aunque
    cargue bien, sospechar primero de esto antes de tocar JS.
