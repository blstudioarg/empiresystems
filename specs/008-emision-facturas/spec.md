# Feature Specification: Emisión de facturas (borrador → emitida)

**Feature Branch**: `008-emision-facturas`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: "Emisión de facturas: transición de borrador a emitida. Al emitir una factura ordinaria en estado borrador, el sistema asigna el número correlativo fiscal dentro de su serie (sin huecos ni duplicados, seguro ante concurrencia), congela la fecha de expedición y los datos del emisor/cliente/régimen, y pasa la factura a estado emitida. Una factura emitida es inmutable (no se puede editar ni borrar; correcciones vía rectificativa en feature posterior). El listado, vista y PDF muestran el número fiscal completo (serie + número) en lugar de Borrador. Alcance: solo facturas ordinarias ya soportadas por la feature 005. Fuera de alcance: Verifactu real (hash/QR/XML/AEAT), simplificadas, rectificativas, ciclo B2B, CRUD de series, pagos y stock."

## Clarifications

### Session 2026-07-03

- Q: ¿El contador correlativo se reinicia cada año natural o es continuo de por vida de la serie? → A: Reinicia por año — el correlativo vuelve a 1 el 1 de enero de cada año dentro de cada serie; el contador es por (serie, año).
- Q: Al emitir, ¿qué fecha de expedición se congela: la del borrador o la del acto de emisión (hoy)? → A: Fecha de emisión (hoy) — se fija `fecha_expedicion = hoy` al emitir, sobrescribiendo la del borrador, para que el orden de números correlativos coincida con el orden cronológico.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Emitir una factura en borrador (Priority: P1)

Un usuario del tenant que ha creado una factura ordinaria en estado borrador la revisa y la
**emite**. Al hacerlo, el sistema le asigna el siguiente número correlativo de su serie, fija la
fecha de expedición, congela los datos que identifican la operación (emisor, cliente, régimen
impositivo y totales ya calculados) y la deja en estado "emitida". A partir de ese momento la
factura tiene un número fiscal legible y ya no es un borrador.

**Why this priority**: Es el corazón de la feature y del producto: sin la transición a "emitida"
con numeración fiscal, las facturas nunca dejan de ser borradores y el CRM no cumple su función
legal de emitir facturas. Todo lo demás (inmutabilidad, visualización) depende de que esta
transición exista.

**Independent Test**: Se puede probar de punta a punta tomando una factura borrador válida,
ejecutando la acción de emitir, y verificando que (a) recibe el siguiente número correlativo de su
serie, (b) su estado pasa a "emitida", (c) queda con fecha de expedición fijada y datos congelados,
y (d) los totales persistidos no cambian respecto al borrador.

**Acceptance Scenarios**:

1. **Given** un tenant con una serie ordinaria cuyo próximo número es N y una factura borrador
   válida (con cliente identificado y al menos una línea con importe), **When** el usuario emite la
   factura, **Then** la factura pasa a estado "emitida", recibe `numero = N`, un `numero_completo`
   legible según el formato de la serie, y el próximo número de la serie pasa a N+1.
2. **Given** dos facturas borrador de la misma serie, **When** se emiten una tras otra, **Then**
   reciben números correlativos consecutivos sin huecos ni duplicados (N y N+1).
3. **Given** una factura borrador, **When** se emite, **Then** la fecha de expedición queda fijada a
   la fecha del acto de emisión (hoy), sobrescribiendo la del borrador, y se congela junto con el
   régimen impositivo, los datos del emisor y los datos fiscales del cliente.
4. **Given** una factura recién emitida, **When** se consultan sus totales (base, cuotas por tipo,
   recargo, IRPF, total), **Then** coinciden exactamente con los que tenía como borrador (la emisión
   no recalcula ni altera importes).
5. **Given** una serie cuya última factura del año anterior fue el número M, **When** se emite la
   primera factura de esa serie en el nuevo año, **Then** recibe el número 1 de ese año (el contador
   se reinició), no M+1.

---

### User Story 2 - Una factura emitida es inmutable (Priority: P2)

Una vez emitida, la factura no puede editarse ni borrarse: cualquier corrección posterior deberá
hacerse mediante una factura rectificativa (feature posterior). El sistema impide editar, actualizar
o eliminar una factura que no esté en estado borrador, y no ofrece esas acciones en la interfaz para
facturas emitidas.

**Why this priority**: Es un requisito normativo (Principio II de la constitución: la factura
emitida es inmutable) y protege la integridad de la numeración correlativa y de la futura cadena
Verifactu. Sin este candado, la feature de emisión sería insegura y no conforme.

**Independent Test**: Con una factura ya emitida, intentar editarla, actualizarla o eliminarla
(por interfaz y por petición directa) y verificar que el sistema lo rechaza y la factura permanece
intacta; y que la acción de emitir tampoco se ofrece dos veces sobre la misma factura.

**Acceptance Scenarios**:

1. **Given** una factura en estado "emitida", **When** el usuario intenta editarla o guardar cambios,
   **Then** el sistema rechaza la operación y la factura permanece sin cambios.
2. **Given** una factura en estado "emitida", **When** el usuario intenta eliminarla, **Then** el
   sistema rechaza el borrado y la factura permanece.
3. **Given** una factura ya emitida, **When** se intenta volver a emitirla, **Then** el sistema
   rechaza la re-emisión (no consume otro número correlativo) y la factura conserva su número.
4. **Given** una factura emitida, **When** se listan las acciones disponibles en la interfaz,
   **Then** solo se ofrecen acciones no destructivas (ver, PDF) y no editar/eliminar/emitir.

---

### User Story 3 - El número fiscal es visible y la emisión queda registrada (Priority: P3)

Tras emitir, el número fiscal completo (serie + número) aparece en el listado de facturas, en la
vista de detalle y en el PDF, en lugar de la etiqueta "Borrador". Además, el acto de emisión queda
registrado en el historial de eventos de la factura, como base para la futura auditoría y el
encadenamiento Verifactu.

**Why this priority**: Da visibilidad y trazabilidad al resultado de la emisión. Es valioso pero
depende de que exista la transición (US1); sin US1 no hay número que mostrar ni evento que registrar.

**Independent Test**: Emitir una factura y verificar que su número completo aparece en listado,
detalle y PDF; y que existe un evento de "emitida" asociado a la factura con su fecha.

**Acceptance Scenarios**:

1. **Given** una factura emitida, **When** el usuario la ve en el listado, la vista de detalle o el
   PDF, **Then** ve el número completo legible (serie + número) en lugar de "Borrador".
2. **Given** una factura que se acaba de emitir, **When** se consulta su historial de eventos,
   **Then** existe un evento de tipo "emitida" con la fecha/hora del acto y no se puede alterar ni
   borrar ese evento (registro append-only).
3. **Given** una factura todavía en borrador, **When** se ve en listado/detalle/PDF, **Then** sigue
   mostrando "Borrador" (sin número) — el cambio de identificador solo ocurre al emitir.

---

### Edge Cases

- **Borrador incompleto**: al intentar emitir una factura sin líneas, sin cliente identificado, o
  con datos fiscales del cliente insuficientes para una factura ordinaria (falta NIF, nombre o
  domicilio del receptor), el sistema rechaza la emisión con un mensaje claro y la factura sigue en
  borrador sin consumir número.
- **Concurrencia en la misma serie**: si dos emisiones de la misma serie ocurren a la vez, cada una
  obtiene un número distinto y consecutivo; nunca se asigna el mismo número dos veces ni se salta uno.
- **Aislamiento entre tenants**: la numeración de un tenant es independiente de la de otro; emitir en
  el tenant A no afecta el próximo número del tenant B, y un usuario nunca puede emitir ni ver
  facturas de otro tenant.
- **Fallo a mitad de la emisión**: si algo falla durante la emisión (p. ej. al persistir), la
  factura no queda en un estado intermedio inconsistente ni consume un número que luego quede
  huérfano; o se emite completamente o no se emite.
- **Re-emisión**: intentar emitir una factura que ya no está en borrador no produce efecto y se
  informa al usuario.

## Requirements *(mandatory)*

### Functional Requirements

**Transición y numeración**

- **FR-001**: El sistema DEBE permitir emitir una factura únicamente si está en estado "borrador";
  cualquier otro estado de partida rechaza la emisión.
- **FR-002**: Al emitir, el sistema DEBE asignar a la factura el siguiente número correlativo de su
  serie **para el año en curso** y avanzar el contador correspondiente, garantizando numeración
  **correlativa, sin huecos ni duplicados**, incluso ante emisiones concurrentes de la misma serie.
- **FR-002a**: La numeración correlativa DEBE **reiniciarse a 1 al comienzo de cada año natural**
  dentro de cada serie: el contador es por combinación (serie, año). La primera factura de una serie
  en un año recibe el número 1 de ese año, independientemente de los números del año anterior.
- **FR-003**: La asignación del número y el avance del contador de la serie DEBEN ser atómicos: o la
  factura queda emitida con su número y el contador avanzado, o no cambia nada (sin números
  huérfanos ni estados intermedios).
- **FR-004**: Al emitir, el sistema DEBE generar el `numero_completo` legible de la factura a partir
  del formato definido en su serie (p. ej. serie + año + número), y persistirlo.
- **FR-005**: La numeración DEBE ser independiente por tenant y por serie (Principio I): el contador
  de una serie de un tenant no interfiere con las de otros tenants ni con otras series.

**Congelado e inmutabilidad**

- **FR-006**: Al emitir, el sistema DEBE fijar la fecha de expedición de la factura a la **fecha del
  acto de emisión (hoy)**, sobrescribiendo cualquier valor previo del borrador, y congelarla junto
  con el régimen impositivo, los datos identificativos del emisor y los datos fiscales del cliente
  vigentes en ese momento. El año de esa fecha determina el contador correlativo aplicable (FR-002a).
- **FR-007**: La emisión NO DEBE recalcular ni modificar los importes de la factura: los totales
  (base, cuotas por tipo, recargo, IRPF, total) persistidos como borrador se conservan tal cual.
- **FR-008**: Una factura en estado "emitida" DEBE ser inmutable: el sistema DEBE impedir editarla,
  actualizarla o eliminarla, tanto desde la interfaz como ante peticiones directas.
- **FR-009**: El sistema NO DEBE ofrecer en la interfaz acciones de editar, eliminar ni re-emitir
  sobre facturas que no estén en borrador; para ellas solo se ofrecen acciones no destructivas
  (ver, PDF).
- **FR-010**: El sistema DEBE rechazar cualquier intento de emitir una factura que ya haya sido
  emitida, sin consumir un nuevo número.

**Validaciones previas a emitir**

- **FR-011**: El sistema DEBE rechazar la emisión de una factura ordinaria si no tiene al menos una
  línea con importe, o si el cliente/receptor no está identificado con los datos fiscales mínimos de
  una factura completa (NIF, nombre/razón social y domicilio), informando el motivo y dejando la
  factura en borrador.

**Visibilidad y trazabilidad**

- **FR-012**: El sistema DEBE mostrar el `numero_completo` (serie + número) de una factura emitida en
  el listado, la vista de detalle y el PDF; mientras la factura sea borrador DEBE seguir mostrando
  "Borrador" sin número.
- **FR-013**: Al emitir, el sistema DEBE registrar un evento de emisión en el historial de eventos de
  la factura, con su fecha/hora, en un registro **append-only** que no se edita ni se borra.

### Key Entities *(include if feature involves data)*

- **Factura (cabecera, existente)**: adquiere en esta feature la transición borrador → emitida. Pasan
  a estar poblados: `estado = emitida`, `numero`, `numero_completo`, `fecha_expedicion` congelada, y
  el `regimen_impositivo` congelado. Los importes ya existían del borrador y no cambian. Las columnas
  de Verifactu/ciclo B2B siguen reservadas y sin usar en esta feature.
- **Serie (existente)**: aporta el formato del número completo y el contador de próximo número, que
  es **por año natural** (se reinicia a 1 cada 1 de enero). Al emitir, avanza el contador del año en
  curso. El CRUD de series sigue fuera de alcance; se usa la serie ordinaria por defecto del tenant.
- **Evento de factura (historial, existente/reservado)**: registro append-only de operaciones sobre
  la factura; en esta feature se añade el evento "emitida". Base para la futura auditoría y
  encadenamiento Verifactu.
- **Cliente (existente)**: aporta los datos fiscales del receptor que se validan al emitir y se
  congelan en la factura.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las facturas emitidas dentro de una misma serie y año tienen números
  correlativos consecutivos empezando en 1, sin huecos ni duplicados, incluso emitiendo varias en
  paralelo; el contador se reinicia a 1 en cada nuevo año natural.
- **SC-002**: Ninguna factura emitida puede editarse, eliminarse ni re-emitirse: el 100% de esos
  intentos son rechazados y la factura permanece intacta con su número original.
- **SC-003**: Los totales de una factura tras emitir coinciden en el 100% de los casos con los que
  tenía como borrador (la emisión no altera importes).
- **SC-004**: El número fiscal completo (serie + número) es visible en listado, detalle y PDF de
  toda factura emitida; ninguna factura emitida se muestra como "Borrador".
- **SC-005**: La numeración de cada tenant es independiente: emitir en un tenant nunca cambia el
  próximo número ni expone facturas de otro tenant (0 fugas entre tenants en las pruebas de
  aislamiento).
- **SC-006**: Toda factura emitida tiene exactamente un evento de "emitida" registrado con su
  fecha/hora.

## Assumptions

- La factura, la serie ordinaria por defecto, el modelo de líneas y el cálculo de importes en
  backend ya existen (feature 005-facturas); esta feature reutiliza esas piezas y solo implementa la
  transición de estado, la numeración efectiva y el candado de inmutabilidad.
- La **fecha de expedición** de la factura se fija a la fecha del acto de emisión (hoy) al emitir,
  sobrescribiendo la del borrador; una vez emitida queda congelada. La numeración correlativa se
  asigna en el orden en que se emiten las facturas y se reinicia por año natural dentro de cada serie.
- La emisión es una acción explícita del usuario sobre una factura borrador concreta (no un proceso
  masivo ni automático).
- "Verifactu real" (cálculo de huella/hash encadenado, QR, exportación XML y envío a la AEAT) queda
  **fuera de alcance**; las columnas correspondientes siguen reservadas y nulas. El registro del
  evento de "emitida" se hace de forma append-only para no bloquear el diseño de ese encadenamiento
  posterior, pero sin calcular la huella.
- Quedan fuera de alcance: facturas simplificadas y rectificativas, el ciclo comercial B2B
  (aceptada/rechazada/pagada), el CRUD de series, los pagos/cobros y los movimientos de stock.
- Todos los cálculos e importes permanecen en el backend (Principio III); el cliente nunca es fuente
  de verdad de un importe ni del número asignado.
