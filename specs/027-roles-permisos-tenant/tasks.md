# Tasks: Sistema de roles y permisos por tenant

**Input**: Design documents from `specs/027-roles-permisos-tenant/`
**Prerequisites**: plan.md, research.md, data-model.md, contracts/http.md, quickstart.md

**Tests**: la constitución (Principio IV) exige test-first para aislamiento multi-tenant,
enforcement de acceso, provisión atómica y anti-lockout — esas tareas van ANTES de su
implementación y deben fallar primero. La UI (datatable/modal) sigue flujo flexible.

**Organización**: por user story para permitir implementación y test independientes.

## Phase 1: Setup

- [ ] T001 Crear rama `027-roles-permisos-tenant` desde el estado actual (cerrar/commitear antes el trabajo pendiente de la 026 con el usuario)
- [ ] T002 Instalar `spatie/laravel-permission:^6` vía composer y publicar `config/permission.php` + migración `create_permission_tables`
- [ ] T003 Configurar `config/permission.php`: `'teams' => true`, `'team_foreign_key' => 'tenant_id'`; ajustar la migración publicada en `database/migrations/` para que la columna team sea `tenant_id` BIGINT con FK a `tenants.id` `cascadeOnDelete` (research.md D1, data-model.md)
- [ ] T004 Correr `php artisan migrate` y verificar tablas `permissions`, `roles`, `model_has_roles`, `role_has_permissions` con `tenant_id` donde corresponde

## Phase 2: Foundational (bloquea todas las user stories)

- [ ] T005 [P] Crear `app/Support/CatalogoPermisos.php`: catálogo estático de los 17 permisos con clave, etiqueta y módulo según la tabla de research.md D3; métodos `todos()`, `porModulo()`, `claves()`
- [ ] T006 [P] Test unitario en `tests/Unit/CatalogoPermisosTest.php`: el catálogo expone las 17 claves, agrupación por módulo correcta, sin claves duplicadas (test-first: escribir antes que T005 esté cerrado si se trabaja en paralelo)
- [ ] T007 Crear `database/seeders/PermisosSeeder.php` idempotente: `firstOrCreate` por clave del catálogo + `forgetCachedPermissions()`; tras sembrar, sincronizar el rol "Administrador" de cada tenant existente con el catálogo completo (RN-04, research.md D6.3)
- [ ] T008 Test de idempotencia del seeder en `tests/Feature/PermisosSeederTest.php`: correr 2 veces no duplica; permiso nuevo en catálogo llega al rol Administrador de cada tenant pero NO a otros roles (edge case de spec) — escribir ANTES de dar por cerrado T007
- [ ] T009 Añadir trait `HasRoles` a `app/Models/User.php`
- [ ] T010 Modificar `app/Http/Middleware/SetTenantContext.php`: `setPermissionsTeamId($tenant->getTenantKey())` tras `tenancy()->initialize()`; `setPermissionsTeamId(null)` en la rama de contexto central (research.md D2)
- [ ] T011 Modificar `app/Providers/AppServiceProvider.php`: añadir `Gate::before` con bypass para `isSuperAdmin()` (research.md D4); mantener por ahora `gestiona-fichajes` (se elimina en T031)
- [ ] T012 Crear `app/Support/ProvisionadorRoles.php`: `provisionarAdministrador(Tenant $t, ?User $admin)` — crea rol "Administrador" del tenant con todos los permisos y lo asigna; maneja el seteo/restauración temporal de `setPermissionsTeamId` para contexto central (research.md D6.1); y `provisionarUsuarioBase(Tenant $t)` para el rol "Usuario"

**Checkpoint**: base spatie operativa — las user stories pueden arrancar.

## Phase 3: User Story 1 — Control de acceso por vista según rol (P1) 🎯 MVP

**Goal**: usuarios solo ven en el sidebar y solo acceden por ruta a las secciones permitidas; super admin sin restricciones.

**Independent Test**: dos usuarios del mismo tenant con permisos distintos → sidebar distinto y 403 en URL directa no permitida (quickstart.md §2.3).

### Tests (test-first — deben fallar primero)

- [ ] T013 [P] [US1] Test en `tests/Feature/SidebarPermisosTest.php`: usuario con `ver-clientes` sin `ver-facturas` ve "Clientes" y no ve "Facturas" en el HTML del sidebar; grupo sin entradas visibles no se renderiza; secciones personales (fichar, mi-jornada, perfil) siempre visibles
- [ ] T014 [P] [US1] Test de enforcement en `tests/Feature/RutasPermisosTest.php`: por cada permiso del catálogo, GET a su ruta índice sin el permiso → 403 (HTML y JSON), con el permiso → 200; usuario sin ningún rol → 403 en todas las secciones de gestión y 200 en fichar/mi-jornada/perfil
- [ ] T015 [P] [US1] Test de aislamiento en `tests/Feature/RolesAislamientoTest.php` (≥2 tenants, Principio I): rol creado en tenant A no existe para B (`Role::where` con team context B), usuario de B no puede recibir rol de A, permisos efectivos no cruzan tenants
- [ ] T016 [P] [US1] Test super admin en `tests/Feature/SuperAdminBypassTest.php`: super admin central pasa cualquier `can:` (Gate::before) y las rutas `super_admin.*` siguen exigiendo `EnsureSuperAdmin`

### Implementation

- [ ] T017 [US1] Refactor `routes/web.php`: envolver cada sección en `->middleware('can:<permiso>')` según la tabla del catálogo (contracts/http.md — dashboard, clientes+localidades, articulos+unidades, stock, proveedores, compras, facturas+pagos+facturae, pos, configuracion, archivos+carpetas, campanas, plantillas-email, usuarios, logs, bancos+cuentas); renombrar el grupo `can:gestiona-fichajes` → `can:ver-jornada`; fichajes/mi-jornada/perfil/logout quedan solo con `auth`
- [ ] T018 [US1] Refactor `resources/views/partials/sidebar.blade.php`: reemplazar `@if(auth()->user()->rol === UserRole::Admin)` y el `@if/@else` de super admin por `@can('<permiso>')` por entrada; grupos envueltos en `@canany([...])`; badge `sidebar-user-role` muestra nombre del rol spatie o label del enum para super admin (research.md D7); el badge de alertas queda dentro de `@can('ver-jornada')`
- [ ] T019 [US1] Manejo de 403 JSON: verificar/ajustar en `bootstrap/app.php` (`withExceptions`) que `AuthorizationException` responda `{"message": ...}` con status 403 cuando `wantsJson` (contracts/http.md)
- [ ] T020 [US1] Correr los tests T013–T016 hasta verde + suite completa (`php artisan test`) para confirmar que nada existente se rompe

**Checkpoint**: enforcement completo funcionando con roles sembrados a mano — MVP demostrable.

## Phase 4: User Story 4 — Provisión automática al crear tenant (P2)

**Goal**: tenant nuevo nace con rol "Administrador" completo asignado a su admin; tenants existentes migrados.

**Independent Test**: crear tenant desde super admin → login del admin → menú completo y acceso a roles (quickstart.md §2.1).

*Nota de orden: va antes que US2 porque US1+US4 dejan el sistema consistente para cualquier tenant; la UI de gestión (US2) llega después.*

### Tests (test-first)

- [ ] T021 [P] [US4] Ampliar `tests/Feature/SuperAdmin/TenantCrudTest.php`: alta de tenant crea rol "Administrador" con TODOS los permisos del catálogo asignado al admin inicial (FR-007); si falla un paso (p. ej. dominio duplicado) no queda tenant/usuario/rol parcial (atomicidad RN-03)
- [ ] T022 [P] [US4] Test de migración de datos en `tests/Feature/MigracionRolesTenantsExistentesTest.php`: con 2 tenants pre-spatie (users con enum `admin`/`usuario`), tras la migración cada tenant tiene rol Administrador (todos los permisos → sus admin) y rol Usuario (→ sus usuario); equivalencia de accesos SC-005

### Implementation

- [ ] T023 [US4] Modificar `app/Http/Controllers/SuperAdmin/TenantController.php@store`: dentro de la transacción existente, llamar a `ProvisionadorRoles::provisionarAdministrador($tenant, $adminUser)` (+ `provisionarUsuarioBase`)
- [ ] T024 [US4] Crear migración de datos `database/migrations/xxxx_provisionar_roles_tenants_existentes.php`: sembrar catálogo (PermisosSeeder) + recorrer tenants creando/asignando roles según `users.rol` (data-model.md §Migración); omitir usuarios centrales
- [ ] T025 [US4] Correr T021–T022 hasta verde + suite completa

**Checkpoint**: cualquier tenant (nuevo o existente) es operable sin configuración manual.

## Phase 5: User Story 2 — Gestión de roles del tenant (P2)

**Goal**: pantalla de roles con cards + datatable con checkboxes + modal de crear/editar con permisos agrupados por módulo.

**Independent Test**: crear rol "Ventas", editarlo, eliminarlo; verificar datatable/cards y aislamiento (quickstart.md §2.2).

### Tests (test-first para reglas de negocio)

- [ ] T026 [P] [US2] Test CRUD en `tests/Feature/RolesTest.php`: index JSON solo roles del tenant activo con `catalogo` agrupado y `totales`; store valida nombre requerido/único-por-tenant (mismo nombre en otro tenant OK — RN-05) y permisos existentes en catálogo (≥1); update de rol de otro tenant → 404 (resolución manual, no implicit binding); destroy con usuarios asignados → 409 (RN-01); destroy/update del rol Administrador que viole RN-02 (perder `ver-roles`/`ver-usuarios`, renombrar, eliminar) → 422/409; toda ruta `/roles` exige `can:ver-roles`

### Implementation

- [ ] T027 [US2] Crear `app/Http/Requests/StoreRolRequest.php` y `UpdateRolRequest.php`: validación de nombre (único por tenant vía `Rule::unique` con `tenant_id`) y `permisos[]` contra `CatalogoPermisos::claves()`, mensajes en español
- [ ] T028 [US2] Crear `app/Http/Controllers/RolController.php` (index/store/update/destroy, patrón `wantsJson()` de `SuperAdminTenantController`): resolución manual por tenant, reglas RN-01/RN-02, `forgetCachedPermissions()` tras cada cambio, payloads según contracts/http.md
- [ ] T029 [US2] Registrar rutas `/roles` en `routes/web.php` bajo `middleware('can:ver-roles')` (contracts/http.md)
- [ ] T030 [US2] Correr T026 hasta verde
- [ ] T031 [US2] Eliminar `Gate::define('gestiona-fichajes')` de `app/Providers/AppServiceProvider.php` y actualizar `tests/Unit/ServicioCumplimientoTest.php` u otros tests que lo referencien (research.md D5); suite completa en verde
- [ ] T032 [US2] **Invocar skills de diseño (`frontend-design`, `ui-ux-pro-max`) según CLAUDE.md** y crear `resources/views/roles/index.blade.php` + `resources/views/roles/_modales.blade.php`: cards informativas (total roles, usuarios con rol, permisos del catálogo), datatable con checkboxes de selección, botón "Agregar" → modal con nombre + checkboxes de permisos agrupados por módulo con "marcar módulo"; editar reutiliza el modal; patrón visual de `super_admin/tenants/index` y override de paginación de docs/04-front-guidelines.md
- [ ] T033 [US2] Crear `public/js/plugins-init/roles.init.js`: datatable AJAX contra `roles.index` JSON, alta/edición/borrado vía fetch con `window.showToast`, render de grupos de permisos desde `catalogo`; registrar `@push` de estilos/scripts en la vista
- [ ] T034 [US2] Añadir entrada "Roles" al sidebar (`resources/views/partials/sidebar.blade.php`) bajo el módulo Usuarios, envuelta en `@can('ver-roles')`
- [ ] T035 [US2] Validación visual en navegador (pedir confirmación al usuario para usar herramienta de navegador según CLAUDE.md): flujo quickstart §2.2 completo, light y dark mode

**Checkpoint**: gestión completa de roles autoservicio por tenant.

## Phase 6: User Story 3 — Asignación de roles a usuarios (P3)

**Goal**: asignar/cambiar rol de cada usuario desde la gestión de usuarios existente.

**Independent Test**: cambiar rol de un usuario y verificar que su menú/accesos cambian (quickstart.md §2.3–2.4).

### Tests (test-first)

- [ ] T036 [P] [US3] Test en `tests/Feature/AsignacionRolUsuarioTest.php`: PATCH asigna rol del mismo tenant (syncRoles — un rol por usuario); rol de otro tenant → 404; `role_id: null` deja sin rol; quitar el último acceso a `ver-roles`+`ver-usuarios` del tenant → 422 anti-lockout (RN-02); usuario sin rol solo accede a secciones personales; ruta exige `can:ver-usuarios`

### Implementation

- [ ] T037 [US3] Añadir `PATCH /usuarios/{usuario}/rol` en `routes/web.php` y método `actualizarRol` en `app/Http/Controllers/UsuarioController.php` con guard anti-lockout server-side (contracts/http.md); extender payload JSON de `index` con `rol_asignado` y `roles_disponibles`
- [ ] T038 [US3] Actualizar `resources/views/usuarios/index.blade.php` + su init JS: columna "Rol" con select por fila (o en el modal de usuario existente), guardado AJAX con toast; correr T036 hasta verde

**Checkpoint**: circuito completo rol → usuario → acceso.

## Phase 7: Polish & Cross-Cutting

- [ ] T039 [P] Documentar en `docs/04-front-guidelines.md` el procedimiento obligatorio (FR-013): "nueva entrada de menú ⇒ (1) permiso en `CatalogoPermisos` + seeder, (2) entrada del sidebar con `@can`, (3) rutas con `can:`, (4) solo el rol Administrador lo recibe automáticamente (re-correr seeder); los demás roles opt-in por tenant"
- [ ] T040 [P] Actualizar `docs/00-vision.md` (módulo de roles) y `docs/03-modelo-datos.md` (tablas spatie con `tenant_id`, decisión catálogo global) — cierre de spec según CLAUDE.md
- [ ] T041 Revisar si aplica enmienda a la constitución (`/speckit-constitution`): el enforcement por permisos complementa el Principio I; solo enmendar si se considera expansión material
- [ ] T042 Ejecutar quickstart.md completo (suite + e2e manual) y validación final de SC-001…SC-005

## Dependencies & Execution Order

```text
Phase 1 (Setup) → Phase 2 (Foundational) → US1 (P1, MVP)
                                          → US4 (P2, depende de Foundational; independiente de US1)
US2 (P2) depende de Foundational (+ sidebar de US1 para T034)
US3 (P3) depende de US2 (necesita roles para asignar)
Phase 7 al final.
Orden recomendado (secuencial): US1 → US4 → US2 → US3
```

**Parallel opportunities**: T005/T006, T013–T016 (4 archivos de test distintos), T021/T022, T026 mientras se cierra US4, T039/T040.

## Implementation Strategy

**MVP = Phase 1 + 2 + US1**: enforcement real con roles sembrados por migración — ya protege la app. Luego US4 (tenants operables), US2 (UI autoservicio), US3 (asignación), Polish. Cada checkpoint deja la suite completa en verde antes de avanzar.
