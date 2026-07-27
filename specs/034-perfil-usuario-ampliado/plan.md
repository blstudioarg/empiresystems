# Implementation Plan: Ampliación del perfil de usuario (Mi perfil)

**Branch**: `034-perfil-usuario-ampliado` | **Date**: 2026-07-26 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/034-perfil-usuario-ampliado/spec.md`

## Summary

Ampliar `/perfil` (hoy solo lectura básica: avatar, nombre, badge de estado, rol legado, email,
tenant, fecha de alta) para que muestre el rol real y permisos efectivos vía Spatie, permita
autoservicio de cambio de contraseña y edición de nombre/email (con verificación por enlace
firmado para el email), muestre un resumen de actividad reciente propia reutilizando
`RegistradorActividad`/`logs_actividad`, muestre datos de empleado y estado de fichaje cuando el
usuario esté vinculado a un `MiembroEquipo`, y muestre quién aprobó la cuenta y cuándo. El enfoque
técnico es reutilizar al máximo servicios y patrones ya existentes (catálogo de permisos, log de
actividad, fichajes, toastr/`withButtonLoading`) en vez de construir infraestructura nueva; solo
se agrega lo estrictamente necesario: 3 columnas transitorias en `users` para el cambio de correo
pendiente, y las acciones/controladores para las nuevas operaciones de autoservicio.

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: Laravel 12, `spatie/laravel-permission` (rol/permisos), `stancl/tenancy`
(single-database, trait `BelongsToTenant`), servicios ya existentes del propio proyecto:
`App\Services\RegistradorActividad`, `App\Support\CatalogoPermisos`, `App\Support\AgenteUsuario`,
`App\Support\GeolocalizadorIp`, `App\Support\Formato`, `FichajeController::estadoActual()` /
`RegistroFichajes`, mecanismo de correo transaccional ya usado en el proyecto (mailer de Laravel).

**Storage**: MySQL/MariaDB. Cambios de esquema: 3 columnas nuevas en `users`
(`pending_email`, `pending_email_token`, `pending_email_expires_at`); ninguna tabla nueva. Se
reutilizan `logs_actividad`, `fichajes`, `miembros_equipo`, `sessions` (driver `database`) tal
como existen hoy.

**Testing**: PHPUnit (Feature tests de Laravel), siguiendo el patrón ya usado en el proyecto para
controllers/perfil; tests de aislamiento multi-tenant obligatorios donde se consulten datos de
otro modelo (actividad, fichajes, sesiones) por tratarse de datos de tenant.

**Target Platform**: Aplicación web Laravel con Blade + jQuery/Bootstrap (template NexaDash ya
integrado), compatible con hosting compartido (cPanel/Hostinger).

**Project Type**: Aplicación web monolítica (single Laravel app, sin frontend/backend separados).

**Performance Goals**: Sin requisitos especiales; la vista de perfil es de bajo tráfico (una
carga por usuario por sesión típica), consultas acotadas a un único usuario/tenant.

**Constraints**: Debe funcionar en hosting compartido sin infraestructura adicional (sin colas
dedicadas obligatorias, sin servicios externos nuevos); el envío del enlace de verificación de
email puede encolarse con el driver de colas ya configurado en el proyecto o enviarse síncrono,
según lo que ya use el resto del sistema para correos transaccionales.

**Scale/Scope**: Una vista existente ampliada con ~6 sub-secciones y 4 acciones de escritura
nuevas (cambiar contraseña, editar nombre, solicitar/confirmar cambio de email, cancelar/reenviar
verificación de email). No introduce entidades de negocio nuevas más allá de 3 columnas
transitorias en `users`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Principio I (Aislamiento Multi-Tenant)** — PASS. Las consultas nuevas (actividad reciente,
  fichajes recientes, datos de miembro de equipo) se hacen sobre modelos que ya usan el trait
  `BelongsToTenant` de `stancl/tenancy` (`LogActividad`, `Fichaje`, `MiembroEquipo`), por lo que
  el scope de tenant es automático; además todas se filtran explícitamente por el usuario
  autenticado (`auth()->id()` / su `MiembroEquipo` asociado), nunca por un id recibido del
  cliente. La limpieza de sesiones en cambio de contraseña se filtra por `user_id` de la sesión
  actual, sin cruzar tenants (la tabla `sessions` de Laravel no tiene `tenant_id`, pero solo se
  opera sobre las filas del propio usuario autenticado). Se añaden tests de aislamiento para las
  consultas de actividad y fichajes reutilizando el patrón de ≥2 tenants de prueba.
- **Principio II (Cumplimiento Normativo / RGPD-LOPDGDD)** — PASS con nota. Las 3 columnas nuevas
  (`pending_email`, `pending_email_token`, `pending_email_expires_at`) son datos personales
  transitorios: se sobrescriben en cada nueva solicitud, se limpian al confirmar/cancelar, y se
  ignoran (tratan como caducadas) por expiración al leerse — no se acumulan indefinidamente ni
  requieren un comando de purga programado como `logs_actividad`, porque no es un ledger de
  eventos sino un único estado pendiente por usuario acotado en el tiempo. No aplica el patrón de
  `RetencionLogsTenant`/`logs:purgar` por no ser una tabla de eventos acumulativos; se documenta
  esta decisión aquí en vez de en `docs/03-modelo-datos.md` porque no cambia el modelo de datos
  personal ya documentado (usuarios), solo agrega columnas de soporte a un flujo ya cubierto por
  el principio. La actividad reciente reutiliza `logs_actividad`, que ya cumple (a)-(c) del
  principio (retención/purga, distinción autorizado/denegado, IP+user-agent, intentos fallidos).
- **Principio III (Integridad Financiera Server-Side)** — N/A. No hay importes ni cálculo fiscal
  involucrado en esta feature.
- **Principio IV (Test-First en Lógica Crítica)** — Aplica parcialmente: no es cálculo de
  impuestos/numeración/Verifactu, pero SÍ toca aislamiento multi-tenant (consultas de actividad y
  fichajes ajenas a la tabla `users`), así que los tests de aislamiento correspondientes se
  escriben primero (red-green-refactor) antes de implementar esas consultas. El resto (UI,
  formularios de edición) sigue el flujo de test estándar del proyecto (no estrictamente
  test-first).
- **Principio V (Simplicidad / Hosting Compartido)** — PASS. No se introduce infraestructura
  nueva (sin colas obligatorias, sin servicios externos, sin tablas nuevas más allá de 3 columnas
  en `users`); se reutiliza el mailer y el driver de sesión `database` ya configurados.

No hay violaciones que requieran justificación en "Complexity Tracking".

## Project Structure

### Documentation (this feature)

```text
specs/034-perfil-usuario-ampliado/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── ProfileController.php          # amplía show(); agrega updatePassword(),
│   │                                       # updateNombre(), solicitarCambioEmail(),
│   │                                       # confirmarCambioEmail(), cancelarCambioEmail(),
│   │                                       # reenviarVerificacionEmail()
│   └── Requests/
│       └── Profile/
│           ├── UpdatePasswordRequest.php
│           ├── UpdateNombreRequest.php
│           └── SolicitarCambioEmailRequest.php
├── Mail/
│   └── VerificarCambioEmail.php           # Mailable con el enlace firmado
├── Models/
│   └── User.php                            # + pending_email, pending_email_token,
│                                            # pending_email_expires_at (fillable/casts)
└── Services/
    └── (reutiliza RegistradorActividad, RegistroFichajes, CatalogoPermisos existentes)

database/migrations/
└── 2026_07_26_xxxxxx_add_pending_email_to_users_table.php

resources/views/
├── profile/
│   └── show.blade.php                      # secciones nuevas: rol/permisos, seguridad
│                                            # (contraseña + datos), actividad reciente,
│                                            # empleado/fichajes, aprobación
└── ayuda/
    └── profile.blade.php                   # actualizar con los campos/acciones nuevas

routes/
└── web.php                                  # nuevas rutas bajo el grupo ya existente de /perfil

tests/
└── Feature/
    └── Profile/
        ├── ProfileRolPermisosTest.php
        ├── ProfilePasswordTest.php
        ├── ProfileNombreEmailTest.php
        ├── ProfileActividadRecienteTest.php      # incluye aislamiento multi-tenant
        ├── ProfileEmpleadoFichajesTest.php        # incluye aislamiento multi-tenant
        └── ProfileAprobacionTest.php
```

**Structure Decision**: Proyecto Laravel monolítico existente; esta feature es una ampliación de
un módulo ya implementado (`profile/*`, feature 016), no una app nueva. Se sigue la estructura
MVC estándar del proyecto (Controller + FormRequests + Blade + rutas en `routes/web.php`), sin
introducir carpetas ni capas nuevas, consistente con el Principio V (simplicidad).

## Complexity Tracking

*Sin violaciones que justificar — tabla omitida intencionalmente.*
