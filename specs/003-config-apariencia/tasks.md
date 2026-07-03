---

description: "Task list — Configuración del tenant (Apariencia / Marca)"
---

# Tasks: Configuración del tenant — Apariencia / Marca

**Input**: Design documents from `specs/003-config-apariencia/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: INCLUIDOS. El spec exige tests de aislamiento multi-tenant (test-first, Principio IV) y
cobertura de guardado de colores, subida/validación de logo y acceso solo autenticado.

**Organization**: Tareas agrupadas por user story para implementación y testeo independientes.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede correr en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1 (colores), US2 (logo), US3 (tabs futuras)

## Path Conventions

Monolito Laravel: `app/`, `resources/views/`, `database/`, `public/`, `tests/Feature/` en la raíz.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Preparar assets y ruta base sin lógica de negocio.

- [X] T001 [P] Vendorizar el color picker: copiar `template/Laravel-NexaDash-v1.0-28_May_2025/package/public/vendor/jquery-asColorPicker/` a `public/vendor/jquery-asColorPicker/` (css, js, images).
- [X] T002 [P] Crear `public/js/plugins-init/jquery-asColorPicker.init.js` que inicialice `$('.as_colorpicker').asColorPicker()` (modo simple), basándose en el init del banco.
- [X] T003 Ejecutar `php artisan storage:link` (symlink `public/storage`) para poder servir el logo desde el disco `public`. Documentar en quickstart si ya estaba.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migraciones, modelos, ruta/controlador base y pantalla con tabs — necesarios para TODAS
las user stories.

**⚠️ CRITICAL**: Ninguna user story puede empezar hasta completar esta fase.

- [X] T004 Crear migración `database/migrations/xxxx_create_configuraciones_table.php` con columnas de data-model.md (id, tenant_id index, clave, valor nullable, tipo, grupo, descripcion nullable, timestamps) e índices único `(tenant_id, clave)` e index `(tenant_id, grupo)`.
- [X] T005 Crear migración `database/migrations/xxxx_add_logo_path_to_tenants_table.php` que añada `logo_path` varchar nullable a `tenants`.
- [X] T006 [P] Crear modelo `app/Models/Configuracion.php` con trait `BelongsToTenant`, `$fillable` (tenant_id, clave, valor, tipo, grupo, descripcion) y relación `tenant()`.
- [X] T007 [P] Actualizar `app/Models/Tenant.php`: añadir `logo_path` a `$fillable` y a `getCustomColumns()`.
- [X] T008 [P] Crear factory `database/factories/ConfiguracionFactory.php` para usar en tests.
- [X] T009 Crear `app/Support/AparienciaTenant.php`: resuelve del tenant activo las 3 claves `apariencia.color_*` (una query cacheada por `tenant_id`), expone colores efectivos con fallback a defaults del template, el `logo_path`, y calcula el bloque de variables CSS (incluye derivar `--rgba-primary-1..9` desde el primario HEX→RGB). Método para invalidar la caché.
- [X] T010 Crear `app/Http/Controllers/ConfiguracionController.php` con `show()` (retorna vista `configuracion.index` con la apariencia vigente). El `update()` se implementa en US1.
- [X] T011 Registrar rutas en `routes/web.php` dentro del grupo `['auth','tenant.context']`: `GET /configuracion` → `configuracion.show`; `PUT /configuracion/apariencia` → `configuracion.apariencia.update`.
- [X] T012 Crear vista `resources/views/configuracion/index.blade.php` con estructura de tabs (Bootstrap nav-tabs); tab "Apariencia / Marca" activa incluyendo el partial `_tab_apariencia`. Placeholders de tabs futuras se detallan en US3.
- [X] T013 Enlazar la pantalla desde el dropdown de perfil en `resources/views/partials/header.blade.php`: el item "Settings/Configuración" (hoy `javascript:void(0)`) apunta a `route('configuracion.show')`.
- [X] T014 Incluir en `resources/views/layouts/app.blade.php` (en `<head>`, tras `style.css`) el partial `resources/views/partials/apariencia-tenant.blade.php` y compartir la apariencia del tenant con las vistas (View Composer sobre el layout, o `AppServiceProvider`).

**Checkpoint**: Ruta accesible, pantalla con tabs visible, modelos y storage listos.

---

## Phase 3: User Story 1 - Personalizar colores de marca (Priority: P1) 🎯 MVP

**Goal**: El usuario fija color primario, secundario y de fondo de topbar; se guardan por tenant y se
aplican a la interfaz.

**Independent Test**: Login en tenant A, cambiar los 3 colores, guardar, recargar y ver los colores
aplicados; verificar persistencia en `configuraciones` y aislamiento respecto a otro tenant.

### Tests for User Story 1 (test-first) ⚠️

> Escribir primero, deben FALLAR antes de implementar. Aislamiento = NON-NEGOTIABLE (Principio IV).

- [X] T015 [P] [US1] `tests/Feature/ConfiguracionTenantIsolationTest.php`: con ≥2 tenants, guardar colores como usuario de A NO crea/modifica `configuraciones` de B; cargar `/configuracion` como A no muestra valores de B.
- [X] T016 [P] [US1] En `tests/Feature/ConfiguracionAparienciaTest.php`: guardar los 3 colores persiste las claves `apariencia.color_*` con el `tenant_id` activo y devuelve éxito.
- [X] T017 [P] [US1] En `tests/Feature/ConfiguracionAparienciaTest.php`: un color con formato inválido (no HEX `#RRGGBB`) devuelve error de validación y no persiste.
- [X] T018 [P] [US1] En `tests/Feature/ConfiguracionAparienciaTest.php`: `GET /configuracion` sin autenticar redirige a `/login`.

### Implementation for User Story 1

- [X] T019 [US1] Crear `app/Http/Requests/UpdateAparienciaRequest.php` con reglas: `color_primario|color_secundario|color_topbar` nullable + regex HEX `/^#[0-9A-Fa-f]{6}$/`; `restablecer` nullable boolean (logo se añade en US2).
- [X] T020 [US1] Implementar `ConfiguracionController::update()`: upsert de las claves `apariencia.color_*` en `configuraciones` para el tenant activo (tipo=string, grupo=apariencia); invalidar caché de `AparienciaTenant`; responder redirect con flash `success` o JSON si `Accept: application/json` (patrón de `ClienteController`).
- [X] T021 [US1] Implementar el partial `resources/views/partials/apariencia-tenant.blade.php`: emitir `<style>:root{...}</style>` con `--primary`, `--primary-hover`, `--rgba-primary-1..9`, `--secondary` y `--topbar-bg` solo para las claves configuradas por el tenant (usando `AparienciaTenant`). Añadir la regla que aplica `--topbar-bg` al contenedor de la topbar.
- [X] T022 [US1] Crear `resources/views/configuracion/_tab_apariencia.blade.php` con el formulario (method PUT, `@csrf`): 3 inputs `.as_colorpicker` precargados con los colores vigentes, botón Guardar y acción Restablecer. Cargar el CSS/JS del color picker vía `@push`.
- [X] T023 [US1] Implementar la acción **Restablecer** en `update()` (cuando `restablecer=true`): borrar las 3 claves de color del tenant e invalidar caché (el logo se contempla en US2).
- [X] T024 [P] [US1] Test de restablecer colores en `tests/Feature/ConfiguracionAparienciaTest.php`: tras restablecer, las claves `apariencia.color_*` del tenant ya no existen.

**Checkpoint**: US1 funcional — colores guardados y aplicados, con aislamiento verificado. **MVP.**

---

## Phase 4: User Story 2 - Subir logo con vista previa (Priority: P2)

**Goal**: El usuario sube un logo, ve preview antes de guardar, y el logo se muestra en el menú.

**Independent Test**: Seleccionar imagen válida → ver preview → guardar → logo en nav-header y
`tenants.logo_path` seteado; archivo inválido/grande → rechazo sin cambiar el logo vigente.

### Tests for User Story 2 ⚠️

- [X] T025 [P] [US2] En `tests/Feature/ConfiguracionAparienciaTest.php` (con `Storage::fake('public')`): subir imagen válida guarda el fichero, setea `tenants.logo_path` del tenant activo y responde éxito.
- [X] T026 [P] [US2] En `tests/Feature/ConfiguracionAparienciaTest.php`: subir un archivo no-imagen o que supera el tamaño máximo devuelve error de validación y NO cambia `logo_path`.
- [X] T027 [P] [US2] En `tests/Feature/ConfiguracionTenantIsolationTest.php`: el logo subido por A no afecta al `logo_path` de B.

### Implementation for User Story 2

- [X] T028 [US2] Añadir la regla del logo a `UpdateAparienciaRequest`: `logo` `nullable|image|mimes:png,jpg,jpeg,webp|max:1024`.
- [X] T029 [US2] Extender `ConfiguracionController::update()`: si viene `logo`, almacenarlo en disco `public` bajo `logos/{tenant_id}/`, actualizar `tenants.logo_path`, borrar el fichero anterior si existía; en `restablecer=true` poner `logo_path=null` y borrar el fichero.
- [X] T030 [US2] Añadir al `_tab_apariencia.blade.php` el `input type=file` de logo (form ya `multipart/form-data`) con un `<img>` de preview y mostrar el logo actual si existe.
- [X] T031 [P] [US2] Crear `public/js/plugins-init/configuracion-apariencia.init.js`: al elegir fichero, usar `FileReader` para pintar la vista previa en el `<img>`. Encolarlo en la vista.
- [X] T032 [US2] Actualizar `resources/views/partials/nav-header.blade.php`: renderizar `<img src="{{ asset('storage/'.tenant logo_path) }}">` cuando el tenant tenga `logo_path`; si no, el logo SVG por defecto.

**Checkpoint**: US1 + US2 funcionan de forma independiente.

---

## Phase 5: User Story 3 - Estructura de tabs preparada para crecer (Priority: P3)

**Goal**: La pantalla muestra tabs diferenciadas; además de "Apariencia / Marca", marcadores de
secciones futuras inertes (sin error).

**Independent Test**: Abrir `/configuracion`, ver la barra de tabs con "Apariencia / Marca" funcional y
otras secciones señalizadas como próximas que no producen error al seleccionarlas.

### Implementation for User Story 3

- [X] T033 [US3] En `resources/views/configuracion/index.blade.php`, añadir tabs marcador (p. ej. Facturación, Verifactu, Email) con estado "Próximamente" y contenido inerte (sin formularios), deshabilitadas o con aviso.
- [X] T034 [P] [US3] En `tests/Feature/ConfiguracionAparienciaTest.php`: `GET /configuracion` autenticado responde 200 y la vista contiene la navegación de tabs (incluida "Apariencia / Marca").

**Checkpoint**: Las 3 user stories funcionan de forma independiente.

---

## Phase N: Polish & Cross-Cutting Concerns

- [X] T035 Ejecutar la suite completa `php artisan test` y dejar todo en verde.
- [X] T036 Validar manualmente los escenarios de `quickstart.md` (colores, logo+preview, aislamiento, restablecer, acceso).
- [X] T037 [P] Al cerrar la feature, revisar y actualizar `docs/03-modelo-datos.md` (tabla `configuraciones` implementada y `tenants.logo_path` en uso) según CLAUDE.md; evaluar si procede `/speckit-constitution` (previsiblemente no: sin cambio de principios).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup — BLOQUEA todas las user stories.
- **User Stories (Phase 3-5)**: dependen de Foundational. US1 es el MVP; US2 y US3 pueden ir después.
- **Polish**: tras completar las stories deseadas.

### User Story Dependencies

- **US1 (P1)**: tras Foundational. Independiente.
- **US2 (P2)**: tras Foundational. Reutiliza el mismo controlador/FormRequest/vista que US1 (mismos
  archivos), por lo que en la práctica se implementa después de US1 para evitar conflictos de edición.
- **US3 (P3)**: tras Foundational. Independiente (solo toca la vista index de tabs).

### Within Each User Story

- Tests test-first (fallan primero) → FormRequest → controlador → vistas/JS.

### Parallel Opportunities

- Setup: T001 y T002 en paralelo.
- Foundational: T006, T007, T008 en paralelo (archivos distintos) tras las migraciones.
- US1: los tests T015–T018 en paralelo antes de implementar.
- US2: los tests T025–T027 en paralelo antes de implementar.
- Nota: US1 y US2 comparten `ConfiguracionController`, `UpdateAparienciaRequest` y
  `_tab_apariencia.blade.php` → NO editar esos archivos en paralelo entre ambas stories.

---

## Parallel Example: User Story 1

```text
# Tests de US1 juntos (deben fallar primero):
Task: T015 aislamiento en tests/Feature/ConfiguracionTenantIsolationTest.php
Task: T016 guardado de colores en tests/Feature/ConfiguracionAparienciaTest.php
Task: T017 validación de color inválido
Task: T018 acceso solo autenticado
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 Setup → 2. Phase 2 Foundational → 3. Phase 3 US1 → 4. **Validar US1** → demo.

### Incremental Delivery

Setup + Foundational → US1 (MVP: colores) → US2 (logo) → US3 (tabs futuras). Cada story agrega valor
sin romper las anteriores.

---

## Notes

- [P] = archivos distintos, sin dependencias.
- Verificar que los tests de aislamiento fallan antes de implementar (Principio IV).
- Commit tras cada tarea o grupo lógico.
- Evitar editar en paralelo archivos compartidos entre US1 y US2.
