# Feature Specification: Gestión de Albaranes de Entrega

**Feature Branch**: `029-gestion-albaranes`

**Created**: 2026-07-08

**Status**: Draft

**Input**: User description: "Gestión de Albaranes (CRM): módulo propio de albaranes de entrega,
independiente de Leads/Oportunidades/Presupuestos pero integrado con ellos. Un albarán puede
crearse (a) desde un presupuesto aceptado, heredando sus líneas con seguimiento de cantidad
pendiente de entrega por línea (entrega parcial en varios albaranes), o (b) directamente contra un
cliente sin presupuesto previo (venta/entrega ad-hoc). Ciclo de vida: borrador → entregado (dispara
movimiento de stock de salida, nuevo origen "Albaran" en OrigenMovimientoStock y columna
albaran_id en movimientos_stock) → facturado (terminal). Desde "entregado" y mientras no esté
facturado, se puede anular: genera el movimiento de stock inverso (entrada), mismo patrón que las
rectificativas de factura. Conversión a factura: selección múltiple de N albaranes del mismo
cliente, todos en estado "entregado" y no facturados, se consolidan en una única factura borrador
con las líneas separadas por albarán de origen (trazabilidad), sin duplicar el movimiento de stock
(EmisorFacturas::moverStock() debe saltarse las facturas que provienen de albaranes, porque el
stock ya se movió al entregarlos). Vista propia: listado con cards informativas (total, pendientes
de facturar, entregados) + datatable con selección múltiple de filas para la conversión a factura,
entrada en el sidebar dentro del grupo CRM junto a Leads/Oportunidades/Presupuestos, con su propio
permiso ver-albaranes."

## Objetivo y contexto

La feature 028 cerró el embudo comercial **Lead → Oportunidad → Presupuesto → Factura**, pero deja
sin cubrir un paso intermedio que muchas pymes necesitan: la **entrega física** de lo vendido antes
de facturarlo, y la posibilidad de facturar de una sola vez varias entregas ya realizadas. Esta
feature añade el **albarán de entrega** como documento propio, no fiscal, cuya función principal es
**mover stock en el momento real de la entrega** (no en el momento de facturar) y permitir agrupar
varias entregas de un mismo cliente en una única factura.

El albarán es conceptualmente distinto del presupuesto: el presupuesto es una oferta de precio
previa a la venta que no mueve inventario; el albarán es la constancia de una entrega ya ocurrida
que sí lo mueve. Ambos reutilizan el mismo patrón de línea (artículo, cantidad, precio) pero no se
mezclan: viven en tablas y vistas separadas, con ciclos de vida distintos.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Generar un albarán desde un presupuesto aceptado, con entrega parcial (Priority: P1)

Un comercial tiene un presupuesto aceptado con varias líneas de artículos. En vez de entregar todo
de una vez, entrega una parte ahora (por ejemplo, la mitad de las unidades de cada línea) y genera
un albarán con esas cantidades. Cuando confirma el albarán como "entregado", el stock de los
artículos entregados baja en ese momento. Más adelante, cuando entrega el resto, genera un segundo
albarán con las cantidades pendientes del mismo presupuesto.

**Why this priority**: Es el caso de uso central que motiva la feature — el motivo de que el
presupuesto y la factura, tal como existen hoy, no alcancen para representar entregas parciales de
una misma venta.

**Independent Test**: Crear un presupuesto aceptado con una línea de 100 unidades de un artículo
con gestión de stock; generar un albarán con 40 unidades y confirmarlo como entregado; verificar que
el stock del artículo baja en 40 unidades y que el presupuesto muestra 60 unidades pendientes de
entrega en esa línea; generar un segundo albarán con las 60 restantes y verificar que la línea del
presupuesto queda completamente entregada.

**Acceptance Scenarios**:

1. **Given** un presupuesto aceptado con líneas de artículos, **When** el comercial genera un
   albarán desde ese presupuesto con una cantidad igual o menor a la pendiente de cada línea,
   **Then** el albarán se crea en estado "borrador" con esas líneas y cantidades, vinculado al
   presupuesto de origen.
2. **Given** un albarán en borrador, **When** el comercial lo confirma como "entregado", **Then**
   el sistema genera un movimiento de stock de salida por cada línea de artículo con gestión de
   stock, y el albarán queda de solo lectura salvo por la acción de anular.
3. **Given** un presupuesto con una línea parcialmente entregada, **When** el comercial genera un
   nuevo albarán sobre ese mismo presupuesto, **Then** solo puede indicar, como máximo, la cantidad
   pendiente de entrega de cada línea (la ya entregada en albaranes previos no se puede repetir).
4. **Given** un presupuesto con todas sus líneas completamente entregadas, **When** se consulta,
   **Then** no ofrece la acción de generar un nuevo albarán sobre él.

---

### User Story 2 - Albarán directo a cliente, sin presupuesto previo (Priority: P2)

Un comercial necesita registrar la entrega de mercancía a un cliente sin que haya existido un
presupuesto previo (venta directa acordada de palabra o por otro canal). Da de alta un albarán
directamente desde la ficha del cliente, con sus propias líneas de artículos, y lo confirma como
entregado.

**Why this priority**: Cubre el caso de venta directa, común en pymes que no siempre cotizan antes
de entregar, pero es secundario respecto al flujo principal que sí pasa por presupuesto (US1).

**Independent Test**: Desde la ficha de un cliente existente, crear un albarán nuevo con líneas de
artículos sin ningún presupuesto de origen, confirmarlo como entregado y verificar que el stock baja
igual que en el caso derivado de un presupuesto.

**Acceptance Scenarios**:

1. **Given** un cliente existente, **When** el comercial crea un albarán directo desde su ficha con
   líneas de artículos, **Then** el albarán se guarda sin `presupuesto_id`, vinculado únicamente al
   cliente.
2. **Given** un albarán directo en borrador, **When** se confirma como entregado, **Then** se
   comporta igual que un albarán derivado de presupuesto en cuanto al movimiento de stock.

---

### User Story 3 - Consolidar varios albaranes en una única factura (Priority: P1)

Un comercial tiene varios albaranes entregados y aún no facturados de un mismo cliente (por ejemplo,
tres entregas a lo largo del mes). Al cierre del mes, los selecciona todos desde el listado de
albaranes y genera una única factura que agrupa las líneas de los tres, cada una identificada con el
albarán del que proviene.

**Why this priority**: Es el entregable de mayor valor de negocio de la feature — resuelve el caso
de "facturar mensualmente varias entregas sueltas" que ni el presupuesto ni la factura resuelven
hoy, y es el motivo original por el que se pidió esta feature.

**Independent Test**: Crear tres albaranes entregados del mismo cliente con distintas líneas de
artículos; seleccionarlos y convertirlos a factura; verificar que la factura resultante contiene
todas las líneas de los tres albaranes correctamente identificadas por su origen, que su total
coincide con la suma de los tres, y que no se generó ningún movimiento de stock adicional en la
conversión (el stock ya se movió al entregar cada albarán).

**Acceptance Scenarios**:

1. **Given** varios albaranes en estado "entregado", no facturados, del mismo cliente, **When** el
   comercial los selecciona y pide "Convertir a factura", **Then** se crea una única factura en
   estado borrador con las líneas de todos ellos, y cada albarán queda marcado como "facturado"
   (terminal, de solo lectura).
2. **Given** una selección de albaranes de **distintos** clientes, **When** el comercial intenta
   convertirlos juntos, **Then** el sistema rechaza la operación indicando que deben ser del mismo
   cliente.
3. **Given** una selección que incluye un albarán ya facturado o anulado, **When** se intenta
   convertir, **Then** el sistema excluye esos albaranes de la operación o la rechaza informando
   cuáles no son convertibles.
4. **Given** una factura generada a partir de albaranes, **When** se emite esa factura, **Then** el
   proceso de emisión NO genera un nuevo movimiento de stock por esas líneas (ya se movió al
   entregar los albaranes de origen).

---

### User Story 4 - Anular un albarán entregado antes de facturarlo (Priority: P3)

Un comercial confirmó por error un albarán como entregado (cantidad equivocada, cliente equivocado)
y necesita anularlo antes de que se facture, revirtiendo el efecto sobre el stock.

**Why this priority**: Es una red de seguridad operativa, no un flujo de negocio central; el sistema
ya sería utilizable sin ella (a costa de requerir un ajuste manual de stock aparte), pero mejora
notablemente la confianza en el módulo.

**Independent Test**: Confirmar un albarán como entregado (el stock baja), anularlo, y verificar que
el stock vuelve a su valor anterior mediante un movimiento de entrada trazado al albarán anulado.

**Acceptance Scenarios**:

1. **Given** un albarán en estado "entregado" y no facturado, **When** el comercial lo anula,
   **Then** el sistema genera un movimiento de stock de entrada que revierte exactamente las
   cantidades del albarán, y el albarán pasa a estado "anulado" (terminal).
2. **Given** un albarán ya facturado, **When** se intenta anular, **Then** el sistema lo rechaza:
   solo se anula desde "entregado" y antes de facturar.

---

### Edge Cases

- **Presupuesto con líneas que cambian de precio o se dan de baja tras generarse un albarán**: el
  albarán conserva las cantidades/importes con los que se generó; no relee el catálogo actual.
- **Intento de generar un albarán con una cantidad mayor a la pendiente de una línea del
  presupuesto**: el sistema lo rechaza informando la cantidad máxima disponible para esa línea.
- **Artículo sin gestión de stock (servicio o producto sin control de inventario) en una línea de
  albarán**: la línea se registra igual (concepto, cantidad, precio) pero no genera movimiento de
  stock, igual que ya ocurre hoy con las líneas de factura.
- **Presupuesto que se marca "facturado" directamente (sin pasar por albarán, como ya permite la
  feature 028)** convive sin conflicto con el flujo de albaranes: son dos caminos alternativos desde
  presupuesto aceptado hacia la factura, no exigen pasar siempre por albarán.
- **Anular un albarán cuyo presupuesto de origen ya está marcado como completamente entregado**: al
  anular, la cantidad de esa línea vuelve a quedar pendiente de entrega en el presupuesto.
- **Selección de albaranes a convertir con distinto régimen impositivo** (no debería ocurrir si son
  del mismo cliente/tenant, pero se valida igualmente): el sistema rechaza la conversión si detecta
  regímenes impositivos incompatibles entre los albaranes seleccionados.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir crear un albarán vinculado a un presupuesto **aceptado**,
  heredando cliente, receptor y líneas del presupuesto, con la cantidad de cada línea acotada a la
  cantidad pendiente de entrega de esa línea (inicialmente, la cantidad total del presupuesto).
- **FR-002**: El sistema DEBE permitir crear un albarán directamente contra un cliente, sin
  presupuesto de origen, con sus propias líneas de artículos (artículo, cantidad, precio,
  descuento, régimen impositivo), calculadas con la misma lógica de backend que presupuestos y
  facturas.
- **FR-003**: El sistema DEBE llevar, por cada línea de presupuesto, la cantidad ya entregada
  acumulada en albaranes generados a partir de ella, y DEBE impedir generar un albarán que supere la
  cantidad pendiente de cualquiera de sus líneas.
- **FR-004**: El sistema DEBE gestionar el ciclo de vida del albarán con, como mínimo, los estados
  `borrador`, `entregado`, `facturado` y `anulado`, registrando autor y fecha de cada transición.
- **FR-005**: Al confirmar un albarán como `entregado`, el sistema DEBE generar un movimiento de
  stock de salida por cada línea cuyo artículo tenga gestión de stock activa, trazado a ese albarán.
- **FR-006**: El sistema DEBE permitir anular un albarán que esté en estado `entregado` y no
  facturado, generando el movimiento de stock inverso (entrada) que revierte exactamente las
  cantidades entregadas, y devolviendo esas cantidades al saldo pendiente de su presupuesto de
  origen si lo tiene.
- **FR-007**: El sistema NO DEBE permitir anular un albarán ya `facturado`.
- **FR-008**: El sistema DEBE permitir seleccionar varios albaranes en estado `entregado`, no
  facturados, **del mismo cliente**, y convertirlos en una única factura en estado borrador, con las
  líneas de todos ellos identificadas por el albarán del que provienen.
- **FR-009**: El sistema DEBE rechazar la conversión a factura si los albaranes seleccionados
  pertenecen a distintos clientes, o si alguno no está en estado `entregado`/no facturado.
- **FR-010**: Al convertir albaranes en factura, el sistema NO DEBE generar un nuevo movimiento de
  stock por esas líneas (el movimiento ya ocurrió al entregar cada albarán); el proceso de emisión
  de esa factura DEBE omitir el movimiento de stock para las líneas que provienen de albaranes.
- **FR-011**: Tras convertirse en factura, cada albarán involucrado DEBE quedar en estado
  `facturado`, terminal y de solo lectura, enlazado a la factura resultante.
- **FR-012**: El sistema DEBE mostrar un listado propio de albaranes, con cards informativas (total
  de albaranes, pendientes de facturar, entregados) y una tabla con selección múltiple de filas para
  la conversión a factura.
- **FR-013**: El acceso al módulo de albaranes DEBE respetar el sistema de roles y permisos por
  tenant existente (feature 027), con un permiso propio para ver/gestionar albaranes.
- **FR-014**: Albaranes y sus líneas DEBEN llevar `tenant_id` indexado y estar cubiertos por el
  global scope de tenant; ninguna consulta puede cruzar tenants.

### Key Entities *(include if feature involves data)*

- **Albarán**: documento de entrega no fiscal, con `tenant_id`, vínculo opcional a un presupuesto de
  origen, vínculo a un cliente, estado del ciclo de vida (`borrador`, `entregado`, `facturado`,
  `anulado`), fecha de entrega, y enlace a la factura resultante si se facturó.
- **Línea de albarán**: artículo, cantidad, precio, descuento, régimen impositivo e importes,
  espejo de línea de presupuesto/factura, con vínculo opcional a la línea de presupuesto de la que
  proviene (para el seguimiento de cantidad pendiente de entrega).
- **Movimiento de stock por albarán**: extensión del histórico de movimientos de stock existente,
  ahora trazable también a un albarán (además de a facturas y compras), tanto para la salida al
  entregar como para la entrada al anular.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un comercial puede entregar un presupuesto en dos o más albaranes parciales sin que la
  suma de cantidades entregadas pueda superar nunca la cantidad original de cada línea.
- **SC-002**: El stock de un artículo con gestión de stock baja exactamente en el momento en que su
  albarán se confirma como entregado, no antes ni al facturar.
- **SC-003**: Convertir N albaranes entregados del mismo cliente en una factura reproduce el 100% de
  sus líneas e importes sin reintroducción manual, y no duplica ningún movimiento de stock.
- **SC-004**: Ningún albarán se puede facturar dos veces ni anular después de facturado (0 casos en
  pruebas de concurrencia).
- **SC-005**: Ningún albarán de un tenant es accesible desde otro tenant (tests de no-fuga con ≥2
  tenants).

## Assumptions

- **Reutilización del motor de líneas/importes**: igual que el presupuesto, el albarán reutiliza la
  lógica de cálculo de bases/impuestos ya existente; no la duplica.
- **Sin firma de conformidad del receptor en esta versión**: la confirmación de entrega la registra
  un usuario interno del tenant (quien marca el albarán como "entregado"), no el cliente. Una firma
  digital o registro de conformidad del receptor queda fuera de alcance de esta versión (posible
  mejora futura).
- **Sin "pedido de venta" como entidad intermedia**: el albarán puede originarse directamente de un
  presupuesto aceptado o de un cliente; no se introduce una entidad "Pedido" separada en esta
  versión.
- **Un presupuesto puede seguir facturándose directamente** (camino ya existente de la feature 028)
  sin pasar por albarán; el albarán es un camino alternativo/adicional, no obligatorio.
- **Hosting compartido**: confirmar un albarán, anularlo y convertir varios en factura son
  operaciones síncronas puntuales, viables en hosting compartido (Principio V), sin colas
  persistentes ni workers dedicados.
