# Tasks: Dashboard de estadísticas

**Input**: Design documents from `/specs/015-dashboard-estadisticas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/dashboard-service.md, quickstart.md

**Tests**: Incluidos para aislamiento multi-tenant y cálculo de variación porcentual (Principio
IV de la constitución — NON-NEGOTIABLE para lógica que toca aislamiento de datos entre tenants),
más un test de integración de contrato del servicio. El resto de la vista sigue flujo estándar
(sin test-first obligatorio).

**Organization**: Tareas agrupadas por historia de usuario (spec.md) para permitir implementación
y entrega incremental.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1, US2, US3 según spec.md
- Rutas de archivo exactas en cada descripción

## Path Conventions

Proyecto Laravel monolítico existente (ver plan.md → Project Structure): `app/`, `resources/views/`,
`public/`, `routes/`, `tests/` en la raíz del repo.

---

## Phase 1: Setup

**Purpose**: Preparar el vendor de gráficos y el andamiaje de rutas/controlador antes de tocar lógica de negocio

- [X] T001 Vendorizar Chart.js: descargar el build UMD minificado oficial y guardarlo en `public/vendor/chartjs/chart.umd.min.js` (sin gestor de paquetes, mismo patrón que `public/vendor/toastr`)
- [X] T002 Crear `app/Http/Controllers/DashboardController.php` con un método `index()` vacío que por ahora solo devuelve `view('dashboard')`, y actualizar `routes/web.php` para que la ruta `/` (`Route::get('/', ...)->name('dashboard')`) apunte a `[DashboardController::class, 'index']` en vez de la Closure actual

**Checkpoint**: la home sigue funcionando igual que antes (vista vacía), pero ya pasa por un controller real listo para recibir datos.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Construir el servicio de agregación y el helper de variación que **todas** las historias de usuario necesitan — ninguna historia puede completarse sin esto.

**⚠️ CRITICAL**: No completar ninguna historia de usuario sin que esta fase esté terminada y sus tests en verde.

- [X] T003 [P] Test unitario `tests/Unit/VariacionPorcentualTest.php`: casos borde de `App\Support\VariacionPorcentual` — valor anterior = 0 → `null`; ambos = 0 → `null`; caso normal positivo (ej. 100→150 = +50%); caso normal negativo (ej. 150→100 ≈ -33.33%). Este test DEBE fallar primero (la clase aún no existe).
- [X] T004 Implementar `App\Support\VariacionPorcentual` (helper puro, sin dependencias de BD) en `app/Support/VariacionPorcentual.php` con un método estático `calcular(float|int $actual, float|int $anterior): ?float` que satisfaga T003
- [X] T005 [P] Test de aislamiento multi-tenant `tests/Feature/DashboardTest.php` (primer test del archivo): crear 2 tenants con `TenantFactory`, cada uno con sus propias facturas/pagos/clientes/artículos vía factories existentes (`FacturaFactory`, `PagoFactory`, `ClienteFactory`, `ArticuloFactory`), y afirmar que `App\Services\DashboardEstadisticas::resumen()` ejecutado en el contexto de cada tenant (`tenancy()->initialize($tenant)`) solo incluye los importes/conteos de ESE tenant, nunca los del otro (Principio I, NON-NEGOTIABLE). Este test DEBE fallar primero (el servicio aún no existe).
- [X] T006 Implementar el esqueleto de `App\Services\DashboardEstadisticas` en `app/Services/DashboardEstadisticas.php` con el método `resumen(?Carbon $ahora = null): array` devolviendo la forma completa descrita en `contracts/dashboard-service.md` (puede devolver valores en 0/vacíos inicialmente); debe apoyarse en modelos ya scoped por tenant (`Factura`, `Pago`, `Cliente`, `Articulo`) sin queries `DB::` raw sin scope, para que T005 empiece a pasar en cuanto al menos el aislamiento

**Checkpoint**: `DashboardEstadisticas::resumen()` existe, está aislado por tenant y devuelve la forma correcta (aunque el contenido de cada sección se completa historia por historia). `VariacionPorcentual` está listo para reutilizarse en US1.

---

## Phase 3: User Story 1 - Vistazo rápido de facturación y cobros del mes (Priority: P1) 🎯 MVP

**Goal**: KPIs de cabecera (facturado del mes, cobrado del mes, pendiente de cobro, nº de facturas emitidas) con variación % vs mes anterior, tolerando tenant sin datos.

**Independent Test**: con facturas/pagos del mes actual y anterior cargados, `/` muestra las 4 cifras coincidiendo con lo calculado manualmente desde `/facturas` y `/pagos`, incluyendo el signo/valor de la variación; con un tenant vacío, las cifras son 0 sin variación mostrada.

### Tests for User Story 1

- [X] T007 [P] [US1] Ampliar `tests/Feature/DashboardTest.php` con: (a) caso con facturas emitidas/pagadas en mes actual y mes anterior → verificar `kpis.facturado_mes.valor`, `kpis.cobrado_mes.valor`, `kpis.pendiente_cobro.valor`, `kpis.num_facturas_mes.valor` y sus `variacion_pct` correctos; (b) caso factura en `borrador`/`anulada` dentro del mes → verificar que NO se incluye en `facturado_mes`; (c) caso tenant sin ninguna factura → verificar que las 4 cifras son 0 y `variacion_pct` es `null`. Estos tests DEBEN fallar antes de T008.

### Implementation for User Story 1

- [X] T008 [US1] Implementar en `App\Services\DashboardEstadisticas` (`app/Services/DashboardEstadisticas.php`) el cálculo real de `kpis.facturado_mes`, `kpis.num_facturas_mes` (agregando `SUM(total)`/`COUNT(*)` de `facturas` con `estado` IN [emitida, pagada, vencida, rectificada], `tipo != simplificada`, `fecha_expedicion` en el mes actual y en el mes anterior) y de `kpis.cobrado_mes` (agregando `SUM(pagos.importe)` con `anulado_at IS NULL` y `fecha` en el mes), usando `VariacionPorcentual::calcular()` para cada `variacion_pct`, hasta que T007 pase
- [X] T009 [US1] Implementar en `App\Services\DashboardEstadisticas` el cálculo de `kpis.pendiente_cobro.valor` (suma de `saldoPendiente()` sobre facturas con estado emitida/pagada-parcial/vencida cuyo saldo sea > 0; reutilizar `Factura::saldoPendiente()` ya existente, agregando en SQL cuando sea posible o iterando solo sobre el subconjunto de facturas con saldo pendiente, no sobre todas las facturas del tenant)
- [X] T010 [US1] Completar `DashboardController::index()` en `app/Http/Controllers/DashboardController.php` para invocar `DashboardEstadisticas::resumen()` y pasar el array resultante a la vista
- [X] T011 [US1] Añadir a `resources/views/dashboard.blade.php` las 4 cards de KPI siguiendo el patrón "icono flotante" de `docs/04-front-guidelines.md` (div contenedor limpio, `<x-lordicon size="50">` sin `icon-box`/`bg-primary-light`), usando `App\Support\Formato::moneda()` para importes y mostrando "Sin datos previos" cuando `variacion_pct` sea `null` en vez de un porcentaje
- [X] T012 [US1] Ejecutar `php artisan test --filter=DashboardTest` y `php artisan test --filter=VariacionPorcentualTest` y confirmar que todos los tests de esta fase están en verde

**Checkpoint**: la home ya es un MVP funcional — un usuario ve de un vistazo la salud financiera del mes, incluso en un tenant recién creado.

---

## Phase 4: User Story 2 - Tendencia de facturación y brecha de cobro (Priority: P2)

**Goal**: Gráfico de evolución de facturación de 12 meses y gráfico comparativo facturado-vs-cobrado de 6 meses, con estado vacío si no hay histórico.

**Independent Test**: con actividad distribuida en varios meses (incluyendo meses sin facturas y un mes con cobro parcial), ambos gráficos muestran los importes correctos mes a mes, incluyendo ceros en meses sin actividad y evidenciando la brecha en el mes con cobro parcial.

### Tests for User Story 2

- [X] T013 [P] [US2] Ampliar `tests/Feature/DashboardTest.php` con: (a) facturas distribuidas en 12 meses con algún mes sin actividad → verificar que `serie_facturacion_12_meses` tiene exactamente 12 elementos en orden cronológico, con `facturado = 0` en el mes sin actividad; (b) un mes con facturación mayor al cobro → verificar que `comparativo_6_meses` tiene 6 elementos y ese mes muestra `facturado > cobrado`. Deben fallar antes de T014.

### Implementation for User Story 2

- [X] T014 [US2] Implementar en `App\Services\DashboardEstadisticas` el cálculo de `serie_facturacion_12_meses` (12 meses calendario terminando en el mes actual, `GROUP BY` año/mes sobre `facturas` con el mismo filtro de estados que T008, incluyendo meses sin filas con `facturado = 0`) hasta que la parte (a) de T013 pase
- [X] T015 [US2] Implementar en `App\Services\DashboardEstadisticas` el cálculo de `comparativo_6_meses` (6 meses calendario, `facturado` igual criterio que arriba + `cobrado` agregando `pagos` vigentes por mes) hasta que la parte (b) de T013 pase
- [X] T016 [P] [US2] Crear `public/js/plugins-init/dashboard-charts.init.js` que instancie dos gráficos Chart.js (line/area para la serie de 12 meses, bar agrupado para el comparativo de 6 meses) leyendo los datos ya calculados desde atributos `data-*`/variables `window.dashboardData` inyectadas por la vista — sin ningún cálculo de importes en JS, solo formateo de ejes/tooltips
- [X] T017 [US2] Añadir a `resources/views/dashboard.blade.php` las dos cards de gráfico (siguiendo la estructura visual de "Revenue Updates" del banco de templates referenciado en plan.md), cargando `public/vendor/chartjs/chart.umd.min.js` y `public/js/plugins-init/dashboard-charts.init.js` vía `@push('scripts')`, con un estado vacío explicativo cuando no haya ninguna actividad histórica (todas las series en cero)
- [X] T018 [US2] Ejecutar `php artisan test --filter=DashboardTest` y confirmar que los tests nuevos de esta fase están en verde, sin romper los de US1

**Checkpoint**: la home ahora da contexto histórico y visibiliza el gap de cobro, además del vistazo del mes en curso.

---

## Phase 5: User Story 3 - Composición del estado de cartera y acceso rápido (Priority: P3)

**Goal**: Distribución de facturas por estado, top 5 clientes, alertas de stock bajo/negativo y últimas facturas emitidas con enlace al detalle.

**Independent Test**: con facturas en distintos estados, varios clientes con distinto volumen de facturación y artículos con stock bajo mínimo, la home muestra la distribución de estados correcta, el top 5 de clientes ordenado, la lista de alertas de stock (o el mensaje de "no aplica" si el tenant no gestiona stock), y las últimas facturas emitidas enlazando a su detalle.

### Tests for User Story 3

- [X] T019 [P] [US3] Ampliar `tests/Feature/DashboardTest.php` con: (a) facturas en los 6 estados del enum `EstadoFactura` → verificar que `distribucion_estados` cuenta correctamente cada una; (b) más de 5 clientes con distinto volumen de facturación → verificar que `top_clientes` devuelve exactamente 5, ordenados de mayor a menor; (c) un artículo con `gestion_stock=true` y `stock_actual < stock_minimo` → verificar que aparece en `alertas_stock.items` con `gestiona_stock=true`; (d) tenant sin ningún artículo con `gestion_stock=true` → verificar `alertas_stock.gestiona_stock=false` y `items` vacío; (e) más de 8 facturas emitidas → verificar que `facturas_recientes` devuelve como máximo 8, ordenadas por fecha descendente. Deben fallar antes de T020-T022.

### Implementation for User Story 3

- [X] T020 [US3] Implementar en `App\Services\DashboardEstadisticas` el cálculo de `distribucion_estados` (`GROUP BY estado` sobre `facturas` con `tipo != simplificada`, incluyendo los estados sin ninguna factura con `cantidad = 0`) y de `top_clientes` (`GROUP BY cliente_id` con `SUM(total)`, orden descendente, `LIMIT 5`, mismo filtro de estados que T008) hasta que (a) y (b) de T019 pasen
- [X] T021 [US3] Implementar en `App\Services\DashboardEstadisticas` el cálculo de `alertas_stock` reutilizando `Articulo::scopeBajoMinimo()` (`app/Models/Articulo.php`) más la condición de `stock_actual < 0`, y determinando `gestiona_stock` como `Articulo::where('gestion_stock', true)->exists()`, hasta que (c) y (d) de T019 pasen
- [X] T022 [US3] Implementar en `App\Services\DashboardEstadisticas` el cálculo de `facturas_recientes` (últimas 8 facturas por `fecha_expedicion` descendente, `tipo != simplificada`, con `id`, `numero_completo`, `estado`, nombre de cliente del snapshot, `total`, `fecha_expedicion`) hasta que (e) de T019 pase
- [X] T023 [P] [US3] Añadir a `resources/views/dashboard.blade.php` la card de distribución de estados como gráfico donut/pie Chart.js (extender `public/js/plugins-init/dashboard-charts.init.js` con la tercera instancia de gráfico)
- [X] T024 [P] [US3] Añadir a `resources/views/dashboard.blade.php` la lista/tabla de top 5 clientes (widget simple, no DataTable — ver docs/04-front-guidelines.md, esta vista es un dashboard, no un listado paginable)
- [X] T025 [P] [US3] Añadir a `resources/views/dashboard.blade.php` la sección de alertas de stock, distinguiendo visualmente los 3 casos: hay alertas / gestiona stock sin alertas / no gestiona stock (FR-012)
- [X] T026 [P] [US3] Añadir a `resources/views/dashboard.blade.php` la lista de últimas facturas emitidas (5-8 filas) con enlace de cada fila a `route('facturas.show', $factura['id'])` (o la ruta de detalle equivalente ya existente)
- [X] T027 [US3] Ejecutar `php artisan test --filter=DashboardTest` y confirmar que todos los tests del archivo (US1+US2+US3) están en verde

**Checkpoint**: todas las historias de usuario están completas — el dashboard cubre KPIs, tendencia, y composición de cartera/stock/accesos rápidos.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Cierre de la feature una vez las 3 historias están implementadas y verificadas

- [X] T028 [P] Revisar `resources/views/dashboard.blade.php` contra `docs/04-front-guidelines.md` completo (gutter de `.row`, tamaño "sm" de controles si hubiera alguno, sin `alert-*` ad-hoc, `<lord-icon>` con colores del tenant) y corregir cualquier desviación
- [X] T029 Ejecutar la guía de validación manual completa de `quickstart.md` (tenant con datos, tenant nuevo sin datos, mes anterior sin actividad) y confirmar los criterios de éxito SC-001 a SC-004 de `spec.md`
- [X] T030 Ejecutar `php artisan test` (suite completa) para confirmar que no se rompió ningún test existente de otras features
- [X] T031 Revisar si el cambio de esta feature afecta `docs/00-vision.md`/`03-modelo-datos.md` (no debería, al no crear tablas nuevas) y, si algo cambió respecto a lo documentado, actualizar esos docs según la sección "Al cerrar un spec/feature" de `CLAUDE.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias — empieza de inmediato.
- **Foundational (Phase 2)**: depende de Setup. Bloquea TODAS las historias de usuario — `DashboardEstadisticas` y `VariacionPorcentual` son compartidos por US1, US2 y US3.
- **User Stories (Phase 3-5)**: todas dependen de Foundational. Pueden implementarse en orden de prioridad (P1 → P2 → P3) o en paralelo si hay más de una persona, ya que cada una solo agrega campos nuevos al mismo array de `DashboardEstadisticas::resumen()` y secciones nuevas a la misma vista (cuidado con conflictos de merge en `dashboard.blade.php` si se paraleliza).
- **Polish (Phase 6)**: depende de que las historias que se vayan a entregar estén completas.

### User Story Dependencies

- **US1 (P1)**: depende solo de Foundational. Es el MVP.
- **US2 (P2)**: depende solo de Foundational. No depende de US1, pero comparte el mismo archivo de vista y el mismo archivo de tests — en la práctica conviene completar US1 primero para evitar conflictos de merge.
- **US3 (P3)**: depende solo de Foundational. Mismo comentario que US2 sobre archivos compartidos.

### Within Each User Story

- Tests antes que implementación (T007 antes de T008-T009; T013 antes de T014-T015; T019 antes de T020-T022).
- Cálculo en el servicio (`DashboardEstadisticas`) antes que la vista/JS que lo consume.
- Historia completa (incluyendo su checkpoint de tests en verde) antes de pasar a la siguiente si se trabaja secuencialmente.

### Parallel Opportunities

- T003 y T005 (tests de Foundational) se pueden escribir en paralelo — archivos distintos.
- Dentro de US2: T016 (JS) puede avanzar en paralelo a T014-T015 (servicio) una vez se conoce la forma del contrato (ya fijada en `contracts/dashboard-service.md`), aunque no podrá probarse con datos reales hasta que el servicio esté implementado.
- Dentro de US3: T023, T024, T025, T026 (secciones de vista) son archivos/bloques independientes dentro del mismo blade — marcarlas [P] asume que una sola persona las añade en commits separados o que se coordinan para no pisarse en el mismo archivo.
- Distintas historias (US1/US2/US3) pueden repartirse entre varias personas una vez Foundational está listo, coordinando los puntos de contacto en `dashboard.blade.php` y `DashboardEstadisticas.php`.

---

## Parallel Example: Foundational Phase

```bash
Task: "Test unitario tests/Unit/VariacionPorcentualTest.php con casos borde de variación porcentual"
Task: "Test de aislamiento multi-tenant tests/Feature/DashboardTest.php (2 tenants, sin fuga de datos)"
```

## Parallel Example: User Story 3 (vista)

```bash
Task: "Añadir card de distribución de estados (donut) en resources/views/dashboard.blade.php"
Task: "Añadir lista de top 5 clientes en resources/views/dashboard.blade.php"
Task: "Añadir sección de alertas de stock en resources/views/dashboard.blade.php"
Task: "Añadir lista de últimas facturas emitidas en resources/views/dashboard.blade.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Completar Phase 1: Setup.
2. Completar Phase 2: Foundational (CRÍTICO — bloquea todo lo demás).
3. Completar Phase 3: User Story 1.
4. **Parar y validar**: probar US1 de forma independiente contra `quickstart.md` (sección de KPIs).
5. Entregar/demostrar el MVP: la home ya reemplaza el placeholder vacío con valor real.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. + US1 → validar independientemente → demo (MVP).
3. + US2 → validar independientemente → demo (tendencia + brecha de cobro).
4. + US3 → validar independientemente → demo (cartera, stock, accesos rápidos).
5. Polish → cierre de la feature.

### Parallel Team Strategy

Con más de una persona disponible:

1. El equipo completa Setup + Foundational junto (es compartido y bloqueante).
2. Una vez lista Foundational:
   - Persona A: US1 (KPIs).
   - Persona B: US2 (gráficos de tendencia).
   - Persona C: US3 (distribución, ranking, stock, últimas facturas).
3. Coordinar los puntos de contacto compartidos (`DashboardEstadisticas.php`, `dashboard.blade.php`, `dashboard-charts.init.js`) para evitar conflictos de merge simultáneos.

---

## Notes

- [P] = archivos distintos o secciones independientes sin dependencias pendientes.
- [Story] mapea cada tarea a su historia de usuario para trazabilidad con `spec.md`.
- Verificar que los tests fallan antes de implementar (T003, T005, T007, T013, T019).
- Commitear después de cada tarea o grupo lógico.
- Parar en cualquier checkpoint para validar la historia de forma independiente.
- Evitar: tareas vagas, conflictos de archivo simultáneos sin coordinar, dependencias cruzadas entre historias que rompan su independencia.
