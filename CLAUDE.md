# Empire Systems CRM

Responder siempre en español, en toda interacción con el usuario en este proyecto.

SaaS de facturación para España (multi-tenant). Ver `docs/00-vision.md` para la visión completa
del producto y `docs/01-arquitectura.md`, `docs/02-facturacion-espana.md`, `docs/03-modelo-datos.md`
para las decisiones técnicas, normativa y modelo de datos.

## REGLA DE ORO: leer la documentación ANTES de escribir el spec, no al implementar

**Esta es la regla número uno del proyecto y se incumplió al menos una vez (feature 035, perfil de
cliente): se generó el spec/plan/tasks y se implementó una vista con `<table>` planas + `@foreach`
y con "Ver factura" abriendo una pestaña nueva, violando dos convenciones que YA estaban escritas
en `docs/04-front-guidelines.md` ("Listados: SIEMPRE DataTable, nunca una `<table>` plana" y el
patrón de vista previa de PDF en modal). El problema no fue que faltara la regla: fue no leerla.**

Por tanto, **antes de escribir una sola línea de spec, plan, tasks o código**, hay que leer de
verdad (con la herramienta de lectura, no de memoria) la documentación que aplique al área tocada:

- `docs/04-front-guidelines.md` — **obligatorio** si la feature toca cualquier vista, listado,
  formulario, modal, tabla, gráfico o componente visual. No alcanza con abrirlo al implementar: las
  convenciones (DataTable, modal de alta/edición, dropdown de acciones, previews en modal, overrides
  de CSS por vista) **condicionan el propio spec y el desglose de tareas**. Un plan que no las
  refleja produce trabajo que hay que rehacer entero.
- `docs/00-vision.md`, `01-arquitectura.md`, `02-facturacion-espana.md`, `03-modelo-datos.md` —
  para alcance, decisiones técnicas, normativa y modelo de datos.
- `.specify/memory/constitution.md` — reglas no negociables.

Si al leer la doc aparece una convención que aplica, **citarla explícitamente en el plan/tasks**
(qué sección, qué exige), para que quede trazable que se tuvo en cuenta. Si algo del código
existente contradice la doc, es la doc la que manda salvo que el usuario diga lo contrario.

Y si durante el trabajo surge una decisión de UI/arquitectura que valga la pena repetir en el
futuro, **añadirla a la doc correspondiente en el mismo cambio** (ver "Documentación al día en TODO
cambio" más abajo). Una convención que existe solo en el código y no en la doc se vuelve a violar.

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
5. `/speckit-analyze` — chequeo de consistencia entre spec/plan/tasks antes de implementar.
6. `/speckit-implement` — ejecutar las tareas.

No implementar código de negocio nuevo directo, sin pasar por spec → plan → tasks, salvo que el
usuario pida explícitamente saltarse el flujo para algo trivial (fix menor, config, etc.).

**Cuando el usuario pide "un spec" (o "hazme el spec de X"), sin más precisión, el alcance por
defecto es correr los pasos 1 a 5 inclusive (`/speckit-specify` → `/speckit-clarify` si hay
ambigüedad → `/speckit-plan` → `/speckit-tasks` → `/speckit-analyze`), NO detenerse antes.** El
paso 6 (`/speckit-implement`) queda para cuando el usuario lo pida explícitamente por separado —
"un spec" no incluye implementar.

Además, `/speckit-analyze` no es un paso informativo que se corre y se reporta: si detecta
inconsistencias, ambigüedades o huecos entre spec/plan/tasks, hay que **corregirlos ahí mismo**
(editar spec.md/plan.md/tasks.md según corresponda) y volver a correr el análisis hasta que quede
limpio, antes de dar la tarea por terminada. El criterio de "spec listo" es que quede en estado
**realmente implementable sin retrabajo**: no alcanza con generar los artefactos, hay que dejarlos
sin issues pendientes de `/speckit-analyze`.

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
- **Actualizar la base de conocimiento del asistente IA (`resources/ia/conocimiento/*.md`)**:
  toda feature nueva que agregue o cambie una pantalla/módulo o sus reglas de negocio debe
  añadir/actualizar su archivo `.md` (uno por módulo funcional). Es parte obligatoria de cerrar el
  spec, no un extra opcional: si el asistente no lo sabe, le explica mal la app al usuario. Ver la
  regla completa (FR-013) en la sección "Documentación al día en TODO cambio" (capa 4).
- En resumen, cerrar un spec-kit obliga a repasar **las 4 capas** de la sección siguiente
  (`docs/`, `docs/04-front-guidelines.md`, `resources/views/ayuda/`, `resources/ia/conocimiento/`).

## Documentación al día en TODO cambio (con o sin spec)

**Regla transversal, no atada al flujo de spec-kit.** Cada vez que se cambia algo —una feature
completa por spec, un fix menor, un ajuste de config, un cambio de UI, lo que sea— antes de dar el
trabajo por terminado hay que **razonar explícitamente si queda alguna documentación por
actualizar**, y decirlo (aunque la conclusión sea "no hace falta"). No es un paso opcional ni
reservado a los features grandes: aplica a cualquier cambio, se haya usado spec o no.

Las **cuatro** capas de documentación a revisar en cada cambio:

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

4. **Base de conocimiento del asistente IA (`resources/ia/conocimiento/*.md`)** — feature 030,
   regla FR-013. Es lo que el asistente IA sabe sobre la app. **Si un cambio agrega una pantalla o
   módulo, o altera el funcionamiento o las reglas de negocio de uno ya cubierto, hay que añadir o
   actualizar su archivo `.md` aquí** (un archivo por módulo funcional, orden alfabético,
   ensamblado por `ConocimientoAsistente`). Añadir una feature = añadir un archivo, sin tocar el
   resto (invariante SC-007). Una base de conocimiento desactualizada hace que el asistente
   explique mal la app.

Regla de oro: una guía in-app desactualizada es peor que no tenerla (le miente al usuario). Si
tocaste una vista con guía, la guía entra en el mismo cambio, no "después".

## Notas

- **Accesos de desarrollo: siempre en `ACCESOS.local.md`.** Las credenciales para entrar a la app
  en local (superadmin, tenant "Empire Demo" y su admin) están documentadas en
  `ACCESOS.local.md` (raíz del repo, gitignored) y duplicadas en `.env` bajo las claves
  `ACCESO_PERSONAL_*`. Son reproducibles vía `php artisan db:seed --class=AccesoPersonalSeeder`
  (idempotente: crea lo que falte tras un reset de BD, nunca pisa una contraseña de un usuario que
  ya existe). Antes de decir "no sé la contraseña" o resetear algo a ciegas, **leer ese archivo
  primero** — casi seguro ya está resuelto ahí. Si alguna vez se cambia una contraseña a mano desde
  la app (lo cual no debería hacer falta casi nunca, dado que borrar/resetear registros está
  prohibido por regla — ver el punto siguiente), **anotar el cambio en `ACCESOS.local.md` y en
  `.env` en el mismo momento**, no después: el seeder no lo va a detectar ni sincronizar solo.
- **Accesos del servidor de producción: siempre en `ftp.txt`.** Es la hoja por defecto para TODO
  lo del hosting de producción (`empiresass.gestionley.com`) — no solo FTP pese al nombre: FTP,
  base de datos, SSH y accesos de la app en sí (super admin, y a futuro tenants), todo vive ahí.
  Está en la raíz del repo, gitignored. Antes de preguntar por el host, el usuario, la contraseña
  del hosting o el login del super admin de producción, **leer ese archivo primero**. Ahí están
  también las notas de verificación (qué dato estaba mal, por qué `FTP_SECURE=false`, que el rol
  de super admin se asigna por el campo `rol`/`UserRole` y no por Spatie `assignRole`, etc.). Es
  el equivalente de `ACCESOS.local.md` pero para producción, no para el entorno local — no
  confundir los dos. Si alguna credencial cambia, anotarlo en `ftp.txt` en el mismo momento.
  - El acceso se usa vía el servidor MCP `ftp` (paquete npm `mcp-server-ftp`), registrado en
    scope **`local`** (vive en `~/.claude.json` bajo la ruta del proyecto), **no en `.mcp.json`**,
    que sí se commitea: las credenciales no deben entrar al repo. Instrucciones de reconfiguración
    en el propio `ftp.txt`.
  - **Ese hosting es producción compartida, no un entorno de pruebas.** El usuario FTP entra en la
    raíz de la cuenta cPanel y desde ahí se ven `public_html`, `mail`, `logs` y decenas de carpetas
    de subdominios de **clientes reales**. Aplica la política de acciones destructivas con especial
    dureza: nunca borrar ni sobrescribir nada por FTP sin confirmación explícita, y trabajar
    siempre dentro de la carpeta del subdominio que corresponda.
  - El MCP habla FTP/FTPS, **no SFTP**. Y subir archivos por FTP no es un deploy de Laravel: no
    corre `composer install`, ni migraciones, ni `artisan config:cache`.
- **Nunca perder datos de desarrollo/demo (registros con imágenes u otro valor de presentación).**
  El `DatabaseSeeder` está intencionalmente vacío (ver su docblock) y `AccesoPersonalSeeder` es
  idempotente (`firstOrCreate`) precisamente para que el acceso de desarrollo sobreviva a un
  `migrate:fresh`. Pero eso **no** cubre el resto de datos de demo: clientes, artículos, facturas,
  etc. cargados a mano o vía otros seeders, muchos con imágenes/logos/adjuntos subidos manualmente
  que ningún factory puede regenerar (el factory no sabe qué imagen "quedaba bien" para la demo).
  Por tanto:
  - **Nunca ejecutar `migrate:fresh`, `migrate:refresh`, `db:wipe`, o cualquier comando que
    trunque/dropee tablas, sin pedir confirmación explícita antes** (esto ya aplica por la política
    general de acciones destructivas, pero aquí el costo es mayor: se pierden registros con valor
    de presentación que no hay forma automática de recrear).
  - Antes de correr una migración nueva en desarrollo, si hay dudas de que pueda implicar un
    reset (o de que una migración con `down()` destructivo vaya a ejecutarse), avisar y confirmar.
  - Si en algún momento se identifican registros de demo "showcase" (con imagen/logo específico)
    que valga la pena poder recrear tras una pérdida, la solución correcta es sumarlos a un seeder
    idempotente (patrón `AccesoPersonalSeeder`: `firstOrCreate` + imagen versionada en el propio
    repo, no solo en `storage/`), no evitar las migraciones.
  - Si de todos modos se pierden datos (reset accidental, rollback, etc.), decirlo de inmediato en
    vez de seguir como si no hubiera pasado nada.
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
