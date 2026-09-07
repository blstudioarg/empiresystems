# Specification Quality Checklist: Importación conversacional con el asistente

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-09-07
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

Tres decisiones de producto se cerraron con el usuario antes de escribir el spec, así que no quedó
ningún `[NEEDS CLARIFICATION]`: formatos admitidos (todos desde la primera versión), módulos (los
tres ya importables) y qué hacer con las filas que siguen inválidas (importar el resto y reportarlas).

Correcciones aplicadas durante la validación:

1. **US1 y US2 quedaron ambas como P1.** Al principio la corrección conversacional era P2, pero sin
   ella la US1 entrega poco más que la pantalla de importación que ya existe con pasos adicionales:
   el valor de la feature está justo en el diálogo de corrección. Rebanarlas como "MVP + extra"
   habría producido un incremento que no merece la pena desplegar solo.
2. **FR-009 se reforzó** con la prohibición explícita de inventar valores ausentes, y se duplicó como
   escenario de aceptación en US2. Es el riesgo más serio de la feature: un asistente que rellena un
   NIF que no leyó introduce datos falsos en el maestro de clientes, y el usuario no tiene forma de
   detectarlo.
3. **SC-003 y SC-008 se hicieron numéricos** (exactamente 3 de 10; importa 8 de 10 e informa de 2)
   en vez de porcentajes vagos.
4. Se añadieron los casos límite de cambio de conversación a mitad, pérdida de permiso entre análisis
   y confirmación, y servicio de IA no configurado.

Puntos a vigilar en `/speckit-plan`:

- El plan debe dejar explícito **cómo se reutiliza el pipeline de la feature 031** sin duplicar
  validaciones. Si acaba escribiendo un segundo camino de escritura, la feature está mal planteada.
- La retención del material aportado (FR-023) debe reutilizar el mecanismo existente
  (`AlmacenImportaciones` + `importaciones:purgar`), no inventar uno nuevo — lo exige la constitución.
- Falta decidir el umbral de tamaño/filas cuando el material viene de un documento interpretado: el
  límite de 2.000 filas del importador aplica al fichero, pero un PDF de 40 páginas es otro problema
  (coste y latencia), y no es una decisión de producto sino técnica.
