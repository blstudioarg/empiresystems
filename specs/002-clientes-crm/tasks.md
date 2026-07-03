---
description: "Task list for feature 002-clientes-crm"
---

# Tasks: Gestión de Clientes (CRM)

**Input**: Design documents from `/specs/002-clientes-crm/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/clientes-routes.md, quickstart.md

**Tests**: INCLUIDOS. La constitución (Principio IV) exige test-first para aislamiento multi-tenant;
se añaden además tests de CRUD/validación y unit test de la regla de NIF.

**Organization**: Tareas agrupadas por historia de usuario para implementación/validación independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede correr en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1 (listar), US2 (alta), US3 (editar), US4 (eliminar)
- Rutas de archivo exactas en cada tarea.

## Path Conventions

Monolito Laravel: `app/`, `resources/views/`, `database/`, `routes/`, `public/`, `tests/Feature/`,
`tests/Unit/` en la raíz del repo (según plan.md).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Trasplantar assets del template y preparar el terreno.

- [X] T001 [P] Trasplantar assets DataTables desde `template/Laravel-NexaDash-v1.0-28_May_2025/package/public/vendor/datatables/` a `public/vendor/datatables/` (carpetas `css/`, `images/`, `js/jquery.dataTables.min.js` y `responsive/responsive.css` + `responsive/responsive.js`); verificar rutas de `sprintf`/imágenes de orden.
- [X] T002 [P] Crear el init propio de la tabla en `public/js/plugins-init/clientes-datatable.init.js` inicializando `#clientes-table` con `responsive: true`, paginación/búsqueda/orden y `language` en español (no reutilizar el `datatables.init.js` demo del template).
- [X] T002a [P] Crear `public/js/plugins-init/clientes-modal.init.js`: al hacer clic en "Editar", lee los atributos `data-*` de la fila y rellena `#clienteModal` (campos de `_form.blade.php`), cambia el `action` del formulario a la URL de `update` e inserta `@method('PUT')`, luego abre el modal (`bootstrap.Modal`); al hacer clic en "Agregar cliente", limpia el modal y restaura `action`/método de `store`; si la página carga con errores de validación (`$errors->any()` detectable vía un flag en el DOM), reabre el modal automáticamente (ver research D10).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelo, enum, migración, regla de validación, factory, rutas y controlador base que TODAS las historias necesitan.

**⚠️ CRITICAL**: Ninguna historia puede completarse hasta terminar esta fase.

- [X] T003 [P] Crear enum `App\Enums\TipoCliente` (`Empresa='empresa'`, `Particular='particular'`) en `app/Enums/TipoCliente.php`.
- [X] T004 Crear migración `create_clientes_table` en `database/migrations/2026_07_02_xxxxxx_create_clientes_table.php` con todos los campos y índices `(tenant_id, nif)` y `(tenant_id, nombre)` (ver data-model.md); `softDeletes` + `timestamps`.
- [X] T005 Crear modelo `App\Models\Cliente` en `app/Models/Cliente.php` con traits `BelongsToTenant`, `SoftDeletes`, `HasFactory`; `$fillable`, casts (`tipo`→TipoCliente, `aplica_recargo_equivalencia`→bool, decimales→`decimal:2`) y relación `tenant()` (depende de T003, T004).
- [X] T006 [P] Crear regla `App\Rules\NifEspanol` en `app/Rules/NifEspanol.php` validando formato DNI/NIF, NIE (X/Y/Z) y CIF con dígito/letra de control.
- [X] T007 [P] Crear `ClienteFactory` en `database/factories/ClienteFactory.php` con estados `empresa()` y `particular()` (depende de T005).
- [X] T008 Crear `App\Http\Controllers\ClienteController` (esqueleto con métodos index/store/update/destroy — **sin** create/edit, ver research D10) en `app/Http/Controllers/ClienteController.php` y registrar `Route::resource('clientes', ClienteController::class)->only(['index','store','update','destroy'])` dentro del grupo `['auth','tenant.context']` en `routes/web.php` (depende de T005).
- [X] T009 [P] Añadir el ítem "Clientes" (con icono SVG del set del template, enlace a `route('clientes.index')`) en `resources/views/partials/sidebar.blade.php`.

### Test crítico de aislamiento (test-first, Principio IV) ⚠️

> Escribir ANTES de implementar la lógica de las historias; debe FALLAR primero.

- [X] T010 [US1] Escribir `tests/Feature/ClienteTenantIsolationTest.php`: con 2 tenants + sus usuarios, afirmar que (a) el índice de A no incluye clientes de B, (b) crear como A asigna el `tenant_id` de A, (c) `edit`/`update`/`destroy` sobre un id de B devuelve 404 para el usuario de A. (depende de T005–T008; debe fallar antes de US1–US4)

**Checkpoint**: Fundamentos listos — las historias pueden implementarse.

---

## Phase 3: User Story 1 - Ver el listado de clientes del tenant (Priority: P1) 🎯 MVP

**Goal**: Pantalla "Clientes" con 3 cartas de métricas del tenant y tabla DataTables responsive listando los clientes del tenant.

**Independent Test**: Con 2 tenants con clientes distintos, entrar como usuario de A y ver solo los clientes de A, con métricas correctas y tabla usable en escritorio y móvil.

### Tests for User Story 1 ⚠️

- [X] T011 [P] [US1] En `tests/Feature/ClienteCrudTest.php`, test de `index`: devuelve 200, muestra solo clientes del tenant activo y las métricas (total/empresas/particulares) coinciden con los datos sembrados. (debe fallar primero)

### Implementation for User Story 1

- [X] T012 [US1] Implementar `ClienteController@index` en `app/Http/Controllers/ClienteController.php`: cargar clientes del tenant y calcular métricas `total`/`empresas`/`particulares` (counts server-side), pasar a la vista.
- [X] T013 [P] [US1] Crear vista `resources/views/clientes/index.blade.php` (extiende `layouts.app`): 3 cartas adaptadas del bloque `depostit-card`/`same-card` de `crm.blade.php` + tabla `<table id="clientes-table" class="display responsive nowrap">` con columnas Nombre/Razón social · Tipo · NIF · Email · Teléfono · Ciudad · Acciones; botón "Agregar cliente"; estado vacío. Incluye el modal `#clienteModal` (markup base tipo `ui-modal.blade.php` del template: `modal fade` > `modal-dialog` > `modal-content` con header/body/footer) que por ahora queda vacío (el formulario lo aporta T019).
- [X] T014 [US1] En `clientes/index.blade.php`, cargar los assets DataTables vía `@push('styles')` (css + responsive.css) y `@push('scripts')` (jquery.dataTables.min.js, responsive.js, clientes-datatable.init.js, clientes-modal.init.js) (depende de T001, T002, T002a, T013).

**Checkpoint**: US1 funcional y testeable de forma independiente (listado + métricas + responsive + aislamiento).

---

## Phase 4: User Story 2 - Alta de un nuevo cliente (Priority: P1)

**Goal**: Crear clientes (empresa/particular) con validación server-side; quedan asociados al tenant y aparecen en el listado.

**Independent Test**: Como usuario de A, crear un cliente válido y verlo en el listado de A; datos inválidos → errores por campo sin crear registro.

### Tests for User Story 2 ⚠️

- [X] T015 [P] [US2] En `tests/Feature/ClienteCrudTest.php`, tests de `store`: alta válida (empresa y particular) crea el registro con el tenant activo; alta inválida (sin `nombre`, email mal, empresa sin `razon_social`/`nif`) no crea y devuelve errores; NIF con formato inválido rechazado; NIF duplicado en el mismo tenant rechazado; mismo NIF permitido en tenant distinto; **tras el alta, las métricas de `index` (total/empresas/particulares) reflejan el nuevo conteo (FR-012/SC-004)**. (debe fallar primero)
- [X] T016 [P] [US2] Unit test `tests/Unit/NifEspanolTest.php` con casos válidos/inválidos de DNI, NIE y CIF. (debe fallar primero)

### Implementation for User Story 2

- [X] T017 [P] [US2] Crear `App\Http\Requests\StoreClienteRequest` en `app/Http/Requests/StoreClienteRequest.php`: reglas de data-model.md (required condicionales por `tipo`, `NifEspanol`, unicidad NIF por tenant con `Rule::unique(...)->where('tenant_id', ...)`, rangos 0–100) (depende de T006).
- [X] T018 [US2] Implementar `ClienteController@store` (valida con StoreClienteRequest, crea el cliente, redirige a `clientes.index` con flash de éxito; en caso de error, `back()->withErrors()->withInput()`) en `app/Http/Controllers/ClienteController.php`.
- [X] T019 [P] [US2] Crear parcial de campos `resources/views/clientes/_form.blade.php` (todos los campos, `old()`, errores por campo, selector `tipo`, campo oculto para distinguir alta/edición) e incluirlo dentro de `#clienteModal` en `clientes/index.blade.php` (form apuntando por defecto a `clientes.store`) (depende de T013).

**Checkpoint**: US1 + US2 funcionan de forma independiente (listar + alta con validación).

---

## Phase 5: User Story 3 - Edición de un cliente existente (Priority: P2)

**Goal**: Editar datos de un cliente del tenant con validación; cross-tenant → 404.

**Independent Test**: Como usuario de A, editar un cliente de A y ver los cambios; no poder editar uno de B (404).

### Tests for User Story 3 ⚠️

- [X] T020 [P] [US3] En `tests/Feature/ClienteCrudTest.php`, tests de `edit`/`update`: edición válida persiste; inválida devuelve errores; unicidad de NIF ignora el propio cliente; acceder a un cliente de otro tenant devuelve 404. (debe fallar primero)

### Implementation for User Story 3

- [X] T021 [P] [US3] Crear `App\Http\Requests\UpdateClienteRequest` en `app/Http/Requests/UpdateClienteRequest.php` (reglas iguales a Store pero `Rule::unique(...)->ignore($cliente)`) (depende de T006).
- [X] T022 [US3] Implementar `ClienteController@update` (recibe el cliente por route binding bajo el scope de tenant — 404 cross-tenant automático —, valida con UpdateClienteRequest, actualiza, redirige a `clientes.index` con flash; en caso de error, `back()->withErrors()->withInput()`) en `app/Http/Controllers/ClienteController.php`.
- [X] T023 [P] [US3] En cada fila de `clientes/index.blade.php`, añadir al botón "Editar" los atributos `data-*` con todos los campos del cliente (id, tipo, nombre, razón social, NIF, dirección, cp, ciudad, provincia, país, email, teléfono, recargo, IRPF, tipo impositivo, notas) para que `clientes-modal.init.js` (T002a) precargue `#clienteModal` en modo edición (depende de T013, T019).

**Checkpoint**: US1 + US2 + US3 funcionan de forma independiente.

---

## Phase 6: User Story 4 - Eliminación (soft delete) de un cliente (Priority: P2)

**Goal**: Borrado lógico con confirmación; el cliente desaparece del listado pero se conserva en DB; cross-tenant → 404.

**Independent Test**: Como usuario de A, eliminar un cliente → desaparece del listado y baja el conteo; el registro queda con `deleted_at`; no poder borrar uno de B (404).

### Tests for User Story 4 ⚠️

- [X] T024 [P] [US4] En `tests/Feature/ClienteCrudTest.php`, tests de `destroy`: borrado deja `deleted_at` no nulo, el cliente ya no aparece en `index`, borrar un cliente de otro tenant devuelve 404, y **tras el borrado las métricas de `index` bajan en 1 (FR-012/SC-004)**. (debe fallar primero)

### Implementation for User Story 4

- [X] T025 [US4] Implementar `ClienteController@destroy` (`$cliente->delete()` bajo scope de tenant, redirige a `clientes.index` con flash) en `app/Http/Controllers/ClienteController.php`.
- [X] T026 [US4] En `resources/views/clientes/index.blade.php`, añadir en cada fila el form/botón de eliminar (DELETE con method spoofing + CSRF) y confirmación previa con SweetAlert (vendor del template) o `confirm()` como fallback (depende de T013).

**Checkpoint**: CRUD completo; todas las historias funcionan de forma independiente.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Cierre, datos demo y validación final.

- [X] T027 [P] Crear `ClienteSeeder` en `database/seeders/ClienteSeeder.php` con algunos clientes demo (empresa y particular) para el tenant demo e invocarlo desde `DatabaseSeeder` (idempotente).
- [X] T028 [P] Verificar/ajustar mensajes de validación en español (archivo `lang/es` o mensajes en los Form Requests) para todos los campos y para `NifEspanol`.
- [X] T029 Ejecutar la suite completa (`php artisan test`) y la validación manual de `quickstart.md` (login, listado, alta, edición, borrado, aislamiento en móvil y por URL); **cronometrar un alta completa de cliente (SC-002: debe tomar menos de 2 minutos desde que se abre la pantalla)**.
- [X] T030 Revisar cierre de spec (CLAUDE.md): confirmar si algo cambió respecto a `docs/03-modelo-datos.md` (no debería); actualizar docs solo si hubo desvío.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias — puede empezar ya.
- **Foundational (Phase 2)**: depende de Setup (para los assets no, pero conviene). BLOQUEA las historias.
- **User Stories (Phase 3–6)**: dependen de Foundational completo. US1 es el MVP.
- **Polish (Phase 7)**: depende de las historias deseadas completas.

### User Story Dependencies

- **US1 (P1)**: tras Foundational. Base del MVP.
- **US2 (P1)**: tras Foundational y **tras T013** (US1), porque el formulario de alta vive dentro del modal de `index.blade.php`. No depende del resto de US1.
- **US3 (P2)**: tras Foundational, T013 (US1) y T019 (US2) — reutiliza el mismo modal/formulario y solo añade los `data-*` de precarga.
- **US4 (P2)**: tras Foundational. Reutiliza `index.blade.php` de US1 (T013) para el botón de borrado.

### Within Each User Story

- Los tests se escriben y FALLAN antes de implementar.
- Form Requests antes de los métodos del controller que los usan.
- Métodos del controller antes/junto con sus vistas.

### Parallel Opportunities

- T001, T002, T002a en paralelo (Setup).
- T003, T006, T007, T009 en paralelo (archivos distintos); T004→T005→(T007,T008).
- Tests marcados [P] de cada historia en paralelo entre sí.
- Con equipo: **al usar un modal único, US2 y US3 dependen del `index.blade.php`/modal de US1** (T013,
  T019), así que no son 100% paralelizables entre sí como en un diseño de páginas separadas. US1
  debe ir primero (o al menos T013) antes de arrancar US2/US3/US4 en paralelo.

---

## Parallel Example: User Story 2

```bash
# Tests de US2 juntos:
Task: "store tests en tests/Feature/ClienteCrudTest.php"
Task: "unit test en tests/Unit/NifEspanolTest.php"

# Implementación paralelizable (requiere T013 de US1 ya hecho):
Task: "StoreClienteRequest en app/Http/Requests/StoreClienteRequest.php"
Task: "_form.blade.php dentro de #clienteModal en resources/views/clientes/index.blade.php"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 (Setup) → Phase 2 (Foundational, incluye test de aislamiento test-first) → Phase 3 (US1).
2. **STOP y VALIDAR**: listado aislado + métricas + responsive funcionando.
3. Demo del MVP.

### Incremental Delivery

Foundational → US1 (MVP) → US2 (alta) → US3 (edición) → US4 (borrado) → Polish. Cada historia
añade valor sin romper las anteriores.

---

## Notes

- [P] = archivos distintos, sin dependencias pendientes.
- Test-first obligatorio en aislamiento (T010) y recomendado en el resto del CRUD.
- Verificar que los tests fallan antes de implementar.
- Commit tras cada tarea o grupo lógico.
- No introducir el sistema `config/dz.php` del template (prohibido por CLAUDE.md); assets por `@push`.

---

## Post-implementación: corrección a AJAX (research.md D11)

Tras completar T001–T030, el usuario pidió explícitamente que alta/edición/borrado no recarguen la
página (corrige D10, que había descartado AJAX). Cambios aplicados:

- [X] Controller (`store`/`update`/`destroy`) responde JSON cuando `$request->wantsJson()`.
- [X] `clientes-modal.init.js` reescrito: `submit`/`delete` por `$.ajax`, errores 422 inyectados
      inline sin cerrar el modal, `refreshListado()` reemplaza `#clientes-cards`/`#clientes-table-body`
      vía `GET` a `index` (sin navegar).
- [X] `clientes-datatable.init.js` expone `window.initClientesDataTable()` (reutilizado tras refresh).
- [X] Extraído `clientes/_row.blade.php` (única fuente de verdad del markup de fila).
- [X] `_form.blade.php` simplificado: sin `old()`/`@error()`, contenedores `[data-error-for]` vacíos.
- [X] 4 tests nuevos en `ClienteCrudTest` (`postJson`/`putJson`/`deleteJson`) verificando JSON 201/200
      y 422 con errores. Suite completa: 53/53 tests en verde.
