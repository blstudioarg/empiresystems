# Specification Quality Checklist: Mesas redimensionables por celdas en el plano del POS

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-08-19
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

- Iteración 1: la redacción inicial nombraba ficheros y componentes concretos (jQuery UI
  `resizable`, `PosPlanoReacomodo`, `pos-sala-plano.init.js`, columnas `ancho_celdas`/`alto_celdas`)
  dentro de los requisitos. Se reescribieron en términos de comportamiento observable; los detalles
  técnicos quedan para `plan.md`. La única mención residual a implementación vive en la sección
  "Documentación consultada", que es trazabilidad de la regla de oro del proyecto, no requisito.
- Las tres preguntas abiertas (destino del atributo de tamaño, política ante colisión, mapeo de
  conversión) se resolvieron con el usuario antes de escribir el spec y están registradas en
  Clarifications; no quedan marcadores pendientes.
- FR-009 nace de una restricción real de las guías de front: el color del borde ya significa el
  estado de la mesa, así que el feedback de bloqueo no puede reutilizarlo.
- Iteración 2 (tras `/speckit-analyze`): FR-015 exigía que "la vista de servicio de la Sala"
  representara la ocupación en celdas, pero el plano **solo se dibuja en modo edición** (el lienzo
  lleva la clase `activo` únicamente al entrar a editar, y la rejilla de tarjetas se oculta). El
  requisito era inimplementable tal cual. Reescrito para acotar el alcance al editor y declarar
  explícitamente como no-objetivo llevar el plano a la pantalla operativa del camarero.
