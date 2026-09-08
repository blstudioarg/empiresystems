# Implementation Plan: Historial de conversaciones del asistente IA

**Branch**: `045-historial-conversaciones-asistente` | **Date**: 2026-09-07 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/045-historial-conversaciones-asistente/spec.md`

## Summary

La conversación del asistente pasa de vivir en la sesión de Laravel a dos tablas nuevas
(`asistente_conversaciones`, `asistente_mensajes`), con historial por usuario, compactación
automática por resumen al superar los 30 mensajes, y retención de 90 días configurable por tenant con
purga diaria. La sesión conserva solo el id de la conversación activa y la acción pendiente.

El orquestador `AsistenteIa` apenas cambia: `ConversacionAsistente` mantiene su interfaz pública y
sustituye la sesión por base de datos por dentro, así que el loop de tool use, el flujo de
confirmación de escrituras y el streaming SSE siguen igual. Lo nuevo es un `CompactadorConversacion`
aislado (único punto que pide el resumen al proveedor) y una vista de historial dentro del panel.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, trait `BelongsToTenant`),
`openai-php/client` ^0.20 (ya presente, `config/ia.php`)

**Storage**: MySQL/MariaDB. Dos tablas nuevas + una clave en `configuraciones`

**Testing**: PHPUnit (`php artisan test`), con `RefreshDatabase`. Compactación probada sin red
sustituyendo el punto que habla con el proveedor, como en `TurnoTerminaAlProponerTest`

**Target Platform**: hosting compartido cPanel/Hostinger (Principio V)

**Project Type**: aplicación web Laravel monolítica (Blade + JS vanilla, sin build de front)

**Performance Goals**: abrir el historial y retomar una conversación en < 3 s con 50 conversaciones
(SC-002); el turno que compacta cuesta como mucho el doble que uno normal (SC-007)

**Constraints**: sin colas ni workers (Principio V) — la compactación es síncrona dentro del
request; la escritura de sesión dentro de la closure de la `StreamedResponse` requiere
`session()->save()` explícito (`docs/01-arquitectura.md`, Decisión 9)

**Scale/Scope**: 2 tablas, 4 endpoints nuevos + 1 modificado, 1 comando de purga, 1 vista deslizante
dentro del panel existente

## Constitution Check

*GATE: revisado antes de Phase 0 y de nuevo tras Phase 1. Constitución v1.2.0.*

| Principio | Cumplimiento | Cómo |
|---|---|---|
| **I. Aislamiento multi-tenant (NON-NEGOTIABLE)** | ✅ | Ambas tablas llevan `tenant_id` indexado con FK y el trait `BelongsToTenant`. Toda resolución por id se acota además a `user_id`. Tests de aislamiento con ≥2 tenants, escritos antes de implementar (Principio IV). Un id ajeno responde `404`, no `403`. |
| **II. Cumplimiento normativo (RGPD/LOPDGDD)** | ✅ | Es el principio que más pesa aquí: la feature **invierte** una decisión previa de no retener. Se cumple con `asistente.retencion_dias` (default 90) + `asistente:purgar` diario, reutilizando el patrón `RetencionLogsTenant`/`logs:purgar` que la constitución obliga a reutilizar. Minimización: los resultados crudos de las herramientas no viajan al cliente y el resumen sustituye al literal. `docs/03-modelo-datos.md` se actualiza en el mismo cambio (FR-024), como exige el principio. |
| **III. Integridad financiera server-side** | ✅ (no aplica) | La feature no calcula importes. El flujo de escrituras del asistente sigue pasando por los servicios de cálculo existentes vía confirmación explícita (FR-022), sin cambios. |
| **IV. Test-first en lógica crítica (NON-NEGOTIABLE)** | ✅ | Aislamiento multi-tenant entra en el alcance obligatorio: sus tests se escriben primero y deben fallar. Se aplica el mismo rigor al corte de la compactación (no romper pares `assistant`/`tool`), que es donde un error es silencioso y caro de detectar. |
| **V. Simplicidad y hosting compartido** | ✅ | Sin colas, sin Redis, sin extensiones nuevas: dos tablas, un comando programado por el cron único de cPanel que ya existe, y una llamada HTTP más al proveedor. La compactación es síncrona precisamente para no introducir workers. |

**Resultado**: sin violaciones. La sección "Complexity Tracking" queda vacía y se elimina.

**Punto de atención para la revisión** (checklist del Development Workflow, ítem 5): esta feature
introduce tablas con datos personales; el revisor debe verificar explícitamente que el plazo de
retención y la purga están implementados y programados, no solo especificados.

## Project Structure

### Documentation (this feature)

```text
specs/045-historial-conversaciones-asistente/
├── plan.md              # Este fichero
├── spec.md              # Qué y por qué
├── research.md          # Decisiones D1–D11
├── data-model.md        # Tablas, índices, configuración
├── quickstart.md        # Cómo validarlo end-to-end
├── contracts/
│   └── endpoints.md     # Contratos HTTP
├── checklists/
│   └── requirements.md  # Validación de calidad del spec
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   └── PurgarConversacionesAsistente.php   # NUEVO — comando `asistente:purgar`
├── Http/Controllers/
│   ├── AsistenteChatController.php         # MODIFICADO — conversación activa, evento `compactando`
│   └── AsistenteConversacionController.php # NUEVO — listar/abrir/crear/borrar
├── Ia/
│   └── ConversacionAsistente.php           # MODIFICADO — misma interfaz, respaldo en BD
├── Models/
│   ├── AsistenteConversacion.php                  # NUEVO
│   └── AsistenteMensaje.php                       # NUEVO
├── Services/
│   ├── AsistenteIa.php                     # MODIFICADO — dispara la compactación antes del loop
│   └── CompactadorConversacion.php         # NUEVO — único punto que pide el resumen
└── Support/
    └── RetencionAsistenteTenant.php        # NUEVO — calcado de RetencionLogsTenant

bootstrap/app.php                           # MODIFICADO — `asistente:purgar` diario

database/migrations/
├── ..._create_asistente_conversaciones_table.php   # NUEVO
└── ..._create_asistente_mensajes_table.php         # NUEVO

public/js/asistente-chat.js                 # MODIFICADO — historial, eventos nuevos
resources/views/partials/asistente-chat.blade.php   # MODIFICADO — vista de historial + CSS
routes/web.php                              # MODIFICADO — 4 rutas nuevas

docs/01-arquitectura.md                     # MODIFICADO — Decisión 9 (FR-025)
docs/03-modelo-datos.md                     # MODIFICADO — sección del asistente (FR-024)
resources/ia/conocimiento/asistente.md      # MODIFICADO — (FR-025)

tests/
├── Feature/Asistente/
│   ├── HistorialAislamientoTest.php        # NUEVO — Principio I + IV, test-first
│   ├── HistorialEndpointsTest.php          # NUEVO
│   ├── PersistenciaConversacionTest.php    # EXISTENTE — debe seguir en verde
│   └── TurnoTerminaAlProponerTest.php      # EXISTENTE — debe seguir en verde
└── Unit/
    ├── CompactadorConversacionTest.php     # NUEVO — corte sin romper pares assistant/tool
    └── RetencionAsistenteTenantTest.php     # NUEVO
```

**Structure Decision**: se mantiene la estructura del monolito Laravel que ya usa el proyecto
(`app/Ia` para el asistente, `app/Services` para orquestación, `app/Support` para helpers de
configuración). No se introduce ninguna carpeta ni capa nueva: la feature encaja en lo que hay.

## Fases

**Phase 0 — Research**: completada, en [research.md](./research.md). Once decisiones (D1–D11), sin
ningún `NEEDS CLARIFICATION` pendiente: las tres ambigüedades de producto (retención, estrategia de
compactación y alcance de la UI) se resolvieron con el usuario antes de escribir el spec.

**Phase 1 — Diseño y contratos**: completada. [data-model.md](./data-model.md) fija tablas, índices
y el cambio de contenido de la sesión; [contracts/endpoints.md](./contracts/endpoints.md) fija los
cuatro endpoints nuevos y los dos eventos SSE añadidos; [quickstart.md](./quickstart.md) describe
cómo validarlo.

**Constitution re-check tras Phase 1**: sin cambios. El diseño no introdujo nada que roce los
principios: sigue sin colas, sin dependencias nuevas y con retención definida desde el esquema.

**Phase 2 — Tareas**: la genera `/speckit-tasks`. El orden esperado arranca por los tests de
aislamiento (Principio IV, deben fallar primero), sigue por migraciones y modelos, después
`ConversacionAsistente` sobre base de datos manteniendo su interfaz, luego endpoints y UI, y cierra
con compactación, purga y documentación.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Reescribir `ConversacionAsistente` rompe el flujo de escrituras del asistente | Mantener su interfaz pública intacta y apoyarse en los tests existentes (`PersistenciaConversacionTest`, `TurnoTerminaAlProponerTest`, `ConfirmacionEscrituraTest`) como red de seguridad: deben seguir en verde sin tocarlos. |
| La compactación rompe pares `assistant`/`tool` y el proveedor rechaza el hilo | Regla de corte explícita en research D4, cubierta con tests unitarios antes de implementar. |
| Coste inesperado para el tenant por la llamada de resumen | Solo ocurre al cruzar el umbral, no en cada turno. Queda documentado en el spec (Assumptions) y avisado en la UI (FR-011). |
| Una migración en desarrollo se lleva por delante datos de demo | Las dos migraciones son aditivas (`create`), sin `down()` destructivo sobre tablas existentes. El quickstart avisa explícitamente de no usar `migrate:fresh`. |
| El historial crece sin control | Retención de 90 días + purga diaria por lotes de 500, más el tope de 50 en el listado. |
