# Tasks: Registro y aprobación de usuarios

**Feature**: 006-registro-usuarios | **Branch**: `006-registro-usuarios`
**Input**: [plan.md](./plan.md), [spec.md](./spec.md), [data-model.md](./data-model.md),
[contracts/http.md](./contracts/http.md), [research.md](./research.md)

**Tests**: SÍ para áreas críticas (aislamiento multi-tenant + gating de login) por Principio IV
(test-first, NON-NEGOTIABLE). El resto de UI sin tests obligatorios.

**Convención de rutas**: monolito Laravel; paths relativos a la raíz del repo.

---

## Phase 1: Setup

- [X] T001 Crear enum `App\Enums\EstadoUsuario` (`Pendiente`/`Aprobado`/`Rechazado`) en `app/Enums/EstadoUsuario.php`, con helper `default()` → `Pendiente`, siguiendo el patrón de `app/Enums/UserRole.php`.

---

## Phase 2: Foundational (prerequisito bloqueante de todas las historias)

- [X] T002 Crear migración `add_estado_to_users_table` en `database/migrations/2026_07_03_xxxxxx_add_estado_to_users_table.php`: añade `estado` string(20) default `pendiente` (después de `activo`), `aprobado_por` foreignId nullable a `users` con `nullOnDelete`, `aprobado_en` timestamp nullable, e índice `(tenant_id, estado)`. En `up()`, marcar los usuarios existentes como `estado='aprobado'` para no bloquear cuentas vigentes.
- [X] T003 Actualizar `app/Models/User.php`: añadir `estado`, `aprobado_por`, `aprobado_en` a `$fillable`; castear `estado` a `EstadoUsuario`; añadir relación `aprobador()` (`belongsTo(User::class, 'aprobado_por')`); helpers `estaPendiente()`/`estaAprobado()` y `aprobar(User $por)` / `rechazar()` que ajusten `estado` + `activo` según la tabla de correspondencia de data-model.
- [X] T004 Actualizar `database/factories/UserFactory.php`: default `estado=Aprobado`, `activo=true`; añadir states `pendiente()` (estado pendiente, activo false) y `rechazado()`.

**Checkpoint**: `php artisan migrate` corre limpio; el modelo expone estado y helpers.

---

## Phase 3: User Story 1 — Registro de un nuevo solicitante (P1)

**Goal**: Página pública de registro que crea cuentas en estado `pendiente` sin acceso a login.

**Independent Test**: Enviar el formulario con datos válidos → cuenta `pendiente`, `activo=false`,
tenant por defecto; intentar login → rechazado con mensaje de "cuenta no aprobada".

### Tests (test-first: gating de login)

- [X] T005 [P] [US1] Test en `tests/Feature/RegistroTest.php`: POST `/registro` con datos válidos crea usuario `estado=pendiente`, `activo=false`, `tenant_id` del tenant por defecto, y redirige a login con flash success.
- [X] T006 [P] [US1] Test en `tests/Feature/RegistroTest.php`: email duplicado → 422 sin crear duplicado; y un usuario `pendiente` con contraseña correcta NO puede iniciar sesión (mensaje "cuenta no aprobada", no el genérico).

### Implementation

- [X] T007 [US1] Crear `app/Http/Requests/RegisterRequest.php` con reglas `name` (required, max 255), `email` (required, email, unique users), `password` (required, confirmed, min 8), siguiendo el patrón de `StoreClienteRequest`.
- [X] T008 [US1] Crear `app/Http/Controllers/Auth/RegisterController.php` con `create()` (retorna vista `auth.register`) y `store(RegisterRequest)` que crea el usuario con `tenant_id = Tenant::query()->first()->id`, `rol='usuario'`, `estado=Pendiente`, `activo=false`, y redirige a `login` con flash success ("Solicitud registrada…").
- [X] T009 [US1] Crear vista `resources/views/auth/register.blade.php` basada en `template/.../page-register.blade.php`, layout `fullwidth` (como login), con campos name/email/password/password_confirmation, errores de validación y enlace a `/login`. Notificaciones vía flash+toastr.
- [X] T010 [US1] Añadir rutas en `routes/web.php` dentro del grupo `guest`: GET `/registro` → `register.create`, POST `/registro` → `register.store` con `throttle`.
- [X] T011 [US1] Modificar `app/Http/Controllers/Auth/LoginController.php@store`: tras un intento fallido, si existe un usuario con ese email cuya contraseña coincide y `estado ∈ {pendiente, rechazado}`, lanzar `ValidationException` con mensaje específico ("Tu cuenta aún no está aprobada" / "no está habilitada") en vez del genérico; mantener `activo=true` como gate en `Auth::attempt`.

**Checkpoint**: Registro funcional; solicitante creado en pendiente y bloqueado en login. MVP entregable.

---

## Phase 4: User Story 2 — Aprobar/rechazar desde la lista de usuarios (P1)

**Goal**: Vista de Usuarios con lista scopeda por tenant y acciones aprobar/rechazar que habilitan
o bloquean el login, respetando aislamiento y no-self.

**Independent Test**: Con un solicitante pendiente, un usuario aprobado del mismo tenant pulsa
"Aprobar" → estado aprobado y ese usuario ya puede loguearse; no puede operar sobre usuarios de
otro tenant (404) ni sobre sí mismo.

### Tests (test-first: aislamiento + acciones)

- [X] T012 [P] [US2] Test en `tests/Feature/UsuariosTest.php`: aprobar un pendiente → `estado=aprobado`, `activo=true`, `aprobado_por`/`aprobado_en` seteados; el usuario aprobado puede iniciar sesión. Idempotencia al aprobar dos veces.
- [X] T013 [P] [US2] Test en `tests/Feature/UsuariosTest.php`: rechazar → `estado=rechazado`, `activo=false`, login bloqueado; y aprobar/rechazarse a sí mismo está prohibido.
- [X] T014 [P] [US2] Test de aislamiento en `tests/Feature/UsuariosTest.php`: con ≥2 tenants, `GET /usuarios` solo lista usuarios del tenant del autenticado, y aprobar/rechazar un usuario de OTRO tenant devuelve 404 (Principio I).

### Implementation

- [X] T015 [US2] Crear `app/Http/Controllers/UsuarioController.php` con `index()` que devuelve `usuarios.index` (colección `User::where('tenant_id', auth()->user()->tenant_id)` con datos y urls de acción; soporte `wantsJson()` como `ClienteController`).
- [X] T016 [US2] Añadir a `UsuarioController` los métodos `aprobar(Request, string $usuario)` y `rechazar(Request, string $usuario)`: resolver el modelo manualmente con `findOrFail` restringido a `tenant_id` del autenticado (404 si no coincide), bloquear self (`$usuario == auth()->id()` → error), aplicar `aprobar($autenticado)` / `rechazar()` del modelo, y responder redirect/JSON con flash success.
- [X] T017 [US2] Crear vista `resources/views/usuarios/index.blade.php` con tabla de usuarios (nombre, email, rol, badge de estado) y botones aprobar/rechazar (formularios PATCH) condicionados al estado; notificaciones toastr.
- [X] T018 [US2] Añadir rutas en `routes/web.php` dentro del grupo `auth`+`tenant.context`: GET `/usuarios` → `usuarios.index`, PATCH `/usuarios/{usuario}/aprobar` → `usuarios.aprobar`, PATCH `/usuarios/{usuario}/rechazar` → `usuarios.rechazar`.
- [X] T019 [US2] Añadir enlace "Usuarios" en el menú lateral `resources/views/partials/sidebar.blade.php`.

**Checkpoint**: Flujo completo registro→aprobación→login operativo y aislado por tenant.

---

## Phase 5: User Story 3 — Cards informativas (P2)

**Goal**: Tarjetas resumen (total / pendientes / activos) del tenant en la vista de Usuarios.

**Independent Test**: Con un conjunto conocido de usuarios por estado, los contadores de las cards
coinciden exactamente para el tenant activo y no incluyen otros tenants.

- [X] T020 [US3] Ampliar `UsuarioController@index` para calcular `totales` = `{ total, pendientes, activos }` sobre la query scopeda por tenant y pasarlos a la vista (y al JSON).
- [X] T021 [US3] Añadir las cards informativas en la parte superior de `resources/views/usuarios/index.blade.php` usando los `totales` (patrón de cards del template).
- [X] T022 [P] [US3] Test en `tests/Feature/UsuariosTest.php`: los `totales` reflejan los conteos correctos del tenant y excluyen usuarios de otro tenant.

**Checkpoint**: Panorama de usuarios con conteos correctos por tenant.

---

## Phase 6: Polish & Cross-Cutting

- [X] T023 [P] Verificar/añadir mensajes de validación y textos en español consistentes con el resto de vistas (registro y usuarios).
- [X] T024 Ejecutar la guía de validación de [quickstart.md](./quickstart.md) end-to-end y correr `php artisan test --filter=Registro` y `--filter=Usuario` en verde.
- [X] T025 [P] Revisar `docs/03-modelo-datos.md`: si el nuevo campo `estado` de `users` amplía lo documentado, actualizarlo (según regla de cierre de feature en CLAUDE.md).

---

## Dependencies & Execution Order

- **Setup (T001)** → **Foundational (T002–T004)** bloquean todo lo demás.
- **US1 (T005–T011)**: depende de Foundational. Entrega el MVP (registro + gating).
- **US2 (T012–T019)**: depende de Foundational; independiente de US1 salvo que comparte modelo.
- **US3 (T020–T022)**: depende de US2 (misma vista/controlador).
- **Polish (T023–T025)**: al final.

### Oportunidades de paralelismo

- Tests marcados `[P]` (T005, T006, T012, T013, T014, T022) se escriben en paralelo (archivos/asserts independientes) antes de su implementación.
- T023 y T025 (docs/textos) en paralelo con el cierre.
- Dentro de US2, T015/T016 (controlador) y T017 (vista) tocan archivos distintos pero la vista depende de las rutas/urls → hacer controlador+rutas antes que la vista.

## Implementation Strategy

**MVP = Phase 1 + 2 + US1**: registro público que captura solicitantes en estado pendiente y los
bloquea en login. Entregable y testeable por sí solo. Luego US2 (cierra el ciclo con aprobación)
y US3 (cards) como incrementos.
