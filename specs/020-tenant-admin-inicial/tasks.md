---

description: "Task list for 020-tenant-admin-inicial"
---

# Tasks: Alta de usuario administrador al crear un tenant

**Input**: Design documents from `/specs/020-tenant-admin-inicial/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, quickstart.md

**Tests**: Incluidos — el aislamiento multi-tenant del usuario administrador es lógica crítica
bajo el Principio IV (Test-First) de la constitución; se escriben antes que la implementación.

**Organization**: Tareas agrupadas por user story (spec.md). No hay fase de Setup ni
Foundational: no se crean tablas, dependencias ni infraestructura nueva — todo se apoya en
`Tenant`, `User`, `TenantController` y `StoreTenantRequest` ya existentes.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: US1 o US2, según spec.md
- Incluye rutas de archivo exactas

---

## Phase 1: User Story 1 - Crear un tenant con su administrador inicial (Priority: P1) 🎯 MVP

**Goal**: Al dar de alta un tenant desde el panel de super admin, se crea en la misma operación
un usuario administrador que puede iniciar sesión de inmediato en el dominio del tenant.

**Independent Test**: Crear un tenant desde el panel de super admin con email+contraseña de
administrador y verificar que ese usuario se autentica en el dominio del tenant y accede al CRM
con permisos de administrador.

### Tests for User Story 1 ⚠️

> Escribir estos tests primero y confirmar que fallan antes de tocar el controller/request.

- [X] T001 [P] [US1] Test: alta de tenant crea también un `User` con `tenant_id` del tenant
  nuevo, `rol=admin`, `estado=aprobado`, `activo=true`, en
  `tests/Feature/SuperAdmin/TenantCrudTest.php` (método
  `test_alta_crea_usuario_administrador_activo_y_aprobado`)
- [X] T002 [P] [US1] Test: el administrador creado puede iniciar sesión inmediatamente en el
  dominio del tenant recién creado (sin redirect a login por estado pendiente) en
  `tests/Feature/SuperAdmin/TenantCrudTest.php` (método
  `test_administrador_creado_puede_iniciar_sesion_en_el_dominio_del_tenant`)
- [X] T003 [P] [US1] Test de aislamiento (Principio I): crear tenant A y tenant B, cada uno con
  su propio administrador, y verificar que el administrador de A no aparece en
  `User::where('tenant_id', B->id)` ni puede iniciar sesión en el dominio de B, en
  `tests/Feature/SuperAdmin/TenantCrudTest.php` (método
  `test_administrador_de_un_tenant_no_es_visible_ni_autenticable_en_otro_tenant`)
- [X] T004 [US1] Test: si falla la creación del usuario administrador (simulado forzando una
  excepción, p. ej. mockeando `User::creating` o pasando un rol inválido a través de un evento
  de test), no se persiste ni el tenant ni el dominio (FR-005), en
  `tests/Feature/SuperAdmin/TenantCrudTest.php` (método
  `test_fallo_al_crear_administrador_revierte_tenant_y_dominio`)

### Implementation for User Story 1

- [X] T005 [US1] Añadir `admin_email` (`required`, `email`, `max:255`) y `admin_password`
  (`required`, `string`, `min:8`) a las reglas de validación de
  `app/Http/Requests/SuperAdmin/StoreTenantRequest.php`, con mensajes personalizados
  (`admin_email.required`, `admin_password.min`, etc.) siguiendo el estilo ya usado en ese
  archivo
- [X] T006 [US1] Modificar `TenantController::store()` en
  `app/Http/Controllers/SuperAdmin/TenantController.php` para crear, dentro del mismo
  `DB::transaction()` que ya crea `Tenant` y `Domain`, un `User::create([...])` con
  `tenant_id` = id del tenant recién creado, `name` = `'Administrador'`, `email` =
  `admin_email`, `password` = `Hash::make(admin_password)`, `rol` = `UserRole::Admin`,
  `estado` = `EstadoUsuario::Aprobado`, `activo` = `true` (importar `Hash`, `UserRole`,
  `EstadoUsuario`, `User`)
- [X] T007 [US1] Añadir los campos **Email del administrador** y **Contraseña del
  administrador** al formulario en
  `resources/views/super_admin/tenants/_form.blade.php`, envueltos en un contenedor
  identificable (p. ej. `id="admin-fields"`) con sus `invalid-feedback` correspondientes
  (`data-error-for="admin_email"` / `data-error-for="admin_password"`)
- [X] T008 [US1] Actualizar `public/js/plugins-init/super-admin-tenants-modal.init.js`: en
  `resetForm()` (modo alta) mostrar `#admin-fields` y marcar sus inputs `required`; en
  `fillForm()` (modo edición) ocultar `#admin-fields`, quitarles `required` y limpiar sus
  valores, ya que editar un tenant no debe pedir ni tocar credenciales de administrador
  (research.md D5)

**Checkpoint**: User Story 1 completa y verificable de forma independiente (alta de tenant +
administrador funcional de punta a punta).

---

## Phase 2: User Story 2 - Validación de las credenciales del administrador (Priority: P2)

**Goal**: El formulario de alta rechaza email/contraseña de administrador vacíos, mal
formados o demasiado cortos, sin crear nada.

**Independent Test**: Enviar el formulario con `admin_email`/`admin_password` vacíos, con un
email malformado, o con una contraseña de menos de 8 caracteres, y verificar que se muestran
errores de validación y no se crea el tenant.

### Tests for User Story 2 ⚠️

- [X] T009 [P] [US2] Test: enviar el formulario sin `admin_email` ni `admin_password` →
  `assertSessionHasErrors(['admin_email', 'admin_password'])` y no se crea el tenant, en
  `tests/Feature/SuperAdmin/TenantCrudTest.php` (método
  `test_alta_sin_credenciales_de_administrador_falla_y_no_crea_tenant`)
- [X] T010 [P] [US2] Test: enviar `admin_email` con formato inválido (p. ej. `"no-es-email"`)
  → `assertSessionHasErrors('admin_email')` y no se crea el tenant, en
  `tests/Feature/SuperAdmin/TenantCrudTest.php` (método
  `test_alta_con_email_de_administrador_invalido_falla`)
- [X] T011 [P] [US2] Test: enviar `admin_password` de menos de 8 caracteres →
  `assertSessionHasErrors('admin_password')` y no se crea el tenant, en
  `tests/Feature/SuperAdmin/TenantCrudTest.php` (método
  `test_alta_con_contrasena_de_administrador_demasiado_corta_falla`)

### Implementation for User Story 2

- [X] T012 [US2] Verificar que las reglas añadidas en T005 cubren los tres casos anteriores;
  si algún mensaje de error no es claro, ajustar `messages()` en
  `app/Http/Requests/SuperAdmin/StoreTenantRequest.php` (no debería requerir cambios de
  lógica nueva, solo confirmación/ajuste fino de mensajes)

**Checkpoint**: Ambas user stories funcionan de forma independiente y en conjunto.

---

## Phase 3: Polish

- [X] T013 [P] Ejecutar `quickstart.md` completo (validación manual por UI) para confirmar el
  flujo de punta a punta antes de dar la feature por cerrada. Validado en navegador (Chrome
  DevTools MCP, con confirmación del usuario): alta de tenant "QA Tenant Admin SL" con admin
  desde el panel de super admin, y login inmediato de ese administrador en
  `qa-tenant-admin.localhost` sin paso de aprobación, con acceso completo (menú "Usuarios"
  visible, rol "admin"). Datos de prueba dejados en la base de desarrollo a pedido del usuario.
- [X] T014 Revisar si `docs/03-modelo-datos.md` necesita mención del flujo de alta de admin
  inicial (probablemente no, al no cambiar el esquema — confirmar y, si no aplica, no tocar
  nada, según la política de "Al cerrar un spec/feature" de `CLAUDE.md`). Se añadió un párrafo
  en la sección `users` documentando la segunda vía de alta sin flujo de aprobación.

---

## Dependencies & Execution Order

### Phase Dependencies

- **User Story 1 (Phase 1)**: sin dependencias previas — puede empezar de inmediato. Es el MVP.
- **User Story 2 (Phase 2)**: depende de que T005/T006 (reglas de validación y creación del
  usuario) de US1 ya existan, porque reutiliza las mismas reglas de `StoreTenantRequest`. En la
  práctica, US2 son tests adicionales sobre la validación que US1 ya implementa.
- **Polish (Phase 3)**: depende de que US1 y US2 estén completas.

### Within User Story 1

- T001-T004 (tests) deben escribirse y fallar antes de T005-T008 (implementación).
- T005 (reglas de validación) bloquea T006 (el controller usa `$request->validated()`).
- T007 (vista) y T008 (JS) dependen de que los nombres de campo de T005 estén definidos
  (`admin_email` / `admin_password`), pero no dependen entre sí de forma estricta.

### Within User Story 2

- T009-T011 (tests) deben escribirse contra las reglas de T005; si T005 ya está implementada
  desde US1, estos tests deberían pasar sin cambios adicionales (T012 es solo un ajuste fino).

### Parallel Opportunities

- T001, T002, T003 se pueden escribir en paralelo (mismo archivo de test pero métodos
  independientes; en la práctica conviene añadirlos en una sola pasada al archivo para evitar
  conflictos de edición concurrente).
- T009, T010, T011 igual, en paralelo entre sí.
- T007 (vista) y T008 (JS) pueden avanzar en paralelo una vez que T005 fija los nombres de
  campo.

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. T001-T004: escribir tests de US1 y confirmar que fallan.
2. T005-T008: implementar hasta que los tests de US1 pasen.
3. **Validar**: correr `php artisan test --filter=TenantCrudTest` y el flujo manual de
   quickstart.md.
4. Con esto la feature ya es utilizable end-to-end (US1 es el objetivo central de la spec).

### Incremental Delivery

1. US1 completa → tenants nuevos ya nacen con administrador funcional (MVP).
2. US2 añade la red de seguridad de validación (probablemente ya cubierta por T005, solo falta
   confirmarlo con tests explícitos).
3. Polish: validación manual + revisión de docs.
