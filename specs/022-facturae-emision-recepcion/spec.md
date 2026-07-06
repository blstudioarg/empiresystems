# Feature Specification: Emisión y recepción de facturas en formato Facturae

**Feature Branch**: `022-facturae-emision-recepcion`

**Created**: 2026-07-05

**Status**: Draft

**Input**: User description: "Generación y recepción de facturas en formato Facturae (factura electrónica estructurada B2B/B2G). El sistema debe poder EXPORTAR cualquier factura emitida como un XML Facturae 3.2.2 válido, firmado con firma electrónica XAdES-EPES usando el certificado digital del emisor (tenant), mapeando la cabecera, líneas, desglose de impuestos y las menciones especiales (calificación de operación S1/S2/N1/N2, causa de exención E1-E6, mención legal) que ya están modeladas en factura_lineas. El XML firmado se conserva como archivo asociado a la factura. Del lado de RECEPCIÓN, el sistema debe poder IMPORTAR un XML Facturae recibido de un proveedor: leer el XML, volcarlo a una compra (proveedores/compras/compra_lineas ya existentes) con origen=facturae, guardar el archivo recibido, y permitir marcar el estado de ciclo B2B (recibida/aceptada/rechazada/pagada) con fecha para reportarlo en el plazo legal."

## Contexto normativo

- **RD 238/2026** (BOE, 31 marzo 2026): regula la factura electrónica B2B obligatoria derivada de
  la Ley Crea y Crece. Exige formato **estructurado** ajustado al modelo semántico europeo
  **EN 16931**, admitiendo Facturae, UBL, XML CEFACT/ONU (CII) y EDIFACT.
- **Formato de referencia del producto: Facturae 3.2.2**, por ser el exigido por el Kit Digital
  ("al menos Facturae") y el mismo que se usa frente a la Administración pública (FACe), cubriendo
  B2G y B2B con un solo formato. Ver `docs/02-facturacion-espana.md` §2.
- Facturae es un formato **distinto e independiente** del XML del registro de **Verifactu**
  (Orden HAC/1177/2024): Verifactu es el registro con huella/encadenamiento que se conserva/remite
  a la AEAT; Facturae es el documento estructurado que se **intercambia con el cliente/proveedor**.
  Una misma factura genera **ambos**. Esta feature NO toca el registro Verifactu.
- Menciones especiales por línea (calificación de operación S1/S2/N1/N2, causa de exención E1–E6,
  mención legal) ya están modeladas en `factura_lineas` — ver `docs/02-facturacion-espana.md` §6 y
  `docs/03-modelo-datos.md`.

## Clarifications

### Session 2026-07-05

- Q: ¿Cómo se gestiona el certificado digital del emisor para firmar el Facturae (XAdES-EPES)? → A:
  El tenant sube su certificado PKCS#12 (`.p12`/`.pfx`) + contraseña; se guarda cifrado y la firma
  ocurre en el servidor (compatible con hosting compartido, Principio V). Sin HSM/servicio externo
  en esta versión.
- Q: ¿Cuál es el alcance de entrega (emisión) y de recepción en esta versión? → A: Emisión genera y
  conserva el Facturae **y** lo envía automáticamente al cliente por email reutilizando el módulo de
  envío existente (feature 017); recepción por carga manual del XML. Sin plataformas de intercambio
  B2B automatizadas.
- Q: Al importar un Facturae de proveedor cuya firma no se puede verificar, ¿qué hace el sistema? →
  A: Importar con aviso — se valida esquema e importes y se crea la compra, pero se marca con una
  advertencia visible cuando la firma XAdES no es verificable (no se rechaza por ese motivo).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Exportar una factura emitida como Facturae firmado (Priority: P1)

Un usuario de un tenant, tras emitir una factura, necesita entregarla a su cliente en el formato
electrónico estructurado exigido por la ley. Configura una vez el certificado digital del emisor en
la configuración del tenant y, desde el detalle de una factura emitida, genera el archivo Facturae
3.2.2 firmado con XAdES-EPES. El archivo firmado queda asociado a la factura (para descargarlo o
reenviarlo más adelante) y se envía automáticamente al cliente por email reutilizando el módulo de
envío existente (feature 017).

**Why this priority**: Es el núcleo de valor y la obligación legal principal del RD 238/2026 y del
Kit Digital. Sin la generación del documento estructurado firmado, la app no cumple la categoría de
factura electrónica. Es el MVP: entrega valor por sí solo aunque no exista aún la recepción.

**Independent Test**: Con un tenant que tiene certificado configurado y una factura en estado
`emitida`, generar el Facturae y verificar que el XML producido (a) valida contra el esquema
Facturae 3.2.2, (b) refleja fielmente cabecera, líneas, desglose de impuestos y menciones
especiales de la factura, y (c) contiene una firma XAdES-EPES verificable con el certificado del
emisor. Se prueba de extremo a extremo sin depender de la recepción.

**Acceptance Scenarios**:

1. **Given** una factura en estado `emitida` de un tenant con certificado válido configurado,
   **When** el usuario solicita exportarla a Facturae, **Then** el sistema genera un XML Facturae
   3.2.2 firmado con XAdES-EPES, lo guarda como archivo asociado a la factura y lo ofrece para
   descarga.
2. **Given** una factura con líneas de distintos tipos impositivos y una línea con inversión del
   sujeto pasivo (`S2`, cuota 0), **When** se exporta a Facturae, **Then** el desglose de impuestos
   del XML separa base imponible por tipo y refleja la calificación de operación y la mención legal
   correspondiente en la línea con ISP.
3. **Given** una factura con una línea exenta (causa `E5`, entrega intracomunitaria art. 25 LIVA),
   **When** se exporta a Facturae, **Then** el XML marca la operación como exenta con su causa/
   artículo y la mención legal, sin repercutir cuota.
4. **Given** un tenant SIN certificado configurado (o con certificado caducado), **When** el usuario
   intenta exportar a Facturae, **Then** el sistema impide la generación y muestra un mensaje claro
   indicando que debe configurar/renovar el certificado, sin generar un archivo inválido.
5. **Given** una factura ya exportada anteriormente, **When** el usuario vuelve al detalle de la
   factura, **Then** puede descargar de nuevo el mismo archivo Facturae firmado ya generado.
6. **Given** una factura con Facturae generado y un cliente con email, **When** se completa la
   generación, **Then** el sistema envía automáticamente el Facturae al cliente por email a través
   del módulo de envío existente (feature 017) y registra el envío; si el cliente no tiene email, la
   generación se conserva igualmente y el envío queda pendiente/omitido con aviso.

---

### User Story 2 - Importar un Facturae recibido de un proveedor como compra (Priority: P2)

Un usuario recibe de un proveedor una factura en formato Facturae (un archivo XML). La sube al
sistema y este lee el XML, crea una compra con `origen=facturae` volcando cabecera y líneas,
asocia el proveedor (o propone crearlo si no existe) y conserva el archivo recibido. Así la app
cumple el requisito de "recibir" facturas electrónicas, apoyándose en el módulo de compras ya
existente (feature 014).

**Why this priority**: El Kit Digital exige emitir **y** recibir. La recepción es el gap actual del
producto. Es P2 porque la emisión (US1) es la obligación primaria y de mayor volumen; la recepción
suma sobre una base (`proveedores`/`compras`/`compra_lineas`) que ya existe.

**Independent Test**: Subir un XML Facturae de proveedor de ejemplo y verificar que se crea una
compra con los datos correctos (proveedor, fechas, líneas, importes, desglose de impuestos),
`origen=facturae`, y que el archivo original queda guardado y recuperable. Se prueba sin depender
de la emisión.

**Acceptance Scenarios**:

1. **Given** un XML Facturae 3.2.2 válido de un proveedor ya registrado en el tenant, **When** el
   usuario lo importa, **Then** el sistema crea una compra con `origen=facturae`, líneas e importes
   volcados del XML, asocia el proveedor existente por su NIF y guarda el archivo recibido.
2. **Given** un XML Facturae cuyo emisor (proveedor) no existe aún en el tenant, **When** el usuario
   lo importa, **Then** el sistema le ofrece crear el proveedor con los datos del XML antes de
   completar la compra.
3. **Given** un archivo que no es un Facturae válido (XML malformado o esquema incorrecto), **When**
   el usuario lo importa, **Then** el sistema rechaza la importación con un mensaje claro del motivo
   y no crea ninguna compra.
6. **Given** un Facturae estructuralmente válido y con importes correctos pero cuya firma XAdES no se
   puede verificar, **When** el usuario lo importa, **Then** el sistema crea la compra pero la marca
   con una advertencia visible sobre la firma (no la rechaza por ese motivo).
4. **Given** un XML Facturae cuyos importes/desglose no cuadran internamente, **When** se importa,
   **Then** el sistema avisa de la discrepancia y no crea una compra con importes inconsistentes.
5. **Given** una compra ya importada de un Facturae, **When** el usuario abre su detalle, **Then**
   puede descargar el XML original recibido.

---

### User Story 3 - Seguimiento del estado de ciclo B2B de una factura recibida (Priority: P3)

Sobre una compra originada por un Facturae recibido, el usuario marca el estado del ciclo comercial
B2B (recibida / aceptada / rechazada / pagada) con su fecha, de modo que el tenant pueda reportar
ese estado al emisor dentro del plazo legal (4 días hábiles según la ley B2B).

**Why this priority**: Es una obligación de la ley B2B pero secundaria respecto a poder recibir el
documento. Aporta valor incremental sobre US2 y no bloquea el MVP.

**Independent Test**: Sobre una compra con `origen=facturae`, cambiar su `estado_b2b` y verificar
que se registra el estado y la fecha del cambio, y que el listado permite filtrar/ver las compras
por estado B2B.

**Acceptance Scenarios**:

1. **Given** una compra con `origen=facturae` en estado `recibida`, **When** el usuario la marca
   como `aceptada`, **Then** el sistema guarda el nuevo estado y la fecha del cambio.
2. **Given** varias compras recibidas en distintos estados, **When** el usuario consulta el listado,
   **Then** puede ver y filtrar por estado de ciclo B2B.

---

### User Story 4 - Validación de identificación fiscal antes de emitir (Priority: P3)

Antes de generar un Facturae (que exige NIF válido de emisor y receptor), el sistema valida en
formato y dígito de control el NIF/CIF/NIE de emisor y receptor, y —opcionalmente, para entregas
intracomunitarias (causa E5)— verifica el NIF-IVA del cliente contra VIES para justificar la
exención.

**Why this priority**: Evita generar Facturae inválidos o registros rechazados. Es P3 porque es una
salvaguarda de calidad que refuerza US1; su ausencia no impide la generación en el caso general,
pero sí mejora la fiabilidad y es requisito para la exención intracomunitaria.

**Independent Test**: Introducir NIF/CIF/NIE válidos e inválidos en datos de cliente y del emisor y
verificar que el sistema acepta los correctos y bloquea/avisa de los incorrectos con dígito de
control erróneo; para un cliente intracomunitario, verificar el flujo de comprobación VIES.

**Acceptance Scenarios**:

1. **Given** un cliente con un NIF cuyo dígito de control es incorrecto, **When** se intenta emitir/
   exportar su factura a Facturae, **Then** el sistema señala el NIF inválido y no genera un
   documento con identificación fiscal errónea.
2. **Given** una entrega intracomunitaria (causa `E5`) a un cliente con NIF-IVA, **When** el usuario
   solicita la verificación VIES, **Then** el sistema consulta VIES y refleja si el NIF-IVA es
   válido para justificar la exención; si VIES no está disponible, el sistema lo indica sin bloquear
   irreversiblemente la operación.

---

### Edge Cases

- **Certificado caducado o revocado** en el momento de firmar: la generación se detiene con aviso;
  no se produce un archivo firmado inválido.
- **Factura en `borrador`** (no emitida): no se permite exportar a Facturae (solo facturas emitidas,
  inmutables, son documentos definitivos — Principio II).
- **Factura rectificativa**: debe exportarse como Facturae reflejando su condición de rectificativa,
  motivo y referencia a la(s) factura(s) rectificada(s).
- **Régimen impositivo no-IVA** (IGIC/IPSI): el desglose del Facturae debe reflejar el impuesto del
  régimen congelado en la factura, no asumir IVA.
- **Facturae recibido con más de un impuesto o con recargo de equivalencia**: el importador debe
  volcar el desglose correctamente o rechazar con aviso si no puede representarlo.
- **Reimportación del mismo Facturae** ya cargado: el sistema debe evitar duplicar la compra
  (detección por emisor + número + fecha) o avisar de posible duplicado.
- **Facturae con datos personales del emisor/receptor**: el archivo conservado contiene datos
  identificables; aplica retención/purga conforme a RGPD/LOPDGDD (Principio II).
- **XML muy grande o con muchas líneas**: la generación/importación debe completarse sin degradar la
  experiencia del usuario.

## Requirements *(mandatory)*

### Functional Requirements

**Emisión (US1)**

- **FR-001**: El sistema MUST permitir exportar una factura en estado `emitida` como un documento
  XML en formato **Facturae 3.2.2**.
- **FR-002**: El Facturae generado MUST validar contra el esquema oficial de Facturae 3.2.2.
- **FR-003**: El Facturae generado MUST ir firmado con **firma electrónica XAdES-EPES** usando el
  certificado digital del emisor (tenant).
- **FR-004**: El sistema MUST mapear al Facturae la cabecera de la factura (emisor, receptor,
  número/serie, fechas de expedición y operación), las líneas, el desglose de impuestos por tipo y
  las menciones especiales por línea (calificación de operación S1/S2/N1/N2, causa de exención
  E1–E6 y mención legal) ya modeladas en `factura_lineas`.
- **FR-005**: El sistema MUST reflejar en el Facturae el impuesto del **régimen impositivo congelado
  en la factura** (IVA/IGIC/IPSI), sin asumir IVA.
- **FR-006**: El sistema MUST conservar el XML firmado como **archivo asociado a la factura** y
  permitir su descarga posterior sin regenerarlo.
- **FR-006a**: Tras generar el Facturae, el sistema MUST enviarlo automáticamente al cliente por
  email reutilizando el módulo de envío existente (feature 017) y registrar el envío. Si el cliente
  no tiene email, la generación se conserva igualmente y el envío queda omitido con aviso al usuario.
- **FR-007**: El sistema MUST impedir la generación cuando el tenant no tiene un certificado válido
  configurado (ausente, caducado o revocado) y comunicar el motivo al usuario.
- **FR-008**: El sistema MUST impedir exportar a Facturae facturas que no estén `emitida` (p. ej.
  `borrador`).
- **FR-009**: El sistema MUST exportar correctamente las **facturas rectificativas**, incluyendo su
  condición, motivo y referencia a la(s) factura(s) rectificada(s).

**Configuración del certificado del emisor**

- **FR-010**: El tenant MUST poder configurar el certificado digital del emisor que se usará para
  firmar (aportando el certificado y su clave de acceso).
- **FR-011**: El sistema MUST validar el certificado aportado (que se puede usar para firmar y que
  no está caducado) al configurarlo y advertir de su fecha de caducidad.
- **FR-012**: El sistema MUST proteger el certificado y su clave en reposo, restringiendo su uso al
  proceso de firma del propio tenant.

**Recepción (US2)**

- **FR-013**: El sistema MUST permitir importar un archivo XML Facturae recibido de un proveedor.
- **FR-014**: Al importar, el sistema MUST crear una **compra** con `origen=facturae`, volcando
  cabecera y líneas del XML y guardando el archivo recibido (`archivo_recibido_path`).
- **FR-015**: El sistema MUST asociar la compra al proveedor existente por su NIF y, si no existe,
  ofrecer crearlo con los datos del XML antes de completar la importación.
- **FR-016**: El sistema MUST rechazar, con mensaje claro y sin crear compra, archivos que no sean un
  Facturae válido (XML malformado, esquema incorrecto) o cuyos importes no cuadren internamente.
- **FR-016a**: Cuando el Facturae recibido es estructuralmente válido y sus importes cuadran pero su
  **firma XAdES no es verificable** (p. ej. cadena de certificado no confiable), el sistema MUST
  importar igualmente la compra pero **marcarla con una advertencia visible** sobre la firma; no se
  rechaza por ese motivo.
- **FR-017**: El sistema MUST permitir descargar el XML original recibido desde el detalle de la
  compra.
- **FR-018**: El sistema MUST evitar (o advertir de) la **importación duplicada** del mismo Facturae
  (mismo emisor + número + fecha).

**Estado de ciclo B2B (US3)**

- **FR-019**: El sistema MUST permitir marcar el `estado_b2b` de una compra recibida
  (recibida/aceptada/rechazada/pagada) y registrar la **fecha** del cambio.
- **FR-020**: El sistema MUST permitir consultar y filtrar las compras recibidas por `estado_b2b`.

**Validación fiscal (US4)**

- **FR-021**: El sistema MUST validar en formato y dígito de control el NIF/CIF/NIE del emisor y del
  receptor antes de generar el Facturae, e impedir generar documentos con identificación fiscal
  inválida.
- **FR-022**: El sistema MUST permitir verificar el NIF-IVA del cliente contra **VIES** para las
  entregas intracomunitarias (causa E5); la indisponibilidad de VIES no debe bloquear
  irreversiblemente la operación, pero sí quedar reflejada.

**Cumplimiento transversal**

- **FR-023**: Toda la información y archivos de esta feature MUST estar aislada por tenant
  (Principio I): un tenant nunca ve ni firma con datos/certificados de otro.
- **FR-024**: Los archivos Facturae conservados (emitidos y recibidos) contienen datos personales y
  MUST quedar sujetos al patrón de retención/purga configurable por tenant (Principio II /
  RGPD-LOPDGDD).

### Key Entities *(include if feature involves data)*

- **Certificado del emisor (tenant)**: atributos **del propio tenant** (emisor). Incluye el archivo
  del certificado en formato PKCS#12 (`.p12`/`.pfx`) y su **contraseña de acceso** (guardada
  **cifrada**, nunca en claro), más metadatos derivados: titular, validez/caducidad, estado. Uno
  vigente por tenant. Sensible: se protege en reposo y su uso se restringe a la firma del propio
  tenant (Principio I — un tenant nunca firma con el certificado de otro).
- **Archivo Facturae emitido**: XML 3.2.2 firmado, asociado 1:1 a una factura emitida; se conserva y
  se puede volver a descargar.
- **Compra recibida por Facturae**: extiende la entidad `compras` existente con `origen=facturae`,
  `formato_recepcion`, `archivo_recibido_path`, `estado_b2b` y `estado_b2b_fecha` (ya previstos en
  `docs/03-modelo-datos.md`).
- **Línea de factura con menciones especiales**: `factura_lineas` existente, con
  `calificacion_operacion`, `causa_exencion` y `mencion_legal` que alimentan el desglose del
  Facturae.
- **Resultado de validación fiscal**: veredicto de validez de un NIF/CIF/NIE (formato + dígito de
  control) y, para intracomunitarias, el resultado de la consulta VIES.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los Facturae generados por el sistema validan contra el esquema oficial
  Facturae 3.2.2 y su firma XAdES-EPES es verificable con el certificado del emisor.
- **SC-002**: Un usuario puede exportar y descargar el Facturae firmado de una factura emitida en
  menos de 3 clics desde el detalle de la factura.
- **SC-003**: El 100% de las menciones especiales (ISP, exenciones E1–E6, no sujetas) presentes en
  las líneas de una factura se reflejan correctamente en el Facturae generado.
- **SC-004**: Un Facturae de proveedor válido se importa y crea una compra con datos e importes
  correctos, sin intervención manual de reintroducción de líneas, en la mayoría de los casos.
- **SC-005**: El 100% de los archivos no-Facturae o inválidos se rechazan en la importación sin
  crear compras corruptas.
- **SC-006**: Ningún tenant puede acceder, firmar con, ni importar hacia datos/certificados de otro
  tenant (0 fugas en los tests de aislamiento).
- **SC-007**: El estado de ciclo B2B de una factura recibida puede registrarse y consultarse para
  cumplir el reporte dentro del plazo legal de 4 días hábiles.

## Assumptions

- **Certificado del emisor** (confirmado en Clarifications): el tenant aporta su propio certificado
  digital tipo software, en formato PKCS#12 (`.p12`/`.pfx`) con contraseña, ambos **atributos del
  tenant**; el sistema los almacena de forma protegida (contraseña cifrada) y firma **en servidor**.
  No se contempla firma en dispositivo del usuario ni HSM/servicio externo de firma en esta versión.
- **Entrega al cliente** (confirmado en Clarifications): la feature genera y conserva el Facturae
  **y lo envía automáticamente al cliente por email** reutilizando el módulo de envío existente
  (feature 017); además permite su descarga y reenvío manual. NO incluye integración con
  plataformas/puntos de intercambio B2B automatizados en esta versión.
- **Recepción por carga manual**: la recepción se realiza subiendo el XML recibido; no se incluye
  recepción automática desde un buzón/plataforma en esta versión.
- **Facturae 3.2.2** es la versión de referencia; UBL/CII/EDIFACT quedan fuera de alcance aunque el
  RD los admita.
- **Verifactu es independiente**: esta feature no modifica el registro/encadenamiento Verifactu; se
  asume que el modelo de factura ya provee los datos necesarios (líneas, desglose, menciones).
- **Menciones especiales ya modeladas**: se reutilizan los campos de `factura_lineas`
  (`calificacion_operacion`, `causa_exencion`, `mencion_legal`) documentados; esta feature los
  consume, no los redefine.
- **Compras ya implementadas**: se reutilizan `proveedores`/`compras`/`compra_lineas` (feature 014);
  los campos de recepción (`origen`, `formato_recepcion`, `archivo_recibido_path`, `estado_b2b`,
  `estado_b2b_fecha`) están previstos en `docs/03-modelo-datos.md` y se materializan en esta feature.
- **VIES** es una verificación externa opcional, relevante solo para entregas intracomunitarias
  (E5); su indisponibilidad no bloquea irreversiblemente el flujo.
- **Compatibilidad hosting compartido** (Principio V): la firma XAdES-EPES y la validación de esquema
  deben poder ejecutarse en el entorno de hosting objetivo sin requerir infraestructura dedicada;
  si alguna dependencia lo exige, se documenta en `docs/01-arquitectura.md` antes de adoptarla.
