---

description: "Task list — 037 Panel de Super Admin aislado + home con estadísticas de tenants"
---

# Tasks: Panel de Super Admin aislado + home con estadísticas de tenants

**Input**: Design documents from `specs/037-super-admin-panel-aislado/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/http.md](./contracts/http.md),
[contracts/panel-home.md](./contracts/panel-home.md), [quickstart.md](./quickstart.md)

**Tests**: SÍ, obligatorios. Constitución Principio IV (NON-NEGOTIABLE): el aislamiento multi-tenant
es área crítica ⇒ los tests de US1 se escriben **antes** de la implementación y deben fallar primero.
Los tests de US2/US3 no son test-first estricto, pero son parte de la definición de "hecho".

**Organization**: agrupadas por historia de usuario para poder entregar US1 (el arreglo de seguridad)
sin esperar a la home.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1 / US2 / US3
- Toda tarea incluye la ruta exacta del archivo

## Convenciones que condicionan estas tareas (no reinterpretarlas al implementar)

Citadas desde `docs/04-front-guidelines.md` (leídas antes del spec, REGLA DE ORO de `CLAUDE.md`);
tabla completa en `plan.md`, sección "Cumplimiento de docs/04-front-guidelines.md":

- **"Icono flotante en cards informativas (métricas)"** — `<x-lordicon>` dentro de un `<div>` **sin
  clases** (nunca `icon-box bg-primary-light`), `size="50"`.
- **"Colores de los lordicon"** — no pasar `colors` a mano.
- **"Gap entre `.row` y `margin-bottom` de `.card`"** — nada de `mb-3`/`g-3` en las columnas.
- **"Tamaño de formularios: todo sm por defecto"** — nada de `.btn-sm`.
- **"Notificaciones"** (`CLAUDE.md`) — toastr vía flash/`showToast`, nunca `.alert` ad-hoc.
- **"Listados: SIEMPRE DataTable"** — aplica al listado de tenants (intacto), **no** a los widgets de
  resumen de la home; la excepción se documenta en la guía como parte de esta feature (T029).
- **"Ayuda contextual"** — la vista nueva declara sus `@section('ayuda*')` (T024).
- **"Nueva entrada de menú ⇒ nuevo permiso"** — **no aplica** a la rama de Super Admin del sidebar
  (fuera de `CatalogoMenu` por diseño): no se toca `CatalogoPermisos` ni `CatalogoMenu` (T023).

---

## Phase 1: Setup

**Purpose**: preparar el terreno; no hay dependencias que instalar (cero librerías nuevas).

- [X] T001 Verificar que no hace falta ninguna migración ni dependencia nueva: releer [data-model.md](./data-model.md) y confirmar que `tenants`, `users`, `facturas`, `logs_actividad` y `domains` ya tienen todo lo necesario. Si alguna tarea posterior pide una migración, es señal de desvío del plan y hay que parar.
- [X] T002 [P] Confirmar que `public/vendor/chartjs/` está vendorizado y en uso (referencia: `public/js/plugins-init/dashboard*.js`, `new Chart(...)`) para reutilizarlo en la home sin añadir dependencias.

---

## Phase 2: Foundational (bloqueante)

**Purpose**: la ruta y el controller de la home deben existir antes que el middleware, porque el
middleware redirige a `super_admin.home` (si no existe, el bloqueo produce un error de ruta).

**⚠️ CRÍTICO**: ninguna historia puede completarse hasta terminar esta fase.

- [X] T003 Crear `app/Http/Controllers/SuperAdmin/PanelController.php` con el método `index()` devolviendo la vista `super_admin.panel.index` (de momento con `datos` vacío; el servicio llega en US2).
- [X] T004 Crear la vista mínima `resources/views/super_admin/panel/index.blade.php` extendiendo `layouts.app` con el título "Panel de Super Admin" (esqueleto; el contenido llega en US2).
- [X] T005 Registrar la ruta `GET /super_admin` como `super_admin.home` apuntando a `PanelController@index`, **dentro** del grupo `['tenant.context', 'auth', 'super_admin']` existente y **antes** del `Route::resource('tenants', …)`, en `routes/web.php` (ver [contracts/http.md](./contracts/http.md) §2).

**Checkpoint**: `/super_admin` responde 200 para un Super Admin y 403 para el resto.

---

## Phase 3: User Story 1 — El Super Admin no puede entrar a pantallas de empresa (P1) 🎯 MVP

**Goal**: cerrar estructuralmente el acceso del Super Admin al área de tenant, sin romper el acceso
de los usuarios de empresa.

**Independent Test**: `php artisan test --filter=SuperAdminAreaTenantBloqueada` en verde + la
matriz de acceso de [contracts/http.md](./contracts/http.md) §4 comprobada a mano.

### Tests para US1 (TEST-FIRST — deben fallar antes de implementar) ⚠️

- [X] T006 [US1] Crear `tests/Feature/SuperAdmin/SuperAdminAreaTenantBloqueadaTest.php` con los casos de navegación: como Super Admin en el dominio central, `GET /clientes`, `/facturas`, `/articulos`, `/configuracion`, `/logs` y `/` redirigen a `route('super_admin.home')` y dejan flash `warning`. **Ejecutar y confirmar que fallan** antes de seguir.
- [X] T007 [P] [US1] En el mismo archivo, casos de API: `GET /clientes` con `Accept: application/json` responde 403 JSON con `message`, y `POST /clientes` responde 403 sin crear ningún registro (`assertDatabaseCount`).
- [X] T008 [P] [US1] En el mismo archivo, casos de allowlist: como Super Admin, `GET /perfil` responde 200, `POST /logout` redirige a login y `GET /localidades` responde 200.
- [X] T009 [P] [US1] En el mismo archivo, casos de no-regresión (FR-005) con **≥2 tenants** (Principio I): un usuario con permisos de cada tenant accede a `/clientes` y `/facturas` **de su propio dominio** con 200 y no ve datos del otro tenant; y sigue recibiendo 403 en `/super_admin/tenants`.
- [X] T009b [P] [US1] En el mismo archivo, **guardia estructural de FR-002/SC-002**: recorrer `Route::getRoutes()` y afirmar que **toda** ruta cuyo middleware incluye `tenant.context` y `auth` y que **no** pertenece al grupo `super_admin` lleva también `sin_super_admin`. Es el test que hace que una sección futura no pueda quedarse fuera del bloqueo por olvido.
- [X] T009c [P] [US1] En el mismo archivo, **registro del intento (FR-008/SC-008)**: con `Log::spy()`, afirmar que un intento bloqueado emite un `warning` `super_admin.acceso_area_tenant_bloqueado` con `usuario_id`, `ruta`, `ip` y `user_agent`, y que **no** se creó ninguna fila en `logs_actividad` (`assertDatabaseCount`).
- [X] T010 [P] [US1] En el mismo archivo, caso de simetría de dominio (FR-006, escenario 6 de US1): una sesión de Super Admin presentada en el dominio de un tenant no obtiene la pantalla de ese tenant.

### Implementación para US1

- [X] T011 [US1] Crear `app/Http/Middleware/BloquearSuperAdminAreaTenant.php` implementando las reglas de [contracts/http.md](./contracts/http.md) §1: no-super-admin pasa; allowlist `logout`, `profile.*`, `localidades.index` pasa; `expectsJson()` → 403 JSON; resto → redirect a `super_admin.home` con flash `warning`. Mensaje exacto del contrato.
- [X] T012 [US1] Añadir en el mismo middleware el `Log::warning('super_admin.acceso_area_tenant_bloqueado', [...])` con usuario, método, URI, nombre de ruta, IP y user-agent (FR-008). **No** escribir en `logs_actividad` (research D4).
- [X] T013 [US1] Registrar el alias `'sin_super_admin' => BloquearSuperAdminAreaTenant::class` en `bootstrap/app.php`, junto a `tenant.context` y `super_admin`.
- [X] T014 [US1] Aplicar `sin_super_admin` al grupo del área de tenant en `routes/web.php` (`['tenant.context', 'auth', 'sin_super_admin']`), **después** de `auth`. Verificar que el grupo `super_admin` NO lo lleva.
- [X] T015 [US1] Eliminar de `app/Http/Controllers/DashboardController.php` la rama `if ($request->user()->isSuperAdmin()) { return redirect()->route('super_admin.tenants.index'); }`, ya inalcanzable, dejando un comentario de una línea que apunte al middleware como única puerta (research D3).
- [X] T016 [US1] Ejecutar `php artisan test --filter=SuperAdmin` y `php artisan test` completos: los tests nuevos en verde y **cero regresiones** en `SuperAdminBypassTest`, `TenantDomainResolutionTest` y el resto de la batería (SC-003). Si algún test existente se pone rojo, es una regresión a arreglar, no un test a "ajustar".

**Checkpoint**: US1 entregable por sí sola — el agujero de acceso queda cerrado aunque la home siga
siendo el esqueleto de T004.

---

## Phase 4: User Story 2 — Home del Super Admin con la foto del SaaS (P2)

**Goal**: que el Super Admin aterrice en una vista de conjunto de los tenants.

**Independent Test**: `php artisan test --filter=SuperAdminPanelHome` en verde + los pasos 3 y 5 de
[quickstart.md](./quickstart.md).

### Tests para US2

- [X] T017 [P] [US2] Crear `tests/Feature/SuperAdmin/SuperAdminPanelHomeTest.php`: con un conjunto conocido de tenants (activos/inactivos, altas en meses distintos) y usuarios, afirmar la forma y los valores exactos de `datos` según [contracts/panel-home.md](./contracts/panel-home.md), incluidas las invariantes (`activos + inactivos == tenants`, `count(serie_altas) == 12`), el caso "cero tenants" (estado vacío) y que el Super Admin no se cuenta en `totales.usuarios`.
- [X] T018 [P] [US2] En el mismo archivo, test de no-fuga (FR-019, Principio I): con 2 tenants con facturas, el ranking expone solo recuentos y ningún dato de negocio (números de factura, nombres de cliente, importes) aparece en la respuesta.

### Implementación para US2

- [X] T019 [US2] Crear `app/Services/EstadisticasTenants.php` con `resumen(): array` devolviendo exactamente la estructura de [contracts/panel-home.md](./contracts/panel-home.md). Reglas obligatorias de [data-model.md](./data-model.md): consultas agregadas con `groupBy('tenant_id')` cruzadas en PHP (prohibido consultar dentro de un bucle de tenants), eje de 12 meses generado en PHP rellenando ceros, sin caché.
- [X] T020 [US2] Conectar `SuperAdmin\PanelController@index` al servicio y pasar `datos` a la vista; sin variante JSON (la home no se recarga por AJAX).
- [X] T021 [US2] Desarrollar `resources/views/super_admin/panel/index.blade.php`: 5 tarjetas de métricas (total, activos, inactivos, altas del mes, usuarios) con `<x-lordicon>` en un `<div>` sin clases, `size="50"`, `trigger="hover"`, `target=".card"` — iconos existentes en `public/icons/lordicon/` (p. ej. `empresa`, `people`, `wired-outline-153-bar-chart`); `<canvas id="chart-altas-tenants">`; widgets "Últimos tenants creados" y "Ranking por tamaño" como `<table class="table table-borderless mb-0">` + `@foreach` (patrón de `partials/dashboard-contenido.blade.php`); estado vacío de FR-015 con acción "Crear el primer tenant" enlazando a la gestión de tenants.
- [X] T022 [US2] Crear `public/js/plugins-init/super-admin-panel.init.js`: lee la serie desde `window.panelSuperAdminState` (declarado en la vista con `@json`) y crea el `new Chart(...)` de barras; encolarlo junto a `vendor/chartjs/...` en el `@push('scripts')` de la vista.
- [X] T023 [US2] Añadir la entrada "Inicio" del panel a la rama `@if (auth()->user()->isSuperAdmin())` de `resources/views/partials/sidebar.blade.php`, apuntando a `super_admin.home`, con `size="30"` y `class="nav-text ms-2"` (convención "Menú lateral: tamaño de ícono y texto"). **No** tocar `CatalogoMenu` ni `CatalogoPermisos` (FR-007, research D6.10).
- [X] T024 [US2] Crear `resources/views/ayuda/super-admin-panel.blade.php` y declarar en la vista `@section('ayuda-titulo', 'Panel de Super Admin')` + `@section('ayuda') @include('ayuda.super-admin-panel') @endsection`, siguiendo el markup de la convención "Ayuda contextual" (intro + `<ol>` de pasos + `<p class="ayuda-nota">`). Al escribir los tests que afirmen texto de la vista, recordar que la ayuda también se renderiza: afirmar sobre marcadores de HTML, no sobre texto plano ambiguo.

**Checkpoint**: US1 + US2 funcionando de forma independiente.

---

## Phase 5: User Story 3 — Detección de tenants que necesitan atención (P3)

**Goal**: señalar en la home los tenants desactivados, sin usuarios que puedan entrar o sin actividad
reciente.

**Independent Test**: `php artisan test --filter=SuperAdminPanelAtencion` en verde + paso 4 de
[quickstart.md](./quickstart.md).

### Tests para US3

- [X] T025 [P] [US3] Crear `tests/Feature/SuperAdmin/SuperAdminPanelAtencionTest.php` con un tenant por criterio (desactivado; sin usuarios `aprobado`+`activo`; sin `logs_actividad` en 30 días) y afirmar que cada uno aparece con el motivo correcto.
- [X] T026 [P] [US3] En el mismo archivo: un tenant que cumple **dos** criterios aparece una sola vez con ambos motivos; y con todos los tenants en orden, `atencion` queda vacío (estado vacío positivo, FR-018).

### Implementación para US3

- [X] T027 [US3] Añadir a `app/Services/EstadisticasTenants.php` el bloque `atencion` con los tres criterios de [contracts/panel-home.md](./contracts/panel-home.md), en consultas agregadas (una por criterio, sin N+1) y motivos como array por tenant.
- [X] T028 [US3] Añadir el widget "Requieren atención" a `resources/views/super_admin/panel/index.blade.php` con un badge por motivo, enlace a la gestión del tenant, y estado vacío positivo ("Todos los tenants en orden") cuando la lista viene vacía.

**Checkpoint**: las tres historias funcionan de forma independiente.

---

## Phase 6: Polish & documentación (regla transversal de `CLAUDE.md`)

- [X] T029 [P] Documentar en `docs/04-front-guidelines.md` la excepción a "Listados: SIEMPRE DataTable" para los **widgets de resumen de dashboard/panel** (top-N fijo, no listado de módulo), citando `partials/dashboard-contenido.blade.php` y la home del panel como referencias (research D6.1).
- [X] T030 [P] Añadir a `docs/01-arquitectura.md`, como ampliación de la Decisión 4 (panel Super Admin), la decisión de aislamiento: el área de tenant se cierra al Super Admin con un middleware a nivel de grupo, `Gate::before` se mantiene intacto, y los intentos bloqueados van al log de aplicación y no a `logs_actividad` (con el porqué: `tenant_id` NOT NULL).
- [X] T031 [P] Verificar la capa 4 de documentación (`resources/ia/conocimiento/*.md`): esta feature no añade ni cambia pantallas del área de tenant, así que **no** corresponde archivo nuevo; dejar constancia de la verificación en el PR/commit en vez de omitirla en silencio (research D8).
- [ ] T032 Ejecutar la validación completa de [quickstart.md](./quickstart.md) (secciones 2 a 6) contra el entorno local, incluida la comprobación del `storage/logs/laravel.log`. **Omitido a propósito**: recorrido manual en navegador requiere confirmación explícita (regla de `CLAUDE.md`); preguntado al usuario al cerrar la feature y decidió que la cobertura de tests automatizados (equivalente funcional de cada paso del quickstart) alcanza, sin recorrido manual.
- [X] T033 Revisar rendimiento contra SC-006: contar las consultas de la home (p. ej. con `DB::listen` temporal o Telescope si estuviera disponible) y confirmar que el número **no crece** con el número de tenants.
- [X] T034 Verificar FR-016: el listado de gestión de tenants (`resources/views/super_admin/tenants/index.blade.php` + `super-admin-tenants-*.init.js`) conserva toda su funcionalidad (alta, edición, usuarios del tenant, borrado, columnas, exportación) — la feature no debe haberlo tocado; confirmar con `git diff` que esos archivos no aparecen modificados salvo motivo justificado.
- [X] T035 Repaso final de la matriz de acceso de [contracts/http.md](./contracts/http.md) §4 y de que ninguna pantalla del panel enlaza al área de tenant (edge case de enlaces internos).

---

## Dependencies & Execution Order

### Dependencias entre fases

- **Phase 1 (Setup)**: sin dependencias.
- **Phase 2 (Foundational)**: depende de Phase 1. **BLOQUEA** a US1 (el middleware redirige a
  `super_admin.home`, que debe existir).
- **Phase 3 (US1)**: depende de Phase 2. Entregable por sí sola (MVP).
- **Phase 4 (US2)**: depende de Phase 2. Independiente de US1 en código, aunque el aterrizaje
  automático solo se aprecia con US1 desplegada.
- **Phase 5 (US3)**: depende de T019 (el servicio de US2 existe) — es el único acoplamiento real
  entre historias, y es de archivo, no de comportamiento.
- **Phase 6**: al final.

### Dentro de cada historia

- Los tests de US1 (T006–T010, incluidos T009b y T009c) van **antes** de T011–T015 y deben fallar
  primero (Principio IV).
- Servicio antes que vista; vista antes que init JS.

### Oportunidades de paralelismo

- T001 y T002 en paralelo.
- T007–T010 (con T009b y T009c) en paralelo entre sí (mismo archivo de test pero métodos
  independientes: si se reparte entre personas, coordinar el archivo).
- T017 y T018 en paralelo; T025 y T026 en paralelo.
- T029, T030 y T031 en paralelo (documentos distintos).

---

## Implementation Strategy

### MVP primero (solo US1)

1. Phase 1 → Phase 2 → Phase 3.
2. **PARAR y VALIDAR**: sección 2 de `quickstart.md` + batería completa de tests.
3. Es desplegable tal cual: cierra el problema de seguridad sin la home definitiva.

### Entrega incremental

1. Setup + Foundational → base lista.
2. US1 → validar → desplegar (MVP, arreglo de seguridad).
3. US2 → validar → desplegar (home con estadísticas).
4. US3 → validar → desplegar (tenants que requieren atención).
5. Phase 6 → documentación y verificación final.

---

## Notes

- **Cero migraciones** en esta feature. Cualquier tarea que acabe necesitando una es un desvío del
  plan: parar y revisar (`CLAUDE.md`: nunca `migrate:fresh`/`refresh` sin confirmación explícita).
- **No tocar `Gate::before`** ni `SuperAdminBypassTest`: el bypass de permisos es deseado; lo que se
  añade es una regla de contexto (research D1).
- El texto del mensaje de bloqueo es el mismo en redirect y en JSON (contrato), para que los tests
  puedan afirmarlo una sola vez.
- Commit por tarea o grupo lógico; parar en cualquier checkpoint para validar.
