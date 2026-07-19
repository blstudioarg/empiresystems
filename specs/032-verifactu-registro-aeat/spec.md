# Feature Specification: Verifactu — registro y remisión de facturas a la AEAT

**Feature Branch**: `032-verifactu-registro-aeat`

**Created**: 2026-07-19

**Status**: Draft

**Input**: User description: registro Verifactu (huella SHA-256 encadenada, QR + texto "VERI*FACTU", XML del registro) generado al emitir cada factura, y remisión en tiempo real al web service de la AEAT (modo Veri*Factu), respetando RD 1007/2023, RD 254/2025 y Orden HAC/1177/2024. Ver `docs/02-facturacion-espana.md` §1 y §6.

## Contexto y encaje

Esta feature activa la maquinaria Verifactu cuyos campos ya se reservaron desde el diseño inicial del modelo de datos (`facturas.huella`, `huella_anterior`, `qr_contenido`, `verifactu_estado`, `registro_xml`, `registrada_at`; tabla `factura_eventos`; `factura_lineas.calificacion_operacion` y `causa_exencion`; claves de config `verifactu.activo` / `verifactu.entorno`). Hasta ahora todos esos campos existían pero no se poblaban. El punto de enganche es el acto de **emitir** una factura (pasar de `borrador` a `emitida`), hoy implementado en `App\Services\EmisorFacturas::emitir()`, que numera, mueve stock y registra el evento `emitida` pero **no** calcula huella ni encadena ni remite nada.

Aplica a los **tres** tipos de factura que hoy se emiten: ordinaria, rectificativa y simplificada (POS, vía `RegistroTicket`/`EmisorFacturas`).

## Clarifications

### Session 2026-07-19

- Q: Si la AEAT no responde o rechaza el envío, ¿qué pasa con la emisión de la factura? → A: Emitir nunca se bloquea — la factura se registra localmente (huella/QR) y queda emitida; el envío fallido queda en `error` y se reintenta.
- Q: Si la remisión está activada pero el tenant no tiene certificado válido, ¿qué hace al emitir? → A: Registra local y deja el envío pendiente/error por falta de certificado; no bloquea la emisión (la validación de NIF del emisor sí es bloqueante).
- Q: ¿Cómo se reintenta un envío que quedó en `error`? → A: Automático (proceso programado periódico) + manual (el usuario puede forzar el reintento desde la factura).
- Q: ¿Cómo se separan los registros de entorno `pruebas` de los de `producción`? → A: Entorno fijo por tenant; el entorno se marca en cada registro y cambiar a un entorno con cadena ya iniciada se impide o exige reinicio explícito. Los registros de prueba no forman parte de la cadena fiscal de producción.
- Q: ¿Qué nivel de configuración de Verifactu por tenant? → A: Un solo flag `verifactu.activo` (todo o nada). Apagado: la factura se emite como hasta ahora, **sin** huella, QR ni envío. Encendido: al emitir se genera el registro local (huella + QR) **y** se remite a la AEAT. La cadena de huellas arranca en la primera factura emitida con el flag activo; las facturas emitidas con el flag apagado quedan fuera de la cadena.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cada factura emitida genera su registro Verifactu inalterable y encadenado (Priority: P1)

Cuando un usuario del tenant emite una factura (ordinaria, rectificativa o simplificada), el sistema genera de forma automática y en el mismo acto de emisión el **registro Verifactu** de esa factura: calcula su huella SHA-256 a partir de los campos normativos del registro más la huella del registro Verifactu inmediatamente anterior del mismo tenant, construye el XML del registro conforme a la Orden HAC/1177/2024, marca la fecha de registro y deja el estado en `registrada`. Cada emisión añade un eslabón a la cadena del tenant; esa cadena no puede tener huecos ni reordenarse, y como una factura emitida ya es inmutable, ningún registro previo puede alterarse a posteriori.

**Why this priority**: Es el núcleo normativo de la feature y la base sobre la que se apoyan el QR y la remisión a la AEAT. Sin la huella encadenada correcta no hay nada válido que enseñar ni que enviar. Es lo que exige la constitución (Principio II) y lo que evita la sanción de hasta 50.000 €.

**Independent Test**: Emitir varias facturas en un tenant y verificar que cada una queda con huella no vacía, que la `huella_anterior` de cada registro coincide con la `huella` del registro previo del mismo tenant, que el primer registro del tenant tiene el marcador de "sin registro anterior" definido por la norma, y que el XML del registro contiene los campos mínimos. Testeable sin necesidad de conexión con la AEAT.

**Acceptance Scenarios**:

1. **Given** un tenant sin ninguna factura emitida, **When** el usuario emite su primera factura, **Then** el registro Verifactu de esa factura queda con una huella calculada, la `huella_anterior` con el valor normativo de "primer registro de la cadena", `verifactu_estado = registrada`, `registrada_at` con la marca temporal de emisión, y un evento en `factura_eventos` de tipo alta con su propia huella.
2. **Given** un tenant con N facturas emitidas encadenadas, **When** el usuario emite la factura N+1, **Then** la `huella_anterior` del nuevo registro es exactamente la `huella` del registro N, y la huella nueva se calcula incluyéndola.
3. **Given** dos tenants distintos emitiendo facturas en paralelo, **When** ambos emiten, **Then** cada uno mantiene su propia cadena independiente y ninguna huella de un tenant entra en la cadena del otro.
4. **Given** una factura rectificativa que se emite, **When** se genera su registro, **Then** el registro refleja su condición de rectificativa y la referencia a la factura rectificada, y encadena igual que cualquier otro registro del tenant.
5. **Given** una factura ya emitida con su registro, **When** se intenta editarla o borrarla, **Then** la operación se rechaza (regla de inmutabilidad ya vigente) y la cadena permanece intacta.
6. **Given** una emisión que falla a mitad (p. ej. error al construir el registro), **When** se aborta, **Then** ni la factura queda emitida ni se crea un eslabón parcial en la cadena (todo o nada).

---

### User Story 2 - El PDF de la factura lleva QR de cotejo AEAT y el texto "VERI*FACTU" (Priority: P2)

Una vez emitida y registrada la factura, su representación imprimible (PDF A4 y ticket 80 mm del POS) incluye el **código QR** que apunta a la URL oficial de cotejo de la AEAT con los datos del registro, junto con el texto **"VERI*FACTU"**, de modo que quien reciba la factura pueda verificarla contra la AEAT. El QR se genera aunque la remisión a la AEAT esté temporalmente inactiva.

**Why this priority**: Es un requisito visible y obligatorio de la factura, pero depende de que el registro (US1) ya exista. Aporta valor al receptor y completa la factura de cara al cumplimiento, por eso va inmediatamente después del núcleo.

**Independent Test**: Emitir una factura y generar su PDF (A4 y ticket); comprobar que aparece el QR y el texto "VERI*FACTU", y que el contenido del QR corresponde a la URL oficial de cotejo con los datos del registro (NIF emisor, nº/serie, fecha, importe total, huella).

**Acceptance Scenarios**:

1. **Given** una factura emitida y registrada, **When** el usuario descarga/visualiza su PDF A4, **Then** el PDF muestra el QR de cotejo y el texto "VERI*FACTU".
2. **Given** una factura simplificada emitida desde el POS, **When** se genera su ticket 80 mm, **Then** el ticket muestra igualmente el QR y el texto "VERI*FACTU".
3. **Given** una factura registrada localmente pero cuya remisión a la AEAT aún no se completó, **When** se genera el PDF, **Then** el QR aparece igualmente (el QR no depende del acuse de la AEAT).
4. **Given** una factura en `borrador` (aún no emitida), **When** se previsualiza, **Then** no se muestra QR ni "VERI*FACTU" (no hay registro todavía).

---

### User Story 3 - El registro se remite en tiempo real a la AEAT y su estado se refleja (Priority: P3)

Cuando el tenant tiene la remisión activada (`verifactu.activo`) y su certificado configurado, tras registrar la factura el sistema **envía el registro al web service de la AEAT** en el entorno configurado (`verifactu.entorno`: pruebas o producción), usando el certificado del tenant. Según la respuesta, el estado de la factura pasa de `registrada` a `enviada` (aceptada por la AEAT) o a `error` (rechazo/incidencia), quedando el detalle del acuse o del error en el log de eventos. Un envío en `error` puede reintentarse sin romper la cadena ni duplicar el registro. La **anulación** de una factura, cuando la normativa la permita, genera y remite su correspondiente registro de anulación.

**Why this priority**: Es la parte de mayor valor de cumplimiento en el modo Veri*Factu, pero también la de mayor dependencia externa (endpoints y certificados de la AEAT) y la que puede operar de forma diferida sin invalidar el registro ya generado localmente. Por eso se prioriza después de tener registro + QR sólidos.

**Independent Test**: Con la remisión activada apuntando al entorno de pruebas de la AEAT, emitir una factura y verificar que se intenta el envío, que una respuesta de aceptación deja la factura en `enviada` con el acuse guardado, y que una respuesta de rechazo la deja en `error` con el motivo guardado y disponible para reintento. Con la remisión desactivada, la factura queda en `registrada` sin intentar envío.

**Acceptance Scenarios**:

1. **Given** un tenant con remisión activada y certificado válido, **When** emite una factura y la AEAT la acepta, **Then** `verifactu_estado = enviada`, se guarda el acuse en un evento y el estado es visible para el usuario.
2. **Given** el mismo tenant, **When** la AEAT rechaza el registro, **Then** `verifactu_estado = error`, se guarda el motivo del rechazo, y la factura sigue siendo válida y encadenada localmente (el registro local no se deshace por un error de envío).
3. **Given** una factura en `error` de envío, **When** el usuario o un proceso reintenta la remisión, **Then** se reenvía el mismo registro (misma huella, sin crear un eslabón nuevo) y el estado se actualiza según la nueva respuesta.
4. **Given** un tenant con remisión desactivada (`verifactu.activo = false`), **When** emite una factura, **Then** la factura queda `registrada` con su huella/QR y no se intenta ninguna llamada a la AEAT.
5. **Given** un tenant con remisión activada pero sin certificado válido configurado, **When** intenta emitir, **Then** el sistema informa del problema de configuración de forma clara y no deja la cadena en estado inconsistente. *(Ver Assumptions: emisión vs. envío ante falta de certificado.)*
6. **Given** una factura emitida y remitida que debe anularse (cuando la normativa lo permite), **When** el usuario la anula, **Then** se genera y remite un registro de anulación encadenado, y el estado refleja la anulación.

---

### Edge Cases

- **Concurrencia en la cadena**: dos emisiones simultáneas del mismo tenant no deben poder tomar la misma `huella_anterior` y producir una bifurcación o un hueco; el encadenamiento debe serializarse por tenant (igual criterio que la numeración de serie, Principio III).
- **Fallo de red / timeout con la AEAT**: el registro local ya existe; el envío queda en `error`/pendiente de reintento sin perder ni duplicar el eslabón.
- **Primer registro del tenant**: debe usar el marcador normativo de "sin registro anterior" y no fallar por ausencia de huella previa.
- **Tenant sin datos fiscales válidos del emisor** (NIF inválido): no debe poder generar un registro inválido; se bloquea con mensaje claro antes de encadenar.
- **Rectificativa y simplificada**: ambos tipos encadenan y se registran; la simplificada puede no llevar receptor (variante simple) y el registro debe admitir NIF receptor ausente.
- **Reintento tras `error`**: no debe recalcular una huella distinta ni alterar `huella_anterior`; se reenvía el registro ya sellado.
- **Cambio de entorno pruebas↔producción**: registros de prueba no deben mezclarse con la cadena de producción de un tenant real. *(Ver Assumptions.)*
- **Desglose con líneas exentas / con inversión del sujeto pasivo**: el registro debe reflejar la calificación de operación (S1/S2/N1/N2) y la causa de exención (E1–E6) por desglose, con cuota 0 donde corresponda.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-000**: El registro Verifactu DEBE estar gobernado por un único flag por tenant `verifactu.activo` (todo o nada). Con el flag **apagado**, la factura se emite como hasta ahora, sin huella, sin QR y sin envío a la AEAT, y no entra en ninguna cadena. Con el flag **encendido**, aplican FR-001 en adelante (registro local + QR + remisión). La cadena de huellas del tenant arranca en la primera factura emitida con el flag activo.
- **FR-001**: Con `verifactu.activo` encendido, el sistema DEBE generar el registro Verifactu de una factura en el mismo acto de emisión (transición `borrador → emitida`), de forma atómica: si falla el registro, la emisión completa se revierte y no queda ni factura emitida ni eslabón parcial.
- **FR-002**: El sistema DEBE calcular la huella del registro como SHA-256 sobre los campos normativos del registro más la huella del registro Verifactu anterior del mismo tenant, siguiendo el algoritmo y el orden de campos definidos por la AEAT (FAQ huella/hash).
- **FR-003**: El sistema DEBE encadenar los registros por tenant: la `huella_anterior` de cada registro es la `huella` del registro inmediatamente anterior del mismo tenant; el primer registro del tenant usa el marcador normativo de "sin registro anterior".
- **FR-004**: El encadenamiento DEBE serializarse por tenant de modo que dos emisiones concurrentes no produzcan huecos, duplicados ni bifurcaciones en la cadena.
- **FR-005**: El sistema DEBE construir y persistir el XML del registro conforme a la Orden HAC/1177/2024, con los campos mínimos: NIF emisor, NIF receptor (si aplica), serie y número de factura, fecha, base imponible por tipo, tipo y cuota del impuesto indirecto (IVA/IGIC/IPSI) por desglose, calificación de operación y causa de exención por desglose, huella del registro y huella del registro anterior.
- **FR-006**: El sistema DEBE registrar en `factura_eventos` (log inalterable append-only) los eventos Verifactu: `verifactu_alta` y `verifactu_anulacion` **con su huella** (participan del encadenamiento), y `verifactu_enviado` / `verifactu_error` **sin huella** (son intentos de comunicación, no eslabones). Una factura **rectificativa** genera un evento de **alta** como cualquier otra factura, no un tipo de evento propio.
- **FR-007**: El sistema DEBE aplicar el registro Verifactu a los tres tipos de factura que se emiten: ordinaria, rectificativa y simplificada (POS).
- **FR-008**: El sistema DEBE impedir editar o borrar una factura emitida (inmutabilidad ya vigente) y DEBE garantizar que ningún registro previo de la cadena se altere.
- **FR-009**: Para las facturas emitidas con `verifactu.activo`, el sistema DEBE generar el código QR apuntando a la URL oficial de cotejo de la AEAT con los datos del registro, y DEBE mostrar el QR junto al texto "VERI*FACTU" en el PDF A4 y en el ticket 80 mm del POS. Las facturas emitidas sin el flag no muestran QR ni el texto.
- **FR-010**: El QR y el texto "VERI*FACTU" DEBEN generarse a partir del registro local, con independencia de si la remisión a la AEAT se completó (una factura registrada pero con envío en `error` igualmente muestra el QR).
- **FR-011**: Con `verifactu.activo` encendido, tras generar el registro el sistema DEBE remitir el registro al web service de la AEAT en el entorno configurado (`verifactu.entorno`: pruebas/producción), autenticándose con el certificado del tenant. Si falta el certificado, el envío queda pendiente/`error` sin bloquear la emisión (FR-018).
- **FR-012**: El sistema DEBE reflejar el resultado de la remisión en `verifactu_estado`: `registrada` (sellada localmente, aún no aceptada por la AEAT), `enviada` (aceptada por la AEAT) o `error` (rechazo/incidencia), guardando el acuse o el motivo del error en el log de eventos.
- **FR-013**: El sistema DEBE permitir reintentar una remisión en `error` reenviando el mismo registro ya sellado (misma huella, misma `huella_anterior`), sin crear un eslabón nuevo ni alterar la cadena. El reintento DEBE ser **automático** (un proceso programado periódico que reprocesa los envíos en `error`, siguiendo el patrón de comandos programados vía `bootstrap/app.php` → `withSchedule`) **y** además **manual** (el usuario puede forzar el reintento desde la propia factura).
- **FR-014**: Un fallo o rechazo de la remisión a la AEAT NO DEBE deshacer el registro local ya generado; la factura permanece emitida, encadenada y con su huella/QR.
- **FR-015**: El sistema DEBE generar y remitir un registro de anulación encadenado cuando una factura emitida pase al estado `anulada`. **Nota de alcance**: el estado `EstadoFactura::Anulada` ya existe en el modelo, pero **hoy no hay ningún flujo que lo dispare** (no existe ruta de anulación de facturas; sí la hay para pagos y compras). Por tanto esta feature DEBE aportar también el disparador mínimo de anulación de una factura emitida, restringido a los supuestos en que la normativa la permite (una factura ya remitida se corrige por **rectificativa**; la anulación queda para el caso de registro erróneo que no llegó a producir efectos). La vía ordinaria de corrección sigue siendo la rectificativa, que genera un registro de **alta** normal.
- **FR-016**: Toda la lógica de cálculo de huella, encadenamiento, construcción del XML y remisión DEBE ocurrir exclusivamente en el backend, en un servicio dedicado, nunca en el cliente ni duplicada ad-hoc (Principio III).
- **FR-017**: La cadena de huellas, el estado y los registros DEBEN estar aislados por `tenant_id` mediante el scope de tenant; los tests DEBEN demostrar que no hay fuga ni cruce de cadenas entre tenants (Principio I).
- **FR-018**: El sistema DEBE validar los datos fiscales mínimos del emisor (NIF válido) antes de generar un registro; si faltan o son inválidos, DEBE bloquear con un mensaje claro sin dejar la cadena inconsistente. En cambio, la **ausencia de certificado** válido del tenant NO bloquea la emisión: la factura se registra localmente y el envío queda pendiente/`error`.
- **FR-019**: El sistema DEBE exponer al usuario el estado Verifactu de cada factura (registrada / enviada / error) de forma comprensible, y permitir accionar el reintento cuando esté en `error`.
- **FR-020**: Cada registro DEBE quedar marcado con el entorno (`pruebas` / `producción`) en que se generó. El sistema DEBE impedir emitir en un entorno distinto al de una cadena ya iniciada para el tenant, salvo reinicio explícito de la cadena; los registros de `pruebas` NO forman parte de la cadena fiscal de `producción`.

### Key Entities *(include if feature involves data)*

- **Registro Verifactu de la factura**: los datos de cumplimiento asociados a una factura emitida — huella propia, huella del registro anterior, contenido/URL del QR, estado Verifactu, XML del registro y marca temporal de registro. Vive sobre la propia factura (columnas ya reservadas en `facturas`). Inmutable una vez emitida.
- **Evento de factura (`factura_eventos`)**: entrada append-only del log inalterable del SIF; para Verifactu, cada alta/rectificación/anulación de registro añade un evento con su huella. También guarda el resultado de la remisión (acuse/error) a la AEAT.
- **Cadena Verifactu del tenant**: secuencia ordenada de registros de un mismo tenant enlazados por huella; conceptual, materializada por el par (`huella`, `huella_anterior`) de cada factura emitida del tenant.
- **Configuración Verifactu del tenant**: `verifactu.activo` (remisión on/off) y `verifactu.entorno` (pruebas/producción), más el certificado del tenant (reutilizado de la infraestructura de firma existente).
- **Desglose por operación (`factura_lineas` / `factura_impuestos`)**: calificación de operación (S1/S2/N1/N2) y causa de exención (E1–E6) por tipo impositivo, que alimentan el registro.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En un tenant con `verifactu.activo`, el 100 % de las facturas emitidas (ordinaria, rectificativa y simplificada) quedan con huella no vacía y correctamente encadenada a la anterior del mismo tenant. En un tenant con el flag apagado, el 100 % se emiten sin huella/QR y sin intentar envío.
- **SC-002**: Recorriendo la cadena de un tenant desde el primer registro, la verificación de encadenamiento (cada `huella_anterior` = `huella` previa) es correcta en el 100 % de los eslabones, sin huecos ni bifurcaciones, incluso bajo emisiones concurrentes.
- **SC-003**: Cero cruces de cadena entre tenants: ninguna huella de un tenant aparece como `huella_anterior` de otro (verificado por test de aislamiento con ≥2 tenants).
- **SC-004**: El 100 % de los PDF (A4 y ticket 80 mm) de facturas emitidas **con `verifactu.activo`** muestran el QR de cotejo AEAT y el texto "VERI*FACTU", y el contenido del QR corresponde a la URL oficial con los datos del registro; las emitidas sin el flag no lo muestran.
- **SC-005**: Con la remisión activada contra el entorno de pruebas de la AEAT, una factura aceptada queda en `enviada` y una rechazada queda en `error` con su motivo, en ambos casos conservando intacto el registro local.
- **SC-006**: Un reintento de una remisión en `error` reenvía el mismo registro sin alterar la huella ni la `huella_anterior` y sin crear un eslabón adicional (verificable comparando el estado de la cadena antes y después).
- **SC-007**: Con `verifactu.activo` apagado, ninguna emisión realiza llamadas a la AEAT y el 100 % de las facturas quedan **sin huella, sin QR y fuera de la cadena** (estado `pendiente`), con el comportamiento de emisión idéntico al previo a esta feature.
- **SC-008**: Una emisión que falla al construir el registro no deja ninguna factura emitida ni eslabón parcial (verificable: la cadena queda idéntica a antes del intento).

## Assumptions

- **Envío diferido vs. bloqueo de emisión** (resuelto, ver Clarifications): la generación del registro local (huella + XML + QR) ocurre síncrona al emitir; **emitir nunca queda bloqueado por la disponibilidad ni el rechazo de la AEAT**. Si el envío falla, la factura queda `registrada`/`error` y es reintentable (FR-013, FR-014).
- **Certificado ausente con remisión activa** (resuelto, ver Clarifications): la factura se **registra localmente** y el envío queda pendiente/`error`; no impide la emisión. La validación de NIF del emisor sí es bloqueante (FR-018).
- **Separación pruebas/producción** (resuelto, ver Clarifications): entorno fijo por tenant, marcado en cada registro; los registros de `pruebas` no forman parte de la cadena fiscal de `producción` (FR-020).
- **Reutilización de infraestructura**: el certificado por tenant y la firma se apoyan en la infraestructura ya existente de la feature 022 (Facturae): `App\Support\CertificadoTenant` y el mecanismo de firma XAdES, adaptados al formato de firma que exija Verifactu (que puede diferir del de Facturae).
- **Punto de enganche**: el servicio Verifactu se invoca desde el flujo de emisión existente (`EmisorFacturas::emitir()` y el flujo POS `RegistroTicket`), sin duplicar la lógica de numeración/stock ya presente.
- **Modo "No Veri*Factu"** (registros solo para conservación/inspección sin remisión) queda **fuera de alcance**: esta feature implementa el modo Veri*Factu (remisión en tiempo real).
- **Ciclo B2B / Ley Crea y Crece** (`estado_b2b`: aceptada/rechazada/pagada) queda **fuera de alcance**; es otra feature, aunque comparta tablas.
- **Conservación a largo plazo / backups** del registro y de los XML queda fuera del alcance de esta feature (más allá de la persistencia normal en base de datos).
- **Formato exacto del algoritmo de huella, del XML y de la URL del QR**: se toman de las especificaciones técnicas oficiales de la AEAT (Orden HAC/1177/2024, FAQ huella/hash) vigentes; cualquier discrepancia se resuelve citando la fuente oficial en `docs/02-facturacion-espana.md` antes de codificar (Principio II).
