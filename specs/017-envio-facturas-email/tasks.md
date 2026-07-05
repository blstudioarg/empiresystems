# Tasks: Envío de facturas por email (SMTP por tenant)

**Feature**: `017-envio-facturas-email` | **Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

**Tests**: incluidos (el proyecto es test-first por constitución, Principio IV; la cobertura de
aislamiento multi-tenant es obligatoria).

**Organización**: por user story. US1 (config + prueba) y US2 (envío de factura) son ambas P1; US1
es prerequisito funcional de US2 (no se puede enviar sin correo configurado).

---

## Phase 1: Setup

- [ ] T001 Registrar las rutas nuevas en `routes/web.php` (dentro del grupo tenant/auth existente): `PUT|PATCH /configuracion/email` → `configuracion.email.update`, `POST /configuracion/email/prueba` → `configuracion.email.prueba`, `POST /facturas/{factura}/enviar` → `facturas.enviar`.

---

## Phase 2: Foundational (prerequisitos bloqueantes)

**Bloquea US1 y US2.** El catálogo de claves y el mailer los usan ambas historias.

- [ ] T002 [P] Crear `app/Support/EmailTenant.php`: constantes `CLAVE_*` (`email.smtp_host`, `smtp_port`, `smtp_encryption`, `smtp_usuario`, `smtp_password`, `remitente`, `remitente_nombre`, `responder_a`) + `DEFAULT_*`; método `valores($tenantId): array` (resuelve las claves del tenant, descifra `smtp_password` con `Crypt::decryptString`), `estaConfigurado($tenantId): bool` (host, puerto, encryption, usuario, password, remitente no vacíos). Seguir el patrón de `App\Support\AparienciaTenant`.
- [ ] T003 [P] Crear `app/Exceptions/EmailNoConfiguradoException.php` (excepción de dominio simple, mensaje por defecto "El correo del tenant no está configurado").
- [ ] T004 Crear `app/Services/TenantMailer.php`: lee `EmailTenant::valores(tenant())`, si `! estaConfigurado` lanza `EmailNoConfiguradoException`; hace `Config::set('mail.mailers.tenant_smtp', [transport=>'smtp', host, port, encryption, username, password])` y devuelve `Mail::mailer('tenant_smtp')`. Exponer también los datos de remitente/reply-to resueltos. NO tocar el mailer `default` ni el `.env`. (depende de T002, T003)
- [ ] T005 [P] Añadir a `app/Models/Factura.php` el método `fueEnviada(): bool` (existe ≥1 `eventos` con `tipo_evento = 'envio_email'` y `detalle['resultado'] === 'ok'`), reutilizando la relación `eventos()` existente.

**Checkpoint**: catálogo de config, mailer por tenant, excepción y accessor de estado listos.

---

## Phase 3: User Story 1 — Configurar el correo del tenant + email de prueba (P1)

**Goal**: el tenant configura su SMTP de forma segura y verifica las credenciales con un email de prueba.

**Independent Test**: guardar la config, recargar (password enmascarada), re-guardar sin password conserva la anterior, y el botón de prueba manda un correo con credenciales válidas / falla controlado con inválidas.

### Tests (US1)

- [ ] T006 [P] [US1] Crear `tests/Feature/ConfiguracionEmailTest.php` con casos: guardar config válida persiste las 8 claves con `grupo='email'`; `email.smtp_password` queda **cifrada** (no en claro) en BD; guardar con password vacía **conserva** la anterior; validación rechaza remitente no-email y puerto fuera de rango; email de prueba con `Mail::fake()` asertando el envío; config incompleta → mensaje de error controlado (no excepción); aislamiento: la config de un tenant no es visible/usable desde otro.

### Implementation (US1)

- [ ] T007 [P] [US1] Crear `app/Http/Requests/UpdateEmailRequest.php` con las reglas de `data-model.md` (host required/max255, port integer 1..65535, encryption in ssl,tls, usuario required, password nullable, remitente email required, remitente_nombre nullable, responder_a nullable email). `authorize()` acorde al resto de requests del proyecto.
- [ ] T008 [US1] Añadir `updateEmail(UpdateEmailRequest $request)` a `app/Http/Controllers/ConfiguracionController.php`: por cada clave `email.*` `Configuracion::updateOrCreate(['tenant_id','clave'],['valor','tipo','grupo'=>'email'])`; `smtp_password` solo se escribe (cifrada con `Crypt::encryptString`) si viene rellena, si viene vacía no se toca; flash/JSON de éxito. Seguir el patrón de `updateFacturacion`. (depende de T007)
- [ ] T009 [US1] Añadir `enviarPrueba()` a `ConfiguracionController`: construye `TenantMailer`, envía un correo simple al `remitente`/email del usuario; captura `EmailNoConfiguradoException` y `TransportExceptionInterface`/genéricas → flash/JSON `error` con mensaje claro; éxito → flash/JSON `success`. (depende de T004)
- [ ] T010 [US1] Crear `resources/views/configuracion/_tab_email.blade.php`: formulario con host, puerto, cifrado (select ssl/tls), usuario, password (vacío + placeholder `••••••••` si ya hay una), remitente, remitente_nombre, responder_a; botón "Enviar email de prueba" (POST al endpoint de prueba). Notificaciones vía toastr/flash, sin alerts ad-hoc. Leer `docs/04-front-guidelines.md` antes.
- [ ] T011 [US1] Registrar la tab "Email" en `resources/views/configuracion/index.blade.php` (nav + include del partial), siguiendo el patrón de las tabs existentes. Pasar a la vista, desde `ConfiguracionController::show`, los valores `email.*` resueltos (sin la password en claro: solo un booleano "hay password guardada").
- [ ] T012 [P] [US1] Añadir las 8 claves `email.*` a `database/seeders/ConfiguracionSeeder.php` (con `firstOrCreate`, `grupo='email'`, tipos correctos, defaults de `EmailTenant`; `smtp_password` sembrada vacía).

**Checkpoint**: US1 entregable y testeable de forma independiente.

---

## Phase 4: User Story 2 — Enviar una factura emitida por email (P1)

**Goal**: enviar una factura emitida al cliente con el PDF adjunto desde la cuenta del tenant, con traza y marca de "enviada".

**Independent Test**: con un tenant que ya tiene SMTP configurado, enviar una factura emitida a una dirección de prueba, verificar adjunto PDF, evento registrado y badge "Enviada" en el listado.

### Tests (US2)

- [ ] T013 [P] [US2] Crear `tests/Feature/FacturaEnvioEmailTest.php` con `Mail::fake()`: envío de factura emitida crea `FacturaMail` con adjunto y registra `factura_eventos` (`tipo_evento='envio_email'`, `resultado='ok'`); tenant sin email configurado → error controlado, sin envío; factura en borrador → bloqueada; destinatario vacío/ inválido → error de validación; simplificada sin `cliente_email` exige destinatario manual; fallo de transporte → registra `resultado='error'` y NO marca enviada; aislamiento: una factura de otro tenant → 404 (`findOrFail` respeta `TenantScope`); reenvío añade un segundo evento.

### Implementation (US2)

- [ ] T014 [P] [US2] Crear `resources/views/emails/factura.blade.php`: cuerpo del email de factura (saludo, nº de factura, importe, mención del adjunto), coherente con la marca del tenant.
- [ ] T015 [P] [US2] Crear `app/Mail/FacturaMail.php` (Mailable): recibe `Factura` + bytes del PDF; `from(remitente, remitente_nombre)`, `replyTo(responder_a)` si existe; vista `emails.factura`; `attachData($pdf, "<numero_completo>.pdf", ['mime'=>'application/pdf'])`.
- [ ] T016 [US2] Añadir `enviar(Request $request, string $factura)` a `app/Http/Controllers/FacturaController.php`: resolver `Factura::with([...])->findOrFail($factura)` **en el cuerpo** (NO implicit binding); validar `estado ≠ borrador/anulada` y `destinatario` (email required); generar PDF con `Pdf::loadView('facturas.pdf',...)->output()`; construir `TenantMailer` (captura `EmailNoConfiguradoException` → error controlado); enviar `FacturaMail`; registrar `FacturaEvento` (`envio_email`, `{destinatario,resultado}`); en fallo de transporte registrar `resultado='error'` y devolver error controlado. Flash/JSON según `contracts/envio-factura.md`. (depende de T004, T005, T015)
- [ ] T017 [US2] Editar `resources/views/facturas/index.blade.php`: acción "Enviar por email" en el menú de acciones (solo si `estado ≠ borrador`; avisar/deshabilitar si el tenant no tiene email configurado), prompt/modal para confirmar/editar destinatario precargado con `cliente_email`, y **badge "Enviada"** cuando `factura->fueEnviada()`. Notificaciones vía toastr. Leer `docs/04-front-guidelines.md` antes. (depende de T016, T005)

**Checkpoint**: US2 entregable; feature completa end-to-end.

---

## Phase 5: Polish & Cross-Cutting

- [ ] T018 [P] Actualizar `docs/03-modelo-datos.md`: añadir las 8 claves `email.*` al catálogo de `configuraciones` (tabla de "Ejemplos de claves") y una nota sobre el evento `factura_eventos.tipo_evento = 'envio_email'`.
- [ ] T019 [P] Añadir nota de deuda técnica (envío síncrono → migrar a cola cuando escale) donde el proyecto registre estas notas (p. ej. comentario en `TenantMailer`/`FacturaController::enviar` y/o `docs/01-arquitectura.md`).
- [ ] T020 Ejecutar `php artisan test --filter=ConfiguracionEmailTest` y `--filter=FacturaEnvioEmailTest`; correr Pint/formato si aplica; validar los escenarios de `quickstart.md`.

---

## Dependencies & Execution Order

- **Setup (T001)** → primero.
- **Foundational (T002–T005)** → bloquea US1 y US2. T004 depende de T002+T003.
- **US1 (T006–T012)**: T008 dep. T007; T009 dep. T004. Resto en paralelo donde `[P]`.
- **US2 (T013–T017)**: T016 dep. T004/T005/T015; T017 dep. T016/T005.
- **Polish (T018–T020)** → al final.
- US1 antes que US2 en la práctica (US2 requiere correo configurado para prueba manual real; con `Mail::fake()` los tests de US2 son independientes).

## Parallel Opportunities

- Foundational: T002, T003, T005 en paralelo (T004 después).
- US1: T006, T007, T012 en paralelo; T010/T011 tras el controller.
- US2: T013, T014, T015 en paralelo; luego T016 → T017.
- Polish: T018, T019 en paralelo.

## MVP

**US1 + US2** juntas forman el MVP real (enviar una factura no tiene sentido sin configurar el correo). Si hubiera que partir: US1 sola ya entrega valor (deja al tenant listo y con credenciales verificadas).
