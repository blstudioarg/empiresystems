---

description: "Task list for feature implementation"

---

# Tasks: Ampliación del perfil de usuario (Mi perfil)

**Input**: Design documents from `/specs/034-perfil-usuario-ampliado/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/rutas-perfil.md, quickstart.md

**Tests**: Incluidos. La constitución del proyecto exige tests de aislamiento multi-tenant para toda consulta sobre datos de tenant (Principio I/IV); además se incluyen tests de feature por historia siguiendo el patrón ya usado en el proyecto.

**Organization**: Tareas agrupadas por historia de usuario (spec.md), en orden de prioridad (P1 → P3).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1..US6)

---

## Phase 1: Setup

- [X] T001 Confirmar que las rutas nuevas se agregan dentro del grupo ya existente de `/perfil` (autenticado, tenancy inicializada) en `routes/web.php`, sin crear un grupo nuevo ni permisos nuevos (el perfil es autoservicio, exceptuado de la regla "nueva entrada de menú ⇒ nuevo permiso" de `docs/04-front-guidelines.md`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Prerrequisito compartido por las historias que requieren verificar aislamiento multi-tenant (US4 y US5)

**⚠️ CRITICAL**: US4 y US5 no pueden completar sus tests de aislamiento sin este helper

- [X] T002 Crear trait de test `ConTenantSecundario` en `tests/Feature/Concerns/ConTenantSecundario.php` que provisione un segundo tenant con su propio usuario, `LogActividad`, `MiembroEquipo` y `Fichaje` de prueba, reutilizable por los tests de aislamiento de US4 y US5

**Checkpoint**: Con T001-T002 listos, cualquier historia puede empezar

---

## Phase 3: User Story 1 - Ver mi rol y permisos reales (Priority: P1) 🎯 MVP

**Goal**: El perfil muestra el rol dinámico real (Spatie) del usuario y sus permisos efectivos, agrupados por módulo, en vez del rol legado desactualizado.

**Independent Test**: Iniciar sesión con un usuario cuyo rol Spatie difiera del valor legado en `rol`, entrar a `/perfil` y comprobar que el rol mostrado es el real, con sus permisos listados por módulo.

### Tests for User Story 1

- [X] T003 [P] [US1] Test: perfil muestra rol Spatie real (no el legado) y permisos agrupados por módulo; superadmin ve "acceso total" sin listado de permisos, en `tests/Feature/Profile/ProfileRolPermisosTest.php`

### Implementation for User Story 1

- [X] T004 [US1] Actualizar `ProfileController@show` para pasar a la vista: rol Spatie real (`getRoleNames()->first()`), flag de superadmin, y permisos efectivos agrupados por módulo vía `App\Support\CatalogoPermisos::porModulo()` filtrado a `getAllPermissions()`, en `app/Http/Controllers/ProfileController.php`
- [X] T005 [US1] Reemplazar el bloque de rol legado (`$user->rol?->label()`) por el rol Spatie real + nueva sección "Permisos" agrupada por módulo (o mensaje de acceso total para superadmin) en `resources/views/profile/show.blade.php`
- [X] T006 [US1] Actualizar `resources/views/ayuda/profile.blade.php` explicando la sección de rol/permisos reales

**Checkpoint**: US1 funcional y testeable de forma independiente — corrige el bug del rol legado.

---

## Phase 4: User Story 2 - Cambiar mi contraseña (Priority: P1) 🎯 MVP

**Goal**: Autoservicio de cambio de contraseña, con invalidación de otras sesiones activas.

**Independent Test**: Cambiar la contraseña desde `/perfil`, cerrar sesión, iniciar sesión con la nueva (funciona) y con la anterior (falla); verificar que otra sesión activa en otro dispositivo queda invalidada.

### Tests for User Story 2

- [X] T007 [P] [US2] Test: cambio de contraseña exitoso, error si la contraseña actual es incorrecta, error si la confirmación no coincide o no cumple `min:8`, e invalidación de sesiones de otros dispositivos preservando la actual, en `tests/Feature/Profile/ProfilePasswordTest.php`

### Implementation for User Story 2

- [X] T008 [P] [US2] Crear `UpdatePasswordRequest` (`contrasena_actual` requerido, `password` `required|confirmed|min:8`) en `app/Http/Requests/Profile/UpdatePasswordRequest.php`
- [X] T009 [US2] Añadir `updatePassword()` en `app/Http/Controllers/ProfileController.php`: validar `Hash::check()` contra la actual, actualizar con `Hash::make()`, y borrar filas de `sessions` del usuario distintas a `session()->getId()` (depende de T008)
- [X] T010 [US2] Añadir ruta `PUT /perfil/contrasena` → `profile.password.update` en `routes/web.php`
- [X] T011 [US2] Añadir formulario "Cambiar contraseña" en `resources/views/profile/show.blade.php`, envío AJAX con `window.withButtonLoading` y feedback vía `window.showToast`/flash toastr
- [X] T012 [US2] Actualizar `resources/views/ayuda/profile.blade.php` con la sección de cambio de contraseña

**Checkpoint**: US1 + US2 completan el MVP (P1) — perfil con identidad de acceso correcta y autoservicio de seguridad básico.

---

## Phase 5: User Story 3 - Editar mi nombre y correo electrónico (Priority: P2)

**Goal**: Edición inmediata de nombre; cambio de email con verificación por enlace firmado temporal (24h), sin aplicarse hasta confirmar.

**Independent Test**: Editar nombre (se refleja de inmediato); solicitar cambio de email con contraseña actual, confirmar vía enlace recibido, verificar que el login sigue funcionando con el email viejo hasta la confirmación, y que cancelar/reenviar funcionan.

### Tests for User Story 3

- [X] T013 [P] [US3] Test: editar nombre se aplica de inmediato y sin pedir contraseña, en `tests/Feature/Profile/ProfileNombreTest.php`
- [X] T014 [P] [US3] Test: solicitar cambio de email (requiere contraseña correcta), confirmar por enlace válido, enlace vencido no aplica el cambio, cancelar limpia el estado pendiente, reenviar invalida el enlace anterior, y error si el email ya está en uso en el mismo tenant, en `tests/Feature/Profile/ProfileEmailTest.php`

### Implementation for User Story 3

- [X] T015 [US3] Migración `add_pending_email_to_users_table` (`pending_email` string nullable, `pending_email_token` string(64) nullable, `pending_email_expires_at` timestamp nullable) en `database/migrations/2026_07_26_000001_add_pending_email_to_users_table.php`
- [X] T016 [US3] Añadir `pending_email`, `pending_email_token`, `pending_email_expires_at` a `$fillable`/casts en `app/Models/User.php` (depende de T015)
- [X] T017 [P] [US3] Crear `UpdateNombreRequest` (`name` requerido, longitud razonable) en `app/Http/Requests/Profile/UpdateNombreRequest.php`
- [X] T018 [P] [US3] Crear `SolicitarCambioEmailRequest` (`contrasena_actual` requerido, `nuevo_email` formato válido + único entre `email`/`pending_email` del mismo tenant) en `app/Http/Requests/Profile/SolicitarCambioEmailRequest.php`
- [X] T019 [US3] Crear Mailable `VerificarCambioEmail` con URL firmada temporal (`URL::temporarySignedRoute`, 24h) en `app/Mail/VerificarCambioEmail.php` + vista `resources/views/emails/verificar-cambio-email.blade.php`
- [X] T020 [US3] Añadir en `app/Http/Controllers/ProfileController.php`: `updateNombre()`, `solicitarCambioEmail()` (genera token, guarda las 3 columnas, envía `VerificarCambioEmail`), `confirmarCambioEmail()` (valida firma + token + no vencido + `Auth::id() === $user->id`, aplica `email = pending_email`, limpia columnas), `cancelarCambioEmail()`, `reenviarVerificacionEmail()` (regenera token/vencimiento, invalida el enlace anterior) (depende de T016-T019)
- [X] T021 [US3] Añadir rutas en `routes/web.php`: `PUT /perfil/nombre` → `profile.nombre.update`, `POST /perfil/email` → `profile.email.solicitar`, `GET /perfil/email/confirmar/{user}` (middleware `signed`) → `profile.email.confirmar`, `DELETE /perfil/email/pendiente` → `profile.email.cancelar`, `POST /perfil/email/pendiente/reenviar` → `profile.email.reenviar`
- [X] T022 [US3] Añadir formularios "Editar nombre" y "Cambiar email" (con aviso de solicitud pendiente + botones cancelar/reenviar cuando aplique) en `resources/views/profile/show.blade.php`, con AJAX + `window.withButtonLoading` + toastr
- [X] T023 [US3] Actualizar `resources/views/ayuda/profile.blade.php` con la edición de nombre/email y el flujo de verificación

**Checkpoint**: US1-US3 funcionales de forma independiente.

---

## Phase 6: User Story 4 - Ver mi actividad reciente (Priority: P2)

**Goal**: Bloque de actividad reciente propia (últimos 5 eventos) reutilizando `logs_actividad`, con enlace "ver más" solo para quien tenga permiso `ver-logs`.

**Independent Test**: Generar un login fallido y uno exitoso, entrar a `/perfil` y comprobar que ambos aparecen con resultado/IP/navegador/ubicación.

### Tests for User Story 4

- [X] T024 [P] [US4] Test: actividad reciente muestra los últimos 5 eventos propios con resultado/navegador/ubicación, estado vacío si no hay actividad, y el enlace "ver más" solo aparece con permiso `ver-logs`, en `tests/Feature/Profile/ProfileActividadRecienteTest.php`
- [X] T025 [P] [US4] Test de aislamiento multi-tenant: un usuario del tenant A nunca ve actividad de un usuario del tenant B, en `tests/Feature/Profile/ProfileActividadRecienteAislamientoTest.php` (usa `ConTenantSecundario`, depende de T002)

### Implementation for User Story 4

- [X] T026 [US4] Añadir en `ProfileController@show` la consulta de los últimos 5 `LogActividad` de `usuario_id = auth()->id()`, enriquecida con `App\Support\AgenteUsuario::label()` y `App\Support\GeolocalizadorIp::ubicacion()`, en `app/Http/Controllers/ProfileController.php`
- [X] T027 [US4] Añadir sección "Actividad reciente" con estado vacío y enlace condicional "ver más" (`@can('ver-logs')`) hacia `route('logs.index')` en `resources/views/profile/show.blade.php`
- [X] T028 [US4] Actualizar `resources/views/ayuda/profile.blade.php` con la sección de actividad reciente

**Checkpoint**: US1-US4 funcionales de forma independiente.

---

## Phase 7: User Story 5 - Ver mis datos de empleado y fichajes (Priority: P3)

**Goal**: Puesto, centro de trabajo y estado de fichaje en vivo para usuarios vinculados a un `MiembroEquipo` activo; sección omitida si no hay vínculo o está dado de baja.

**Independent Test**: Con un usuario vinculado y con fichajes, comparar el estado mostrado en `/perfil` contra el que muestra `/fichajes`; con un usuario sin vínculo, confirmar que la sección no aparece.

### Tests for User Story 5

- [X] T029 [P] [US5] Test: perfil muestra puesto/centro/estado de fichaje con vínculo activo; sección omitida sin vínculo o con `dado_baja_at` seteado, en `tests/Feature/Profile/ProfileEmpleadoFichajesTest.php`
- [X] T030 [P] [US5] Test de aislamiento multi-tenant: fichajes/miembro de equipo de otro tenant nunca aparecen en el perfil, en `tests/Feature/Profile/ProfileEmpleadoFichajesAislamientoTest.php` (usa `ConTenantSecundario`, depende de T002)

### Implementation for User Story 5

- [X] T031 [US5] Extraer la lógica de derivación de estado (hoy duplicada entre `FichajeController::estadoActual()` y `RegistroFichajes::validarSecuencia()`) a `RegistroFichajes::estadoActual(int $miembroId): string` en `app/Services/RegistroFichajes.php`, y hacer que `FichajeController::estadoActual()` delegue en ese método, en `app/Http/Controllers/FichajeController.php`
- [X] T032 [US5] Añadir en `ProfileController@show`, cuando exista `miembroEquipo` activo: puesto, centro de trabajo, estado de fichaje (vía `RegistroFichajes::estadoActual()`) y últimos eventos de `Fichaje`, en `app/Http/Controllers/ProfileController.php` (depende de T031)
- [X] T033 [US5] Añadir sección "Mi puesto y fichaje" (condicional a vínculo activo, oculta si no hay vínculo o está dado de baja) en `resources/views/profile/show.blade.php`, formateando distancias con `App\Support\Formato`
- [X] T034 [US5] Actualizar `resources/views/ayuda/profile.blade.php` con la sección de empleado/fichajes

**Checkpoint**: US1-US5 funcionales de forma independiente.

---

## Phase 8: User Story 6 - Ver quién aprobó mi cuenta (Priority: P3)

**Goal**: Mostrar aprobador y fecha de aprobación cuando la cuenta ya fue aprobada; estado "pendiente" en caso contrario.

**Independent Test**: Con un usuario aprobado, comprobar que se muestra el nombre del aprobador y la fecha; con uno pendiente, comprobar que se muestra el estado pendiente en su lugar.

### Tests for User Story 6

- [X] T035 [P] [US6] Test: perfil muestra aprobador + fecha cuando `estado = aprobado`, y estado pendiente cuando no, en `tests/Feature/Profile/ProfileAprobacionTest.php`

### Implementation for User Story 6

- [X] T036 [US6] Cargar (eager load) la relación `aprobador()` en `ProfileController@show`, en `app/Http/Controllers/ProfileController.php`
- [X] T037 [US6] Añadir bloque "Aprobación de cuenta" (aprobador + fecha, o estado pendiente) en `resources/views/profile/show.blade.php`
- [X] T038 [US6] Actualizar `resources/views/ayuda/profile.blade.php` con la sección de aprobación de cuenta

**Checkpoint**: Las 6 historias de usuario funcionan de forma independiente.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [X] T039 [P] Ejecutar todos los escenarios de `quickstart.md` manualmente y documentar el resultado
- [X] T040 Revisar si el patrón "solicitud con enlace firmado + estado pendiente" (cambio de email) amerita documentarse como convención reutilizable en `docs/04-front-guidelines.md`
- [X] T041 Revisar y actualizar `resources/ia/conocimiento/*.md` (módulo de perfil) para reflejar las secciones nuevas, según la regla FR-013 (feature 030) de `CLAUDE.md`
- [X] T042 Revisar `docs/03-modelo-datos.md` por si las 3 columnas nuevas de `users` ameritan una mención breve (aunque no cambian el modelo conceptual)
- [X] T043 Ejecutar la suite completa de tests (`php artisan test`) y confirmar que todo pasa en verde, incluidos los tests de aislamiento multi-tenant

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias
- **Foundational (Phase 2)**: depende de Setup; bloquea únicamente los tests de aislamiento de US4 y US5 (el resto de cada historia no depende de T002)
- **User Stories (Phase 3-8)**: US1 y US2 no dependen de Foundational; US4 y US5 dependen de T002 solo para sus tests de aislamiento. Las historias son independientes entre sí (ninguna bloquea a otra)
- **Polish (Phase 9)**: depende de las historias que se decida incluir en el alcance de la entrega

### Dentro de cada historia

- Tests antes que implementación (deben fallar primero)
- Requests/migraciones antes que el controller que los usa
- Controller antes que la vista que consume sus datos
- Ayuda in-app se actualiza en el mismo cambio, no después (regla del proyecto)

### Parallel Opportunities

- T003 (US1), T007 (US2), T013+T014 (US3), T024+T025 (US4), T029+T030 (US5), T035 (US6): todos los tests marcados [P] de historias distintas pueden escribirse en paralelo
- Dentro de US3: T017 y T018 (FormRequests) en paralelo entre sí
- Las historias US1, US2, US4, US6 pueden implementarse en paralelo por distintos desarrolladores una vez completado Setup; US5 requiere T031 antes de T032; US3 requiere T015→T016 antes de T020

---

## Parallel Example: User Story 4

```bash
# Tests de US4 en paralelo:
Task: "Test actividad reciente en tests/Feature/Profile/ProfileActividadRecienteTest.php"
Task: "Test aislamiento multi-tenant en tests/Feature/Profile/ProfileActividadRecienteAislamientoTest.php"
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2, ambas P1)

1. Completar Phase 1 (Setup) y Phase 2 (Foundational, solo si se planea llegar a US4/US5)
2. Completar Phase 3 (US1): corrige el bug del rol legado
3. Completar Phase 4 (US2): cambio de contraseña self-service
4. **Validar de forma independiente** ambas historias contra sus criterios de aceptación en spec.md
5. Este es el MVP entregable: perfil con identidad de acceso correcta + autoservicio de seguridad básico

### Incremental Delivery

1. Setup + Foundational → base lista
2. US1 → validar → (opcional) desplegar
3. US2 → validar → desplegar (MVP completo)
4. US3 (edición nombre/email) → validar → desplegar
5. US4 (actividad reciente) → validar → desplegar
6. US5 (empleado/fichajes) → validar → desplegar
7. US6 (aprobación) → validar → desplegar
8. Polish (Phase 9) al cierre del alcance elegido

---

## Notes

- [P] = archivos distintos, sin dependencias pendientes entre sí
- Cada historia es independientemente completable y testeable, según lo exigido por spec.md
- Los tests de aislamiento multi-tenant (T025, T030) son obligatorios por el Principio I/IV de la constitución del proyecto, no opcionales
- Commitear después de cada tarea o grupo lógico
- Cerrar el spec exige repasar las 4 capas de documentación (docs/, front-guidelines, ayuda in-app, conocimiento IA) — cubierto en T040-T042 más las actualizaciones de ayuda dentro de cada historia
