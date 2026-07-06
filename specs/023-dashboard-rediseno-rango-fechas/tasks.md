# Tasks: Rediseño del Dashboard con filtro por rango de fechas

**Feature**: 023-dashboard-rediseno-rango-fechas
**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Contrato**: [contracts/dashboard.md](./contracts/dashboard.md)

**Test-first**: El proyecto exige TDD (Constitución, Principio IV). Cada story escribe primero sus
tests (que fallan) y luego la implementación.

**Convención de rutas**: monolito Laravel en la raíz del repo (`app/`, `resources/`, `public/`, `tests/`).

---

## Phase 1: Setup

- [X] T001 Leer `app/Enums/EstadoCompra.php` y confirmar el/los estado(s) que representan gasto real (confirmada) para el cómputo de gastos/IVA soportado; anotar el valor exacto en un comentario de referencia en [data-model.md](./data-model.md).
- [X] T002 Verificar dependencias UMD del date range picker del template en `template/Laravel-NexaDash-v1.0-28_May_2025/package/resources/views/form-pickers.blade.php` (revisar el `require(...)`/`define([...])` del wrapper y si depende de moment.js) antes de vendorizar; anotar hallazgos en [research.md](./research.md) (R8).

---

## Phase 2: Foundational (bloquea todas las stories)

**El value object del rango es prerrequisito de todo el servicio.**

- [X] T003 [P] Crear enum `App\Enums\PresetRango` (`mes`, `trimestre`, `anio`, `personalizado`) en `app/Enums/PresetRango.php`.
- [X] T004 Escribir tests del value object en `tests/Unit/RangoFechasTest.php`: constructores `mesEnCurso/trimestreEnCurso/anioEnCurso` (inicio de periodo natural → hoy), `personalizado`, `desdePeticion` con fallback a mes en curso, `anterior()` (misma duración, termina el día antes de `desde`), `dias()`, `granularidad()` (día si ≤62, mes si >62). (Debe fallar.)
- [X] T005 Implementar `App\Support\RangoFechas` en `app/Support/RangoFechas.php` según [data-model.md](./data-model.md) hasta que T004 pase.

**Checkpoint**: `RangoFechas` disponible y probado para las stories.

---

## Phase 3: User Story 1 — Métricas correctas de facturación (P1) 🎯 MVP

**Goal**: El facturado (y sus derivados) cuenta cada operación una sola vez con su neto, filtrado por
rango, sin doble conteo de rectificativas.

**Independent Test**: 100 € rectificada por sustitución a 75 € ⇒ facturado 75 (no 175); por diferencias
(delta −25) ⇒ 75. Borradores/anuladas/simplificadas no cuentan.

### Tests (escribir primero)

- [X] T006 [P] [US1] En `tests/Feature/DashboardTest.php`, añadir tests del facturado neto: sustitución no duplica (75), diferencias netea (75), exclusión de borrador/anulada/simplificada, y equivalencia del rango por defecto (mes en curso) con el comportamiento histórico. (Debe fallar.)
- [X] T007 [P] [US1] En `tests/Feature/DashboardTest.php`, añadir test de `top_clientes` por facturado **neto** del rango (una sustitución no infla al cliente). (Debe fallar.)
- [X] T008 [P] [US1] En `tests/Feature/DashboardTest.php`, añadir test de aislamiento multi-tenant del facturado neto (dos tenants, cada `resumen()` solo ve lo suyo). (Debe fallar.)

### Implementación

- [X] T009 [US1] Refactorizar `App\Services\DashboardEstadisticas` (`app/Services/DashboardEstadisticas.php`) para que `resumen(RangoFechas $rango)` reciba el rango y calcule el **facturado neto en PHP**: cargar facturas facturables (no simplificadas, no rectificativas, estados `ESTADOS_FACTURADOS`) con `fecha_expedicion` en el rango, eager load de `rectificativa`, sumar `totalCobrable()`. Sustituir el `SUM(total)` de `resumenMensual`.
- [X] T010 [US1] Reescribir `topClientes()` en el mismo servicio para agrupar por cliente sobre el facturado **neto** del rango (no `SUM(total)`), respetando el rango.
- [X] T011 [US1] Ajustar `distribucion_estados` y `facturas_recientes` para respetar el rango (`fecha_expedicion` en `[desde, hasta]`).
- [X] T012 [US1] Actualizar `DashboardController` (`app/Http/Controllers/DashboardController.php`) para pasar `RangoFechas::mesEnCurso()` por ahora (el parseo de filtros llega en US2) y `dashboard.blade.php` para consumir las claves renombradas (`kpis.facturado`, `serie_facturacion`, `comparativo`, `top_clientes`).
- [X] T013 [US1] Actualizar los tests existentes de `DashboardTest.php` al nuevo contrato de claves (`facturado_mes` → `kpis.facturado`, etc.) y ejecutar `php artisan test --filter=Dashboard` hasta verde.

**Nota de implementación (bug de límite de fecha en SQLite)**: `fecha_expedicion`/`pagos.fecha` se guardan como
`Y-m-d 00:00:00` incluso siendo columnas `date` (formato de fecha de la grammar SQLite usada en tests).
Un `whereBetween('columna', [desde, hasta])` con `hasta` como string `Y-m-d` excluye por comparación de
string cualquier fila fechada exactamente ese día (antes no se notaba porque el límite superior de
`resumenMensual` era fin de mes, no "hoy"; con presets "en curso" terminando en hoy, el caso se da siempre).
Solución: acotar con `>= desde` y `< hasta + 1 día` (helper `acotarFecha()` en el servicio) en vez de
`whereBetween` con el string de `hasta`.

**Checkpoint**: Facturado neto correcto y probado; dashboard sigue mostrando el mes en curso.

---

## Phase 4: User Story 2 — Filtro por rango de fechas (P1)

**Goal**: El usuario elige mes/trimestre/año en curso o rango personalizado y todo se recalcula; el
rango persiste en la URL; un rango inválido no rompe.

**Independent Test**: `?preset=anio` refleja el año; `?preset=personalizado&desde&hasta` acota; rango
inválido → mes en curso + aviso.

### Tests (escribir primero)

- [X] T014 [P] [US2] En `tests/Feature/DashboardTest.php`, tests de la ruta con query params: sin params ⇒ mes en curso; `preset=trimestre|anio` ⇒ periodo correcto; `personalizado` válido ⇒ acota; inválido (`hasta<desde`, fechas mal, preset desconocido) ⇒ 200 + mes en curso + aviso `warning`. (Debe fallar.)
- [X] T015 [P] [US2] En `tests/Feature/DashboardTest.php`, test de que la variación `variacion_pct` compara contra `rango.anterior()` de igual duración. (Debe fallar.)

### Implementación

- [X] T016 [US2] Crear `App\Http\Requests\DashboardFiltroRequest` en `app/Http/Requests/DashboardFiltroRequest.php`: valida `preset` ∈ enum y, si `personalizado`, `desde`/`hasta` fechas válidas con `hasta >= desde`.
- [X] T017 [US2] Actualizar `DashboardController::index` para construir `RangoFechas::desdePeticion(...)` desde el request validado, con fallback a `mesEnCurso()` + flash `warning` ante rango inválido (no lanzar 422). Pasar `rango` a la vista.
- [X] T018 [US2] Parametrizar por rango los métodos restantes del servicio: `kpis.cobrado` (por `pagos.fecha` en rango), `kpis.num_facturas`, `serie_facturacion` (granularidad adaptativa día/mes vía `RangoFechas::granularidad()`), `comparativo` (sub-periodos del rango), y `variacion_pct` vs. `rango.anterior()`. Dejar `pendiente_cobro` y `alertas_stock` **sin** filtrar (a hoy).
- [X] T019 [US2] Añadir el selector de rango en `resources/views/dashboard.blade.php` (presets + date range picker para personalizado), reflejando el rango activo y persistiendo los params en la URL. Cargar el picker vendorizado.
- [X] T020 [US2] Vendorizar el date range picker del template en `public/vendor/<picker>/` (+ dependencias UMD detectadas en T002) y su init en `public/js/plugins-init/dashboard-charts.init.js`; respetar el orden `@stack('styles')` antes de `style.css` (CLAUDE.md). Submit del filtro por AJAX (recarga solo `#dashboard-contenido`, sin navegar) + `history.pushState` para persistir los query params en la URL.
- [X] T021 [US2] Marcar en la UI los indicadores "a día de hoy" (pendiente de cobro, alertas de stock) para dejar claro que no dependen del rango (FR-012). Ejecutar `php artisan test --filter=Dashboard` hasta verde.

**Checkpoint**: Filtro funcional y persistente; todos los KPIs/series responden al rango.

---

## Phase 5: User Story 3 — Widgets nuevos y rediseño (P2)

**Goal**: Añadir gastos/resultado, IVA repercutido vs. soportado y ventas POS (métrica separada);
rediseño enfocado con estados vacíos.

**Independent Test**: Compra confirmada ⇒ gastos e IVA soportado; factura ⇒ IVA repercutido; resultado =
facturado − gastos; simplificada ⇒ ventas POS (no en facturado).

### Tests (escribir primero)

- [X] T022 [P] [US3] En `tests/Feature/DashboardTest.php`, tests de: `kpis.gastos` (compras confirmadas del rango), `kpis.resultado` = facturado − gastos, y exclusión de compras borrador/anulada. (Debe fallar.)
- [X] T023 [P] [US3] En `tests/Feature/DashboardTest.php`, tests de `impuestos.repercutido`/`soportado` (neteo del repercutido; etiqueta según `regimen_impositivo`) y de `kpis.ventas_pos` (simplificadas del rango, **no** sumadas al facturado). (Debe fallar.)

### Implementación

- [X] T024 [US3] Añadir al servicio los agregados nuevos: `gastos` y `soportado` (compras confirmadas del rango: `SUM(total)` y `SUM(cuota_impuesto_total)`), `resultado`, `repercutido` (neteado en el mismo recorrido de facturas del facturado), y `ventas_pos` (`SUM(total)` de simplificadas del rango). Etiqueta de impuesto según régimen del tenant.
- [X] T025 [US3] Aplicar las skills de diseño (`frontend-design`, `ui-ux-pro-max`, `emil-design-eng`) y rediseñar `resources/views/dashboard.blade.php`: nuevas tarjetas KPI (gastos, resultado, ventas POS), widget de impuestos repercutido/soportado, manteniendo un layout enfocado (no sobrecargado) con `same-card`.
- [X] T026 [US3] Añadir/ajustar los charts en `public/js/plugins-init/dashboard-charts.init.js` para las series nuevas (impuestos, resultado) reutilizando chartjs/morris; estados vacíos por widget (sin datos en el periodo / no aplica al tenant).

**Nota de implementación (T025/T026)**: en vez de un chart nuevo para impuestos, se optó por una lista de
2-3 líneas (repercutido/soportado/a liquidar) dentro de una `same-card` — el volumen de datos (2 números
+ 1 derivado) no justifica un chart, y mantiene el widget "enfocado" (Principio V, Simplicidad). El
selector de rango es un `<form>` GET con `btn-check`/`btn-group` (Mes/Trimestre/Año/Personalizado) +
`bootstrap-daterangepicker` para personalizado.

**Ampliación posterior (recarga AJAX)**: a pedido del usuario, el filtro pasó de recargar la página
completa a **recargar solo el contenido por AJAX**. El contenido (todo salvo la barra de filtro) se
extrajo a `resources/views/partials/dashboard-contenido.blade.php`; `DashboardController::index`
detecta `wantsJson()` y devuelve `{ html, rango, graficos, aviso }` (ver
[contracts/dashboard.md](./contracts/dashboard.md)); el init JS sustituye `#dashboard-contenido`,
reconstruye los charts (destruyendo las instancias previas de Chart.js) y sincroniza la URL con
`history.pushState` (+ `popstate` para atrás/adelante). Ante un fallo de red degrada a navegación
normal. Tests JSON añadidos en `DashboardTest`.

**Checkpoint**: Dashboard completo con la foto de negocio ampliada.

---

## Phase 6: Polish & Cross-Cutting

- [X] T027 [P] Verificar consistencia: la cifra "Facturado" del dashboard coincide con la card "Importe total" del listado de facturas para el mismo periodo (misma lógica de neteo).
- [X] T028 [P] Verificación visual manual (con confirmación del usuario para Chrome DevTools/Playwright): recorrer los escenarios de [quickstart.md](./quickstart.md) (presets, personalizado, inválido, tenant vacío, persistencia en URL).

**Nota de verificación visual (T028)**: hecha con Chrome DevTools MCP (confirmación del usuario) contra un
tenant QA descartable creado y eliminado en la misma sesión (sin tocar datos reales de `empire_crm`).
Confirmado: selector Mes/Trimestre/Año/Personalizado (verificado con submit por navegación; el submit por
AJAX se añadió después, cubierto por tests JSON); picker
`bootstrap-daterangepicker` en español con estilos correctos; rango personalizado acota los KPIs/series
correctamente; rango inválido (`hasta<desde`) cae a mes en curso y emite `toastr.warning(...)` con el
mensaje esperado; tenant/periodo sin datos muestra estados vacíos en los 6 widgets sin errores;
"Pendiente de cobro" y "Alertas de stock" no varían al cambiar el rango (FR-012).
- [X] T029 Actualizar `docs/00-vision.md`/`03-modelo-datos.md` solo si el rediseño cambió algo documentado (nuevos widgets/decisiones); si no, no tocar. Ejecutar la suite completa `php artisan test` para descartar regresiones.

**Nota (T029)**: `docs/00-vision.md` y `03-modelo-datos.md` no documentaban el contrato interno del
dashboard (claves de `resumen()`, KPIs) — no hay nada que actualizar. Sin migraciones ni tablas nuevas,
tampoco aplica enmienda de constitución. Suite completa: **497 passed** (1460 assertions).

---

## Dependencies & Execution Order

- **Setup (T001–T002)** → informan a Foundational/US2/US3 pero no bloquean el código base.
- **Foundational (T003–T005)** → **bloquea** todas las stories (el servicio necesita `RangoFechas`).
- **US1 (T006–T013)** → MVP. Depende de Foundational.
- **US2 (T014–T021)** → depende de US1 (servicio ya parametrizable y vista con claves nuevas).
- **US3 (T022–T026)** → depende de US1 (recorrido de facturas para netear repercutido); independiente de US2 en cálculo, pero la vista se integra sobre la de US2.
- **Polish (T027–T029)** → al final.

## Parallel Opportunities

- T003 en paralelo con T002/T001.
- Tests de cada story marcados `[P]` se escriben en paralelo (mismo archivo `DashboardTest.php`: coordinar para no colisionar, o dividir en archivos `DashboardRangoTest.php`/`DashboardFacturadoTest.php` si se prefiere paralelismo real).
- T027 y T028 en paralelo en Polish.

## MVP Scope

**US1 (T003–T013)** es el MVP entregable: corrige el bug financiero (doble conteo) y deja el dashboard
funcionando con el mes en curso. US2 (filtro) y US3 (widgets nuevos) son incrementos sobre esa base.
