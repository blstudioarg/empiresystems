# Tasks: Gestor documental por tenant (Drive)

**Feature**: `019-gestor-documental` | **Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

**Docs**: [research.md](./research.md) · [data-model.md](./data-model.md) ·
[contracts/](./contracts/) · [quickstart.md](./quickstart.md)

**Convenciones**: Laravel 12, `BelongsToTenant` + `SoftDeletes`, Form Requests, vistas Blade sobre
layout `default`, JS en `public/js/plugins-init/`, notificaciones toastr (`window.showToast`).
Test-first OBLIGATORIO (Principio IV) en el aislamiento multi-tenant de `carpetas`/`archivos` y en
la barrera de descarga/preview: esos tests se escriben antes y deben fallar primero.

---

## Phase 1: Setup

- [X] T001 Crear el disco privado `documentos` (driver `local`, root `storage_path('app/tenants')`, visibility `private`) en `config/filesystems.php`
- [X] T002 Registrar las claves de configuración del grupo `archivos` (`archivos.limite_mb` default `10` integer; `archivos.tipos_permitidos` json) en `database/seeders/ConfiguracionSeeder.php` con `firstOrCreate` por `(tenant_id, clave)`
- [X] T003 [P] Crear `app/Support/ArchivosTenant.php` con constantes de defaults (`CLAVE_LIMITE_MB`/`DEFAULT_LIMITE_MB=10`, `EXTENSIONES_PERMITIDAS`, `MIMES_PERMITIDOS`) y helpers de resolución de config por tenant
- [X] T004 [P] Añadir la clave `archivos.limite_mb` (y su default) a la tabla "Ejemplos de claves" de `docs/03-modelo-datos.md` (paso 2 del alta de configuración)

---

## Phase 2: Foundational (bloqueante para todas las historias)

**Migraciones, modelos y servicio de almacenamiento — prerequisito de cualquier historia.**

- [X] T005 [P] Migración `create_carpetas_table` en `database/migrations/` (id, tenant_id fk+índice, parent_id fk nullable→carpetas, nombre varchar(255), softDeletes, timestamps; único `(tenant_id, parent_id, nombre)`; índice `(tenant_id, parent_id)`) — ver [data-model.md](./data-model.md)
- [X] T006 [P] Migración `create_archivos_table` en `database/migrations/` (id, tenant_id fk+índice, carpeta_id fk nullable→carpetas, nombre, nombre_original, ruta, mime, extension, tamano unsignedBigInteger, subido_por fk nullable→users nullOnDelete, softDeletes, timestamps; índice `(tenant_id, carpeta_id)`)
- [X] T007 [P] Modelo `app/Models/Carpeta.php` (`BelongsToTenant`, `SoftDeletes`, `HasFactory`; relaciones `tenant`, `padre`/`subcarpetas` autorreferenciadas, `archivos`; `$fillable`)
- [X] T008 [P] Modelo `app/Models/Archivo.php` (`BelongsToTenant`, `SoftDeletes`, `HasFactory`; relaciones `tenant`, `carpeta`, `subidoPor`; casts `tamano`→integer; `$fillable`)
- [X] T009 [P] Factory `database/factories/CarpetaFactory.php`
- [X] T010 [P] Factory `database/factories/ArchivoFactory.php`
- [X] T011 Servicio `app/Services/AlmacenArchivos.php`: único punto de escritura física — `guardar(UploadedFile, tenantId): array{ruta,mime,extension,tamano,nombre_original}` (nombre físico UUID en `tenants/{tenantId}/documentos/{uuid}.{ext}`, disco `documentos`) y `borrar(ruta)`; atómico ante fallo (D2/D6 de research)
- [X] T012 Rutas del módulo en `routes/web.php` dentro del grupo `['tenant.context','auth']`: `archivos.index`, `archivos.store`, `archivos.update`, `archivos.destroy`, `archivos.descargar`, `archivos.preview`, `carpetas.store`, `carpetas.update`, `carpetas.destroy` (ver [contracts/](./contracts/))

**Checkpoint**: esquema + modelos + servicio + rutas listos. Ninguna historia es funcional aún.

---

## Phase 3: User Story 1 — Subir y descargar (Priority: P1) 🎯 MVP

**Goal**: un usuario del tenant sube documentos (lista blanca, ≤límite) a la raíz y los descarga.

**Independent Test**: subir un PDF a la raíz, verlo listado con metadatos, descargarlo idéntico.

### Tests (test-first — Principio IV para aislamiento)

- [X] T013 [P] [US1] `tests/Feature/ArchivoAislamientoTenantTest.php`: con ≥2 tenants, B pide `archivos.descargar`/`archivos.preview` de un archivo de A por id → 404, sin binario; B no ve archivos de A en `archivos.index` (debe fallar primero)
- [X] T014 [P] [US1] `tests/Feature/ArchivoSubidaTest.php`: acepta tipo de la lista blanca; rechaza tipo fuera de lista y `.exe` renombrado a `.pdf` (MIME real); rechaza > `archivos.limite_mb`; sin registro ni fichero huérfano ante fallo

### Implementación

- [X] T015 [US1] `app/Http/Requests/StoreArchivoRequest.php`: valida `archivo` (mimes/mimetypes ∈ lista blanca de `ArchivosTenant`, max ≤ `archivos.limite_mb`) y `carpeta_id` nullable acotado al tenant activo
- [X] T016 [US1] `ArchivoController@store` en `app/Http/Controllers/ArchivoController.php`: valida, llama `AlmacenArchivos::guardar`, crea registro con `nombre=nombre_original` y `subido_por=auth()->id()`, transacción atómica; responde JSON con el registro (o flash `success`)
- [X] T017 [US1] `ArchivoController@descargar`: resuelve el modelo **manualmente acotado al tenant activo** (nunca binding implícito, ver memoria `project_tenant_route_binding`), devuelve binario del disco privado con `Content-Disposition: attachment` y nombre visible; otro tenant → 404
- [X] T018 [US1] `ArchivoController@index`: lista archivos (y carpetas) del nivel actual del tenant; en esta historia basta con la raíz; pasa datos a la vista
- [X] T019 [US1] Vista `resources/views/archivos/index.blade.php` (esqueleto sobre layout `default`): listado de archivos con nombre, tamaño, tipo, fecha, "subido por"; botón subir y botón descargar
- [X] T020 [US1] `public/js/plugins-init/archivos-explorer.js`: subida AJAX (drag & drop + barra de progreso), inserción del archivo en el listado sin recargar, toastr de resultado (éxito/error) vía `window.showToast`

**Checkpoint**: MVP entregable — subir y descargar funciona y está aislado por tenant.

---

## Phase 4: User Story 2 — Organizar en carpetas y navegar (Priority: P2)

**Goal**: crear carpetas anidadas, navegar con breadcrumbs, subir dentro de la carpeta actual.

**Independent Test**: crear carpeta, entrar, subir dentro, volver por migas; el archivo vive dentro.

### Tests (test-first para aislamiento)

- [X] T021 [P] [US2] `tests/Feature/CarpetaAislamientoTenantTest.php`: B no puede crear con `parent_id` de A ni ver carpetas de A (debe fallar primero)
- [X] T022 [P] [US2] `tests/Feature/CarpetaCrudTest.php` (parte crear): crea carpeta; rechaza nombre duplicado en el mismo `(tenant_id, parent_id)`; permite mismo nombre en niveles distintos

### Implementación

- [X] T023 [US2] `app/Http/Requests/StoreCarpetaRequest.php`: `nombre` required max:255 único en `(tenant_id, parent_id)` sobre no borrados; `parent_id` nullable acotado al tenant
- [X] T024 [US2] `CarpetaController@store` en `app/Http/Controllers/CarpetaController.php`: crea la carpeta en el nivel indicado; JSON/flash `success`; 422 en duplicado
- [X] T025 [US2] Ampliar `ArchivoController@index`: aceptar query `carpeta` (id del tenant), calcular breadcrumbs (raíz→carpeta actual) y listar subcarpetas + archivos del nivel; carpeta de otro tenant → 404
- [X] T026 [US2] Ampliar `StoreArchivoRequest`/`@store`: respetar `carpeta_id` del nivel actual al subir (archivo asociado a la carpeta, no a la raíz)
- [X] T027 [US2] UI navegación en `archivos/index.blade.php` + partial `_grid.blade.php`: breadcrumbs clicables, tarjetas de carpeta (doble clic/entrar), botón "nueva carpeta"
- [X] T028 [US2] Ampliar `archivos-explorer.js`: crear carpeta (modal/inline + AJAX), navegar entrando/saliendo por breadcrumbs, subir al `carpeta_id` actual

**Checkpoint**: organización jerárquica y navegación funcionando, aisladas por tenant.

---

## Phase 5: User Story 3 — Renombrar, mover y borrar (Priority: P2)

**Goal**: renombrar archivos/carpetas, mover archivos entre carpetas, borrar (carpeta en cascada).

**Independent Test**: renombrar (contenido intacto), mover archivo, borrar carpeta con contenido.

### Tests

- [X] T029 [P] [US3] `tests/Feature/ArchivoCrudTest.php`: renombrar cambia solo `nombre` (descarga idéntica, SC-005); mover cambia `carpeta_id`; borrar hace soft delete + elimina fichero físico; B no puede renombrar/mover/borrar archivo de A → 404
- [X] T030 [P] [US3] Ampliar `CarpetaCrudTest.php`: renombrar respeta unicidad por nivel; borrar carpeta con subcarpetas+archivos borra en cascada (registros soft-deleted + ficheros físicos eliminados) en transacción; B no puede borrar carpeta de A → 404

### Implementación

- [X] T031 [US3] `app/Http/Requests/UpdateArchivoRequest.php`: `nombre` sometimes required max:255; `carpeta_id` sometimes nullable acotado al tenant (renombrar y/o mover)
- [X] T032 [US3] `app/Http/Requests/UpdateCarpetaRequest.php`: `nombre` required max:255 único en `(tenant_id, parent_id)` excepto sí misma
- [X] T033 [US3] `ArchivoController@update` y `@destroy`: resolución manual acotada al tenant; update toca solo `nombre`/`carpeta_id` (fichero intacto); destroy = soft delete + `AlmacenArchivos::borrar(ruta)`
- [X] T034 [US3] `CarpetaController@update` (renombrar) y `@destroy` (cascada en transacción, recorriendo el subárbol: borra subcarpetas, archivos y sus ficheros físicos); devuelve conteo de elementos borrados
- [X] T035 [US3] UI acciones en `archivos/index.blade.php` + partial `_lista.blade.php`: renombrar (inline/modal), mover archivo (selección de carpeta destino), borrar con confirmación; **confirmación reforzada** para carpeta con contenido mostrando nº de elementos (FR-018)
- [X] T036 [US3] Ampliar `archivos-explorer.js`: renombrar/mover/borrar vía AJAX con feedback toastr inmediato; diálogo de confirmación reforzada para borrado de carpeta (usa el conteo devuelto por el backend)

**Checkpoint**: mantenimiento completo del espacio; borrado en cascada seguro.

---

## Phase 6: User Story 4 — Previsualizar sin descargar (Priority: P3)

**Goal**: preview inline de PDF e imágenes; el resto solo descarga.

**Independent Test**: previsualizar un PDF y una imagen embebidos; un `.docx` ofrece descarga.

### Tests

- [X] T037 [P] [US4] Ampliar `ArchivoAislamientoTenantTest.php`/`ArchivoCrudTest.php`: `archivos.preview` sirve PDF/imagen inline (`Content-Disposition: inline`, mime correcto); tipo sin preview → 404/redirect a descarga; B pide preview de A → 404

### Implementación

- [X] T038 [US4] `ArchivoController@preview`: resolución manual acotada al tenant; solo tipos con preview (pdf/imágenes) → binario inline con su `mime`; resto → 404/redirect a `archivos.descargar`
- [X] T039 [US4] Partial `resources/views/archivos/partials/_preview-modal.blade.php`: modal con `<iframe>` para PDF y `<img>` para imágenes, apuntando a `archivos.preview`
- [X] T040 [US4] Ampliar `archivos-explorer.js`: abrir modal de preview para tipos soportados; para el resto, acción descarga

**Checkpoint**: las 4 historias completas.

---

## Phase 7: Polish & Cross-Cutting

- [X] T041 [P] Diseño de la vista con las skills antes del pulido final: `frontend-design` + `ui-ux-pro-max` + `emil-design-eng` sobre la demo file-manager de NexaDash (`template/`), respetando `docs/04-front-guidelines.md` (FR-017b/D7)
- [X] T042 [P] Vista rejilla ↔ lista (`_grid`/`_lista`) con toggle persistido y estados vacío/carga cuidados (FR-017/SC-006)
- [X] T043 [P] Iconos por extensión (pdf/imagen/doc/hoja/presentación/texto) y miniatura para imágenes vía endpoint de preview (D8)
- [X] T044 [P] Input de configuración: `resources/views/configuracion/_tab_archivos.blade.php` con el campo límite de tamaño (paso 3 del alta de config); wiring en `ConfiguracionController::update`
- [X] T045 Entrada en el menú lateral (`resources/views/partials/sidebar.blade.php`) hacia `archivos.index`
- [X] T046 [P] Validar los 6 escenarios de [quickstart.md](./quickstart.md) end-to-end (incluye el de aislamiento) y ejecutar `php artisan test --filter=Archivo` + `--filter=Carpeta` en verde
- [X] T047 [P] Actualizar `docs/00-vision.md` / `docs/03-modelo-datos.md` si el alcance final difiere de lo documentado (nuevas tablas `carpetas`/`archivos`); si no cambió nada, no tocar (regla de cierre de feature en `CLAUDE.md`)

---

## Dependencies & Execution Order

- **Setup (P1)** → **Foundational (P2)** bloquean todo lo demás.
- **US1 (P3ph)** es el MVP; depende solo de Foundational.
- **US2** depende de Foundational (usa `ArchivoController@index`/`store` de US1 como base, pero es una feature slice independiente y testeable).
- **US3** depende de que existan carpetas/archivos (Foundational + conceptos de US1/US2).
- **US4** depende de Foundational + tener archivos (US1).
- **Polish** al final.

### Paralelización

- Foundational: T005–T010 en paralelo (migraciones/modelos/factories, ficheros distintos); T011 y T012 tras los modelos.
- Dentro de cada historia, los tests `[P]` se escriben en paralelo antes de la implementación.
- Polish: T041–T044, T046, T047 en gran medida paralelizables.

## Independent Test Criteria (resumen)

- **US1**: subir PDF a raíz + descargar idéntico; tipo/tamaño inválido rechazado sin huérfanos.
- **US2**: crear carpeta anidada, subir dentro, navegar por migas; duplicado por nivel rechazado.
- **US3**: renombrar (contenido intacto), mover archivo, borrar carpeta en cascada con confirmación.
- **US4**: preview inline de PDF/imagen; `.docx` → descarga.

## MVP Scope

**Solo User Story 1** (Phases 1–3): subir y descargar documentos aislados por tenant. Entregable y
demostrable por sí mismo.

## Post-MVP: mover carpetas (agregado tras el cierre inicial)

La Assumption original ("no se mueven carpetas, solo archivos") se revirtió a pedido explícito
después de cerrar la feature. Alcance agregado, con test-first para aislamiento/ciclos:

- [X] `Carpeta::descendientesIds()` (recorrido recursivo de subcarpetas) en `app/Models/Carpeta.php`.
- [X] `UpdateCarpetaRequest`: soporta `parent_id` (sometimes/nullable), valida que el destino no sea
  la propia carpeta ni un descendiente, y revalida unicidad de `nombre` por nivel destino con los
  valores efectivos (vía `withValidator`, porque un move-only no manda `nombre`).
- [X] Tests: `CarpetaCrudTest` (mover a otra carpeta, mover a raíz, rechazo por ciclo consigo misma,
  rechazo por ciclo con subcarpeta propia, unicidad en destino) + `CarpetaAislamientoTenantTest`
  (no se puede mover con `parent_id` de otro tenant, no se puede mover una carpeta de otro tenant).
- [X] Fix relacionado: `ArchivoController::index` no exponía `parent_id`/`update_url`/`delete_url`
  en el payload de carpetas del nivel actual (bug pre-existente que rompía renombrar/borrar/mover
  de carpetas desde la vista); unificado con `carpetaPayload()`.
- [X] JS (`archivos-explorer.js`): carpetas ahora son `draggable="true"` (antes solo archivos);
  drag&drop generalizado a `moverItem()` que resuelve `carpeta_id` vs `parent_id` según el tipo.

## Post-MVP: buscador global (agregado tras el cierre inicial)

- [X] `ArchivoController::buscar()`: búsqueda por nombre (`LIKE`) en archivos y carpetas de **todo
  el tenant** (no acotada al nivel actual), límite 50 por tipo, sin paginación (YAGNI). Cada
  resultado incluye `ruta` (breadcrumb en texto vía `rutaDe()`) para ubicar el ítem.
- [X] Tests (`ArchivosBusquedaTest`): encuentra por nombre en cualquier nivel, aislamiento
  multi-tenant, sin resultados → listas vacías.
- [X] UI: input de búsqueda con debounce (300ms) en `archivos/index.blade.php`; mientras hay
  término activo se ocultan las migas de pan y se muestra el conteo de resultados; al navegar a
  una carpeta (click en un resultado o en migas) se limpia la búsqueda.
