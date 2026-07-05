# Implementation Plan: Envío de facturas por email (SMTP por tenant)

**Branch**: `017-envio-facturas-email` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/017-envio-facturas-email/spec.md`

## Summary

Añadir la capacidad de enviar una factura **emitida** por email al cliente, con el PDF adjunto,
usando la **cuenta SMTP propia de cada tenant** (sin SMTP global ni fallback). Se apoya en la
infraestructura existente: almacén clave-valor `configuraciones` (grupo `email`), generación de PDF
(`facturas.pdf` vía `barryvdh/laravel-dompdf`), log `factura_eventos` para trazabilidad, y el patrón
de tabs de configuración. El envío es **síncrono** dentro del request (deuda técnica anotada; se
migrará a cola cuando el volumen lo exija). Un servicio `TenantMailer` arma un mailer on-the-fly con
las credenciales del tenant activo; la contraseña se guarda **cifrada** (`Crypt::encryptString`).

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: `stancl/tenancy` (scoping por tenant), `barryvdh/laravel-dompdf` (PDF, ya
en uso en `FacturaController::pdf`), `symfony/mailer` (transporte SMTP, incluido en Laravel), toastr
(notificaciones front, ya vendorizado).

**Storage**: MySQL/MariaDB. Tablas existentes reutilizadas: `configuraciones` (grupo `email`),
`factura_eventos`. **Sin migraciones nuevas.**

**Testing**: PHPUnit (Feature tests). `Mail::fake()` para aserciones de envío sin SMTP real.
Cobertura obligatoria de aislamiento multi-tenant (≥2 tenants).

**Target Platform**: Hosting compartido (cPanel/Hostinger) → SMTP saliente estándar; sin workers de
cola garantizados (justifica el envío síncrono).

**Project Type**: Web application (Laravel monolito, Blade + controladores).

**Performance Goals**: N/A crítico. El envío síncrono debe completar dentro del timeout del request
(~30 s); un único email con un adjunto PDF está muy por debajo.

**Constraints**: La contraseña SMTP nunca viaja al front en claro. El mailer se arma solo con datos
del tenant activo. No tocar el mailer `default` ni el `.env` en runtime.

**Scale/Scope**: 50–80 tenants; volumen de envíos bajo (facturas puntuales). 1 tab de configuración
nueva, 1 servicio, 1 excepción de dominio, 1 Mailable, 1 endpoint de envío, 1 endpoint de prueba.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ `configuraciones` y `factura_eventos` ya llevan
  `tenant_id` + `BelongsToTenant`. La factura a enviar se resuelve **en el cuerpo del controller**
  (`Factura::findOrFail`, respeta `TenantScope`), no por implicit binding (pitfall conocido). El
  `TenantMailer` lee solo `tenant()` activo. Tests de fuga entre tenants incluidos (una factura y una
  config de correo de un tenant no son usables desde otro).
- **II. Cumplimiento Normativo España-First**: ✅ Enviar una factura **no altera** su estado fiscal ni
  su contenido; la factura emitida sigue inmutable. El envío es un evento append-only en
  `factura_eventos` (`tipo_evento = 'envio_email'`), coherente con el log inalterable existente. No
  toca numeración, impuestos ni Verifactu.
- **III. Integridad financiera / cálculos en backend**: ✅ No hay cálculo de importes nuevo; se
  reutiliza el PDF ya calculado.
- **IV. Test-First**: ✅ Feature tests antes de implementar (config, envío, aislamiento, errores
  controlados).
- **V. Simplicidad**: ✅ Cero tablas nuevas, cero dependencias nuevas; se reutiliza todo el andamiaje
  existente. Envío síncrono (lo más simple que funciona) con la deuda de cola documentada.

**Resultado**: PASA sin violaciones. Sección Complexity Tracking no aplica.

## Project Structure

### Documentation (this feature)

```text
specs/017-envio-facturas-email/
├── plan.md              # Este archivo
├── research.md          # Fase 0 — decisiones técnicas
├── data-model.md        # Fase 1 — claves de config + evento
├── quickstart.md        # Fase 1 — guía de validación end-to-end
├── contracts/
│   ├── configuracion-email.md   # contrato de la tab Email + endpoints de config/prueba
│   └── envio-factura.md         # contrato del endpoint de envío de factura
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Support/
│   └── EmailTenant.php              # NUEVO — catálogo de claves email.* + defaults + resolución/cifrado
├── Services/
│   └── TenantMailer.php             # NUEVO — arma Mail::mailer('tenant_smtp') con la config del tenant
├── Exceptions/
│   └── EmailNoConfiguradoException.php   # NUEVO — config incompleta → flash 'error' controlado
├── Mail/
│   └── FacturaMail.php              # NUEVO — Mailable con PDF adjunto (reusa facturas.pdf)
├── Http/
│   ├── Controllers/
│   │   ├── ConfiguracionController.php   # EDIT — updateEmail() + enviarPrueba()
│   │   └── FacturaController.php         # EDIT — enviar()
│   └── Requests/
│       └── UpdateEmailRequest.php   # NUEVO — validación de la config SMTP
│
resources/views/
├── configuracion/
│   ├── index.blade.php              # EDIT — registrar la nueva tab
│   └── _tab_email.blade.php         # NUEVO — formulario SMTP + botón "enviar prueba"
├── facturas/
│   └── index.blade.php              # EDIT — badge "enviada" + acción "Enviar por email"
└── emails/
    └── factura.blade.php            # NUEVO — cuerpo del email de factura

database/seeders/
└── ConfiguracionSeeder.php          # EDIT — sembrar claves email.* del tenant demo

routes/web.php                       # EDIT — POST facturas/{factura}/enviar,
                                     #        PUT configuracion/email, POST configuracion/email/prueba

tests/Feature/
├── ConfiguracionEmailTest.php       # NUEVO
└── FacturaEnvioEmailTest.php        # NUEVO

docs/03-modelo-datos.md              # EDIT (al cerrar) — claves email.* en el catálogo
```

**Structure Decision**: Web application Laravel monolito existente. Se respeta la separación ya
establecida `Support/` (catálogos de config + resolución), `Services/` (lógica de dominio),
`Exceptions/`, `Mail/`, y el patrón de tabs de `resources/views/configuracion/`. No se introduce
ninguna estructura nueva.

## Complexity Tracking

No aplica — Constitution Check pasa sin violaciones.
