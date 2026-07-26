# Specification Quality Checklist: Reporting Comercial CRM

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-19
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

Validación ejecutada en una sola iteración; todos los ítems pasan.

### Observaciones iniciales — todas resueltas

1. **Ampliación de alcance deliberada (FR-011 a FR-014)**: la spec no se limita a *leer* datos
   existentes; añade el catálogo de canales de captación y toca el alta e importación de leads.
   Justificado en "Objetivo y contexto" (punto 2) y registrado en Complexity Tracking del plan.
   ✅ Confirmado por el usuario.
2. **FR-023 definía el alcance por "permisos de gestión comercial"** sin fijar cuál. ✅ Resuelto en
   research D4: dos claves nuevas, `ver-informes-comerciales` y `ver-informes-equipo`.
3. **FR-006 exigía explicitar la fecha de adscripción de cada indicador** sin fijarla. ✅ Resuelto
   en research D1, con tabla de criterios (cohorte / evento / instantánea) como fuente de verdad.

### Correcciones aplicadas tras `/speckit-analyze`

| ID | Hallazgo | Corrección |
|----|----------|------------|
| G1 | El informe omitía la facturación pese a estar en el input original | Añadidos **FR-030 y FR-031**: negocio cerrado del embudo (solo facturas originadas en presupuesto), con ratio presupuesto→factura en FR-004. Tareas T013, T024 |
| U1 | `FiltrosInforme` referenciado sin tarea que lo cree | Nueva tarea **T006** en Foundational + añadido a la estructura del plan |
| I1 | Asunción de exportación contradecía research D7 | Reescrita: se reutiliza la *infraestructura* Excel, no el contrato por filas |
| I2 | Key Entities omitía `leads.convertido_at` | Actualizado, con la razón por la que es necesario |
| G2 | Sin test de aislamiento multi-tenant en exportación | Nueva tarea **T066** |
| G3 | Sin factory de `CanalCaptacion` | Nueva tarea **T037** |
| G4 | Edge cases sin cobertura (borrado lógico, comercial de baja) | Nueva tarea **T016** |
| U2 | FR-004 decía "duración media" en singular | Pluralizado: dos ciclos, lead→cliente y apertura→cierre |
| I3 | `canal_id` (contrato) vs `canal_captacion_id` (columna) | Documentado en ambos artefactos |
| A1 | T005 mezclaba código con ejecución | Reformulada como cambio de código + nota en quickstart |
| G5 | Edge case de cambio de permisos en caliente | Nueva tarea **T059** |
| G6 | SC-001 solo verificable manualmente | Aceptado: es un criterio de UX, cubierto por el recorrido de quickstart |

**Estado**: 0 hallazgos abiertos. Listo para `/speckit-implement`.
