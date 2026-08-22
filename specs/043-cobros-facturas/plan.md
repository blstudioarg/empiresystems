# Implementation Plan: Módulo de Cobros de facturas

**Branch**: `043-cobros-facturas` | **Date**: 2026-08-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/043-cobros-facturas/spec.md`

## Summary

Nueva pantalla `/cobros` (permiso propio `ver-cobros`, entrada propia en el catálogo de menú bajo
el grupo "Facturas") que centraliza la gestión de cobro de facturas. **Cero lógica de negocio
nueva**: `RegistroPagos::registrar()/anular()`, `Factura::totalCobrable()/montoCobrado()/
saldoPendiente()/estadoCobro()/admiteCobros()` y los endpoints `facturas.pagos.*` / `pagos.anular`
ya existen y se reutilizan tal cual.

Lo que sí se construye:

1. **`CobroController`** con `index()` (vista + DataTable **server-side**, patrón
   `LogActividadController`) y `resumen()` (JSON de las 4 métricas por rango de fechas).
2. **`App\Support\ConsultaCobros`** — único sitio donde vive el espejo SQL de `totalCobrable()`
   y `montoCobrado()`, necesario para poder filtrar/ordenar/paginar por saldo y estado de cobro
   en el servidor (SC-004). Blindado por un test de paridad contra los métodos del modelo.
3. **Extracción del modal de cobros ya existente** en `facturas/index.blade.php` +
   `facturas-datatable.init.js` a un **partial Blade compartido** y un **módulo JS compartido**,
   consumidos por las dos pantallas. No se duplica el modal.
4. Vista `cobros/index.blade.php` con tira de cards de métricas + DataTable + filtros, guía
   `ayuda/cobros.blade.php` y archivo de conocimiento IA `resources/ia/conocimiento/cobros.md`.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), Spatie
Permission (permisos por tenant), jQuery + DataTables vendorizado
(`public/vendor/datatables/`), `bootstrap-daterangepicker` (ya vendorizado, usado por
`informes-comerciales`), toastr, lordicon.

**Storage**: MySQL/MariaDB. **Sin migraciones**: no se crea ni altera ninguna tabla ni columna
(supuesto explícito de la spec). Se leen `facturas`, `pagos`, `clientes`, `series`.

**Testing**: PHPUnit (`tests/Feature`, `tests/Unit`). Nuevos:
`CobrosModuloTest` (acceso/permiso/aislamiento/filtros/orden/paginación),
`ConsultaCobrosParidadTest` (espejo SQL ≡ métodos del modelo),
`RutasPermisosTest`/`CatalogoPermisosTest` (contabilidad al alta del permiso, ya existentes).

**Target Platform**: Navegador de escritorio sobre Laravel en hosting compartido cPanel.

**Project Type**: Aplicación web monolítica Blade + jQuery (sin build step, Principio V).

**Performance Goals**: SC-004 — listado <2 s con 5.000 facturas por tenant, paginando,
filtrando y buscando. De ahí la decisión de DataTable **server-side** (D1 de research).

**Constraints**: sin build step, sin dependencias nuevas (todo lo necesario ya está
vendorizado); importes calculados en servidor (Principio III); nada fuera del scope de tenant
(Principio I).

**Scale/Scope**: 1 pantalla nueva, 1 controller, 1 clase de soporte, 2 rutas nuevas,
1 permiso nuevo, 1 entrada de menú, 1 guía de ayuda, 1 archivo de conocimiento IA, y una
refactorización de extracción (modal de cobros) que toca `facturas/index.blade.php` y
`facturas-datatable.init.js` **sin cambiar su comportamiento**.

## Constitution Check

*GATE: pasa antes de Phase 0 y se re-evalúa tras Phase 1.*

| Principio | Cómo lo cumple este plan | Estado |
|---|---|---|
| **I. Aislamiento multi-tenant (NON-NEGOTIABLE)** | Todas las consultas parten de `Factura`/`Pago`, que usan `BelongsToTenant`. Además, igual que `LogActividadController`, `ConsultaCobros` añade un `where('facturas.tenant_id', ...)` **explícito** antes de search/order/paginación (memoria `project_tenant_route_binding`: el binding implícito de rutas se salta el scope, por eso `PagoController` ya resuelve con `findOrFail` en el cuerpo — se mantiene). Test obligatorio con 2 tenants (SC-006). | ✅ |
| **II. Cumplimiento normativo España-First** | No se toca numeración, impuestos, inmutabilidad ni Verifactu. Las facturas emitidas siguen siendo inmutables: esta pantalla solo crea/anula filas de `pagos`, exactamente como hoy. No se introducen datos personales nuevos (no hay tabla nueva ⇒ no aplica retención RGPD). | ✅ |
| **III. Integridad financiera server-side** | Todos los importes (métricas, total cobrable, cobrado, saldo, días de retraso) se calculan en el servidor. El JS solo pinta. La validación del importe del cobro sigue en `StorePagoRequest` + `RegistroPagos`, no se duplica en cliente (el cliente solo prerrellena). | ✅ |
| **IV. Test-first en lógica crítica (NON-NEGOTIABLE)** | Área crítica tocada: **aislamiento multi-tenant** y el **espejo SQL de importes**. `CobrosAislamientoTest` y `ConsultaCobrosParidadTest` se escriben y se ven fallar **antes** de implementar `ConsultaCobros` (T-orden explícito en tasks.md). El resto (vista, JS, ayuda) sigue el flujo flexible. | ✅ |
| **V. Simplicidad / hosting compartido** | Sin dependencias nuevas, sin build step, sin tablas nuevas, sin jobs ni cachés. Se reutiliza `RangoFechas`/`PresetRango` y el daterangepicker ya vendorizado. Fuera de alcance declarado y respetado: exportación, conciliación, recordatorios. | ⚠️ ver Complexity Tracking (espejo SQL) |

**Gate: PASA.** La única desviación (duplicar en SQL una regla que ya existe en PHP) está
justificada y acotada abajo.

## Convenciones de front que condicionan este plan

Leídas de `docs/04-front-guidelines.md` **antes** de escribir este plan (REGLA DE ORO de
`CLAUDE.md`). Cada una se cita con la sección exacta y con lo que exige:

| Sección de `docs/04-front-guidelines.md` | Qué exige | Dónde se aplica aquí |
|---|---|---|
| "Listados: SIEMPRE DataTable, nunca una `<table>` plana" (L496) | `<table id="…" class="display responsive nowrap w-100">` con `<thead>` + `<tbody>` vacío, datos por AJAX con `dataSrc: 'data'`. Nada de `@foreach`. | `cobros/index.blade.php` + `cobros-datatable.init.js`. El sub-listado de cobros dentro del modal también es DataTable (ya lo es hoy en facturas). |
| "DataTable: botones Anterior/Siguiente verticales" (L839) | Override CSS de `width:auto` por id de tabla en el `@push('styles')` de la vista. | `#cobros-facturas-table_wrapper` y `#cobros-table_wrapper` en la vista nueva. **Memoria `feedback_datatable_pagination_css`.** |
| "Columna Acciones de los listados" (L863) | Una sola columna con **un dropdown** "Acciones"; nunca botones sueltos; destructivo separado por `<hr class="dropdown-divider">`. | `renderAcciones()` de `cobros-datatable.init.js`: Registrar cobro / Cobros / Ver factura. |
| "Ver un documento: SIEMPRE en modal" (L576) | `modal-dialog-centered modal-xl`, `modal-body p-0` con `height:80vh`, iframe apuntando a la ruta `*.pdf` (nunca `*.edit`, nunca `target="_blank"`), y **reset del `src` en `hidden.bs.modal`**. | FR-027/FR-028. Se reutiliza el markup exacto de `facturas/index.blade.php`. |
| "Icono flotante en cards informativas (métricas)" + "Rail de acento y conteo animado" (L242) | `<x-lordicon>` en un `<div>` **sin clases** (nunca `icon-box bg-primary-light`), `data-metric="<clave>"` en el `<h3>`; el rail y la animación salen solos vía `:has()` + `metric-cards.js`. El color del acento lo decide la clase del número (`text-danger` para el vencido). | Las 4 cards del resumen. La card "Vencido" lleva `text-danger` para que el rail comunique alerta. |
| "Etiqueta de criterio de fecha en indicadores agregados" (L1212) | Si el criterio de fecha del indicador no es obvio, mostrarlo como `.criterio-badge` junto al título, no solo en tooltip. | FR-008: "Cobrado" lleva `.criterio-evento` (fecha del cobro); "Pendiente"/"Vencido" llevan `.criterio-instantanea` (foto a hoy). |
| "Badges de estado: `.badge.light.badge-*`" (L180) | Familia del template; nunca gris medio sobre texto oscuro; no inventar colores. | Pendiente → `badge-warning`, Parcial → `badge-info`, Cobrada → `badge-success`, Vencida → `badge-danger`. Mismo mapa que ya usa `facturas-datatable.init.js`. |
| "Badge secundario bajo el estado principal" (L1223) | Un segundo estado se apila en un `<div class="mt-1">` dentro del mismo `render()`, no en una columna nueva. | El badge "Vencida (N días)" se apila bajo el badge de estado de cobro. |
| "Modales: siempre centrados" (L114) + "Tamaño de formularios: todo sm" (L87) | `modal-dialog-centered`; inputs `form-control-sm`. | Modal de cobros y formulario de registro. |
| "Estado de carga en botones (AJAX)" (L395) | `window.withButtonLoading($btn, fn)`, nunca `prop('disabled')` a mano. | Submit de registrar cobro. |
| "CSRF en peticiones AJAX sin formulario" (L460) | Anular cobro no serializa form ⇒ header `X-CSRF-TOKEN` explícito desde el `<meta>`. | `anularCobro()` del módulo compartido (ya lo hace hoy; se conserva al extraer). |
| "Confirmación de acciones irreversibles" (L307) | `window.confirmDelete(...)` para la anulación. | FR-024. |
| "Notificaciones" (L491) + `CLAUDE.md` | Solo toastr / `window.showToast`. | Todas las respuestas de registrar/anular. |
| "Nunca imprimir directo un campo `decimal:N`" (L960) | En Blade usar `App\Support\Formato`; en JSON castear a `(float)`/`number_format` en el controller. | El controller ya devuelve `number_format(..., 2, '.', '')`, patrón idéntico a `PagoController`. |
| "Ayuda contextual" (L981) | `@section('ayuda-titulo')` + `@section('ayuda')` al final de la vista, contenido en `resources/views/ayuda/<slug>.blade.php`, markup `<p>` intro + `<ol>` pasos + `<p class="ayuda-nota">`. | `resources/views/ayuda/cobros.blade.php` (FR-029). **Memoria `feedback_ayuda_text_collides_assertSee`: los tests asertan sobre marcadores de HTML, no sobre texto que también aparece en la ayuda.** |
| "Nueva entrada de menú ⇒ nuevo permiso" (L1017) | Orden obligatorio: permiso en `CatalogoPermisos` → `PermisosSeeder` → entrada en `CatalogoMenu` (nunca editar `sidebar.blade.php`) → `can:` en las rutas → difusión opt-in al resto de roles. Actualizar la contabilidad de `CatalogoPermisosTest` y `RutasPermisosTest`. | FR-001/FR-002. |
| "DataTables `ajax.url` no puede ser una función" (memoria `feedback_datatables_ajax_url_function`) | Los parámetros dinámicos (filtros) van en `ajax.data`, nunca construyendo la URL en una función. | Todos los filtros del listado viajan por `ajax.data`. |

## Project Structure

### Documentation (this feature)

```text
specs/043-cobros-facturas/
├── plan.md              # Este archivo
├── spec.md
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   └── cobros-endpoints.md
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── CobroController.php            # NUEVO — index() (vista + DataTable server-side) y resumen()
├── Http/Requests/
│   └── FiltroCobrosRequest.php        # NUEVO — valida filtros y rango del listado/resumen
└── Support/
    ├── ConsultaCobros.php             # NUEVO — query base + espejo SQL de totalCobrable/montoCobrado
    ├── CatalogoPermisos.php           # MOD   — alta de 'ver-cobros' (módulo "Facturas")
    ├── CatalogoMenu.php               # MOD   — entrada 'cobros' dentro del grupo 'facturas'
    └── RangoFechas.php                # SIN CAMBIOS — se reutiliza tal cual

resources/views/
├── cobros/
│   ├── index.blade.php                # NUEVO — cards + filtros + DataTable + modales
│   └── _filtros.blade.php             # NUEVO — barra de filtros (partial)
├── partials/
│   └── _cobros_modal.blade.php        # NUEVO — modal de cobros extraído de facturas/index
├── facturas/index.blade.php           # MOD   — pasa a @include del partial extraído
└── ayuda/cobros.blade.php             # NUEVO — guía in-app (FR-029)

public/js/plugins-init/
├── cobros-datatable.init.js           # NUEVO — DataTable server-side + filtros + cards
├── cobros-modal.js                    # NUEVO — módulo compartido: historial, registrar, anular
└── facturas-datatable.init.js         # MOD   — delega en cobros-modal.js (mismo comportamiento)

resources/ia/conocimiento/
└── cobros.md                          # NUEVO — base de conocimiento del asistente (FR-030)

routes/web.php                         # MOD   — grupo can:ver-cobros con 2 rutas

tests/
├── Feature/
│   ├── CobrosModuloTest.php           # NUEVO — permiso, filtros, orden, paginación, métricas
│   └── CobrosAislamientoTest.php      # NUEVO — 2 tenants, sin fuga (Principio I, test-first)
└── Unit/
    └── ConsultaCobrosParidadTest.php  # NUEVO — SQL ≡ métodos del modelo (test-first)
```

**Structure Decision**: monolito Laravel existente; no se introduce estructura nueva. El único
elemento arquitectónico nuevo es `App\Support\ConsultaCobros`, que sigue el patrón ya establecido
por otras clases de `app/Support` (`MenuTenant`, `RangoFechas`, `VencimientoFactura`).

## Complexity Tracking

| Violación | Por qué hace falta | Alternativa más simple, y por qué se descarta |
|---|---|---|
| **Espejo en SQL de `Factura::totalCobrable()` y `montoCobrado()`** dentro de `ConsultaCobros` — la misma regla vive en dos sitios (PHP y SQL), lo que roza el Principio V (simplicidad) y el FR-004 (no duplicar reglas) | FR-011/FR-012/FR-015 + SC-004 exigen **filtrar y ordenar por saldo pendiente, estado de cobro y días de retraso con paginación server-side**. Esos tres valores no son columnas: dependen de la suma de pagos vigentes y de la rectificativa asociada. Sin expresarlos en SQL no hay forma de ordenar ni paginar por ellos. | **Cargar todas las facturas y calcular en PHP** (lo que hace hoy `FacturaController::index`): funciona con pocos registros y muere en SC-004 (5.000 facturas ⇒ 5.000 `totalCobrable()`, cada uno con su consulta de rectificativa y de pagos). **Materializar `saldo_pendiente` en una columna**: violaría el supuesto de "sin tablas ni columnas nuevas" y crea un dato derivado que se puede desincronizar — justo lo que la constitución evita en `stock_actual` documentándolo como caché. **Mitigación adoptada**: el SQL vive en **una sola clase** (`ConsultaCobros`), con un comentario que apunta a los métodos del modelo como fuente de verdad, y un test de paridad (`ConsultaCobrosParidadTest`) que recorre un dataset mixto —emitida, parcial, cobrada, rectificada por sustitución, rectificada por diferencias, sin vencimiento— y afirma que la fila SQL y el método del modelo coinciden al céntimo. Si alguien cambia la regla en el modelo y no en el SQL, ese test se pone rojo. |
| **Refactor de extracción sobre `facturas/index.blade.php` y `facturas-datatable.init.js`**, que no son parte del alcance nuevo | Duplicar el modal de cobros y su JS (~150 líneas) en dos pantallas garantiza que diverjan; SC-005 exige cero discrepancias entre las dos vistas y SC-008 que facturas no cambie de comportamiento. | **Copiar y pegar el modal**: descartado por SC-005/SC-008. La extracción es a comportamiento constante y queda cubierta por la suite existente de facturas, que debe seguir verde sin tocarla (SC-008). |
