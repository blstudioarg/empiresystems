# Feature Specification: Importación conversacional con el asistente

**Feature Branch**: `046-importacion-conversacional-asistente`

**Created**: 2026-09-07

**Status**: Draft

**Input**: User description: "Si le digo que necesito importar registros, que me empiece a ayudar: que pida documentos (Excel, PDF, foto, texto, lo que sea), haga un análisis y me vaya corrigiendo —por ejemplo, «3 clientes de las capturas que me mandaste no tienen el NIF, que es obligatorio, ¿querés darme esa info?»—; que se comprometa en el proceso y junto al usuario haga una importación exitosa y rápida. Y que haya sugerencias destacadas de prompt."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Importar desde un fichero hablando con el asistente (Priority: P1)

Una persona le dice al asistente que necesita dar de alta un montón de clientes. El asistente le pide
el fichero, ella lo adjunta en el propio chat, y en unos segundos recibe un diagnóstico concreto:
cuántos registros se leyeron, cuántos entrarían tal cual y qué le falta a los que no. Puede abrir una
tabla con todos los campos para revisarlos. Confirma y quedan importados.

**Why this priority**: es el corazón de la petición. Hoy la importación existe pero vive en una
pantalla aparte que hay que conocer, exige una plantilla concreta y devuelve una lista de rechazos
que la persona tiene que arreglar sola, a mano, fuera de la app.

**Independent Test**: adjuntar un Excel de clientes en el chat, comprobar que el asistente reporta
válidos y rechazados con motivo, confirmar, y verificar que los válidos están en la base de datos.

**Acceptance Scenarios**:

1. **Given** una persona en el chat, **When** dice que quiere importar clientes, **Then** el
   asistente le explica qué puede recibir y le pide el fichero.
2. **Given** un fichero adjuntado, **When** el asistente lo analiza, **Then** responde con cuántos
   registros leyó, cuántos son válidos y cuáles no, cada uno con su motivo concreto.
3. **Given** un análisis con registros válidos, **When** la persona confirma, **Then** esos registros
   quedan creados y se le informa del resultado.
4. **Given** una propuesta de importación, **When** la persona abre el detalle, **Then** ve una tabla
   con **todos** los campos de cada registro y el estado del análisis.
5. **Given** un fichero de un módulo no importable (facturas), **When** lo adjunta, **Then** el
   asistente lo rechaza explicando que ese módulo no se importa, sin intentarlo.

---

### User Story 2 - Que el asistente me pida lo que falta y lo corrija conmigo (Priority: P1)

El análisis dice que a tres registros les falta el NIF, que es obligatorio. En vez de dejar a la
persona arreglando el fichero por su cuenta, el asistente le dice exactamente cuáles son y le
pregunta si quiere dárselos. Ella responde en el chat ("el de Acme es B12345678, los otros dos
déjalos fuera"), el asistente aplica las correcciones y vuelve a analizar. Se repite hasta que la
importación está lista.

**Why this priority**: es lo que diferencia esto de la pantalla de importación que ya existe. Sin
esta historia, el asistente sería un lector de ficheros con pasos extra. Va como P1 junto a la US1
porque, por separado, la primera entrega poco más de lo que ya hay.

**Independent Test**: adjuntar un fichero con registros incompletos, responder en el chat los datos
que faltan, y comprobar que el nuevo análisis los da por válidos sin volver a subir nada.

**Acceptance Scenarios**:

1. **Given** un análisis con registros rechazados por un dato obligatorio ausente, **When** el
   asistente responde, **Then** nombra los registros afectados y pide ese dato concreto.
2. **Given** una petición de datos, **When** la persona los da en el chat, **Then** el asistente los
   aplica y presenta un análisis actualizado, sin pedir que se vuelva a subir el fichero.
3. **Given** un registro que la persona decide descartar, **When** lo dice, **Then** ese registro
   queda fuera de la importación y así se refleja en el recuento.
4. **Given** un dato que la persona no tiene, **When** el asistente lo necesita, **Then** **nunca lo
   inventa**: o lo pide, o descarta el registro y lo dice.
5. **Given** varias rondas de corrección, **When** la persona confirma, **Then** se importa lo
   acordado en la última versión, no una anterior.

---

### User Story 3 - Adjuntar cosas que no son una hoja de cálculo (Priority: P2)

La persona no tiene un Excel: tiene un PDF que le pasó su gestoría, una foto de una hoja, o un listado
pegado en el chat. El asistente lo interpreta igual y sigue el mismo camino.

**Why this priority**: es lo que hoy es imposible por cualquier vía, y donde el asistente aporta algo
que ninguna pantalla puede. Va detrás de US1/US2 porque necesita el mismo circuito de análisis y
corrección ya montado.

**Independent Test**: adjuntar una foto de un listado de clientes y comprobar que produce el mismo
tipo de análisis que un Excel equivalente.

**Acceptance Scenarios**:

1. **Given** un PDF, una imagen o texto pegado con varios registros, **When** la persona lo aporta,
   **Then** el asistente extrae las filas y las analiza como si vinieran de una hoja de cálculo.
2. **Given** un documento del que solo se leen algunos campos, **When** el asistente lo analiza,
   **Then** distingue lo que **leyó** de lo que **no pudo leer**, y no rellena huecos por su cuenta.
3. **Given** un documento que no contiene registros importables, **When** se aporta, **Then** el
   asistente lo dice claramente en vez de inventar filas.
4. **Given** varios documentos en la misma conversación, **When** se aportan, **Then** se acumulan en
   una única importación.

---

### User Story 4 - Sugerencias destacadas para saber qué pedirle (Priority: P3)

Al abrir el asistente sin conversación, la persona ve un saludo y unas sugerencias agrupadas por
categoría. Una de ellas es importar registros: así descubre que puede hacerlo.

**Why this priority**: no habilita nada nuevo, pero es cómo se descubre todo lo anterior. Un
asistente potente que nadie sabe usar vale poco.

**Independent Test**: abrir el panel sin conversación previa y comprobar que aparecen las
sugerencias, y que al pulsar una se envía como mensaje.

**Acceptance Scenarios**:

1. **Given** el panel abierto sin conversación, **When** se muestra, **Then** aparecen sugerencias
   agrupadas por categoría.
2. **Given** una sugerencia, **When** la persona la pulsa, **Then** se envía como si la hubiera
   escrito.
3. **Given** una persona sin permiso sobre un módulo, **When** ve las sugerencias, **Then** no se le
   ofrecen las que no podría ejecutar.
4. **Given** una conversación ya empezada, **When** la persona escribe, **Then** las sugerencias
   desaparecen y no estorban.

---

### Edge Cases

- **Fichero enorme o con demasiadas filas**: se aplican los mismos límites que la importación
  existente, y el asistente los explica en vez de fallar.
- **Fichero ilegible, vacío o protegido con contraseña**: mensaje claro, nunca un error técnico.
- **Columnas que no coinciden** con lo esperado: el asistente propone a qué campo corresponde cada
  una y pide confirmación, en vez de rechazar el fichero entero.
- **Duplicados**: dentro del propio fichero y contra lo ya existente en la empresa; ambos se
  reportan como tales, no como registros nuevos.
- **La conversación se abandona a medias**: el fichero subido no puede quedarse indefinidamente.
- **La persona pierde el permiso** sobre el módulo entre el análisis y la confirmación: no se importa.
- **Se cambia de conversación** con una importación a medias: no se arrastra al hilo nuevo.
- **Un documento con datos personales de terceros**: se conserva el mínimo tiempo necesario.
- **El servicio de IA no está configurado**: los ficheros estructurados deberían seguir pudiendo
  analizarse; los que necesitan interpretación, no, y hay que decirlo.

## Requirements *(mandatory)*

### Functional Requirements

**Aportar material**

- **FR-001**: Las personas MUST poder adjuntar ficheros a la conversación del asistente.
- **FR-002**: El sistema MUST aceptar hojas de cálculo, PDF, imágenes y texto pegado en el chat.
- **FR-003**: El sistema MUST rechazar con un mensaje comprensible lo que no puede procesar, sin
  errores técnicos.
- **FR-004**: Los ficheros aportados MUST quedar acotados a la empresa y a la persona que los subió.

**Análisis**

- **FR-005**: El sistema MUST analizar el material aportado y reportar cuántos registros se leyeron,
  cuántos son válidos y cuáles no, **cada uno con su motivo concreto**.
- **FR-006**: El análisis MUST usar exactamente las mismas reglas de validación que el alta manual
  del módulo, sin un juego paralelo más laxo.
- **FR-007**: El análisis MUST detectar duplicados dentro del material aportado y contra los datos ya
  existentes de la empresa, y distinguir ambos casos.
- **FR-008**: El análisis MUST ser no destructivo: nada se escribe hasta la confirmación.
- **FR-009**: Al interpretar material no estructurado, el sistema MUST distinguir lo que leyó de lo
  que no pudo leer, y **nunca inventar** un valor ausente.
- **FR-010**: El sistema MUST admitir solo los módulos que ya son importables (clientes, artículos y
  proveedores) y rechazar el resto explicando por qué.

**Corrección conversacional**

- **FR-011**: El asistente MUST nombrar los registros afectados por un problema y pedir el dato que
  falta, en vez de limitarse a listar rechazos.
- **FR-012**: Las personas MUST poder aportar los datos que faltan escribiendo en el chat, sin volver
  a subir el fichero.
- **FR-013**: Las personas MUST poder descartar registros concretos de la importación.
- **FR-014**: Tras cada corrección el sistema MUST presentar un análisis actualizado.
- **FR-015**: El sistema MUST importar siempre la última versión acordada, nunca una intermedia.

**Confirmación e importación**

- **FR-016**: La importación MUST ejecutarse solo tras confirmación explícita de la persona.
- **FR-017**: Antes de confirmar, las personas MUST poder ver una tabla con **todos** los campos de
  cada registro a importar, y el estado del análisis.
- **FR-018**: Al confirmar, el sistema MUST importar los registros válidos y **reportar los que
  quedaron fuera con su motivo**, sin abortar por ellos.
- **FR-019**: La confirmación MUST revalidar los datos: entre el análisis y la confirmación, otra
  persona de la empresa pudo crear un registro que ahora colisione.
- **FR-020**: Los registros importados MUST quedar siempre asignados a la empresa activa, sin que el
  material aportado pueda influir en eso.
- **FR-021**: El sistema MUST exigir sobre el módulo el mismo permiso que la importación existente, y
  comprobarlo también al confirmar.
- **FR-022**: La importación MUST quedar registrada en el historial de actividad.

**Retención (RGPD, Principio II)**

- **FR-023**: El material aportado MUST conservarse solo mientras la importación esté en curso, con
  un plazo máximo acotado y purga automática, reutilizando el mecanismo ya existente para los
  ficheros de importación.
- **FR-024**: Una importación abandonada MUST dejar de ocupar espacio pasado ese plazo.

**Sugerencias**

- **FR-025**: El panel MUST mostrar sugerencias agrupadas por categoría cuando no hay conversación.
- **FR-026**: Pulsar una sugerencia MUST enviarla como mensaje.
- **FR-027**: Las sugerencias MUST respetar los permisos: no se ofrece lo que la persona no podría
  hacer.

**Documentación (parte del entregable)**

- **FR-028**: La documentación de arquitectura, modelo de datos, guías de front, guía in-app y base
  de conocimiento del asistente MUST reflejar esta feature en el mismo cambio.

### Key Entities

- **Importación en curso**: el material aportado más las correcciones acordadas, asociada a una
  persona, una empresa y una conversación. Efímera: vive mientras dura el proceso.
- **Registro propuesto**: una fila a importar, con sus campos, su estado (válido o rechazado) y el
  motivo si está rechazado.
- **Análisis**: el resultado de evaluar el material en un momento dado: leídos, válidos, rechazados
  con motivos, y qué no se pudo interpretar.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una persona sin conocimiento previo de la pantalla de importación consigue importar un
  fichero de 50 clientes hablando con el asistente, sin ayuda externa.
- **SC-002**: Ningún registro se escribe sin confirmación explícita: 0 escrituras en las pruebas
  previas a confirmar.
- **SC-003**: Ante material con 10 registros de los que 3 tienen un dato obligatorio ausente, el
  asistente identifica **exactamente esos 3** y pide ese dato concreto.
- **SC-004**: Tras aportar los datos en el chat, el análisis actualizado da por válidos los
  registros corregidos sin volver a subir el fichero, en el 100 % de los casos.
- **SC-005**: Ninguna persona accede a material aportado por otra ni de otra empresa: 0 accesos
  indebidos en las pruebas de aislamiento.
- **SC-006**: El material aportado desaparece por completo pasado el plazo de retención.
- **SC-007**: Un error del material (ilegible, demasiado grande, módulo no admitido) siempre produce
  una explicación comprensible, nunca un error técnico.
- **SC-008**: Sobre un lote de 10 registros con 2 inválidos, confirmar importa 8 e informa de los 2
  con su motivo.

## Assumptions

- **Se reutiliza el pipeline de importación existente** (feature 031) como autoridad: sus
  validaciones, sus límites, su forzado de empresa y su revalidación al confirmar. El asistente
  orquesta y conversa; no es una segunda vía de escritura con reglas propias.
- **Módulos**: clientes, artículos y proveedores, los que ya implementan el contrato importable.
  Facturas y albaranes quedan fuera a propósito: tocan numeración correlativa y Verifactu.
- **Filas que siguen mal tras conversar**: se importan las válidas y se reportan las demás, que es la
  convención ya establecida en el producto.
- **Coste**: interpretar material no estructurado implica consultas adicionales al proveedor de IA,
  pagadas con la clave de la propia empresa. Los ficheros estructurados no consumen nada de eso.
- **Adjuntos**: esta feature los introduce **acotados al flujo de importación**. Adjuntar ficheros
  para cualquier otra cosa sigue fuera de alcance.
- **Sugerencias**: textos fijos por categoría, sin generación por IA.
- **Idioma**: material en español. Otros idiomas pueden funcionar pero no es un objetivo.
