---
description: "Task list for feature 007 — Panel Super Admin y resolución de tenant por dominio"
---

# Tasks: Panel Super Admin — Gestión de Tenants por Dominio

**Input**: Design documents from `/specs/007-super-admin-tenants/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/http.md, quickstart.md

**Tests**: Test-first OBLIGATORIO en las áreas críticas del Principio IV de la constitución
(resolución de tenant por dominio, aislamiento entre tenants, gate login↔dominio, regla de borrado
por facturas emitidas). El resto (UI/CRUD no crítico) puede seguir un flujo de test más flexible.

**Organization**: Tareas agrupadas por historia de usuario para implementación/testeo independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ir en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1..US5 (mapea a las historias del spec)

## Path Conventions

Monolito Laravel (repo root): `app/`, `resources/views/`, `routes/`, `database/migrations/`,
`tests/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: preparar la base de dominios (idiomática de stancl) sobre la que se apoya todo.

- [X] T001 Verificar/ajustar `config/tenancy.php`: `central_domains` incluye el/los host(s) central(es) de dev y producción (p. ej. `localhost`, `127.0.0.1`, dominio central real); confirmar `domain_model => Domain::class`.
- [X] T002 Crear migración de la tabla `domains` (grupo central) en `database/migrations/2026_07_xx_xxxxxx_create_domains_table.php` según data-model.md (id, `domain` único, `tenant_id` fk cascade, timestamps).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: infraestructura que TODAS las historias necesitan: relación Tenant↔Domain,
normalización de dominio y estructura de rutas/guard del área super_admin.

**⚠️ CRITICAL**: ninguna historia puede completarse hasta terminar esta fase.

- [X] T003 [P] Añadir relación `domains()` (hasMany) y accesor de dominio único a `app/Models/Tenant.php` (usar trait `HasDomains` de stancl o relación explícita), sin romper `getCustomColumns()`/hook `created` existentes.
- [X] T004 [P] Crear helper de normalización de dominio en `app/Support/DominioTenant.php` (trim, minúsculas, quitar esquema `http(s)://`, quitar path/query y barra final) — D6.
- [X] T005 Crear middleware `app/Http/Middleware/EnsureSuperAdmin.php` (auth + rol super_admin + `tenant_id` null + host en `central_domains`; si no, 403 o redirect a login) y registrar su alias (`super_admin`) en `bootstrap/app.php`.
- [X] T006 Registrar el grupo de rutas `super_admin` en `routes/web.php` (prefijo `super_admin`, middleware `EnsureSuperAdmin`, restringido al dominio central) con las 6 rutas de contracts/http.md apuntando a `SuperAdmin\TenantController`.

**Checkpoint**: base de dominios + guard + rutas listos.

---

## Phase 3: User Story 1 — Identificación del tenant por dominio (Priority: P1) 🎯 MVP

**Goal**: el host de la petición determina el tenant activo; login validado contra el dominio;
host desconocido → 404 controlado.

**Independent Test**: dos tenants con dominios distintos; acceder por cada dominio resuelve el
tenant correcto sin fuga; usuario de un tenant no entra por el dominio de otro.

### Tests for User Story 1 (test-first, Principio IV) ⚠️

> Escribir primero y verlos FALLAR antes de implementar.

- [X] T007 [P] [US1] Test de resolución por host en `tests/Feature/TenantDomainResolutionTest.php`: host de tenant A → contexto = A; host de tenant B → contexto = B; ≥2 tenants con datos propios, sin fuga (aislamiento).
- [X] T008 [P] [US1] Test en el mismo archivo: host no central y sin registro en `domains` → 404/abort, sin exponer datos.
- [X] T009 [P] [US1] Test de gate login↔dominio en `tests/Feature/TenantDomainResolutionTest.php`: usuario de B logueándose desde dominio de A → rechazado; desde dominio de B → OK; super_admin solo válido en dominio central.

### Implementation for User Story 1

- [X] T010 [US1] Modificar `app/Http/Middleware/SetTenantContext.php` para resolver el tenant por host (lookup en `domains` vía helper T004/relación T003): host central → contexto central sin tenant; host de tenant activo → `tenancy()->initialize($tenant)`; tenant inactivo → logout/redirect (comportamiento actual de `activo`); host desconocido no central → abort(404). Conservar headers no-cache existentes.
- [X] T011 [US1] Modificar `app/Http/Controllers/Auth/LoginController.php`: si hay tenant resuelto por dominio, exigir `user.tenant_id == tenant->id` (si no coincide, tratar como no autenticable, análogo a `tenantIsUsable`); super_admin (`tenant_id` null) solo válido en dominio central.
- [X] T012 [US1] Verticar que los tests T007–T009 pasan (verde) y que el login existente de la feature 001 sigue funcionando con el nuevo mecanismo (1 tenant + su dominio).

**Checkpoint**: la resolución por dominio funciona de forma aislada; base para el resto de historias.

---

## Phase 4: User Story 2 — Listar tenants (Priority: P1)

**Goal**: el super_admin ve el listado de tenants (nombre comercial, dominio, NIF, estado) desde el
dominio central; nadie más accede.

**Independent Test**: con varios tenants, el panel los lista todos; roles no super_admin y no
autenticados son denegados.

### Tests for User Story 2 (test-first en el guard) ⚠️

- [X] T013 [P] [US2] Test de guard en `tests/Feature/SuperAdmin/TenantCrudTest.php`: no autenticado → redirect login; usuario de tenant (no super_admin) → 403; super_admin en dominio central → 200 y ve la lista.

### Implementation for User Story 2

- [X] T014 [US2] Crear `app/Http/Controllers/SuperAdmin/TenantController.php` con `index()` (contexto central, lista tenants + su dominio; sin global scope de tenant).
- [X] T015 [US2] Crear vista `resources/views/super_admin/tenants/index.blade.php`: DataTable con columnas nombre/dominio/NIF/estado + columna Acciones como **dropdown único** (docs/04-front-guidelines.md), override de paginación vertical, cards de métricas opcionales.
- [X] T016 [P] [US2] Crear el init JS de la DataTable en `public/js/plugins-init/super-admin-tenants-datatable.init.js` (patrón `clientes-datatable.init.js`: `renderAcciones()` con dropdown, `window.confirmDelete` para borrado).

**Checkpoint**: listado accesible solo por super_admin en dominio central.

---

## Phase 5: User Story 3 — Crear tenant (Priority: P1)

**Goal**: alta de tenant con dominio único + datos fiscales; el dominio queda operativo al instante.

**Independent Test**: crear tenant con dominio no usado → aparece en la lista y su dominio resuelve;
dominio duplicado/ inválido → rechazo.

### Tests for User Story 3 (test-first en unicidad/creación) ⚠️

- [X] T017 [P] [US3] Test en `tests/Feature/SuperAdmin/TenantCrudTest.php`: alta OK crea `tenants` + 1 fila en `domains`; dominio duplicado → 422; dominio normalizado (casing/espacios/esquema) tratado como el mismo.

### Implementation for User Story 3

- [X] T018 [P] [US3] Crear `app/Http/Requests/SuperAdmin/StoreTenantRequest.php` (dominio requerido/único/normalizado/formato válido; nombre_comercial, razon_social, nif [+ `NifEspanol`], regimen_impositivo [enum], email; `activo` default true) — data-model.md.
- [X] T019 [US3] Implementar `create()` y `store()` en `TenantController` (contexto central): normaliza dominio, crea `Tenant` + su `Domain` en transacción; flash `success`; conserva el hook `Tenant::created` (serie F).
- [X] T020 [US3] Crear vista `resources/views/super_admin/tenants/form.blade.php` (alta/edición): campo dominio + datos fiscales, controles "sm", sin `mb-3` en columnas del `.row`, errores inline + toastr.

**Checkpoint**: super_admin puede dar de alta tenants operativos.

---

## Phase 6: User Story 4 — Editar tenant (Priority: P2)

**Goal**: modificar datos del tenant y su dominio (unicidad ignorando el propio); activar/desactivar.

**Independent Test**: cambiar nombre/dominio se refleja; nuevo dominio resuelve y el anterior no;
dominio de otro tenant → rechazo; desactivar bloquea login de sus usuarios.

### Tests for User Story 4 ⚠️

- [X] T021 [P] [US4] Test en `tests/Feature/SuperAdmin/TenantCrudTest.php`: update de dominio a uno libre OK (anterior deja de resolver); a uno de otro tenant → 422; `activo=false` bloquea login de sus usuarios.

### Implementation for User Story 4

- [X] T022 [P] [US4] Crear `app/Http/Requests/SuperAdmin/UpdateTenantRequest.php` (igual que Store pero unicidad de dominio ignorando el dominio actual del tenant).
- [X] T023 [US4] Implementar `edit()` y `update()` en `TenantController`: actualiza `tenants` y/o su registro `domains` (normalizado); soporta toggle `activo`; flash `success`. Reutiliza `form.blade.php` (T020).

**Checkpoint**: edición completa, incluida activación/desactivación.

---

## Phase 7: User Story 5 — Eliminar tenant (Priority: P2)

**Goal**: eliminar tenant sin facturas emitidas; bloquear (ofrecer desactivar) si las tiene.

**Independent Test**: tenant sin facturas → se elimina y su dominio deja de resolver; tenant con
factura `emitida` → se impide, ninguna factura se pierde.

### Tests for User Story 5 (test-first, Principio II) ⚠️

- [X] T024 [P] [US5] Test en `tests/Feature/SuperAdmin/TenantCrudTest.php`: destroy de tenant con factura `emitida` → bloqueado (302 flash error / 409), tenant y facturas intactos; destroy de tenant sin facturas → eliminado + su `domains` borrado (cascade).

### Implementation for User Story 5

- [X] T025 [US5] Implementar `destroy()` en `TenantController`: consultar facturas `emitida` del tenant objetivo con filtro EXPLÍCITO por `tenant_id` (sin depender del global scope, contexto central — D5); si hay → impedir + flash `error` ofreciendo desactivar; si no → eliminar (cascade borra dominio) + flash `success`.
- [X] T026 [US5] En `index.blade.php`/init JS: la acción Eliminar del dropdown usa `window.confirmDelete` (modal genérico, nunca `confirm()` nativo) — docs/04-front-guidelines.md.

**Checkpoint**: ciclo CRUD completo respetando inmutabilidad fiscal.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T027 [P] Añadir enlace/entrada de navegación al panel super_admin (solo visible para super_admin) donde corresponda (sidebar o menú del dominio central).
- [X] T028 [P] Ejecutar la guía `specs/007-super-admin-tenants/quickstart.md` (escenarios 1–7) y dejar constancia.
- [X] T029 Revisar docs tras cierre: si cambió el modelo/arquitectura (nueva tabla `domains`, resolución por dominio), actualizar `docs/01-arquitectura.md` y `docs/03-modelo-datos.md`; evaluar si procede `/speckit-constitution` (CLAUDE.md "Al cerrar un spec").
- [X] T030 `php artisan test` completo en verde (incl. suites de features previas — no romper login/tenant existentes).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup. BLOQUEA todas las historias.
- **US1 (Phase 3)**: depende de Foundational. Es la base de contexto para US2–US5.
- **US2 (Phase 4)**: depende de Foundational (guard/rutas). Independientemente testeable.
- **US3 (Phase 5)**: depende de Foundational; usa la vista `form` que crea. Reforzada por US1 para
  verificar "dominio operativo".
- **US4 (Phase 6)**: depende de US3 (reutiliza `form.blade.php` y patrón de controller).
- **US5 (Phase 7)**: depende de US2 (dropdown de acciones en el index) y de existir tenants.
- **Polish (Phase 8)**: tras las historias deseadas.

### Within Each User Story

- Tests críticos (US1, y unicidad/borrado en US3/US5) se escriben y FALLAN antes de implementar.
- Modelos/relaciones → requests → controller → vista → JS.

### Parallel Opportunities

- T003/T004 en paralelo (archivos distintos).
- Tests marcados [P] de una misma historia en paralelo.
- T016 (JS) en paralelo con la vista una vez definido el contrato de columnas.
- Requests (T018, T022) en paralelo con sus vistas.

---

## Parallel Example: User Story 1

```bash
# Tests primero (deben fallar):
Task: "T007 resolución por host en tests/Feature/TenantDomainResolutionTest.php"
Task: "T008 host desconocido → 404 en el mismo archivo"
Task: "T009 gate login↔dominio en el mismo archivo"
```

---

## Implementation Strategy

### MVP First (US1 + US2 + US3 — todas P1)

1. Phase 1 Setup → Phase 2 Foundational.
2. US1 (resolución por dominio) → validar aislamiento y login.
3. US2 (listar) + US3 (crear) → panel super_admin operativo mínimo: dar de alta y ver tenants con
   su dominio funcionando.
4. **STOP & VALIDATE**: quickstart escenarios 1–5.

### Incremental Delivery

- +US4 (editar/desactivar) → validar → demo.
- +US5 (eliminar con regla de facturas) → validar → demo.

---

## Notes

- El área super_admin es la ÚNICA que opera fuera del scope de tenant (excepción explícita del
  Principio I). Toda consulta de datos de un tenant concreto desde el panel filtra `tenant_id`
  explícitamente.
- No reintroducir alerts Bootstrap ad-hoc: flash → toastr (CLAUDE.md). Confirmación de borrado con
  el modal genérico, nunca `confirm()`.
- Commit tras cada tarea o grupo lógico. Verificar que los tests fallan antes de implementar en las
  áreas test-first.
