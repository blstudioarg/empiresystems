# Feature Specification: Pagos y cobros de facturas

**Feature Branch**: `010-pagos-facturas`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: "Registro de pagos/cobros aplicados a facturas emitidas: permitir registrar uno o varios cobros (parciales o totales) contra una factura emitida, ver el saldo pendiente de cobro, y listar/anular pagos. Basado en la tabla `pagos` ya diseñada en docs/03-modelo-datos.md (Fase 2, no implementada): tenant_id, factura_id, fecha, importe, metodo (mismo enum que forma_pago), referencia opcional. Reglas clave: solo se puede registrar pago contra una factura en estado emitida (no borrador); la suma de pagos no puede superar el total de la factura; debe respetar aislamiento multi-tenant; una factura con pagos que cubren el total pasa a considerarse 'cobrada' (derivar el estado de cobro a partir de los pagos, no como columna editable a mano). No confundir con Verifactu (fuera de alcance aquí)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar un cobro contra una factura emitida (Priority: P1)

Como usuario del tenant, quiero registrar un cobro (total o parcial) contra una factura ya emitida, para llevar constancia de qué facturas están pagadas y cuáles siguen pendientes de cobro.

**Why this priority**: Es el núcleo de la feature — sin poder registrar un cobro, no hay nada que listar, ver ni anular. Es el mínimo que ya aporta valor (saber qué se cobró).

**Independent Test**: Se puede probar de forma aislada emitiendo una factura de importe conocido, registrando un pago por una parte del total, y verificando que el saldo pendiente se reduce en esa misma cantidad.

**Acceptance Scenarios**:

1. **Given** una factura en estado `emitida` con total 121,00 €, **When** el usuario registra un pago de 121,00 € con fecha, método e importe, **Then** el pago queda guardado y la factura pasa a considerarse `cobrada` (saldo pendiente 0,00 €).
2. **Given** una factura en estado `emitida` con total 121,00 €, **When** el usuario registra un pago parcial de 50,00 €, **Then** el pago queda guardado, el saldo pendiente pasa a 71,00 € y la factura se considera `parcialmente cobrada`.
3. **Given** una factura en estado `borrador`, **When** el usuario intenta registrar un pago contra ella, **Then** el sistema rechaza la operación y no se crea ningún pago.
4. **Given** una factura `emitida` con saldo pendiente de 30,00 €, **When** el usuario intenta registrar un pago de 50,00 €, **Then** el sistema rechaza la operación (el pago excede el saldo pendiente) y no se crea ningún pago.

---

### User Story 2 - Ver el estado de cobro y el historial de pagos de una factura (Priority: P1)

Como usuario del tenant, quiero ver de un vistazo si una factura está pendiente, parcialmente cobrada o cobrada, y consultar el detalle de los pagos que se le aplicaron, para saber en qué situación de cobro está cada cliente sin tener que hacer cuentas a mano.

**Why this priority**: Registrar un pago sin poder verlo después no aporta valor real; esta historia es la que cierra el ciclo mínimo útil junto con la US1 (forman el MVP).

**Independent Test**: Se puede probar de forma aislada registrando 0, 1 y varios pagos sobre distintas facturas y verificando que el estado de cobro mostrado (pendiente/parcial/cobrada) y el listado de pagos coinciden con lo registrado.

**Acceptance Scenarios**:

1. **Given** una factura `emitida` sin pagos registrados, **When** el usuario consulta su detalle, **Then** ve el estado de cobro `pendiente` y el saldo pendiente igual al total de la factura.
2. **Given** una factura con dos pagos parciales registrados, **When** el usuario consulta su detalle, **Then** ve el listado de ambos pagos (fecha, importe, método, referencia) y el saldo pendiente recalculado como total menos la suma de esos pagos.
3. **Given** una lista de facturas de distintos estados de cobro, **When** el usuario consulta el listado de facturas, **Then** puede identificar visualmente cuáles están pendientes, parcialmente cobradas y cobradas.

---

### User Story 3 - Anular un pago registrado por error (Priority: P2)

Como usuario del tenant, quiero poder anular un pago que se registró por error (importe equivocado, factura equivocada, duplicado), para que el saldo pendiente de la factura vuelva a reflejar la realidad sin tener que editar el pago original.

**Why this priority**: Es una corrección de errores operativos, no parte del flujo principal de cobro. Aporta valor una vez que ya existe el registro de pagos (US1/US2), pero el sistema es útil sin ella si se asume que los pagos casi nunca se registran mal.

**Independent Test**: Se puede probar de forma aislada registrando un pago, anulándolo, y verificando que el saldo pendiente de la factura vuelve al valor previo al pago y que el pago anulado se distingue de uno vigente en el listado.

**Acceptance Scenarios**:

1. **Given** una factura con un pago parcial registrado, **When** el usuario anula ese pago, **Then** el pago queda marcado como anulado (no se borra físicamente), deja de contar para el saldo pendiente, y la factura vuelve al estado de cobro que tenía antes de ese pago.
2. **Given** un pago ya anulado, **When** el usuario intenta anularlo de nuevo, **Then** el sistema rechaza la operación (no se puede anular dos veces).

### Edge Cases

- ¿Qué pasa si se intenta registrar un pago con importe cero o negativo? El sistema debe rechazarlo.
- ¿Qué pasa si se registran pagos que en conjunto igualan exactamente el total de la factura pero en múltiples cuotas con decimales? El saldo pendiente debe llegar exactamente a 0,00 € sin arrastrar diferencias de redondeo.
- ¿Qué pasa si se intenta registrar un pago contra una factura rectificativa o contra la factura original que ya fue rectificada? Debe seguir la misma regla general (solo facturas `emitidas` pueden recibir pagos); una factura marcada como rectificada ya no admite nuevos pagos si su total vigente es el de la rectificativa.
- ¿Qué pasa si un usuario intenta ver o anular un pago de otro tenant (por ejemplo, adivinando un ID)? Debe ser rechazado como si el recurso no existiera, igual que el resto de entidades del sistema.
- ¿Qué pasa con una factura anulada/rectificada que ya tenía pagos registrados contra el total anterior? Fuera de alcance de esta feature: se documenta como limitación conocida (ver Assumptions) y no bloquea el MVP.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir registrar un pago (fecha, importe, método, referencia opcional) únicamente contra facturas en estado `emitida`.
- **FR-002**: El sistema DEBE rechazar el registro de un pago si su importe es menor o igual a cero.
- **FR-003**: El sistema DEBE rechazar el registro de un pago si la suma de pagos vigentes (no anulados) de la factura, incluyendo el nuevo, supera el total de la factura.
- **FR-004**: El sistema DEBE calcular el saldo pendiente de una factura como su total menos la suma de sus pagos vigentes (no anulados).
- **FR-005**: El sistema DEBE derivar el estado de cobro de una factura (`pendiente`, `parcialmente cobrada`, `cobrada`) a partir de sus pagos vigentes, sin permitir que un usuario lo fije manualmente.
- **FR-006**: El sistema DEBE permitir consultar el listado de pagos (vigentes y anulados) asociados a una factura, incluyendo fecha, importe, método, referencia y estado (vigente/anulado).
- **FR-007**: El sistema DEBE permitir anular un pago vigente, dejando constancia de la anulación sin eliminar el registro.
- **FR-008**: El sistema DEBE rechazar la anulación de un pago que ya está anulado.
- **FR-009**: El sistema DEBE recalcular el saldo pendiente y el estado de cobro de la factura inmediatamente después de anular un pago.
- **FR-010**: El sistema DEBE aislar los pagos por tenant: un usuario no puede ver, registrar ni anular pagos de facturas que no pertenecen a su tenant.
- **FR-011**: El sistema DEBE mostrar el estado de cobro de cada factura en el listado de facturas existente.
- **FR-012**: El sistema NO DEBE permitir registrar pagos contra facturas en estado `borrador`.

### Key Entities

- **Pago**: Un cobro (parcial o total) aplicado a una factura emitida. Pertenece a un tenant y a una factura. Tiene fecha, importe, método de cobro (mismo catálogo que la forma de pago de la factura), referencia opcional (ej. nº de operación bancaria) y un estado vigente/anulado. Varios pagos pueden aplicarse a la misma factura.
- **Estado de cobro de la factura** (derivado, no almacenado como campo editable): `pendiente` (sin pagos vigentes), `parcialmente cobrada` (pagos vigentes > 0 y < total), `cobrada` (pagos vigentes == total). Se calcula a partir de los pagos vigentes de la factura, no se guarda como decisión manual del usuario.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede registrar un cobro contra una factura emitida y ver el saldo pendiente actualizado en menos de 5 segundos desde la confirmación.
- **SC-002**: El 100% de los intentos de registrar un pago que supere el saldo pendiente de la factura son rechazados sin excepción.
- **SC-003**: El estado de cobro mostrado para cualquier factura coincide siempre con la suma de sus pagos vigentes frente a su total, sin discrepancias detectables por el usuario.
- **SC-004**: Un usuario puede anular un pago mal registrado y ver el saldo pendiente corregido en menos de 5 segundos, sin necesidad de soporte técnico ni acceso a base de datos.
- **SC-005**: Ningún usuario puede ver, registrar o anular pagos de facturas de un tenant distinto al propio, verificado en el 100% de los intentos.

## Assumptions

- Se reutiliza el mismo catálogo de métodos de pago (`metodo`) que ya existe para `forma_pago` en facturas (efectivo, transferencia, tarjeta, etc.), sin crear un catálogo nuevo.
- Un pago se asocia siempre a una única factura; no se cubre en esta feature el cobro único que se reparte entre varias facturas (pago agrupado/conciliación bancaria) — queda fuera de alcance.
- No se cubre en esta feature el impacto de pagos previos cuando una factura es rectificada (el total de la factura original cambia). Se documenta como limitación conocida a resolver en una iteración futura si surge el caso de uso.
- No se cubre en esta feature el envío de recordatorios de cobro, conciliación bancaria automática, ni integración con pasarelas de pago — es registro manual de cobros ya recibidos por otros medios (efectivo, transferencia, etc.).
- La eliminación física de un pago no está contemplada; solo se admite la anulación (soft, conservando el registro), en línea con el principio de integridad financiera del proyecto.
