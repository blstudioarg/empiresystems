# Specification Quality Checklist: POS con mesas y opciones de artículo (hostelería)

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-08-09
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

### Iteración 1 — hallazgos corregidos

1. **Detalles de implementación filtrados**: la redacción inicial nombraba clases CSS, rutas y
   archivos concretos. Corregido: los requisitos se reformularon en términos de comportamiento
   observable. Las referencias a archivos quedan **solo** en la sección "Convenciones de front que
   condicionan esta spec", que es trazabilidad documental exigida por `CLAUDE.md`, no requisito.
2. **Criterios de éxito con métricas técnicas**: sustituidos por métricas de usuario (toques,
   segundos, cuentas simultáneas).
3. **Cobertura de las 4 capas de documentación**: faltaban la ayuda in-app y la base de
   conocimiento del asistente IA, obligatorias al cerrar una feature. Añadidas.
4. **Concurrencia**: no estaba cubierto el escenario de dos dispositivos sobre la misma mesa.
5. **Tope de la simplificada**: la cuenta podía crecer sin aviso hasta el momento del cobro.

### Iteración 2 — decisiones del usuario incorporadas

Las tres preguntas abiertas quedaron resueltas y la sección "Clarifications pendientes" se retiró.
Cambios de fondo respecto a la iteración 1:

1. **Módulo opcional** (aportado por el usuario, no estaba en el alcance original): todo el bloque
   se activa desde una pestaña "POS" en Configuración y arranca **apagado**. Es el cambio de mayor
   impacto: convierte una feature que alteraba el POS de todos los tenants en una que no toca a
   nadie hasta que la enciende. Nueva historia P1, 8 requisitos y 2 criterios de éxito.
2. **Cobro dividido por selección de líneas** (Q3 = C): rompe la premisa de que cobrar cierra la
   cuenta. Nueva historia P4 y 8 requisitos; obligó a revisar el estado de la cuenta, el importe
   mostrado en la mesa, el aviso de tope y las reglas de transferencia/unión.
3. **Suplemento de zona sobre el precio de línea** (Q1 = A) y **opción vinculada sin línea propia**
   (Q2 = A): resueltos, con la consecuencia aceptada documentada en Assumptions en ambos casos.
4. **Zonas sin privilegios**: se añadió explícitamente que ningún nombre de zona tiene significado
   para el sistema, tras confirmarlo el usuario. "Terraza" es un ejemplo, no un concepto.
5. **Grid confirmado sobre plano**: el usuario aportó la captura del editor de plano de GoTPV; se
   verificó que era la opción ya descartada y se confirmó el grid. Registrado en Assumptions junto
   con lo que sí se toma de esa pantalla (las pestañas de zona).
6. **Renumeración**: los requisitos pasaron a 62 y se renumeraron de forma correlativa tras
   detectarse un FR-036 duplicado.

### Estado

Sin marcadores `[NEEDS CLARIFICATION]`. Todos los ítems del checklist en verde.

**Riesgo señalado y ya resuelto**: la feature creció de 5 a 7 historias y de 45 a 64 requisitos, con
los dos bloques caros (cuentas abiertas y cobro dividido) acoplados. El plan lo resolvió en
`research.md` D9: el esquema soporta cobro parcial desde la primera migración, pero la funcionalidad
se implementa como incremento posterior sobre las cuentas abiertas ya en verde (Phase 8 de
`tasks.md`), lo que además la convierte en el corte natural si faltara tiempo.

### Iteración 3 — dos requisitos añadidos tras el plan

FR-063 (importe entregado y cambio a devolver) y FR-064 (contexto de mesa en el modal de cobro),
aportados por el usuario desde la pantalla de cobro de GoTPV. Solo interfaz, sin efecto fiscal ni de
modelo de datos. Se descartaron explícitamente, con motivo escrito en Assumptions, los "métodos"
Autoconsumo, Rotura, Invitación y Cuenta cliente de esa misma pantalla: no son formas de pago sino
salidas sin venta o venta a crédito, y tratarlas como tales produciría documentos a 0 € o descuadres
de caja.

### `/speckit-analyze` — 7 hallazgos, todos corregidos

| # | Severidad | Hallazgo | Corrección |
|---|---|---|---|
| 1 | **ALTA** | El encadenamiento Verifactu es área NON-NEGOTIABLE del Principio IV y ninguna tarea lo probaba bajo cobros parciales — el único caso de la feature que genera varias facturas desde un mismo origen | Nueva tarea T089b (TEST-FIRST) |
| 2 | MEDIA | FR-020 exige confirmación explícita al anular una cuenta; ninguna tarea la cubría | Nueva tarea T050b |
| 3 | MEDIA | El edge case "capacidad desactivada conserva datos" (FR-007) no tenía test | Nueva tarea T018b |
| 4 | MEDIA | `plan.md` quedó desactualizado respecto a FR-063/FR-064, añadidos después | Nota explícita en Technical Context |
| 5 | MEDIA | SC-003 y SC-013 no tenían tarea de verificación | Nuevas tareas T107b y T107c |
| 6 | MEDIA | `quickstart.md` no validaba el vuelto | Nueva sección 8 |
| 7 | BAJA | Recuento de requisitos desactualizado (62 → 64) en plan y checklist | Corregido en ambos |

**Verificado sin hallazgos**: cobertura de los 64 FR (ninguno huérfano), las 7 historias con fase
propia y criterio de test independiente, la desviación deliberada de orden (US6 antes que US5)
trazable en los tres artefactos, y ausencia de fuga de alcance — nada de lo declarado fuera
(plano drag & drop, comandas de cocina, comandero, carta digital, autopedido, reservas, descuentos,
arqueo, devoluciones, autoconsumo/rotura/invitación, cuenta cliente) aparece en plan ni tareas.
