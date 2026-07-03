# Feature Specification: Facturas rectificativas (corregir una factura emitida)

**Feature Branch**: `009-facturas-rectificativas`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: "Facturas rectificativas: corregir una factura ordinaria ya emitida mediante una factura rectificativa. La rectificativa usa su propia serie con numeración correlativa sin huecos (independiente de la serie ordinaria), indica su condición de rectificativa, el motivo de la rectificación y la referencia a la(s) factura(s) rectificada(s). Modalidad por sustitución o por diferencias. Al emitir la rectificativa se le asigna número correlativo fiscal dentro de su serie rectificativa (reinicio anual como en 008), congela fecha de expedición y datos, y queda inmutable igual que una ordinaria emitida. La factura ordinaria rectificada queda marcada/vinculada como rectificada. Alcance: solo rectificar facturas ordinarias ya emitidas por la feature 008. Fuera de alcance: Verifactu real (hash/QR/XML/AEAT), simplificadas, ciclo B2B, CRUD de series, pagos y stock."

## Clarifications

### Session 2026-07-03

- Q: En la modalidad "por diferencias", ¿cómo se representan los importes de la rectificativa? → A: Delta auto-calculado — el usuario introduce los importes/líneas correctos y el sistema calcula automáticamente la diferencia (delta) respecto a la original; el delta puede ser negativo. La rectificativa persiste ese delta como sus totales.
- Q: ¿Se puede rectificar una factura que ya fue rectificada (encadenar varias rectificativas sobre la misma original)? → A: No, una sola vez — al emitir la rectificativa, la original pasa a estado "rectificada" y queda bloqueada para nuevas rectificativas.
- Q (heredada de 008): El contador correlativo se reinicia por año natural dentro de cada serie; el contador es por (serie, año). Aplica igual a la serie rectificativa.
- Q (heredada de 008): Al emitir se congela `fecha_expedicion = hoy` (fecha del acto de emisión). Aplica igual a la rectificativa.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear una rectificativa a partir de una factura emitida (Priority: P1)

Un usuario del tenant detecta un error en una factura ordinaria **ya emitida** (por ejemplo un
importe, un tipo impositivo o un dato del receptor incorrectos). Como la factura emitida es
inmutable, no puede editarla: en su lugar **crea una factura rectificativa** que la referencia.
Al crearla elige la **modalidad** (por sustitución o por diferencias) y escribe el **motivo** de la
rectificación. El sistema precarga en la rectificativa el snapshot del receptor y el régimen
impositivo de la factura original, y la deja en estado **borrador** (todavía sin número fiscal),
lista para ajustar sus líneas.

**Why this priority**: Es el núcleo de la feature. Sin poder crear el documento rectificativo
vinculado a la original, no existe forma legal de corregir una factura ya emitida (Principio II: la
factura emitida es inmutable; toda corrección se hace mediante rectificativa). Todo lo demás
(emisión numerada, marcado de la original) depende de que este documento exista.

**Independent Test**: Se puede probar tomando una factura ordinaria emitida, ejecutando la acción de
"rectificar", y verificando que se crea una nueva factura en borrador con `tipo = rectificativa`,
`es_rectificativa = true`, `factura_rectificada_id` apuntando a la original, `motivo_rectificacion`
y `tipo_rectificacion` (sustitución/diferencias) informados, el snapshot del receptor y el
`regimen_impositivo` copiados de la original, y sin número fiscal asignado todavía.

**Acceptance Scenarios**:

1. **Given** una factura ordinaria en estado "emitida", **When** el usuario crea una rectificativa
   sobre ella indicando modalidad y motivo, **Then** se crea una factura en estado "borrador" con
   `tipo = rectificativa`, vinculada a la original vía `factura_rectificada_id`, con el motivo y la
   modalidad registrados, y el snapshot del receptor y el régimen impositivo copiados de la original.
2. **Given** una factura que **no** está emitida (borrador), **When** el usuario intenta crear una
   rectificativa sobre ella, **Then** el sistema lo rechaza con un mensaje claro (solo se rectifican
   facturas emitidas) y no crea ningún documento.
3. **Given** una factura ordinaria emitida que **ya fue rectificada** (estado "rectificada"), **When**
   el usuario intenta crear otra rectificativa sobre ella, **Then** el sistema lo rechaza (una factura
   solo puede rectificarse una vez) y no crea ningún documento.
4. **Given** una rectificativa en borrador recién creada por diferencias, **When** el usuario ajusta
   las líneas con los importes correctos, **Then** el sistema calcula y persiste como totales de la
   rectificativa el **delta** (diferencia) respecto a los totales de la original, admitiendo valores
   negativos cuando la corrección reduce importes.

---

### User Story 2 - Emitir la rectificativa con su propia numeración fiscal (Priority: P1)

Una vez revisada la rectificativa en borrador, el usuario la **emite**. Al hacerlo, el sistema le
asigna el siguiente número correlativo **de la serie rectificativa** (independiente y separada de la
serie ordinaria), congela la fecha de expedición y los datos, la deja en estado "emitida" e
inmutable, y **marca la factura original como "rectificada"** dejando la vinculación entre ambas.

**Why this priority**: Sin la emisión numerada en serie separada, la rectificativa no tiene validez
fiscal (Principio II: las rectificativas llevan serie separada obligatoriamente y numeración
correlativa sin huecos). Es tan crítica como US1: juntas forman el ciclo mínimo de corrección.

**Independent Test**: Tomar una rectificativa en borrador válida, emitirla, y verificar que (a)
recibe el siguiente correlativo de la serie rectificativa (no de la ordinaria), (b) su
`numero_completo` usa el formato de la serie rectificativa (prefijo "R"), (c) pasa a "emitida" e
inmutable, (d) la factura original pasa a estado "rectificada", y (e) el contador de la serie
rectificativa avanza y se reinicia por año natural igual que la ordinaria.

**Acceptance Scenarios**:

1. **Given** una rectificativa en borrador vinculada a una factura emitida, **When** el usuario la
   emite, **Then** recibe el siguiente número correlativo de la **serie rectificativa** del tenant,
   un `numero_completo` con el formato de esa serie, pasa a estado "emitida" y queda inmutable.
2. **Given** una serie ordinaria y una serie rectificativa en el mismo tenant, **When** se emiten
   facturas de ambos tipos, **Then** cada una consume el contador de **su** serie: emitir una
   rectificativa no altera el próximo número de la ordinaria ni viceversa.
3. **Given** la emisión de una rectificativa, **When** se completa, **Then** la factura original pasa
   de "emitida" a "rectificada" y queda vinculada de forma bidireccional con la rectificativa.
4. **Given** dos rectificativas de la misma serie rectificativa, **When** se emiten una tras otra,
   **Then** reciben números correlativos consecutivos sin huecos ni duplicados.
5. **Given** una serie rectificativa cuya última factura fue del año anterior, **When** se emite la
   primera rectificativa del nuevo año, **Then** recibe el número 1 de ese año (reinicio anual).
6. **Given** una rectificativa recién emitida, **When** se consulta su historial de eventos, **Then**
   existe un evento append-only de tipo "emitida" con su fecha/hora, igual que en una ordinaria.

---

### User Story 3 - La rectificativa es visible, trazable e inmutable (Priority: P2)

La rectificativa emitida se comporta como cualquier factura emitida: su número fiscal completo
(serie rectificativa + número) aparece en el listado, la vista de detalle y el PDF; es inmutable (no
se edita ni se borra); y tanto la rectificativa como la original muestran su vínculo y su condición
(la original indica que fue rectificada y por qué documento; la rectificativa indica a qué factura
rectifica, con el motivo y la modalidad).

**Why this priority**: Da trazabilidad y cumplimiento visible (la rectificativa debe indicar su
condición de rectificativa, el motivo y la referencia a la original — Principio II / normativa). Es
valioso pero depende de que el documento exista y esté emitido (US1+US2).

**Independent Test**: Emitir una rectificativa y verificar que su número aparece en listado, detalle
y PDF; que el PDF/detalle muestra su condición de rectificativa, el motivo y la referencia a la
original; que la original enlaza a la rectificativa; y que intentar editar o borrar la rectificativa
emitida se rechaza.

**Acceptance Scenarios**:

1. **Given** una rectificativa emitida, **When** el usuario la ve en listado, detalle o PDF, **Then**
   ve su número completo (serie rectificativa + número), su condición de "rectificativa", el motivo y
   la referencia a la factura original rectificada.
2. **Given** una rectificativa emitida, **When** el usuario intenta editarla o eliminarla, **Then** el
   sistema lo rechaza y la rectificativa permanece intacta (misma inmutabilidad que una ordinaria
   emitida).
3. **Given** una factura original ya rectificada, **When** el usuario la ve en detalle, **Then** ve
   que está "rectificada" y un enlace/referencia a la rectificativa que la corrige.
4. **Given** una rectificativa por diferencias emitida, **When** se ve su detalle/PDF, **Then** los
   importes mostrados reflejan el delta (diferencia) respecto a la original, con signo cuando
   corresponde.

---

### Edge Cases

- **Original no emitida**: intentar rectificar una factura en borrador se rechaza; solo se rectifican
  facturas en estado "emitida".
- **Original ya rectificada**: una factura solo puede rectificarse una vez; un segundo intento se
  rechaza (la original ya está en estado "rectificada").
- **Falta la serie rectificativa**: si el tenant no tiene una serie rectificativa configurada, la
  creación/emisión no puede asignar número; el sistema usa la serie rectificativa por defecto del
  tenant (sembrada), sin exponer CRUD de series (fuera de alcance).
- **Concurrencia en la serie rectificativa**: dos emisiones simultáneas de rectificativas de la misma
  serie obtienen números distintos y consecutivos, sin huecos ni duplicados (mismo mecanismo de
  bloqueo que la ordinaria).
- **Aislamiento entre tenants**: la serie rectificativa y su numeración son independientes por
  tenant; rectificar en el tenant A nunca afecta la numeración ni expone facturas del tenant B; una
  rectificativa nunca puede referenciar una original de otro tenant.
- **Delta cero**: una rectificativa por diferencias cuyo delta resulta cero (no cambia importes, solo
  corrige un dato del receptor) se admite; el motivo documenta la corrección no monetaria.
- **Fallo a mitad de la emisión**: si algo falla al emitir, ni la rectificativa queda a medias con un
  número huérfano ni la original queda marcada "rectificada" sin rectificativa válida; o se completa
  todo (número + estados + evento + marcado de la original) o no cambia nada.

## Requirements *(mandatory)*

### Functional Requirements

**Creación de la rectificativa**

- **FR-001**: El sistema DEBE permitir crear una factura rectificativa únicamente a partir de una
  factura ordinaria en estado "emitida"; rechazar la creación si la original está en cualquier otro
  estado (borrador, anulada, o ya "rectificada").
- **FR-002**: Al crear la rectificativa, el sistema DEBE registrar su condición (`tipo =
  rectificativa`, `es_rectificativa = true`), la **referencia** a la factura original
  (`factura_rectificada_id`), el **motivo** de la rectificación y la **modalidad**
  (`tipo_rectificacion`: por sustitución o por diferencias).
- **FR-003**: Al crear la rectificativa, el sistema DEBE copiar desde la original el snapshot de los
  datos fiscales del receptor y el `regimen_impositivo`, dejándolos como punto de partida de la
  rectificativa (que luego es editable en borrador como cualquier factura).
- **FR-004**: La rectificativa recién creada DEBE quedar en estado "borrador", sin número fiscal
  asignado, y editable como cualquier borrador hasta que se emita.
- **FR-005**: Una factura solo puede rectificarse **una vez**: el sistema DEBE rechazar crear una
  rectificativa sobre una factura cuyo estado ya sea "rectificada".

**Modalidades e importes**

- **FR-006**: El sistema DEBE soportar las dos modalidades de rectificación:
  - **Por sustitución**: la rectificativa contiene los importes correctos completos que sustituyen a
    los de la original; sus totales son los totales corregidos.
  - **Por diferencias**: el usuario introduce los importes correctos y el sistema **calcula
    automáticamente el delta** (diferencia respecto a la original); los totales persistidos de la
    rectificativa son ese delta, que **puede ser negativo** cuando la corrección reduce importes.
- **FR-007**: Todos los cálculos de importes y del delta DEBEN realizarse en el backend a partir de
  las líneas (Principio III); el cliente nunca es fuente de verdad de un importe ni del delta.
- **FR-008**: El desglose de impuestos de la rectificativa DEBE seguir siendo **por tipo impositivo**
  y coherente con el `regimen_impositivo` congelado (IVA/IGIC/IPSI), igual que en una ordinaria.

**Emisión y numeración en serie separada**

- **FR-009**: Al emitir una rectificativa, el sistema DEBE asignarle el siguiente número correlativo
  de la **serie rectificativa** del tenant (serie separada de la ordinaria, con su propio prefijo,
  p. ej. "R"), garantizando numeración correlativa, sin huecos ni duplicados, incluso ante emisiones
  concurrentes.
- **FR-010**: La numeración de la serie rectificativa DEBE **reiniciarse a 1 al comienzo de cada año
  natural** (contador por combinación serie-año), igual que la serie ordinaria (heredado de 008).
- **FR-011**: La numeración de la serie rectificativa DEBE ser **independiente** de la ordinaria y de
  otros tenants (Principio I): emitir una rectificativa no altera el contador de la ordinaria ni el de
  ningún otro tenant.
- **FR-012**: Al emitir, el sistema DEBE fijar la `fecha_expedicion` de la rectificativa a la fecha
  del acto de emisión (hoy) y congelar los datos (receptor, régimen, totales), igual que una
  ordinaria; la asignación de número y el avance del contador DEBEN ser **atómicos**.
- **FR-013**: Al emitir la rectificativa, el sistema DEBE **marcar la factura original como
  "rectificada"** y mantener la vinculación bidireccional entre ambas; esta transición y la emisión
  DEBEN ocurrir en la misma unidad atómica (o todo o nada).

**Inmutabilidad y trazabilidad**

- **FR-014**: Una rectificativa en estado "emitida" DEBE ser inmutable: el sistema DEBE impedir
  editarla, actualizarla, eliminarla o re-emitirla, con las mismas garantías que una ordinaria
  emitida (heredado de 008).
- **FR-015**: Una factura en estado "rectificada" DEBE ser inmutable y NO DEBE poder rectificarse de
  nuevo ni volver a "emitida"; el sistema no ofrece acciones destructivas ni de re-rectificación
  sobre ella.
- **FR-016**: Al emitir la rectificativa, el sistema DEBE registrar un evento de emisión en el
  historial append-only de la factura (mismo mecanismo que la ordinaria).
- **FR-017**: El sistema DEBE mostrar en listado, detalle y PDF de la rectificativa emitida: su
  `numero_completo` (serie rectificativa + número), su condición de "rectificativa", el motivo y la
  referencia a la factura original; y en la original rectificada, la referencia a su rectificativa.

### Key Entities *(include if feature involves data)*

- **Factura rectificativa (nueva instancia sobre la tabla `facturas` existente)**: una factura con
  `tipo = rectificativa` y `es_rectificativa = true`. Usa las columnas de rectificativa del modelo de
  datos —`factura_rectificada_id`, `motivo_rectificacion`, `tipo_rectificacion`
  (sustitución/diferencias)— que en el estado actual del código **aún no existen en la tabla** y esta
  feature debe **añadir**. Comparte todo el ciclo borrador→emitida→inmutable con la ordinaria.
- **Factura original (existente)**: la factura ordinaria emitida que se corrige. Al emitir su
  rectificativa pasa de `estado = emitida` a `estado = rectificada` y queda vinculada a la
  rectificativa. No puede rectificarse más de una vez.
- **Serie rectificativa (existente en el modelo, a sembrar)**: serie con `tipo = rectificativa` y su
  propio formato/prefijo ("R"), con contador por año natural. El CRUD de series sigue fuera de
  alcance; se usa una serie rectificativa por defecto del tenant, sembrada igual que la ordinaria.
- **Evento de factura (existente, append-only)**: registra el evento "emitida" de la rectificativa y
  la transición de la original a "rectificada" (según se decida en plan) como base de auditoría.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las rectificativas emitidas reciben número de la **serie rectificativa**
  (nunca de la ordinaria), correlativo, sin huecos ni duplicados, con reinicio anual; emitir
  rectificativas nunca altera el próximo número de la serie ordinaria.
- **SC-002**: El 100% de las facturas originales quedan en estado "rectificada" y vinculadas a su
  rectificativa tras emitir esta; ninguna factura puede rectificarse más de una vez (2.º intento
  siempre rechazado).
- **SC-003**: Solo se pueden rectificar facturas en estado "emitida": el 100% de los intentos de
  rectificar borradores u otras facturas no emitidas son rechazados.
- **SC-004**: En modalidad por diferencias, los totales persistidos de la rectificativa coinciden en
  el 100% de los casos con el delta calculado (correcto − original), admitiendo negativos; en
  sustitución coinciden con los importes corregidos completos.
- **SC-005**: Ninguna rectificativa emitida puede editarse, eliminarse ni re-emitirse: el 100% de
  esos intentos se rechazan y la rectificativa permanece intacta.
- **SC-006**: La numeración de rectificativas de cada tenant es independiente: 0 fugas entre tenants
  en las pruebas de aislamiento (una rectificativa nunca referencia ni expone una original de otro
  tenant).
- **SC-007**: Toda rectificativa emitida muestra en detalle/PDF su condición, motivo y referencia a la
  original; toda original rectificada muestra la referencia a su rectificativa.

## Assumptions

- La transición borrador→emitida, la numeración por (serie, año) con bloqueo, el candado de
  inmutabilidad y el registro append-only de eventos ya existen (feature 008): esta feature los
  **reutiliza** para el `tipo = rectificativa` en vez de reimplementarlos.
- Las columnas de rectificativa (`es_rectificativa`, `factura_rectificada_id`,
  `motivo_rectificacion`, `tipo_rectificacion`) están documentadas en `docs/03-modelo-datos.md` pero
  **todavía no están en la migración `facturas`**; esta feature las añade vía migración nueva
  (aditiva, sin romper datos existentes).
- El tenant dispone de una **serie rectificativa por defecto** (prefijo "R", formato análogo a la
  ordinaria) **sembrada** por el sistema; el CRUD de series queda fuera de alcance (se añade en una
  feature posterior). Si más adelante hay varias series rectificativas, la elección de serie se
  resolverá entonces.
- La rectificativa se crea siempre **referenciando una única** factura original (rectificación 1:1).
  Rectificar varias facturas con un solo documento queda fuera de alcance por ahora.
- La `fecha_expedicion` de la rectificativa se fija a la fecha del acto de emisión (hoy), igual que en
  la ordinaria (heredado de 008); una vez emitida queda congelada.
- "Verifactu real" (huella/hash encadenado, QR, XML, envío AEAT) sigue **fuera de alcance**; las
  columnas correspondientes siguen reservadas y nulas, también para las rectificativas. El evento de
  emisión se registra append-only sin calcular huella.
- Quedan fuera de alcance: facturas simplificadas, el ciclo comercial B2B, el CRUD de series, los
  pagos/cobros, los movimientos de stock, y la anulación de facturas (distinta de la rectificación).
- Todos los cálculos e importes (incluido el delta por diferencias) permanecen en el backend
  (Principio III); el cliente nunca es fuente de verdad de un importe ni del número asignado.
