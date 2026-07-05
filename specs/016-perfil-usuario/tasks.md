# Tasks: Vista de perfil de usuario (Mi perfil)

**Feature**: `016-perfil-usuario` | **Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

**Input**: plan.md, spec.md, research.md, data-model.md, contracts/profile-ui.md, quickstart.md

**Alcance**: feature de presentación. El controller (`ProfileController@show`, `updateAvatar`) y
las rutas (`profile.show`, `profile.avatar.update`) YA existen. Falta la vista y la presentación
de labels de enums. Sin migraciones, sin cálculo fiscal, sin rutas nuevas.

---

## Phase 1: Setup

- [X] T001 Leer `docs/04-front-guidelines.md` y anotar convenciones aplicables (layout de preview de imagen, notificaciones toastr, orden de assets) antes de tocar la vista.
- [X] T002 Confirmar en `routes/web.php` que `profile.show` (GET `/perfil`) y `profile.avatar.update` (POST `/perfil/avatar`) están bajo el middleware de auth + tenant, y localizar el enlace de acceso a "Mi perfil" en `resources/views/partials/header.blade.php` / `resources/views/partials/sidebar.blade.php`.

---

## Phase 2: Foundational (prerequisito de US1 y US2)

**Objetivo**: presentación en español de los enums que la vista necesita.

- [X] T003 [P] Añadir método `label(): string` a `app/Enums/UserRole.php` (SuperAdmin → "Super Admin", Admin → "Administrador", Usuario → "Usuario").
- [X] T004 [P] Añadir métodos `label(): string` y `badgeClass(): string` a `app/Enums/EstadoUsuario.php` (Pendiente → "Pendiente"/warning, Aprobado → "Aprobado"/success, Rechazado → "Rechazado"/danger).

**Checkpoint**: enums exponen etiquetas legibles reutilizables en toda la UI.

---

## Phase 3: User Story 1 - Ver mi perfil (Priority: P1) 🎯 MVP

**Objetivo**: renderizar `profile.show` con los datos reales del usuario autenticado, trasplantando
solo la cabecera del template NexaDash y descartando el contenido demo.

**Independent Test**: iniciar sesión, ir a `/perfil` y ver nombre, avatar, rol, email, empresa,
estado y fecha de alta reales, sin errores ni datos demo.

- [X] T005 [US1] Crear `resources/views/profile/show.blade.php` extendiendo `layouts/app.blade.php`, con page-title/breadcrumb "Mi perfil" en español, dentro del `.container`.
- [X] T006 [US1] Trasplantar la card de cabecera de perfil desde `template/Laravel-NexaDash-v1.0-28_May_2025/package/resources/views/profile/overview-profile.blade.php` (avatar redondeado + badge de estado, nombre, lista de meta en línea con iconos `las la-*`), descartando feed, comentarios, galerías, quotes, embeds, "Follow/Offer a Deal", redes sociales, stat cards de earnings, tabs, widgets de columna derecha, to-dos y charts (FR-004).
- [X] T007 [US1] Poblar la cabecera con datos reales de `$user`: `name`, `avatarUrl()`, `rol->label()`, `email`, nombre de empresa (`$user->tenant?->name ?? 'Sin empresa'`), badge de estado (`estado->label()` + `estado->badgeClass()`) y fecha de alta (`created_at`, con fallback "—"). Textos en español (FR-002, FR-003, FR-008, FR-009).
- [X] T008 [US1] Verificar que la vista respeta el tema claro/oscuro persistido (usa solo clases del template/`style.css`, sin estilos que rompan el theming) y que el avatar por defecto se muestra cuando no hay `avatar_path`.

**Checkpoint**: `/perfil` funciona como vista de solo lectura completa (MVP entregable).

---

## Phase 4: User Story 2 - Cambiar mi foto de perfil (Priority: P2)

**Objetivo**: permitir subir/reemplazar la foto desde la vista usando el endpoint existente, con
confirmación toastr.

**Independent Test**: desde `/perfil`, subir una imagen válida → avatar cambia + toast de éxito;
subir archivo inválido → error y avatar sin cambios.

- [X] T009 [US2] Añadir en `resources/views/profile/show.blade.php` un formulario `multipart/form-data` con `@csrf` que apunte a `route('profile.avatar.update')`, con input `file` `avatar` y botón de guardar, integrado en la card de cabecera (patrón de preview de `docs/04-front-guidelines.md`).
- [X] T010 [US2] Asegurar que `resources/views/layouts/app.blade.php` incluye `partials/flash-toastr` (ya global) para que el flash `success` del controller dispare el toast; mostrar los errores de validación (`@error('avatar')`) de forma legible en español. No introducir alerts Bootstrap ad-hoc.

**Checkpoint**: cambio de foto operativo con notificaciones toastr.

---

## Phase 5: Polish & Cross-Cutting

- [X] T011 [P] Crear `tests/Feature/ProfileTest.php`: (a) `GET /perfil` sin auth redirige a login; (b) usuario autenticado ve su `name`/`email`; (c) aislamiento — dos usuarios distintos ven cada uno sus propios datos y no los del otro; (d) valores de reserva ("Sin empresa" cuando `tenant` es null).
- [X] T012 [P] Verificar el flujo completo con `specs/016-perfil-usuario/quickstart.md` (escenarios 1–4) y ejecutar `php artisan test --filter=ProfileTest`.
- [X] T013 Revisar visualmente en claro/oscuro y responsive; confirmar que no quedó ningún resto de contenido demo del template en la vista.

---

## Dependencies & Execution Order

- **Setup (T001–T002)** → primero.
- **Foundational (T003–T004)** → antes de US1/US2 (la vista usa `label()`/`badgeClass()`). T003 y T004 en paralelo (archivos distintos).
- **US1 (T005–T008)** → MVP. Depende de Foundational. T005→T006→T007→T008 secuencial (mismo archivo).
- **US2 (T009–T010)** → depende de que exista la vista (T005). Añade al mismo archivo, tras US1.
- **Polish (T011–T013)** → tras US1 (y US2 para T012/T013). T011 y T012 en paralelo entre sí solo si no compiten por el mismo archivo; T011 crea el test, T012 lo ejecuta → ejecutar T012 después de T011.

## Parallel Opportunities

- T003 ∥ T004 (enums distintos).
- T011 (crear test) puede escribirse en paralelo a la implementación de UI de US1/US2, pero se ejecuta (T012) al final.

## Implementation Strategy

- **MVP = Phase 1 + Phase 2 + Phase 3 (US1)**: perfil de solo lectura funcional en `/perfil`.
- **Incremento 2 = Phase 4 (US2)**: cambio de foto.
- **Cierre = Phase 5**: tests de aislamiento + validación visual.
