# Implementation Plan: Email Marketing (Campañas a Clientes)

**Branch**: `018-email-marketing` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/018-email-marketing/spec.md`

## Summary

Módulo de campañas de email a clientes del tenant. El usuario crea una campaña (asunto + cuerpo
HTML, opcionalmente desde una plantilla reutilizable), selecciona destinatarios entre sus
clientes y la envía. El envío se realiza **sin colas ni cron** (Principio V): el frontend trocea
los destinatarios en tandas pequeñas y las manda secuencialmente a un endpoint por AJAX,
mostrando una barra de progreso; el backend reutiliza `TenantMailer`/`EmailTenant` (feature 017)
para enviar cada correo y devuelve resultado **por destinatario**. Cada intento queda registrado
en una tabla de destinatarios de campaña (fuente de verdad del resultado) y se permite reintentar
solo los fallidos. Todo aislado por tenant.

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), mailer
`tenant_smtp` on-the-fly (`App\Services\TenantMailer`, `App\Support\EmailTenant`), template
NexaDash (Blade + jQuery); editor de texto enriquecido del banco de piezas
(`textarea_editor` / summernote-like ya presente en el template).

**Storage**: MySQL/MariaDB. Nuevas tablas: `campanas`, `campana_destinatarios`,
`plantillas_email`. Reutiliza `clientes` y `configuraciones` (grupo `email`).

**Testing**: PHPUnit/Pest (feature tests). Áreas con test-first obligatorio (Principio IV):
aislamiento multi-tenant de las 3 tablas nuevas. El resto (endpoint de tanda, resultado por
destinatario, reintento) con feature tests estándar.

**Target Platform**: Hosting compartido tipo Hostinger/cPanel (sin worker de colas, sin root).

**Project Type**: Web application (Laravel monolito con Blade).

**Performance Goals**: Cada petición de tanda (5–10 destinatarios) completa muy por debajo del
timeout típico del request (~30 s). Barra de progreso continua sin bloquear el navegador.

**Constraints**: Sin `ShouldQueue`, sin `queue:work`, sin `schedule:run`. Envío SMTP síncrono
dentro de cada request de tanda. Tamaño de tanda fijado en **8** por defecto (ver research.md).

**Scale/Scope**: Campañas de hasta ~50 destinatarios en la fase actual; 3 tablas, 1 controlador
de campañas + 1 de plantillas, 3–4 vistas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: PASS — `campanas`, `campana_destinatarios` y
  `plantillas_email` llevan `tenant_id` indexado y usan `BelongsToTenant` (mismo patrón que
  `Cliente`/`Factura`). Tests de aislamiento con ≥2 tenants obligatorios (Principio IV).
  `campana_destinatarios` deriva su tenant de la campaña; se afirma el scope igualmente.
- **II. Cumplimiento Normativo España-First**: N/A — no toca facturación, numeración, impuestos
  ni Verifactu. Los eventos de envío no participan del encadenamiento (igual criterio que
  `envio_email` en `factura_eventos`).
- **III. Integridad Financiera Server-Side**: N/A — no hay importes. Sí aplica el criterio
  general de que el backend es la fuente de verdad del resultado del envío (no el cliente JS).
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: PASS — el aislamiento de las 3 tablas
  nuevas es área crítica: tests de fuga entre tenants escritos antes de la implementación.
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: PASS — es el eje del diseño:
  envío síncrono por tandas desde el front, sin colas ni cron. No se introducen dependencias
  que requieran VPS. Se reutiliza la infra SMTP existente sin cambiarla.

**Resultado**: PASS. Sin violaciones → Complexity Tracking vacío.

## Project Structure

### Documentation (this feature)

```text
specs/018-email-marketing/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/           # Fase 1
│   ├── campanas.md
│   └── plantillas-email.md
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Campana.php               # nuevo (BelongsToTenant)
│   ├── CampanaDestinatario.php   # nuevo (BelongsToTenant)
│   └── PlantillaEmail.php        # nuevo (BelongsToTenant, SoftDeletes)
├── Mail/
│   └── CampanaMail.php           # nuevo (asunto + cuerpo HTML, sin adjunto)
├── Http/
│   ├── Controllers/
│   │   ├── CampanaController.php          # index, create, store, show, enviarTanda, reintentar
│   │   └── PlantillaEmailController.php   # index, store, update, destroy (CRUD)
│   └── Requests/
│       ├── StoreCampanaRequest.php
│       ├── EnviarTandaRequest.php
│       ├── StorePlantillaEmailRequest.php
│       └── UpdatePlantillaEmailRequest.php
database/
├── migrations/
│   ├── 2026_07_04_..._create_plantillas_email_table.php
│   ├── 2026_07_04_..._create_campanas_table.php
│   └── 2026_07_04_..._create_campana_destinatarios_table.php
└── factories/
    ├── CampanaFactory.php
    ├── CampanaDestinatarioFactory.php
    └── PlantillaEmailFactory.php
resources/views/
├── campanas/
│   ├── index.blade.php    # listado de campañas + acceso a detalle
│   ├── create.blade.php   # composición (banco: email-compose) + selección clientes + progreso
│   └── show.blade.php     # detalle: resultado por destinatario + reintentar fallidos
└── plantillas-email/
    └── index.blade.php    # CRUD (banco: email-template) listado + modal crear/editar
public/js/plugins-init/
├── campanas-form.js       # trocear en tandas, AJAX secuencial, barra de progreso, reintento
└── plantillas-email-modal.init.js
routes/web.php             # rutas nuevas dentro del grupo ['tenant.context','auth']
tests/Feature/
├── CampanaAislamientoTenantTest.php      # test-first (Principio IV)
├── PlantillaEmailAislamientoTenantTest.php # test-first (Principio IV)
├── CampanaEnvioTandaTest.php
└── CampanaReintentoTest.php
```

**Structure Decision**: Web app Laravel monolito. Se sigue el patrón ya establecido en el repo
(controladores por recurso, Form Requests, modelos con `BelongsToTenant`, vistas Blade sobre el
layout `default`, JS de inicialización en `public/js/plugins-init/`). No se introduce ninguna
capa nueva (ni repositorios, ni servicios extra más allá de reutilizar `TenantMailer`).

## Complexity Tracking

> Sin violaciones de la constitución. No aplica.
