---
description: "Task list for feature 004-productos-servicios"
---

# Tasks: Catálogo de Productos/Servicios

**Input**: Design documents from `/specs/004-productos-servicios/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/articulos-routes.md, quickstart.md

**Tests**: INCLUIDOS. La constitución (Principio IV) exige test-first para aislamiento multi-tenant
y para la validación de tipo impositivo por régimen fiscal (IVA/IGIC/IPSI); se añaden además tests
de CRUD/validación estándar.

**Organization**: Tareas agrupadas por historia de usuario para implementación/validación independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede correr en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1 (listar), US2 (alta), US3 (editar), US4 (eliminar)
- Rutas de archivo exactas en cada tarea.

## Path Conventions

Monolito Laravel: `app/`, `resources/views/`, `database/`, `routes/`, `public/`, `tests/Feature/`
en la raíz del repo (según plan.md). Se reutilizan los assets DataTables/toastr/SweetAlert ya
vendorizados en `002-clientes-crm` — no hace falta trasplantar nada nuevo.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Enums y catálogo fijo de tipos impositivos, base para todo lo demás.

- [X] T001 [P] Crear enum `App\Enums\TipoArticulo` (`Producto='producto'`, `Servicio='servicio'`) en `app/Enums/TipoArticulo.php`.
- [X] T002 [P] Crear enum `App\Enums\RegimenImpositivo` (`Iva='iva'`, `Igic='igic'`, `Ipsi='ipsi'`) en `app/Enums/RegimenImpositivo.php`.
- [X] T003 [P] Crear `App\Support\TiposImpositivos` en `app/Support/TiposImpositivos.php` con método estático `validosPara(RegimenImpositivo $regimen): ?array` devolviendo `[0,4,10,21]` para `Iva`, `[0,3,7,9.5,15,20]` para `Igic`, y `null` para `Ipsi` (sin catálogo cerrado) — ver research.md #1.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migraciones (incluye prerrequisito `regimen_impositivo` en `tenants`), modelo, factory, rutas y controlador base que TODAS las historias necesitan.

**⚠️ CRITICAL**: Ninguna historia puede completarse hasta terminar esta fase.

- [X] T004 Crear migración `add_regimen_impositivo_to_tenants_table` en `database/migrations/2026_07_03_xxxxxx_add_regimen_impositivo_to_tenants_table.php`: columna `string('regimen_impositivo', 10)->default('iva')` (ver data-model.md; prerrequisito documentado en research.md #2).
- [X] T005 Actualizar `App\Models\Tenant` en `app/Models/Tenant.php`: añadir `regimen_impositivo` a `$fillable`, cast a `RegimenImpositivo::class`, y a `getCustomColumns()` (depende de T002, T004).
- [X] T006 Crear migración `create_articulos_table` en `database/migrations/2026_07_03_xxxxxx_create_articulos_table.php` con todos los campos e índices `(tenant_id, tipo)` y `(tenant_id, sku)` (ver data-model.md); `softDeletes` + `timestamps`.
- [X] T007 Crear modelo `App\Models\Articulo` en `app/Models/Articulo.php` con traits `BelongsToTenant`, `SoftDeletes`, `HasFactory`; `$fillable`, casts (`tipo`→TipoArticulo, `gestion_stock`/`aplica_recargo_equivalencia`/`activo`→bool, decimales→`decimal:2`/`decimal:4`) y relación `tenant()` (depende de T001, T006).
- [X] T008 [P] Crear `ArticuloFactory` en `database/factories/ArticuloFactory.php` con estados `producto()` y `servicio()` (depende de T007).
- [X] T009 Crear `App\Http\Controllers\ArticuloController` (esqueleto con métodos index/store/update/destroy) en `app/Http/Controllers/ArticuloController.php` y registrar `Route::resource('articulos', ArticuloController::class)->only(['index','store','update','destroy'])` dentro del grupo `['auth','tenant.context']` en `routes/web.php` (depende de T007).
- [X] T010 [P] Añadir el ítem "Productos/Servicios" (icono SVG del set del template, enlace a `route('articulos.index')`) en `resources/views/partials/sidebar.blade.php`.

### Test crítico de aislamiento y de régimen fiscal (test-first, Principio IV) ⚠️

> Escribir ANTES de implementar la lógica de las historias; debe FALLAR primero.

- [X] T011 [US1] Escribir `tests/Feature/ArticuloTenantIsolationTest.php`: con 2 tenants + sus usuarios, afirmar que (a) el índice de A no incluye artículos de B, (b) crear como A asigna el `tenant_id` de A, (c) `update`/`destroy` sobre un id de B devuelve 404 para el usuario de A (depende de T007–T009; debe fallar antes de US1–US4).
- [X] T012 [US2] Escribir en `tests/Feature/ArticuloCrudTest.php` los tests de validación de régimen fiscal (FR-008/FR-009, Principio II): un tenant `regimen_impositivo=iva` rechaza `tipo_impositivo=7` (válido solo IGIC) y acepta `21`; un tenant `regimen_impositivo=igic` rechaza `21` y acepta `7`; un tenant `regimen_impositivo=ipsi` acepta cualquier valor `0–100` (debe fallar antes de implementar T017/T019).

**Checkpoint**: Fundamentos listos — las historias pueden implementarse.

---

## Phase 3: User Story 1 - Ver el catálogo de productos/servicios del tenant (Priority: P1) 🎯 MVP

**Goal**: Pantalla "Productos/Servicios" con cartas de métricas del tenant (total/productos/servicios) y tabla DataTables responsive listando los artículos del tenant.

**Independent Test**: Con 2 tenants con catálogos distintos, entrar como usuario de A y ver solo los artículos de A, con métricas correctas y tabla usable en escritorio y móvil.

### Tests for User Story 1 ⚠️

- [X] T013 [P] [US1] En `tests/Feature/ArticuloCrudTest.php`, test de `index`: devuelve 200, respuesta JSON (`wantsJson`) muestra solo artículos del tenant activo y las métricas (`total`/`productos`/`servicios`) coinciden con los datos sembrados (debe fallar primero).

### Implementation for User Story 1

- [X] T014 [US1] Implementar `ArticuloController@index` en `app/Http/Controllers/ArticuloController.php`: rama `wantsJson()` devuelve `{ data, totales }` (ver contracts/articulos-routes.md) y rama HTML devuelve la vista `articulos.index`.
- [X] T015 [P] [US1] Crear vista `resources/views/articulos/index.blade.php` (extiende `layouts.app`): cartas de métricas (total/productos/servicios) + tabla `<table id="articulos-table" class="display responsive nowrap">` con columnas Código · Nombre · Tipo · Precio · Tipo impositivo · Acciones; botón "Agregar artículo"; modal `#articuloModal` vacío por ahora (formulario lo aporta T021).
- [X] T016 [P] [US1] Crear `public/js/plugins-init/articulos-datatable.init.js` (paralelo a `clientes-datatable.init.js`): `window.initArticulosDataTable()` inicializa `#articulos-table` con `ajax` hacia la URL de índice, `dataSrc` que llama `window.updateArticulosCards(json.totales)`, columnas, botón de acciones (editar/eliminar) con `data-*` de precarga, y `language` en español.
- [X] T017 [US1] En `articulos/index.blade.php`, cargar los assets DataTables (ya vendorizados) vía `@push('styles')`/`@push('scripts')` + `articulos-datatable.init.js` (depende de T015, T016).

**Checkpoint**: US1 funcional y testeable de forma independiente (listado + métricas + responsive + aislamiento).

---

## Phase 4: User Story 2 - Alta de un producto o servicio nuevo (Priority: P1)

**Goal**: Crear artículos (producto/servicio) con validación server-side, incluida la validación de tipo impositivo contra el régimen fiscal del tenant; quedan asociados al tenant y aparecen en el listado.

**Independent Test**: Como usuario de un tenant con régimen IVA, crear un artículo válido con tipo impositivo de IVA y verlo en el listado; intentar guardar con un tipo impositivo de IGIC → rechazado. Datos inválidos (precio negativo, falta nombre) → errores por campo sin crear registro.

### Tests for User Story 2 ⚠️

- [X] T018 [P] [US2] En `tests/Feature/ArticuloCrudTest.php`, tests de `store`: alta válida (producto y servicio) crea el registro con el tenant activo; alta inválida (sin `nombre`, precio negativo, `tipo_impositivo` fuera de rango) no crea y devuelve errores; producto con `gestion_stock=true` exige `stock_actual`; servicio ignora/fuerza a null los campos de stock aunque se envíen; tras el alta, las métricas de `index` reflejan el nuevo conteo (FR-016) (debe fallar primero). Los casos de régimen fiscal ya están cubiertos en T012.

### Implementation for User Story 2

- [X] T019 [P] [US2] Crear `App\Http\Requests\StoreArticuloRequest` en `app/Http/Requests/StoreArticuloRequest.php`: reglas de data-model.md (required condicionales por `tipo`, precio `min:0`, `tipo_impositivo` `between:0,100` + regla custom contra `TiposImpositivos::validosPara(tenant()->regimen_impositivo)`, `stock_actual` required si `tipo=producto` y `gestion_stock=true`) (depende de T003).
- [X] T020 [US2] Implementar `ArticuloController@store` (valida con StoreArticuloRequest, fuerza a null los campos de stock si `tipo=servicio` o `gestion_stock=false`, crea el artículo; responde JSON 201 o redirige con flash de éxito) en `app/Http/Controllers/ArticuloController.php`.
- [X] T021 [P] [US2] Crear parcial de campos `resources/views/articulos/_form.blade.php` (todos los campos, selector `tipo`, selector de `tipo_impositivo` poblado dinámicamente según `tenant()->regimen_impositivo` vía `TiposImpositivos::validosPara()`, campos de stock condicionales) e incluirlo dentro de `#articuloModal` en `articulos/index.blade.php` (form apuntando por defecto a `articulos.store`) (depende de T015).
- [X] T022 [P] [US2] Crear `public/js/plugins-init/articulos-modal.init.js` (paralelo a `clientes-modal.init.js`): submit/delete por `$.ajax`, errores 422 inyectados inline sin cerrar el modal, refresco de la tabla vía `DataTable().ajax.reload(null, false)` tras éxito, mostrar/ocultar campos de stock según `tipo` seleccionado.

**Checkpoint**: US1 + US2 funcionan de forma independiente (listar + alta con validación de régimen fiscal).

---

## Phase 5: User Story 3 - Edición de un producto/servicio existente (Priority: P2)

**Goal**: Editar datos de un artículo del tenant con validación (incluida régimen fiscal); cross-tenant → 404.

**Independent Test**: Como usuario de A, editar un artículo de A y ver los cambios; no poder editar uno de B (404).

### Tests for User Story 3 ⚠️

- [X] T023 [P] [US3] En `tests/Feature/ArticuloCrudTest.php`, tests de `update`: edición válida persiste; inválida devuelve errores; acceder a un artículo de otro tenant devuelve 404 (debe fallar primero).

### Implementation for User Story 3

- [X] T024 [P] [US3] Crear `App\Http\Requests\UpdateArticuloRequest` en `app/Http/Requests/UpdateArticuloRequest.php` (mismas reglas que Store, incluida la validación de régimen fiscal) (depende de T003).
- [X] T025 [US3] Implementar `ArticuloController@update` (resuelve `Articulo::findOrFail($id)` manualmente dentro del método — sin binding implícito de ruta, ver research.md #3 y memoria del proyecto `[[project_tenant_route_binding]]` —, valida con UpdateArticuloRequest, actualiza, fuerza a null los campos de stock si corresponde; responde JSON o redirige con flash) en `app/Http/Controllers/ArticuloController.php`.
- [X] T026 [P] [US3] En cada fila de `articulos-datatable.init.js` (T016), añadir al botón "Editar" los atributos `data-*` con todos los campos del artículo para que `articulos-modal.init.js` (T022) precargue `#articuloModal` en modo edición (depende de T016, T021).

**Checkpoint**: US1 + US2 + US3 funcionan de forma independiente.

---

## Phase 6: User Story 4 - Eliminación (soft delete) de un producto/servicio (Priority: P3)

**Goal**: Borrado lógico con confirmación; el artículo desaparece del listado pero se conserva en DB; cross-tenant → 404.

**Independent Test**: Como usuario de A, eliminar un artículo → desaparece del listado y baja el conteo; el registro queda con `deleted_at`; no poder borrar uno de B (404).

### Tests for User Story 4 ⚠️

- [X] T027 [P] [US4] En `tests/Feature/ArticuloCrudTest.php`, tests de `destroy`: borrado deja `deleted_at` no nulo, el artículo ya no aparece en `index`, borrar un artículo de otro tenant devuelve 404, y tras el borrado las métricas de `index` bajan en 1 (FR-016) (debe fallar primero).

### Implementation for User Story 4

- [X] T028 [US4] Implementar `ArticuloController@destroy` (resuelve manualmente, `$articulo->delete()`, responde JSON o redirige con flash) en `app/Http/Controllers/ArticuloController.php`.
- [X] T029 [US4] En `articulos-datatable.init.js`/`articulos-modal.init.js`, añadir botón de eliminar con confirmación previa (SweetAlert, ya vendorizado) y `$.ajax` DELETE + refresco de la tabla.

**Checkpoint**: CRUD completo; todas las historias funcionan de forma independiente.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Cierre, datos demo y validación final.

- [X] T030 [P] Crear `ArticuloSeeder` en `database/seeders/ArticuloSeeder.php` con artículos demo (producto con/sin stock, servicio) para el tenant demo e invocarlo desde `DatabaseSeeder` (idempotente).
- [X] T031 [P] Verificar/ajustar mensajes de validación en español para todos los campos, incluido el mensaje de tipo impositivo fuera de régimen (ver contracts/articulos-routes.md).
- [X] T032 Ejecutar la suite completa (`php artisan test`) y la validación manual de `quickstart.md` (login, listado, alta con ambos regímenes, edición, borrado, aislamiento); cronometrar un alta completa (SC-003: menos de 1 minuto).
- [X] T033 Revisar cierre de spec (CLAUDE.md): confirmar si algo cambió respecto a `docs/03-modelo-datos.md` (se añadió `regimen_impositivo` a `tenants`, ya documentado — verificar que no haga falta actualizar el doc); si el principio II se ve afectado, considerar `/speckit-constitution`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias — puede empezar ya.
- **Foundational (Phase 2)**: depende de Setup. BLOQUEA las historias.
- **User Stories (Phase 3–6)**: dependen de Foundational completo. US1 es el MVP.
- **Polish (Phase 7)**: depende de las historias deseadas completas.

### User Story Dependencies

- **US1 (P1)**: tras Foundational. Base del MVP.
- **US2 (P1)**: tras Foundational y tras T015 (US1), porque el formulario de alta vive dentro del modal de `index.blade.php`. No depende del resto de US1.
- **US3 (P2)**: tras Foundational, T015 (US1) y T021 (US2) — reutiliza el mismo modal/formulario y solo añade los `data-*` de precarga.
- **US4 (P3)**: tras Foundational. Reutiliza `index.blade.php` de US1 (T015) para el botón de borrado.

### Within Each User Story

- Los tests se escriben y FALLAN antes de implementar.
- Form Requests antes de los métodos del controller que los usan.
- Métodos del controller antes/junto con sus vistas/JS.

### Parallel Opportunities

- T001, T002, T003 en paralelo (Setup).
- T008, T010 en paralelo; T004→T005, T006→T007→(T008,T009).
- Tests marcados [P] de cada historia en paralelo entre sí.
- Como en `002-clientes-crm`, al usar un modal único, US2 y US3 dependen de `index.blade.php`/modal de US1 (T015, T021), así que no son 100% paralelizables entre sí. US1 debe ir primero (o al menos T015) antes de arrancar US2/US3/US4 en paralelo.

---

## Parallel Example: User Story 2

```bash
# Tests de US2 junto con el test de régimen fiscal (Foundational):
Task: "T012 tests de régimen fiscal en tests/Feature/ArticuloCrudTest.php"
Task: "T018 store tests en tests/Feature/ArticuloCrudTest.php"

# Implementación paralelizable (requiere T015 de US1 ya hecho):
Task: "StoreArticuloRequest en app/Http/Requests/StoreArticuloRequest.php"
Task: "_form.blade.php dentro de #articuloModal en resources/views/articulos/index.blade.php"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 (Setup) → Phase 2 (Foundational, incluye tests de aislamiento y régimen fiscal test-first) → Phase 3 (US1).
2. **STOP y VALIDAR**: listado aislado + métricas + responsive funcionando.
3. Demo del MVP.

### Incremental Delivery

Foundational → US1 (MVP) → US2 (alta, con validación de régimen fiscal) → US3 (edición) → US4 (borrado) → Polish. Cada historia añade valor sin romper las anteriores.

---

## Notes

- [P] = archivos distintos, sin dependencias pendientes.
- Test-first obligatorio en aislamiento (T011) y en validación de régimen fiscal (T012) — Principio II y IV de la constitución.
- Verificar que los tests fallan antes de implementar.
- Commit tras cada tarea o grupo lógico.
- Patrón AJAX desde el inicio (aprendido de `002-clientes-crm`): controller responde JSON cuando `wantsJson()`, JS usa `$.ajax`/DataTables `ajax` en vez de recargar página — no repetir la corrección post-hoc de la feature anterior.
- No introducir el sistema `config/dz.php` del template (prohibido por CLAUDE.md); assets por `@push`.
