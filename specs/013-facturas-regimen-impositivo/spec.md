# Feature Specification: Formulario de factura adaptado al régimen impositivo del tenant

**Feature Branch**: `013-facturas-regimen-impositivo`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Adaptar el formulario de emisión de facturas al régimen impositivo del tenant (IVA/IGIC/IPSI). El backend, cálculo, validación, modelo y PDF ya son agnósticos al régimen, y el super admin ya asigna régimen a cada tenant. El hueco es el frontend de creación de factura (create.blade.php y facturas-form.js), hardcodeado a IVA. Objetivo: tipos válidos por régimen, etiquetas IVA/IGIC/IPSI dinámicas, recargo de equivalencia solo en IVA, y que el POS/ticket respete lo mismo. No incluye Verifactu."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Emisión de factura en un tenant de Canarias (IGIC) (Priority: P1)

Una empresa emisora de Canarias (régimen IGIC) crea una factura ordinaria. El formulario de
emisión debe ofrecerle **los tipos impositivos propios del IGIC** (0, 3, 7, 9,5, 15, 20 %) en
lugar de los del IVA, etiquetar la columna de impuesto como **"IGIC %"**, y calcular la
previsualización con esos tipos. Al guardar/emitir, la factura queda con `regimen_impositivo = igic`
congelado y su desglose muestra "IGIC 7%" en el PDF.

**Why this priority**: Es el motivo directo de la feature — hay clientes reales en Canarias que
hoy no pueden facturar correctamente porque el formulario solo permite tipos de IVA. Sin esto,
el producto es inutilizable para ese segmento.

**Independent Test**: Configurar un tenant con `regimen_impositivo = igic`, abrir el formulario de
nueva factura, verificar que los tipos ofrecidos son los del IGIC y que la etiqueta dice "IGIC",
crear una línea al 7 %, emitir y comprobar que el desglose e importes son correctos y sin recargo.

**Acceptance Scenarios**:

1. **Given** un tenant con régimen IGIC, **When** el usuario abre el formulario de nueva factura,
   **Then** el selector de tipo impositivo por línea ofrece únicamente 0, 3, 7, 9,5, 15 y 20 %, y
   la cabecera de la columna dice "IGIC %".
2. **Given** un tenant con régimen IGIC y una línea al 7 %, **When** el usuario emite la factura,
   **Then** el backend acepta el tipo, la factura se guarda con `regimen_impositivo = igic` y el
   desglose de impuestos aparece como "IGIC 7%".
3. **Given** un tenant con régimen IGIC, **When** el usuario intenta emitir con un tipo no válido
   para IGIC (p. ej. 21 %), **Then** la validación de backend lo rechaza con un mensaje claro.

---

### User Story 2 - El recargo de equivalencia no aparece fuera de IVA (Priority: P1)

En un tenant IGIC o IPSI, el recargo de equivalencia (que es un concepto exclusivo del régimen
IVA) **no debe mostrarse ni calcularse** en el formulario ni en la previsualización, aunque el
cliente esté marcado como minorista. En un tenant IVA con cliente en recargo, sí debe seguir
mostrándose y calculándose como hasta ahora.

**Why this priority**: Mostrar o calcular un recargo inexistente en Canarias/Ceuta/Melilla es un
error de cumplimiento normativo visible al usuario y al receptor de la factura.

**Independent Test**: Con un tenant IGIC y un cliente marcado como en recargo, abrir el formulario
y confirmar que no aparece ninguna columna/importe de recargo; repetir con un tenant IVA y
confirmar que sí aparece.

**Acceptance Scenarios**:

1. **Given** un tenant con régimen IGIC o IPSI, **When** se añade una línea (con cualquier cliente),
   **Then** no se muestra ni se suma ningún importe de recargo de equivalencia.
2. **Given** un tenant con régimen IVA y un cliente en recargo de equivalencia, **When** se añade
   una línea al 21 %, **Then** la previsualización muestra el recargo del 5,2 % como hasta ahora.

---

### User Story 3 - Ticket/POS simplificado respeta el régimen (Priority: P2)

El flujo del POS / ticket simplificado usa la misma lógica: ofrece los tipos del régimen del
tenant, etiqueta el impuesto correctamente y no muestra recargo fuera de IVA.

**Why this priority**: Coherencia funcional; un tenant de Canarias que use el POS debe ver IGIC
igual que en la factura ordinaria. Es secundaria porque comparte la corrección de la P1 y se
verifica una vez esta está resuelta.

**Independent Test**: Con un tenant IGIC, abrir el POS, comprobar que los tipos y la etiqueta son
de IGIC y emitir un ticket correctamente.

**Acceptance Scenarios**:

1. **Given** un tenant con régimen IGIC, **When** el usuario emite un ticket desde el POS,
   **Then** los tipos ofrecidos y el desglose corresponden al IGIC.

---

### User Story 4 - IPSI (Ceuta/Melilla) con entrada de tipo libre (Priority: P3)

Un tenant con régimen IPSI puede introducir el porcentaje de impuesto **libremente** (0–100),
porque Ceuta y Melilla tienen tipos propios por ciudad que no forman una lista cerrada. La
etiqueta dice "IPSI %" y tampoco se muestra recargo.

**Why this priority**: Completa la cobertura de los tres regímenes territoriales; menor urgencia
porque los clientes actuales que motivan la feature son de Canarias (IGIC).

**Independent Test**: Con un tenant IPSI, abrir el formulario, comprobar que el campo de tipo
acepta un valor libre (p. ej. 4 %), que la etiqueta dice "IPSI" y que no hay recargo.

**Acceptance Scenarios**:

1. **Given** un tenant con régimen IPSI, **When** el usuario introduce un tipo impositivo libre,
   **Then** el formulario lo acepta, la etiqueta dice "IPSI %" y no se muestra recargo.

---

### Edge Cases

- **Factura en borrador emitida tras cambiar el régimen del tenant**: el régimen se congela en la
  factura al emitir; un borrador guardado antes de un cambio de régimen del tenant se recalcula
  con el régimen vigente al momento de emitir (el backend ya toma `tenant()->regimen_impositivo`).
- **Valor `old()` tras error de validación**: al repoblar el formulario, el tipo previamente
  elegido debe seguir seleccionado aunque provenga de una lista dependiente del régimen.
- **Tenant sin régimen configurado**: no debe ocurrir porque el alta de tenant lo exige como
  campo requerido; si por datos legados faltara, el sistema asume IVA como valor por defecto
  seguro y no rompe el formulario.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El formulario de creación de factura MUST ofrecer, para el tipo impositivo de cada
  línea, únicamente los tipos válidos del régimen impositivo del tenant activo: IVA (0, 4, 10, 21),
  IGIC (0, 3, 7, 9,5, 15, 20). Para IPSI MUST permitir entrada libre de porcentaje (0–100).
- **FR-002**: La cabecera de la columna de impuesto y cualquier etiqueta asociada MUST reflejar el
  nombre del régimen del tenant ("IVA %", "IGIC %" o "IPSI %") en vez de "IVA" fijo.
- **FR-003**: El valor por defecto de tipo impositivo de una línea nueva MUST corresponder al
  régimen del tenant (p. ej. 21 en IVA, 7 en IGIC) y no un 21 fijo.
- **FR-004**: La previsualización de importes en el formulario MUST calcular y mostrar el recargo de
  equivalencia SOLO cuando el régimen del tenant es IVA; en IGIC e IPSI no MUST mostrarse ni sumarse.
- **FR-005**: El flujo del POS / ticket simplificado MUST aplicar las mismas reglas (FR-001 a FR-004)
  usando el régimen del tenant.
- **FR-006**: Los cálculos definitivos de base, cuota, recargo, IRPF y total MUST seguir realizándose
  en el backend; el frontend es solo previsualización y no MUST ser la fuente de verdad del importe.
- **FR-007**: El backend MUST seguir rechazando tipos impositivos no válidos para el régimen del
  tenant (comportamiento ya existente vía la validación de tipo impositivo) y esa validación MUST
  mantenerse coherente con los tipos ofrecidos por el formulario.
- **FR-008**: La solución MUST respetar el aislamiento multi-tenant: el régimen aplicado es siempre
  el del tenant activo, sin posibilidad de que un tenant vea o use el régimen de otro.
- **FR-009**: El PDF y el desglose de impuestos de la factura MUST seguir mostrando el nombre del
  régimen correcto (ya soportado) sin regresiones.

### Key Entities *(include if feature involves data)*

- **Tenant**: portador del `regimen_impositivo` (IVA/IGIC/IPSI) que determina los tipos ofrecidos
  y las etiquetas del formulario. No se añaden campos nuevos.
- **Factura**: congela `regimen_impositivo` al emitir (comportamiento existente). No cambia el
  esquema.
- **Línea de factura**: porta el tipo impositivo elegido dentro del conjunto válido del régimen.

> Nota: esta feature **no introduce entidades ni columnas nuevas**. Es una adaptación de la capa de
> presentación (formulario de factura y POS) a un backend ya agnóstico al régimen.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un tenant con régimen IGIC puede completar y emitir una factura ordinaria de principio
  a fin viendo únicamente tipos de IGIC, sin ninguna referencia a "IVA" en el formulario ni en el
  resultado.
- **SC-002**: En un tenant IGIC o IPSI, el recargo de equivalencia no aparece en el 100 % de los
  formularios y previsualizaciones, incluso con cliente marcado como minorista.
- **SC-003**: En un tenant IVA, el comportamiento actual (tipos 0/4/10/21, recargo 5,2/1,4/0,5)
  permanece idéntico, sin regresiones.
- **SC-004**: El flujo POS/ticket muestra los mismos tipos y etiquetas que el formulario de factura
  para el mismo régimen.
- **SC-005**: Existe cobertura de pruebas automatizadas que verifica al menos: emisión correcta en
  IGIC, ausencia de recargo fuera de IVA, y aislamiento multi-tenant del régimen.

## Assumptions

- El super admin ya asigna un `regimen_impositivo` a cada tenant al crearlo/editarlo (campo
  requerido existente); no se añade una pantalla de configuración de régimen para el propio tenant
  en esta feature.
- Los tipos válidos por régimen y la lógica de cálculo/recargo ya existen y son correctos en el
  backend; esta feature los **expone** en el formulario, no los redefine.
- IPSI se trata como entrada libre por no existir una lista cerrada de tipos para Ceuta/Melilla;
  se acepta como comportamiento MVP.
- Verifactu queda explícitamente **fuera de alcance**; el terreno ya está preparado y se
  implementará en una feature posterior.
- No se modifican `docs/02-facturacion-espana.md` ni el modelo de datos, ya que la normativa de
  regímenes territoriales ya está documentada e implementada en backend.
