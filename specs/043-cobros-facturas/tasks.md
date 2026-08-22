---

description: "Task list — Módulo de Cobros de facturas (043)"
---

# Tasks: Módulo de Cobros de facturas

**Input**: Design documents from `/specs/043-cobros-facturas/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/cobros-endpoints.md](./contracts/cobros-endpoints.md),
[quickstart.md](./quickstart.md)

**Tests**: Se incluyen tareas de test **obligatorias** y en orden test-first para las áreas que la
constitución marca como críticas (Principio IV): **aislamiento multi-tenant** y **cálculo de
importes** (el espejo SQL de `ConsultaCobros`). El resto de tests (UI, filtros, permisos) sigue el
flujo flexible y va después de la implementación.

**Organization**: agrupadas por historia de usuario para poder entregar por incrementos.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizable (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1…US6 según `spec.md`

## Path Conventions

Monolito Laravel en la raíz del repo: `app/`, `resources/views/`, `public/js/`, `routes/`,
`tests/`. Sin `src/`, sin build step.

---

## Phase 1: Setup (acceso al módulo)

**Purpose**: dar de alta permiso, menú y rutas siguiendo el orden obligatorio de
`docs/04-front-guidelines.md` §"Nueva entrada de menú ⇒ nuevo permiso" (L1017). El orden **no es
negociable**: permiso → seeder → menú → rutas → difusión.

- [X] T001 Añadir el permiso `['clave' => 'ver-cobros', 'etiqueta' => 'Cobros', 'modulo' => 'Facturas']` a `CatalogoPermisos::PERMISOS` en `app/Support/CatalogoPermisos.php`
- [X] T002 Actualizar la contabilidad de `claves()` y `clavesUsuarioBase()` en `tests/Feature/CatalogoPermisosTest.php` (ambos totales suben en 1: `ver-cobros` no es un permiso excluido)
- [X] T003 Ejecutar `php artisan db:seed --class=PermisosSeeder` para sembrar la clave y resincronizar el rol "Administrador" de cada tenant
- [X] T004 Añadir la entrada `['clave' => 'cobros', 'etiqueta' => 'Cobros', 'icono' => null, 'ruta' => 'cobros.index', 'permiso' => 'ver-cobros', 'hijos' => []]` como tercer hijo del grupo `facturas` en `app/Support/CatalogoMenu.php` — **no tocar `resources/views/partials/sidebar.blade.php`**
- [X] T005 Registrar en `routes/web.php` el grupo `Route::middleware('can:ver-cobros')` con `GET /cobros` → `CobroController@index` (`cobros.index`) y `GET /cobros/resumen` → `CobroController@resumen` (`cobros.resumen`)
- [X] T006 Mover en `routes/web.php` las tres rutas de pagos (`facturas.pagos.index`, `facturas.pagos.store`, `pagos.anular`) del grupo `can:ver-facturas` al grupo `can:ver-cobros` (research D7)
- [X] T007 Crear la migración de datos en `database/migrations/` que concede `ver-cobros` a todo rol **personalizado** (≠ Administrador/Usuario) de cada tenant que ya tuviera `ver-facturas`, fijando `setPermissionsTeamId($tenant->getTenantKey())` antes y `forgetCachedPermissions()` al terminar (patrón `2026_07_23_12*`). **Sin esta tarea, T006 deja sin cobrar a roles a medida ya en producción.**
- [X] T008 Actualizar `mapaRutas()` en `tests/Feature/RutasPermisosTest.php` con `/cobros` → `ver-cobros`

**Checkpoint**: `php artisan test --filter=CatalogoPermisos` y `--filter=RutasPermisos` en verde.
La entrada "Cobros" aparece en el menú del Administrador (aunque la ruta todavía dé error 500 por
falta de controller — normal en este punto).

---

## Phase 2: Foundational (bloquea TODAS las historias)

**Purpose**: la consulta base. Nada del módulo funciona sin ella. **Test-first obligatorio**
(Principio IV): T009 y T010 se escriben y **se ven fallar** antes de escribir T011.

- [X] T009 [P] Escribir `tests/Unit/ConsultaCobrosParidadTest.php`: dataset mixto (emitida sin cobros, parcial, cobrada, rectificada por sustitución, rectificada por diferencias, sin `fecha_vencimiento`, vencida) y aserción de que la fila de `ConsultaCobros` coincide al céntimo con `Factura::totalCobrable()`, `montoCobrado()`, `saldoPendiente()` y `estadoCobro()`. **Debe fallar** (la clase aún no existe). Cuidado con la memoria `project_factory_tenant_id_pitfall`: forzar el `tenant_id` activo en los factories, no dejar que generen tenants nuevos
- [X] T010 [P] Escribir `tests/Feature/CobrosAislamientoTest.php`: dos tenants con facturas y pagos en ambos; afirmar que ni el listado ni el resumen de uno devuelven una sola fila o un solo céntimo del otro (SC-006). **Debe fallar**
- [X] T011 Crear `app/Support/ConsultaCobros.php` con la query base de research D2/D3: filtro explícito `where('facturas.tenant_id', ...)` **además** del global scope, elegibilidad (`admiteCobros()` traducido a SQL, excluyendo `simplificada`), `LEFT JOIN facturas AS r` para la rectificativa emitida, y las expresiones derivadas `cobrado`, `total_cobrable`, `saldo_pendiente`, `estado_cobro`, `vencida`, `dias_retraso`. Docblock apuntando a los métodos del modelo como fuente de verdad y al test de paridad como red
- [X] T012 Hacer pasar T009 y T010 (verde) sin tocar `app/Models/Factura.php` ni `app/Services/RegistroPagos.php`
- [X] T013 Crear `app/Http/Requests/FiltroCobrosRequest.php` validando `estado_cobro` (in: pendiente/parcial/cobrada), `cliente_id`, `serie_id` (exists dentro del tenant), `solo_vencidas` (boolean), `preset` (in: mes/trimestre/anio/personalizado) y `desde`/`hasta` (`date_format:Y-m-d`); rango inválido no lanza, cae a mes en curso vía `RangoFechas::desdePeticion()` (research D5)
- [X] T014 Crear `app/Http/Controllers/CobroController.php` con el esqueleto de `index()` (vista sin `draw`, JSON con `draw`) y `resumen()`, ambos apoyados en `ConsultaCobros` y `FiltroCobrosRequest`

**Checkpoint**: `php artisan test --filter=ConsultaCobrosParidad --filter=CobrosAislamiento` en
verde. `/cobros` responde (aunque la vista aún esté vacía).

---

## Phase 3: US1 — Ver de un vistazo la situación de cobro (P1) 🎯 MVP

**Goal**: cards de resumen + listado básico con los datos de cobro de cada factura.

**Independent Test**: con facturas en distintos estados de cobro, las 4 cifras del resumen y las
filas del listado coinciden con esas facturas (quickstart escenarios 1-3, 16, 18).

- [X] T015 [US1] Implementar en `CobroController@resumen` las 4 métricas de data-model.md: `pendiente_total`, `vencido_total` y `facturas_pendientes` como instantáneas a hoy (ignoran el rango) y `cobrado_periodo` sumando `pagos.importe` vigentes con `fecha` dentro del rango (FR-009 / research D4), devolviendo también el bloque `periodo`
- [X] T016 [US1] Implementar en `CobroController@index` la respuesta JSON del listado con el contrato exacto de `contracts/cobros-endpoints.md` (importes como `number_format($v, 2, '.', '')`, `dias_retraso` y `fecha_vencimiento` anulables, `pago_url` nulo si el saldo es ≤ 0, `pdf_url` nulo si el usuario no tiene `ver-facturas` — research D8)
- [X] T017 [US1] Crear `resources/views/cobros/index.blade.php` con: `@push('styles')` de DataTables + **el override de paginación previous/next para `#cobros-facturas-table_wrapper` y `#cobros-table_wrapper`** (guía L839, memoria `feedback_datatable_pagination_css`), la tira de 4 cards (`<x-lordicon>` en un `<div>` **sin clases**, `data-metric="…"` en cada `<h3>`, `text-danger` en la de vencido) y la `<table id="cobros-facturas-table" class="display responsive nowrap w-100">` con solo `<thead>` + `<tbody>` vacío
- [X] T018 [US1] Añadir a cada título de card su `.criterio-badge` (`.criterio-evento` en "Cobrado en el periodo", `.criterio-instantanea` en pendiente/vencido/contador) — FR-008, guía L1212
- [X] T019 [US1] Crear `public/js/plugins-init/cobros-datatable.init.js` con la DataTable **server-side** (`serverSide: true`, `processing: true`, `dataSrc: 'data'`, `Accept: application/json`), las columnas del contrato y `language.paginate` en español
- [X] T020 [US1] Implementar `renderEstadoCobro()` en `cobros-datatable.init.js`: badge `.badge.light.badge-*` (pendiente→warning, parcial→info, cobrada→success) y, apilado en un `<div class="mt-1">` dentro del mismo render, el badge `badge-danger` "Vencida (N días)" cuando `row.vencida` (guía L180 y L1223)
- [X] T021 [US1] Conectar las cards al endpoint de resumen desde `cobros-datatable.init.js` (`$('[data-metric="x"]').text(...)`) — **no tocar `metric-cards.js`**, el `MutationObserver` ya anima solo
- [X] T022 [US1] Mostrar el aviso de contexto de rectificación en la fila (`es_rectificada`, `total_nominal`, `modalidad_rectificacion`) para que `total_cobrable ≠ total` no parezca un error (edge case de la spec)
- [X] T023 [US1] Cubrir en `tests/Feature/CobrosModuloTest.php`: acceso con `ver-cobros` (200) y sin él (403); métricas correctas sobre un dataset conocido; el listado excluye borradores y rectificativas e incluye la original rectificada con su importe efectivo (FR-016). **Asertar sobre marcadores de HTML/JSON, no sobre texto plano que también aparece en la guía de ayuda** (memoria `feedback_ayuda_text_collides_assertSee`)

**Checkpoint**: US1 entregable por sí sola — la pantalla ya responde "¿cuánto me deben y quién?".

---

## Phase 4: US2 — Filtrar y ordenar (P1)

**Goal**: filtros combinables y orden por saldo y días de retraso.

**Independent Test**: cada filtro deja exactamente el subconjunto esperado y los órdenes son
correctos (quickstart escenarios 4-6).

- [X] T024 [US2] Aplicar en `ConsultaCobros` los filtros de `FiltroCobrosRequest`: `estado_cobro`, `cliente_id`, `serie_id`, rango de `fecha_expedicion` y `solo_vencidas`, **combinados con AND** (research D10 — "solo vencidas" es un control aparte, nunca un valor más del selector de estado)
- [X] T025 [US2] Implementar la búsqueda de `search.value` sobre `facturas.numero_completo` y sobre el cliente (`whereHas` en `nombre`/`razon_social`) — FR-013, research D9
- [X] T026 [US2] Implementar la lista blanca `COLUMNAS_ORDENABLES` (fecha de expedición, vencimiento, total cobrable, cobrado, saldo pendiente, días de retraso, cliente) con defecto `fecha_vencimiento` ascendente y **nulos al final**; cualquier columna fuera de la lista cae al defecto (patrón `LogActividadController`)
- [X] T027 [US2] Calcular `recordsTotal` y `recordsFiltered` correctamente (total antes de la búsqueda, filtrado después) y paginar con `start`/`length`
- [X] T028 [US2] Crear `resources/views/cobros/_filtros.blade.php`: grupo de botones de estado de cobro (patrón del filtro por tipo de `facturas/index.blade.php`), selects `form-select-sm` de cliente y serie, checkbox "Solo vencidas" y el selector de rango (radios mes/trimestre/año/personalizado + `bootstrap-daterangepicker`, clonado de `informes-comerciales/index.blade.php`)
- [X] T029 [US2] Cablear los filtros en `cobros-datatable.init.js` **vía `ajax.data`** — nunca una función en `ajax.url` (memoria `feedback_datatables_ajax_url_function`); cualquier cambio de filtro hace `table.ajax.reload()` **y** recarga el resumen con los mismos parámetros, para que cards y tabla no se desincronicen
- [X] T030 [US2] Añadir a `tests/Feature/CobrosModuloTest.php` los casos de filtro (cada uno por separado y dos combinados), búsqueda por número y por cliente, orden por saldo y por días de retraso, y paginación

**Checkpoint**: US1 + US2 = pantalla de consulta completa y usable con volumen real.

---

## Phase 5: US3 — Registrar un cobro desde el listado (P2)

**Goal**: registrar cobros sin salir de la pantalla, reutilizando el endpoint existente.

**Independent Test**: un cobro parcial baja el saldo, cambia el estado a parcial y actualiza las
cards (quickstart escenarios 7-10).

- [X] T031 [US3] Extraer el modal `#cobrosModal` de `resources/views/facturas/index.blade.php` (tabla `#cobros-table`, aviso `#cobroContextoRectificada`, formulario `#registrarCobroForm`) a `resources/views/partials/_cobros_modal.blade.php` y dejar en facturas un `@include` — **a comportamiento constante** (research D6)
- [X] T032 [US3] Extraer la lógica equivalente de `public/js/plugins-init/facturas-datatable.init.js` a `public/js/plugins-init/cobros-modal.js`, exponiendo `window.initCobrosModal({ tabla, onCambio })`; `facturas-datatable.init.js` pasa a delegar en él
- [X] T033a [US3] En `resources/views/facturas/index.blade.php` y `public/js/plugins-init/facturas-datatable.init.js`, ocultar las acciones de cobro cuando el usuario **no** tiene `ver-cobros` (tras T006 esas rutas exigen ese permiso): exponer el flag desde el Blade y omitir los `<li>` correspondientes, en vez de ofrecer acciones que devolverían 403. Cubrirlo con un test en `tests/Feature/CobrosModuloTest.php`
- [X] T033 [US3] Verificar SC-008: `php artisan test --filter=Factura --filter=Pago` en verde **sin modificar ni un test**. Si hay que tocar alguno, la extracción cambió comportamiento — revertir y rehacer
- [X] T034 [US3] Incluir el partial del modal en `resources/views/cobros/index.blade.php` e inicializar `initCobrosModal` desde `cobros-datatable.init.js` con un `onCambio` que recargue tabla **y** resumen
- [X] T035 [US3] Añadir la acción "Registrar cobro" al dropdown de `renderAcciones()` en `cobros-datatable.init.js`, visible solo si `row.pago_url` no es nulo (FR-021). **Un único dropdown "Acciones"**, nunca botones sueltos (guía L863)
- [X] T036 [US3] Prerrellenar el formulario al abrirlo: fecha = hoy, importe = saldo pendiente de esa fila (FR-018); inputs en tamaño `sm` (guía L87) y modal `modal-dialog-centered` (guía L114)
- [X] T037 [US3] Envolver el submit en `window.withButtonLoading($btn, fn)` (guía L395) y mapear el 422 de `PagoInvalidoException` a `.invalid-feedback` del campo importe + toast; el éxito cierra el modal, lanza toast de éxito y dispara `onCambio`
- [X] T038 [US3] Añadir a `tests/Feature/CobrosModuloTest.php`: cobro parcial (estado→parcial, saldo baja), cobro total (estado→cobrada), e importes inválidos (mayor al saldo, cero, negativo) que devuelven 422 y **dejan 0 pagos creados** (SC-007)

**Checkpoint**: la pantalla pasa de informe a herramienta de trabajo.

---

## Phase 6: US4 — Historial y anulación (P2)

**Goal**: consultar todos los cobros de una factura y anular uno con confirmación.

**Independent Test**: anular un cobro devuelve saldo y estado al valor esperado, y el cobro sigue
visible marcado como anulado (quickstart escenarios 11-12).

- [X] T039 [US4] Añadir la acción "Cobros" al dropdown de `renderAcciones()`, visible si `row.cobros_url` no es nulo, abriendo el modal compartido con el historial cargado desde `facturas.pagos.index`
- [X] T040 [US4] Verificar que el sub-listado del historial es una **DataTable** (no una `<table>` con `@foreach`), ordenado por fecha descendente, con `zeroRecords`/`emptyTable` = "Sin cobros registrados" (guía L496 — ya lo cumple el modal extraído; confirmarlo tras la extracción)
- [X] T041 [US4] Confirmar que la anulación pasa por `window.confirmDelete(...)` (confirmación explícita, guía L307) y que la petición a `pagos.anular` **envía el header `X-CSRF-TOKEN`** leído del `<meta name="csrf-token">` (guía L460) — es una petición sin form serializado
- [X] T042 [US4] Comprobar que un cobro anulado se sigue mostrando con badge de anulado y **sin** acción de anular (FR-025), y que tras anular se refrescan fila, saldo y cards vía `onCambio`
- [X] T043 [US4] Añadir a `tests/Feature/CobrosModuloTest.php`: anular el único cobro de una factura cobrada la devuelve a pendiente y la hace reaparecer bajo los filtros de pendiente/vencida; anular un cobro ya anulado devuelve 422

---

## Phase 7: US5 — Ver la factura en modal (P3)

**Goal**: consultar el documento sin perder el contexto.

**Independent Test**: abrir y cerrar el PDF conserva filtros, orden y página (quickstart 13).

- [X] T044 [US5] Incluir en `resources/views/cobros/index.blade.php` el modal de vista previa con `modal-dialog-centered modal-xl`, `modal-body p-0` a `height: 80vh` e `<iframe>`, clonado de `facturas/index.blade.php` (guía L576)
- [X] T045 [US5] Añadir la acción "Ver factura" al dropdown, solo si `row.pdf_url` no es nulo (research D8: el usuario necesita `ver-facturas`, porque `facturas.pdf` vive bajo ese permiso)
- [X] T046 [US5] Resetear el `src` del iframe en `hidden.bs.modal` para que al reabrir no se vea un instante el PDF anterior (guía L576), y confirmar FR-028: abrir y cerrar el modal **no** dispara `ajax.reload()` ni resetea la paginación, así que filtros, orden y página quedan intactos
- [X] T047 [US5] Añadir a `tests/Feature/CobrosModuloTest.php` que un usuario con `ver-cobros` pero sin `ver-facturas` recibe `pdf_url: null` en el JSON del listado

---

## Phase 8: US6 — Documentación de producto (P3)

**Goal**: guía in-app y base de conocimiento del asistente. **Obligatorio para cerrar el spec**
(`CLAUDE.md`, capas 3 y 4 de "Documentación al día en TODO cambio").

- [X] T048 [P] [US6] Crear `resources/views/ayuda/cobros.blade.php` con el markup convencional (un `<p>` de intro, un `<ol>` de pasos, un `<p class="ayuda-nota">` final con el error común): qué significan las 4 cifras y su criterio de fecha, cómo filtrar, cómo registrar un cobro y cómo anularlo. Corto: no debería hacer falta scrollear (guía L981)
- [X] T049 [US6] Añadir al final de `resources/views/cobros/index.blade.php` (fuera de `@section('content')`) las secciones `@section('ayuda-titulo', 'Cobros')` y `@section('ayuda') @include('ayuda.cobros') @endsection`
- [X] T050 [P] [US6] Crear `resources/ia/conocimiento/cobros.md` describiendo el módulo para el asistente (FR-013 de la feature 030): qué es, qué muestra cada card y su criterio, qué filtros hay, cómo se registra y se anula un cobro, y qué facturas no aparecen y por qué. **Un archivo nuevo, sin tocar los demás** (invariante SC-007 de la feature 030)
- [X] T051 [US6] Verificar que el asistente responde sobre cobros y que el botón "Ayuda de esta pantalla" muestra la guía nueva (quickstart 14-15)

---

## Phase 9: Polish & Cross-Cutting

- [X] T052 Recorrer los 20 escenarios manuales de [quickstart.md](./quickstart.md) y la lista de "Revisión visual" (paginación no vertical, cabecera de tabla legible, dropdown único de acciones, lordicon sin recortar, importes sin ceros de relleno)
- [X] T053 Verificar SC-004 con ~5.000 facturas de prueba en un tenant: paginar, filtrar y buscar por debajo de 2 s. Si no se cumple, revisar índices existentes sobre `pagos.factura_id` y `facturas.fecha_vencimiento` antes de tocar la consulta
- [X] T054 Verificar SC-005 comparando saldo y estado de 5 facturas entre el módulo de Cobros y el de Facturas: cero discrepancias
- [X] T055 Revisar si `docs/03-modelo-datos.md` o `docs/01-arquitectura.md` necesitan cambios. **Previsión: no** — no hay tablas, columnas ni decisiones de arquitectura nuevas. Dejar constancia explícita de esa conclusión al cerrar
- [X] T056 Añadir a `docs/04-front-guidelines.md` las convenciones nuevas que merezcan repetirse: (a) el patrón "cards de resumen con criterio de fecha explícito sobre un listado server-side" y (b) la regla de que **cards y DataTable de una misma pantalla comparten un único juego de filtros y se recargan juntos**, para que no puedan mostrar periodos distintos
- [X] T057 Ejecutar la suite completa (`php artisan test`) y confirmar que no hay regresiones

---

## Dependencies & Execution Order

```text
Phase 1 (Setup: permiso/menú/rutas)
        │
        ▼
Phase 2 (Foundational: ConsultaCobros — test-first)   ← BLOQUEA TODO
        │
        ├──► Phase 3: US1 (P1) ── MVP entregable
        │           │
        │           ▼
        │    Phase 4: US2 (P1)   (necesita el listado de US1)
        │
        ├──► Phase 5: US3 (P2)   (necesita US1; la extracción T031-T033 es independiente y puede adelantarse)
        │           │
        │           ▼
        │    Phase 6: US4 (P2)   (reutiliza el modal compartido de US3)
        │
        ├──► Phase 7: US5 (P3)   (solo necesita US1)
        │
        └──► Phase 8: US6 (P3)   (independiente de todo salvo que la vista exista)
                    │
                    ▼
             Phase 9: Polish
```

**Dependencias duras**:

- T003 depende de T001. T005/T006 dependen de T001. T007 depende de T006 (y sin T007, T006 es una
  regresión en producción).
- T011 no se escribe hasta ver fallar T009 y T010 (Principio IV).
- T031-T033 (extracción) deben quedar verdes antes de T034: si facturas se rompe, no se sigue.
- T053-T054 requieren todas las historias implementadas.

**Oportunidades de paralelismo**:

- T009 y T010 [P]: archivos de test distintos.
- T048 y T050 [P]: guía de ayuda y conocimiento IA, archivos independientes entre sí y del resto.
- Dentro de US2, T028 (Blade de filtros) puede avanzarse mientras se implementan T024-T027.
- Phase 7 (US5) y Phase 8 (US6) pueden ir en paralelo con Phase 5/6 una vez cerrada US1.

## Implementation Strategy

**MVP = Phase 1 + Phase 2 + Phase 3 (US1)**: una pantalla que ya responde la pregunta que hoy no
responde nada del producto. Todo lo demás es incremento sobre eso.

**Orden recomendado de entrega**: US1 → US2 (la pareja P1 hace la pantalla realmente utilizable)
→ US3 → US4 (la vuelven operativa) → US5 → US6 → Polish.

**Regla de parada**: si en cualquier tarea aparece la necesidad de tocar `RegistroPagos`,
`PagoController` o los métodos de cobro de `Factura`, **parar**: se está introduciendo lógica de
negocio nueva, que es exactamente lo que FR-004 prohíbe en esta feature.
