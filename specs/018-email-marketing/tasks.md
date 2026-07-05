# Tasks: Email Marketing (Campañas a Clientes)

**Feature**: 018-email-marketing | **Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

Convenciones del repo: modelos con `BelongsToTenant`, controladores por recurso, Form Requests,
vistas Blade sobre layout `default`, JS en `public/js/plugins-init/`, notificaciones vía toastr /
`window.showToast`. Antes de tocar front: leer `docs/04-front-guidelines.md` e invocar skills de
diseño (`frontend-design`, `ui-ux-pro-max`, `emil-design-eng`).

**Test-first obligatorio (Principio IV)**: aislamiento multi-tenant de las 3 tablas nuevas — los
tests se escriben y fallan antes de implementar.

---

## Phase 1: Setup

- [X] T001 Crear las 3 migraciones en `database/migrations/`: `plantillas_email` (tenant_id,
  titulo, asunto, cuerpo, activa bool, softDeletes, timestamps), `campanas` (tenant_id, user_id
  nullable, plantilla_email_id nullable nullOnDelete, asunto, cuerpo, estado enum
  borrador/en_curso/finalizada, total_destinatarios, enviados, fallidos, enviada_at nullable,
  timestamps), `campana_destinatarios` (tenant_id, campana_id cascadeOnDelete, cliente_id
  cascadeOnDelete, email nullable, estado enum pendiente/enviado/fallido, error nullable,
  enviado_at nullable, timestamps; único (campana_id, cliente_id); índice (campana_id, estado)).
  Ver [data-model.md](./data-model.md).
- [X] T002 [P] Añadir enums de estado si el repo usa Enums PHP (`app/Enums/EstadoCampana.php` y
  `app/Enums/EstadoDestinatario.php`) siguiendo el patrón de `app/Enums/` existente; si no, usar
  strings validados. Alinear con casts de los modelos.

## Phase 2: Foundational (bloquea todas las historias)

- [X] T003 [P] Crear modelo `app/Models/PlantillaEmail.php` (BelongsToTenant, SoftDeletes,
  HasFactory, fillable, casts `activa`=>bool, relación tenant).
- [X] T004 [P] Crear modelo `app/Models/Campana.php` (BelongsToTenant, HasFactory, fillable,
  casts estado/enviada_at, relaciones tenant/user/plantillaEmail/destinatarios).
- [X] T005 [P] Crear modelo `app/Models/CampanaDestinatario.php` (BelongsToTenant, HasFactory,
  fillable, casts estado/enviado_at, relaciones tenant/campana/cliente).
- [X] T006 [P] Crear factories `database/factories/PlantillaEmailFactory.php`,
  `CampanaFactory.php`, `CampanaDestinatarioFactory.php`.
- [X] T007 Registrar rutas en `routes/web.php` dentro del grupo `['tenant.context','auth']`:
  `Route::resource('plantillas-email', ...)->only(['index','store','update','destroy'])`;
  `GET /campanas`, `GET /campanas/crear`, `POST /campanas`, `GET /campanas/{campana}`,
  `POST /campanas/{campana}/enviar-tanda`, `POST /campanas/{campana}/reintentar`. Ver
  [contracts/](./contracts/).
- [X] T008 Crear `app/Mail/CampanaMail.php` (asunto + cuerpo HTML, vista
  `resources/views/emails/campana.blade.php`, sin adjunto) y su vista Blade. Ver research D5.
- [X] T009 Añadir entrada de navegación al módulo en `resources/views/partials/sidebar.blade.php`
  (Campañas / Plantillas de email).

**Checkpoint**: modelos, rutas, mailer y navegación listos; historias pueden implementarse.

---

## Phase 3: User Story 1 — Enviar campaña a varios clientes (P1) 🎯 MVP

**Objetivo**: crear campaña, seleccionar clientes, enviar por tandas con progreso y resultado por
destinatario.
**Test independiente**: crear campaña con ≥2 clientes (uno sin email), enviar y verificar recibo
+ resumen por destinatario.

### Tests (test-first para aislamiento — Principio IV)

- [X] T010 [P] [US1] Test de aislamiento en `tests/Feature/CampanaAislamientoTenantTest.php`:
  con 2 tenants, un usuario de A no ve/crea campañas ni destinatarios de B, y no puede añadir un
  cliente de B ni enviar tanda a destinatarios de B. Debe fallar antes de implementar.
- [X] T011 [P] [US1] Test de envío por tanda en `tests/Feature/CampanaEnvioTandaTest.php`
  (`Mail::fake()`): tanda marca `enviado`/`fallido` por fila, cliente sin email → `fallido` "sin
  email", un fallo no aborta el resto, contadores y estado de campaña se recalculan, sin SMTP →
  422. Cubre FR-005/006/007/015.

### Implementación

- [X] T012 [US1] `app/Http/Requests/StoreCampanaRequest.php`: valida `asunto`, `cuerpo`,
  `cliente_ids[]` (≥1, existen y del tenant), `plantilla_email_id` opcional del tenant.
- [X] T013 [US1] `app/Http/Requests/EnviarTandaRequest.php`: valida `destinatario_ids[]`
  (≤8, pertenecen a la campaña).
- [X] T014 [US1] `CampanaController@store` en `app/Http/Controllers/CampanaController.php`: crea
  campaña `borrador`, deduplica `cliente_ids` (FR-013), materializa `campana_destinatarios`
  (cliente sin email → `fallido` "sin email"), valida SMTP configurado (`EmailTenant`).
- [X] T015 [US1] `CampanaController@enviarTanda`: procesa solo filas `pendiente` de la campaña,
  envía cada una con `TenantMailer` + `CampanaMail` en try/catch individual, actualiza
  estado/error/enviado_at, recalcula contadores y estado de campaña, devuelve JSON por
  destinatario. Resolver la campaña por scope de tenant en el cuerpo (no implicit binding). Ver
  contrato y memoria `project_tenant_route_binding`.
- [X] T016 [US1] `CampanaController@index` y `@create` (create ofrece plantillas activas y lista
  de clientes del tenant).
- [X] T017 [P] [US1] Vista `resources/views/campanas/index.blade.php` (listado campañas: asunto,
  estado, enviados/fallidos/total, fecha, acceso a detalle). Leer front-guidelines + skills.
- [X] T018 [US1] Vista `resources/views/campanas/create.blade.php`: bloque de composición (banco
  `email-compose`: asunto + editor enriquecido), selección múltiple de clientes (checkboxes tabla
  clientes), barra de progreso. Leer front-guidelines + skills de diseño.
- [X] T019 [US1] JS `public/js/plugins-init/campanas-form.js`: trocea destinatarios en tandas de
  8, llama secuencialmente a `enviar-tanda` por AJAX, actualiza barra de progreso y resumen
  enviados/fallidos, usa `window.showToast`. Ver research D1/D2.

**Checkpoint US1**: MVP funcional — se puede enviar una campaña completa con progreso y resultado.

---

## Phase 4: User Story 2 — Plantillas reutilizables (P2)

**Objetivo**: CRUD de plantillas y precarga en campañas.
**Test independiente**: crear/editar/desactivar plantilla; usar una activa para precargar campaña.

### Tests

- [X] T020 [P] [US2] Test de aislamiento en
  `tests/Feature/PlantillaEmailAislamientoTenantTest.php`: usuario de A no ve/edita/borra
  plantillas de B. Test-first.

### Implementación

- [X] T021 [P] [US2] `app/Http/Requests/StorePlantillaEmailRequest.php` y
  `UpdatePlantillaEmailRequest.php` (titulo, asunto, cuerpo, activa).
- [X] T022 [US2] `app/Http/Controllers/PlantillaEmailController.php`: index (filtro título/estado),
  store, update, destroy (soft delete). Ver [contracts/plantillas-email.md](./contracts/plantillas-email.md).
- [X] T023 [P] [US2] Vista `resources/views/plantillas-email/index.blade.php` (banco
  `email-template`: listado + filtro + modal crear/editar). Leer front-guidelines + skills.
- [X] T024 [P] [US2] JS `public/js/plugins-init/plantillas-email-modal.init.js` (modal CRUD,
  toastr).
- [X] T025 [US2] En `campanas/create` + `campanas-form.js`: selector de plantilla activa que
  precarga asunto/cuerpo editables (FR-010).

**Checkpoint US2**: plantillas gestionables y usables desde campañas.

---

## Phase 5: User Story 3 — Historial y reintentar fallidos (P3)

**Objetivo**: detalle de campaña por destinatario y reintento solo de fallidos.
**Test independiente**: campaña con fallidos → ver desglose → reintentar → 0 duplicados.

### Tests

- [X] T026 [P] [US3] Test en `tests/Feature/CampanaReintentoTest.php`: reintentar repone solo
  filas `fallido` (con email) a `pendiente`, no toca `enviado` (0 duplicados, SC-004/FR-012).

### Implementación

- [X] T027 [US3] `CampanaController@show`: cabecera + tabla de destinatarios (estado/motivo),
  botón reintentar si hay fallidos.
- [X] T028 [US3] `CampanaController@reintentar`: repone `fallido`→`pendiente` (con email) y
  devuelve `destinatario_ids` para el JS.
- [X] T029 [P] [US3] Vista `resources/views/campanas/show.blade.php` (desglose por destinatario +
  reintentar fallidos). Leer front-guidelines + skills.
- [X] T030 [US3] En `campanas-form.js`: soporte reintento (llama a `reintentar`, luego reenvía
  por tandas los IDs devueltos).

**Checkpoint US3**: historial consultable y reintento sin duplicados.

---

## Phase 6: Polish & Cross-Cutting

- [X] T031 [P] Revisar orden CSS/JS de plugins nuevos en `layouts/app.blade.php` (@stack styles
  antes de style.css; deps UMD del editor antes del plugin) según CLAUDE.md.
- [X] T032 [P] Actualizar `docs/03-modelo-datos.md` con las 3 tablas nuevas y `docs/00-vision.md`
  si cambia el alcance (regla de cierre de feature del CLAUDE.md).
- [X] T033 Verificación end-to-end siguiendo [quickstart.md](./quickstart.md) (5 escenarios) y
  `php artisan test --filter=Campana` / `--filter=PlantillaEmail`.

---

## Dependencias

- **Setup (T001-T002)** → **Foundational (T003-T009)** → historias.
- **US1 (P1)** es el MVP y no depende de US2/US3.
- **US2 (P2)** depende de Foundational; T025 toca archivos de US1 (create + JS) → tras US1.
- **US3 (P3)** depende de Foundational; T030 toca `campanas-form.js` (US1) → tras US1.
- **Polish (T031-T033)** al final.

## Paralelizables (ejemplos)

- Foundational: T003, T004, T005, T006 en paralelo (modelos/factories distintos).
- US1: T010 y T011 (tests) en paralelo; T017 en paralelo con lógica de controlador.
- US2: T021, T023, T024 en paralelo.

## MVP sugerido

**Phase 1 + Phase 2 + Phase 3 (US1)** = envío de campañas funcional con progreso y resultado por
destinatario. US2 (plantillas) y US3 (reintento) son incrementos posteriores.

## Total

33 tareas — Setup: 2 · Foundational: 7 · US1: 10 · US2: 6 · US3: 5 · Polish: 3.
