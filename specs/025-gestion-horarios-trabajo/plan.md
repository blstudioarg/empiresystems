# Implementation Plan: Gestión de horarios de trabajo y cumplimiento de jornada

**Branch**: `025-gestion-horarios-trabajo` | **Date**: 2026-07-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/025-gestion-horarios-trabajo/spec.md`

## Summary

Añadir la capa de horario **planificado** sobre el módulo de control horario existente (feature
024): plantillas de horario reutilizables por tenant con tramos por día (turnos partidos),
asignación a miembros con vigencia temporal (histórico), visualización del turno esperado en el
portal "Mi jornada" ya existente, un informe de administración que cruza previsto vs. real leyendo
los `fichajes`, y alertas de incumplimiento (ausencia/retraso) generadas por un comando programado
diario reutilizando la tabla `alertas`. Todo el cálculo de horas/cumplimiento es server-side, el
informe se calcula al vuelo (sin tabla de resultados persistida) y las alertas las produce un job
diario compatible con hosting compartido (`withSchedule` + `schedule:run`).

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), Eloquent, Blade +
template NexaDash ya vendorizado (DataTable, modal CRUD, Chart.js). Sin dependencias nuevas.

**Storage**: MySQL/MariaDB (prod) / SQLite (tests). Tres tablas nuevas: `horarios`,
`horario_tramos`, `asignaciones_horario`. Reutiliza `miembros_equipo`, `fichajes`, `alertas`,
`configuraciones`.

**Testing**: PHPUnit (`php artisan test`). Test-first en la lógica crítica (Principio IV):
aislamiento multi-tenant, resolución de horario vigente por fecha, cálculo de horas y de
cumplimiento (retraso/ausencia/parcial/exceso), idempotencia del comando de alertas.

**Target Platform**: Hosting compartido cPanel/Hostinger (Principio V); el comando diario corre con
la única entrada de cron `schedule:run` ya existente.

**Project Type**: Monolito web Laravel (backend + Blade), raíz del repo.

**Performance Goals**: No hay objetivos de latencia especiales; el informe opera sobre el volumen de
un tenant (decenas de miembros × días del rango). Consultas acotadas por índices `(tenant_id, ...)`.

**Constraints**: multi-tenant estricto (Principio I), cálculo financiero/horario server-side
(Principio III), simplicidad/hosting compartido (Principio V), RGPD (los horarios no son dato
personal sensible nuevo; la asignación referencia al miembro ya existente).

**Scale/Scope**: 3 tablas + 3 modelos + 1 value object de cumplimiento + 1 servicio de cálculo + 1
comando + extensiones a controladores/vistas existentes (`MiembroEquipoController` o nuevo
`HorarioController`, `MiJornadaController`, `InformeJornadaController`, `AlertaController`).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ Las 3 tablas nuevas llevan `tenant_id`
  indexado y usan `BelongsToTenant` (global scope), igual que `miembros_equipo`/`fichajes`/`alertas`.
  Se incluyen tests de aislamiento (≥2 tenants) para horarios, asignaciones y el informe.
- **II. Cumplimiento Normativo España-First (incl. RGPD)**: ✅ El registro de jornada real (art.
  34.9 ET) ya está en 024 y no se altera (los `fichajes` se leen, no se modifican). El horario
  planificado no introduce dato personal nuevo con requisito de retención propio: las tablas nuevas
  guardan cuadrantes y vínculos miembro↔horario, no datos personales sensibles adicionales. No se
  requiere nuevo mecanismo de purga (Principio II, minimización) porque no se añade categoría de dato
  personal purgable — la asignación se conserva como histórico legítimo ligado al miembro.
- **III. Integridad Server-Side**: ✅ Todo el cálculo de horas previstas, horas trabajadas y
  veredictos de cumplimiento ocurre en el backend (`ServicioCumplimiento`); el cliente nunca calcula
  cumplimiento. El comando de alertas es la única fuente de alertas de incumplimiento.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: ✅ Resolución de horario vigente, cálculo de
  horas y clasificación de cumplimiento se escriben test-first (Red-Green-Refactor).
- **V. Simplicidad y Hosting Compartido**: ✅ Sin dependencias nuevas; el job diario usa el cron
  `schedule:run` ya presente. El informe se calcula al vuelo (no se añade tabla de resultados =
  menos superficie). Fuera de v1: festivos, vacaciones, horas extra formales (YAGNI).

**Resultado del gate: PASA.** Sin violaciones que justificar → sin Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/025-gestion-horarios-trabajo/
├── plan.md              # Este archivo
├── spec.md              # Especificación (con Clarifications)
├── research.md          # Fase 0 (decisiones de diseño)
├── data-model.md        # Fase 1 (tablas, modelos, reglas)
├── quickstart.md        # Fase 1 (guía de validación E2E)
├── contracts/
│   └── http.md          # Fase 1 (rutas/endpoints y payloads)
└── checklists/
    └── requirements.md  # Checklist de calidad de la spec
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── DiaSemana.php                      # nuevo (opcional): 0-6 con label; o int directo
├── Models/
│   ├── Horario.php                        # nuevo (BelongsToTenant, hasMany tramos/asignaciones)
│   ├── HorarioTramo.php                   # nuevo
│   └── AsignacionHorario.php              # nuevo (miembro↔horario con vigencia)
├── Support/
│   ├── ConfigFichajes.php                 # extender: umbral retraso, tolerancia exceso
│   └── Cumplimiento/
│       ├── ServicioCumplimiento.php       # nuevo: horas previstas/trabajadas + veredicto por día
│       └── ResultadoDia.php               # nuevo value object: previsto, trabajado, marcas
├── Http/
│   ├── Controllers/
│   │   ├── HorarioController.php          # nuevo: CRUD horarios (+ tramos) tenant-scoped
│   │   ├── AsignacionHorarioController.php# nuevo: asignar/cerrar vigencia
│   │   ├── MiJornadaController.php        # extender: turno esperado hoy/semana
│   │   └── InformeJornadaController.php   # extender: columnas de cumplimiento
│   └── Requests/
│       ├── HorarioRequest.php             # nuevo: nombre + tramos válidos (no solape)
│       └── AsignacionHorarioRequest.php   # nuevo: vigencia sin solape
├── Console/Commands/
│   └── EvaluarCumplimientoJornada.php     # nuevo: job diario → alertas idempotentes
database/
├── migrations/
│   ├── ..._create_horarios_table.php
│   ├── ..._create_horario_tramos_table.php
│   └── ..._create_asignaciones_horario_table.php
├── factories/  (HorarioFactory, HorarioTramoFactory, AsignacionHorarioFactory)
└── seeders/    ConfiguracionSeeder.php     # extender: claves de umbral por tenant
resources/views/
├── horarios/index.blade.php               # nuevo: listado + modal CRUD de horarios/tramos
├── mi-jornada/                            # extender: bloque "turno esperado"
└── jornada/                               # extender: informe con cumplimiento
app/Enums/TipoAlerta.php                    # extender: AusenciaJornada, RetrasoJornada
bootstrap/app.php                           # extender withSchedule: comando diario
routes/web.php                              # extender: rutas horarios/asignaciones
tests/
├── Unit/
│   ├── ServicioCumplimientoTest.php
│   └── AsignacionHorarioVigenteTest.php
└── Feature/
    ├── HorarioCrudTest.php
    ├── AsignacionHorarioTest.php
    ├── InformeCumplimientoTest.php
    ├── MiJornadaTurnoEsperadoTest.php
    └── EvaluarCumplimientoJornadaTest.php
```

**Structure Decision**: Monolito Laravel existente. Se sigue la organización ya usada por la feature
024 (modelos en `app/Models`, servicios/soporte en `app/Support`, comandos en
`app/Console/Commands`, vistas por recurso en `resources/views`). El CRUD de horarios sigue el
patrón "DataTable + modal CRUD" de `docs/04-front-guidelines.md`. La asignación de horario a miembro
se integra en el flujo de `miembros-equipo` (o en la vista de horarios), decisión afinada en
research.md (R4).

## Complexity Tracking

> Sin violaciones de la Constitución → sección no aplicable.
