---

description: "Task list for 001-user-auth"

---

# Tasks: Autenticación de usuarios (login multi-tenant)

**Input**: Design documents from `/specs/001-user-auth/`
**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/routes.md](contracts/routes.md), [quickstart.md](quickstart.md)

**Tests**: Incluidos — el Principio IV (NON-NEGOTIABLE) de `.specify/memory/constitution.md`
exige test-first para lógica de tenant scoping y autenticación. Escribir cada test, verificar que
falla, y recién ahí implementar.

**Organization**: Tareas agrupadas por historia de usuario (spec.md). Historias P1: US1 y US4.

## Path Conventions

Aplicación Laravel monolítica existente en la raíz del repo (`app/`, `database/`, `resources/`,
`routes/`, `tests/`), según Project Structure de [plan.md](plan.md).

---

## Phase 1: Setup

**Purpose**: infraestructura compartida que no pertenece a ninguna historia en particular

- [X] T001 Publicar la config de tenancy con `php artisan vendor:publish --tag=config --provider="Stancl\Tenancy\TenancyServiceProvider"` → genera `config/tenancy.php` (el tag correcto es `config`, no `tenancy-config`)
- [X] T002 [P] ~~Crear migración de `sessions`~~ — verificado innecesario: el Laravel 12 skeleton ya crea `sessions` y `password_reset_tokens` dentro de `0001_01_01_000000_create_users_table.php`; la tabla ya existe en `empire_crm`
- [X] T003 [P] Crear `app/Enums/UserRole.php` — enum de string `super_admin` \| `admin` \| `usuario`, con método estático `default(): self` que devuelve `usuario`

**Checkpoint**: dependencias y configuración base listas

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: esquema de datos, modelos y middleware que TODAS las historias necesitan

**⚠️ CRITICAL**: ninguna historia de usuario puede implementarse hasta terminar esta fase

- [X] T004 Crear `database/migrations/xxxx_create_tenants_table.php` — columnas de `data-model.md` (`nombre_comercial`, `razon_social` nullable, `nif` nullable único, `email` nullable, `activo` boolean default true, `data` json nullable, timestamps)
- [X] T005 Crear `database/migrations/xxxx_add_tenancy_fields_to_users_table.php` — `tenant_id` (fk nullable a `tenants`, índice), `rol` varchar(20) default `usuario`, `activo` boolean default true
- [X] T006 [P] Crear `app/Models/Tenant.php` extendiendo `Stancl\Tenancy\Database\Models\Tenant`: `$incrementing = true`, `$keyType = 'int'`, `getCustomColumns()` con las columnas de T004
- [X] T007 [P] Extender `app/Models/User.php`: agregar `tenant_id`, `rol`, `activo` a `$fillable`; cast `rol` → `UserRole::class` y `activo` → `boolean`; relación `tenant(): BelongsTo`; método `isSuperAdmin(): bool`
- [X] T008 Configurar `config/tenancy.php`: `tenant_model` → `App\Models\Tenant::class`, `bootstrappers` → `[]` (sin bootstrappers en single-DB, según research.md D3)
- [X] T009 Crear `app/Http/Middleware/SetTenantContext.php` — si `auth()->user()->tenant_id` existe y el tenant está activo, `tenancy()->initialize($user->tenant)`; si el tenant está inactivo, `Auth::logout()` + invalidar sesión + redirect a `login` con mensaje; si es super_admin (`tenant_id` null), no inicializa
- [X] T010 Registrar el alias de middleware `tenant.context` → `SetTenantContext` en `bootstrap/app.php`
- [X] T011 [P] Crear `database/factories/TenantFactory.php` — tenant activo por defecto, estado `inactive()`
- [X] T012 [P] Extender `database/factories/UserFactory.php` — estados `superAdmin()` (rol super_admin, tenant_id null), `admin()` (rol admin, con tenant), `inactive()` (activo false); por defecto rol `usuario` con tenant nuevo vía `TenantFactory`
- [X] T013 Correr `php artisan migrate` contra la base local (`empire_crm`) y verificar que las 5 migraciones (incluida `sessions`) aplican sin error

**Checkpoint**: esquema, modelos, factories y middleware de contexto listos — las historias de usuario pueden arrancar

---

## Phase 3: User Story 1 - Iniciar sesión y operar dentro de mi empresa (Priority: P1) 🎯 MVP

**Goal**: un usuario con credenciales válidas entra al panel y su sesión queda con el contexto de
su tenant activo (o sin contexto si es super_admin); credenciales inválidas o cuentas/tenants
inactivos no permiten entrar.

**Independent Test**: crear dos tenants con un usuario cada uno vía factories, iniciar sesión con
cada uno y verificar que el contexto de tenancy corresponde solo al suyo.

### Tests for User Story 1 ⚠️

> Escribir estos tests PRIMERO y verificar que fallan antes de tocar la implementación

- [X] T014 [P] [US1] Test en `tests/Feature/Auth/LoginTest.php`: login con credenciales válidas redirige a `/` y dblo el usuario queda autenticado (`assertAuthenticated()`)
- [X] T015 [P] [US1] Test en `tests/Feature/Auth/LoginTest.php`: email inexistente y contraseña incorrecta devuelven el mismo mensaje de error genérico en `email` y el usuario sigue guest
- [X] T016 [P] [US1] Test en `tests/Feature/Auth/LoginTest.php`: usuario con `activo = false` no puede autenticarse (mismo mensaje genérico)
- [X] T017 [P] [US1] Test en `tests/Feature/Auth/LoginTest.php`: usuario cuyo tenant tiene `activo = false` no puede autenticarse
- [X] T018 [P] [US1] Test en `tests/Feature/Auth/LoginTest.php`: validación — email vacío/mal formado y password vacía devuelven errores de validación y no autentican
- [X] T019 [P] [US1] Test en `tests/Feature/Auth/TenantContextTest.php`: tras login de un usuario con tenant, `tenancy()->initialized === true` y `tenant()->id` coincide con el tenant del usuario
- [X] T020 [P] [US1] Test en `tests/Feature/Auth/TenantContextTest.php`: tras login de un usuario de otro tenant, el contexto corresponde solo al suyo (no fuga entre ambos)
- [X] T021 [P] [US1] Test en `tests/Feature/Auth/TenantContextTest.php`: tras login de un super_admin (tenant_id null), `tenancy()->initialized === false`
- [X] T022 [P] [US1] Test en `tests/Feature/Auth/LoginTest.php`: 6º intento fallido consecutivo con el mismo email+IP devuelve el mensaje de throttling y no procesa el intento

### Implementation for User Story 1

- [X] T023 [US1] Crear `app/Http/Controllers/Auth/LoginController.php` con `create()` (muestra el form) y `store()` (valida, aplica throttle por email+IP con `RateLimiter`, `Auth::attempt(['email'=>.., 'password'=>.., 'activo'=>true], $remember)`, chequea tenant activo post-attempt, regenera sesión, redirige a `intended('/')`)
- [X] T024 [US1] Crear `resources/views/layouts/guest.blade.php` — layout fullwidth (sin sidebar) con los mismos `<link>`/`<script>` de assets NexaDash que `layouts/app.blade.php`, sin el sistema `config/dz.php`
- [X] T025 [US1] Copiar `template/Laravel-NexaDash-v1.0-28_May_2025/package/public/images/login.png` a `public/images/login.png`
- [X] T026 [US1] Crear `resources/views/auth/login.blade.php` a partir de `template/Laravel-NexaDash-v1.0-28_May_2025/package/resources/views/page-login.blade.php`: extiende `layouts.guest`, textos en español, branding "Empire Systems" (reusar el SVG del nav-header, no existe `logo-full.png` en el template), CSRF (`@csrf`), `old('email')`, errores de validación (`@error`), checkbox "Recordarme", sin bloque social ("Or continue with"), sin "Not registered?/Register", sin enlace "Forgot Password?"
- [X] T027 [US1] Registrar rutas en `routes/web.php`: `GET /login` y `POST /login` dentro de `Route::middleware('guest')`, apuntando a `LoginController@create` / `@store`, nombradas `login` / `login.attempt`
- [X] T028 [US1] Aplicar middleware `auth` + `tenant.context` al grupo de rutas internas en `routes/web.php` (incluida la ruta `dashboard` existente)
- [X] T029 [US1] Crear `app/Listeners/LogAuthenticationActivity.php` suscrito a `Illuminate\Auth\Events\{Login,Failed,Lockout}` (info/warning con email e IP, nunca password) y registrar el listener en el proveedor de eventos correspondiente
- [X] T030 [US1] Correr `php artisan test --filter=LoginTest` y `--filter=TenantContextTest` y confirmar que T014–T022 pasan en verde

**Checkpoint**: login funcional de punta a punta, con contexto de tenant correcto — historia independientemente demostrable

---

## Phase 4: User Story 4 - Entrar al sistema desde el día uno (Priority: P1)

**Goal**: sobre una instalación recién migrada, existen credenciales utilizables para el
super_admin y para el admin de un tenant de prueba, sin tocar la base de datos a mano.

**Independent Test**: `php artisan migrate:fresh --seed` y luego login exitoso con ambas
credenciales sembradas (reutiliza el flujo de login de US1, que ya debe estar implementado).

### Tests for User Story 4 ⚠️

- [X] T031 [P] [US4] Test en `tests/Feature/Auth/SeederTest.php`: tras correr `AuthSeeder`, existe un usuario `rol = super_admin` con `tenant_id` null y puede autenticarse con sus credenciales
- [X] T032 [P] [US4] Test en `tests/Feature/Auth/SeederTest.php`: tras correr `AuthSeeder`, existe un tenant activo y un usuario `rol = admin` asociado, que puede autenticarse
- [X] T033 [P] [US4] Test en `tests/Feature/Auth/SeederTest.php`: correr el seeder dos veces no duplica registros (idempotencia por email/nombre)

### Implementation for User Story 4

- [X] T034 [US4] Crear `database/seeders/AuthSeeder.php`: `firstOrCreate` por email para el super_admin (`admin@empiresystems.es`, rol `super_admin`, tenant_id null), un tenant demo ("Empresa Demo SL", activo) y su admin (`demo@empiresystems.es`, rol `admin`); contraseñas de desarrollo definidas como constantes en el seeder
- [X] T035 [US4] Registrar `AuthSeeder` en `database/seeders/DatabaseSeeder.php`
- [X] T036 [US4] Documentar en `README.md` las credenciales de desarrollo sembradas y la advertencia de cambiarlas en producción
- [X] T037 [US4] Correr `php artisan test --filter=SeederTest` y confirmar que T031–T033 pasan; luego `php artisan migrate:fresh --seed` local y login manual con ambas credenciales (paso 2 y 8 de `quickstart.md`)

**Checkpoint**: instalación limpia utilizable desde el día uno — ambas historias P1 completas (MVP)

---

## Phase 5: User Story 2 - Rutas protegidas y cierre de sesión (Priority: P2)

**Goal**: ninguna pantalla interna es accesible sin sesión; cerrar sesión revoca el acceso de
inmediato.

**Independent Test**: sin sesión, pedir cualquier ruta interna → redirige a `/login`. Con sesión,
cerrar sesión → vuelve a estar bloqueado, incluso navegando "atrás".

### Tests for User Story 2 ⚠️

- [X] T038 [P] [US2] Test en `tests/Feature/Auth/RouteProtectionTest.php`: un guest que pide `/` (ruta interna) es redirigido a `/login`
- [X] T039 [P] [US2] Test en `tests/Feature/Auth/RouteProtectionTest.php`: un usuario autenticado que hace `POST /logout` termina su sesión (`assertGuest()`) y es redirigido a `/login`
- [X] T040 [P] [US2] Test en `tests/Feature/Auth/RouteProtectionTest.php`: tras logout, una petición posterior a `/` vuelve a redirigir a `/login` (no queda estado de sesión residual)
- [X] T041 [P] [US2] Test en `tests/Feature/Auth/TenantContextTest.php`: si el tenant de un usuario autenticado pasa a `activo = false` a mitad de sesión, la siguiente petición lo desloguea y redirige a `/login`

### Implementation for User Story 2

- [X] T042 [US2] Agregar `logout()` a `app/Http/Controllers/Auth/LoginController.php` — `Auth::logout()`, invalidar sesión, regenerar token CSRF, redirect a `login`
- [X] T043 [US2] Registrar `POST /logout` en `routes/web.php` dentro del grupo `auth` + `tenant.context`, nombrada `logout`
- [X] T044 [US2] Conectar el enlace "Cerrar sesión" de `resources/views/partials/header.blade.php` a un formulario `POST` a la ruta `logout` (reemplazando el `href="javascript:void(0);"` actual)
- [X] T045 [US2] Correr `php artisan test --filter=RouteProtectionTest` y confirmar T038–T041 en verde (incluye el caso de tenant desactivado a mitad de sesión, ya cubierto por `SetTenantContext` de T009)

**Checkpoint**: acceso completamente cerrado fuera de sesión, logout confiable

---

## Phase 6: User Story 3 - Recordar mi sesión (Priority: P3)

**Goal**: con "Recordarme" marcado, la sesión sobrevive al cierre del navegador; sin marcar, no.

**Independent Test**: login con y sin "Recordarme", expirar la sesión de corta duración en ambos
casos y verificar la diferencia de comportamiento.

### Tests for User Story 3 ⚠️

- [X] T046 [P] [US3] Test en `tests/Feature/Auth/LoginTest.php`: login con `remember = true` deja la cookie `remember_token` seteada y el usuario sigue autenticado tras limpiar la cookie de sesión (simulando cierre de navegador)
- [X] T047 [P] [US3] Test en `tests/Feature/Auth/LoginTest.php`: login sin `remember` no dificulta que, tras limpiar la cookie de sesión, el usuario deje de estar autenticado

### Implementation for User Story 3

- [X] T048 [US3] Confirmar que el checkbox `remember` de `resources/views/auth/login.blade.php` (T026) viaja al request y que `LoginController@store` (T023) lo pasa como segundo argumento de `Auth::attempt()` — ajustar si falta
- [X] T049 [US3] Correr `php artisan test --filter=LoginTest` y confirmar T046–T047 en verde

**Checkpoint**: las 4 historias de usuario completas y verificables de forma independiente

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: cierre de la feature, alineado con el punto "Al cerrar un spec/feature" de `CLAUDE.md`

- [X] T050 [P] Revisar que ninguna contraseña quede logueada (grep de `password` en `storage/logs/laravel.log` tras correr la suite)
- [X] T051 Ejecutar `php artisan test` completo (toda la suite `tests/Feature/Auth/*`) y confirmar 100% verde
- [X] T052 Recorrer manualmente los 9 pasos de [quickstart.md](quickstart.md) contra el entorno local (`empire_crm`)
- [X] T053 Actualizar `docs/03-modelo-datos.md` si el modelo final de `tenants`/`users` difiere del documentado (columna `data`, `rol`, `activo`) — según la regla de `CLAUDE.md` de sincronizar docs al cerrar una feature
- [X] T054 Evaluar si esta feature toca algún principio de la constitución de forma no prevista; si no, no tocar `.specify/memory/constitution.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias — arranca de inmediato
- **Foundational (Phase 2)**: depende de Setup — BLOQUEA todas las historias
- **US1 (Phase 3, P1)**: depende de Foundational
- **US4 (Phase 4, P1)**: depende de Foundational y de que exista `LoginController`/rutas de US1 (T023, T027) para poder loguear con las credenciales sembradas — en la práctica, hacer después de US1
- **US2 (Phase 5, P2)**: depende de Foundational; reutiliza `LoginController` y rutas de US1
- **US3 (Phase 6, P3)**: depende de Foundational y de `LoginController`/vista de login de US1
- **Polish (Phase 7)**: depende de todas las historias que se decida incluir

### User Story Dependencies

- **US1 y US4 son ambas P1** — US1 primero (crea el mecanismo de login), US4 después (lo ejercita con datos sembrados). Ninguna otra historia empieza hasta que Foundational esté listo.
- **US2** puede implementarse justo después de Foundational en paralelo con US4, pero como ambas tocan `LoginController.php` y `routes/web.php`, conviene secuenciar US1 → (US2 y US4 en paralelo por desarrolladores distintos, o secuencial si es una sola persona).
- **US3** es la más chica y depende de que el formulario de login (T026) ya exista.

### Parallel Opportunities

- T002 y T003 en paralelo (Setup).
- T006, T007, T011, T012 en paralelo dentro de Foundational (archivos distintos), pero todos después de T004/T005 (migraciones).
- Todos los tests marcados [P] dentro de cada historia pueden escribirse en paralelo antes de su implementación.
- US2 y US4 pueden avanzar en paralelo entre sí una vez terminada US1 (tocan archivos distintos salvo `routes/web.php`, donde hay que coordinar).

---

## Parallel Example: User Story 1

```bash
# Tests de US1 en paralelo (antes de implementar):
Task: "Test login válido en tests/Feature/Auth/LoginTest.php"
Task: "Test credenciales inválidas en tests/Feature/Auth/LoginTest.php"
Task: "Test usuario inactivo en tests/Feature/Auth/LoginTest.php"
Task: "Test tenant inactivo en tests/Feature/Auth/LoginTest.php"
Task: "Test contexto de tenant en tests/Feature/Auth/TenantContextTest.php"
Task: "Test super_admin sin contexto en tests/Feature/Auth/TenantContextTest.php"
```

---

## Implementation Strategy

### MVP First (US1 + US4)

1. Completar Phase 1: Setup
2. Completar Phase 2: Foundational (crítico — bloquea todo)
3. Completar Phase 3: US1 (login funcional con contexto de tenant)
4. Completar Phase 4: US4 (seeder — permite demostrar el login con datos reales)
5. **PARAR y VALIDAR**: correr `quickstart.md` pasos 1–3 y 8
6. Esto ya es un MVP demostrable: se puede entrar al sistema

### Incremental Delivery

1. Setup + Foundational → base lista
2. US1 → login funcional → validar independientemente
3. US4 → credenciales sembradas → demo end-to-end (MVP completo)
4. US2 → protección total + logout → validar independientemente
5. US3 → remember me → validar independientemente
6. Polish → cierre de la feature según `CLAUDE.md`

---

## Notes

- [P] = archivos distintos, sin dependencias entre sí
- [Story] mapea cada tarea a su historia de usuario para trazabilidad
- Verificar que los tests fallan antes de implementar (Principio IV, NON-NEGOTIABLE)
- Commitear después de cada tarea o grupo lógico
- Parar en cada checkpoint para validar la historia de forma independiente
