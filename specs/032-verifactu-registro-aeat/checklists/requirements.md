# Specification Quality Checklist: Verifactu — registro y remisión de facturas a la AEAT

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

- Referencias a nombres de servicios/columnas existentes (`EmisorFacturas`, `factura_eventos`,
  `verifactu.activo`, etc.) se incluyen a propósito en el *contexto de encaje* y en Assumptions
  para anclar la feature al modelo de datos ya reservado; no son decisiones de implementación
  nuevas sino el sustrato existente. Los requisitos (FR) se mantienen a nivel de qué/por qué.
- Dos supuestos marcados para confirmar en `/speckit-clarify` (no bloqueantes, con default
  razonable elegido): (1) emitir no se bloquea por indisponibilidad de la AEAT; (2) certificado
  ausente registra localmente y deja el envío pendiente en vez de impedir la emisión.
- Items marked incomplete require spec updates before `/speckit-clarify` or `/speckit-plan`
