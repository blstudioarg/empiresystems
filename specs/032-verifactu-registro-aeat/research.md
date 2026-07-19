# Research — Verifactu (feature 032)

**Fase**: 0 · **Fecha**: 2026-07-19 · **Plan**: [plan.md](./plan.md)

> Todas las reglas fiscales aquí decididas deben volcarse a `docs/02-facturacion-espana.md` **antes**
> de codificar, citando fuente BOE/AEAT (Principio II de la constitución).

---

## R1 — Algoritmo de huella y encadenamiento

**Decisión**: Calcular la huella como **SHA-256 en hexadecimal mayúsculas** sobre una cadena de
concatenación de campos en el **orden exacto** que fija la especificación técnica de la AEAT, con el
formato `CLAVE=valor&CLAVE=valor…`, incluyendo como último elemento la **huella del registro
anterior**. Para el **primer registro** de la cadena de un tenant, el campo de huella anterior va
**vacío** (cadena vacía), no un valor sintético inventado.

Campos que entran en la huella del **registro de alta** (según especificación AEAT):
`IDEmisorFactura`, `NumSerieFactura`, `FechaExpedicionFactura`, `TipoFactura`, `CuotaTotal`,
`ImporteTotal`, `Huella` (del registro anterior), `FechaHoraHusoGenRegistro`.

El registro de **anulación** tiene su propio conjunto de campos, más reducido:
`IDEmisorFacturaAnulada`, `NumSerieFacturaAnulada`, `FechaExpedicionFacturaAnulada`, `Huella`
(anterior), `FechaHoraHusoGenRegistro`.

**Rationale**: El algoritmo no admite interpretación: si el orden, el formato de los importes o el
formato de la fecha difieren, la AEAT rechaza el registro y la cadena queda inservible. Por eso se
aísla en un helper puro (`App\Support\HuellaVerifactu`) sin dependencias de base de datos, testeable
directamente contra los **vectores de prueba oficiales** que publica la AEAT.

**Alternativas consideradas**:
- *Hashear un serializado JSON del registro completo*: rechazado — no es lo que exige la norma; la
  AEAT valida la huella recomputándola con su propio orden de campos.
- *Delegar en `josemmo/facturae-php`*: rechazado — la versión instalada (^1.8) **no** implementa
  Verifactu (se verificó: no hay clases de Verifactu en `vendor/josemmo/facturae-php/src/`). Facturae
  y Verifactu son formatos distintos e independientes (docs/02 §2).

**Pendiente de verificar contra fuente oficial al implementar**: formato exacto de
`FechaHoraHusoGenRegistro` (ISO 8601 con huso), número de decimales y separador de `CuotaTotal` /
`ImporteTotal`, y si la huella va en mayúsculas o minúsculas. **Estos tres detalles se confirman
contra la documentación técnica y los vectores de prueba de la AEAT antes de escribir el algoritmo**,
y el test unitario se construye a partir de un vector oficial (no de un valor inventado por nosotros).

---

## R2 — Serialización del encadenamiento bajo concurrencia

**Decisión**: Tomar el "último registro de la cadena del tenant" **dentro de la misma transacción de
emisión** y con **bloqueo pesimista** (`lockForUpdate()`) sobre la fila que representa el último
eslabón, filtrando por `tenant_id`. El registro Verifactu se genera dentro de la transacción ya
existente en `EmisorFacturas::emitir()`, que también asigna número de serie y mueve stock.

**Rationale**: Es exactamente el mismo problema que la numeración correlativa sin huecos, que el
proyecto ya resolvió en `NumeradorFacturas` con transacción + bloqueo (Principio III lo exige
literalmente). Reutilizar el patrón evita inventar un mecanismo nuevo y garantiza que dos emisiones
simultáneas del mismo tenant no lean la misma `huella_anterior`. Al ir en la **misma** transacción
que la emisión, se obtiene la atomicidad que pide FR-001 de forma natural: si algo falla, no queda ni
factura emitida ni eslabón parcial.

**Alternativas consideradas**:
- *Cola serializada por tenant (un job que encadena)*: rechazado — desacopla el sello del acto de
  emitir, abriendo una ventana en la que existe una factura emitida sin registro. Contradice FR-001.
- *Lock aplicativo (cache lock)*: rechazado — menos fiable que el lock de base de datos y añade una
  dependencia de driver de caché que en hosting compartido puede ser `file`.

**Nota de diseño**: conviene que el "puntero al último eslabón" sea consultable de forma barata.
Se resuelve con un índice por `(tenant_id, registrada_at)` / `(tenant_id, id)` sobre las facturas con
huella no nula, sin crear una tabla nueva (ver [data-model.md](./data-model.md)).

---

## R3 — Remisión al web service de la AEAT

**Decisión**: Cliente propio (`App\Services\RemisorVerifactu`) que construye el sobre SOAP del
servicio de remisión de registros de facturación de la AEAT y lo envía autenticándose con **TLS
mutuo** usando el certificado PKCS#12 del tenant (`CertificadoTenant::paraFirmar()`). Dos endpoints
según `verifactu.entorno`: preproducción (pruebas) y producción. El envío se despacha como **job en
cola** (`RemitirRegistroVerifactu`) mediante `DB::afterCommit()`, de modo que solo se envía lo que
realmente quedó confirmado en base de datos.

Respuestas a contemplar: aceptación completa (`Correcto`), aceptación con errores parciales
(`AceptadoConErrores`) y rechazo (`Incorrecto`), más los fallos de transporte (timeout, TLS, 5xx).
Mapeo a estado: aceptado → `enviada`; rechazo o error parcial que afecte al registro → `error` con el
código y descripción de la AEAT guardados en `factura_eventos`; fallo de transporte → `error`
reintentable.

**Rationale**: La AEAT expone el servicio como SOAP con autenticación por certificado; no hay SDK
oficial PHP. Encapsularlo en un servicio propio mantiene el Principio III (un único lugar) y permite
**mockear el transporte en los tests** sin llamar a la AEAT real. Usar la cola saca la latencia y los
fallos del camino crítico de emitir, que es justo lo que exige la decisión de clarify ("emitir nunca
se bloquea").

**Alternativas consideradas**:
- *Envío síncrono dentro de la transacción*: rechazado — acopla la emisión a la disponibilidad de la
  AEAT y arriesga transacciones largas con una llamada de red dentro. Contradice la clarificación.
- *Librería de terceros para Verifactu*: descartada para v1 por no haber una opción madura y estable
  en el ecosistema PHP en el momento de planificar; se reevalúa si aparece.

**Pendiente de verificar al implementar**: URLs exactas de los endpoints de preproducción y
producción, el WSDL vigente y si el proyecto puede apoyarse en `ext-soap` (disponibilidad en hosting
compartido) o conviene construir el sobre a mano y enviarlo con Guzzle + opciones `cert`/`ssl_key`.
**Se prefiere la vía Guzzle** si `ext-soap` no está garantizada en el hosting objetivo (Principio V).

---

## R4 — Generación del QR de cotejo

**Decisión**: Componer la **URL oficial de cotejo** de la AEAT con los parámetros del registro
(NIF del emisor, número de serie de factura, fecha de expedición e importe total) y renderizar el QR
como **imagen embebida en el PDF** (data URI), usando una librería PHP pura sin dependencias de
extensiones gráficas obligatorias.

**Rationale**: DomPDF (ya en uso para los PDF) no genera QR; hay que producir la imagen. La URL debe
ser la oficial para que el receptor pueda cotejar la factura en la sede de la AEAT — es un requisito
de la norma, y fue la decisión explícita del usuario al abrir la feature. Una librería pura-PHP evita
depender de `ext-gd`/`imagick`, alineado con el Principio V (hosting compartido).

**Alternativas consideradas**:
- *Generar el QR en el navegador (JS)*: rechazado — el QR forma parte del documento fiscal; debe
  generarse en backend (Principio III) y estar presente en el PDF descargado o enviado por email.
- *Servicio externo de generación de QR*: rechazado — dependencia de red innecesaria y fuga de datos
  fiscales a un tercero.

**Pendiente de verificar al implementar**: la URL base exacta del servicio de cotejo (difiere entre
preproducción y producción) y el nombre y orden de los parámetros de la query string, según la
especificación técnica del QR de la AEAT. También el tamaño mínimo del QR impreso (la norma fija un
rango de tamaño) para respetarlo en A4 y, sobre todo, en el ticket de 80 mm.

---

## R5 — Configuración por tenant y arranque de la cadena

**Decisión**: `App\Support\VerifactuTenant` con dos claves en `configuraciones` (grupo `verifactu`),
siguiendo el patrón ya establecido por `ConfigTenant`, `CertificadoTenant` y `EmailTenant`:
- `verifactu.activo` (bool, default `false`) — **flag único todo-o-nada** (decisión de clarify).
- `verifactu.entorno` (`pruebas` | `produccion`, default `pruebas`).

Al **encender** el flag, la cadena del tenant arranca en la siguiente factura emitida. Las facturas
emitidas previamente con el flag apagado **no** se registran retroactivamente y quedan fuera de la
cadena (no tienen huella). Al intentar **cambiar de entorno** con una cadena ya iniciada, el sistema
lo impide (FR-020), porque mezclaría registros de prueba con la cadena fiscal real.

**Rationale**: Un único flag es lo más simple que cumple (Principio V / YAGNI) y refleja que la
obligación normativa no arranca hasta 2027 (docs/02 §1): muchos tenants aún no aplican y no tiene
sentido ensuciar sus facturas con huella y QR. El default `false` garantiza que activar la feature no
cambia el comportamiento de ningún tenant existente hasta que alguien lo encienda a conciencia.

**Alternativas consideradas**:
- *Dos flags (registrar vs. enviar)*: evaluado y descartado por el usuario — más superficie y más
  casos que testear para un beneficio (fase intermedia "registro sí, envío no") que no se necesita.
- *Flag global de aplicación en vez de por tenant*: rechazado — viola el Principio I: cada tenant es
  un obligado tributario distinto, con su propio calendario de entrada en vigor y su certificado.

---

## R6 — Reintento de envíos en error

**Decisión**: Doble vía (decisión de clarify):
1. **Automático**: comando `verifactu:reintentar` registrado en `bootstrap/app.php` → `withSchedule`,
   junto a los ya existentes (`logs:purgar`, `facturae:purgar`, `importaciones:purgar`…). Reprocesa
   las facturas en `verifactu_estado = error` reencolando el envío.
2. **Manual**: acción desde la propia factura (`VerifactuController`) para forzar el reintento.

En **ambos casos** se reenvía el **registro ya sellado** — misma `huella` y misma `huella_anterior`.
El reintento **nunca** recalcula la huella ni añade un eslabón nuevo (FR-013): solo cambia el estado
de envío y añade un evento al log.

**Rationale**: El automático cubre las caídas transitorias de la AEAT sin intervención humana; el
manual da control inmediato cuando el usuario ve una factura en error. El patrón de comando
programado ya está establecido en el proyecto, así que no se inventa infraestructura nueva
(Principio V).

**Riesgo controlado**: reintentar un registro que la AEAT **ya aceptó** (p. ej. la respuesta se
perdió por timeout) podría duplicarlo. Se mitiga porque el registro remitido lleva su identificación
única (emisor + serie/número + fecha) y la AEAT trata el reenvío idéntico como duplicado idempotente;
además se acota el número de reintentos automáticos para no insistir indefinidamente sobre un rechazo
funcional (que no se arregla reintentando).

---

## R7 — Alcance del registro de anulación

**Decisión**: Implementar el **registro de anulación** encadenado (FR-015) para el caso en que una
factura emitida se anule. En el modelo actual la corrección ordinaria se hace por **rectificativa**
(que genera su propio registro de alta, encadenado como cualquier otro), de modo que la anulación
propiamente dicha es el caso menos frecuente y se implementa después del flujo de alta.

**Rationale**: La norma distingue registro de **alta** y registro de **anulación**, con conjuntos de
campos y huella distintos (ver R1). Al ser el camino menos común y depender del mismo mecanismo de
encadenamiento, se prioriza por detrás del alta y de la remisión, pero dentro del alcance.

**Alternativas consideradas**:
- *Dejar la anulación fuera de alcance*: rechazado — el usuario incluyó explícitamente la anulación
  al definir el alcance de la feature.

---

## Resumen de dependencias nuevas

| Dependencia | Para qué | Restricción |
|-------------|----------|-------------|
| Librería de QR (PHP puro) | Renderizar el QR de cotejo en el PDF | Sin exigir `ext-gd`/`imagick` (Principio V) |
| Cliente HTTP/SOAP con TLS mutuo | Remitir el registro a la AEAT | Preferir Guzzle + certificado si `ext-soap` no está garantizada |

## Fuentes a citar en `docs/02-facturacion-espana.md`

- AEAT — especificación técnica de la huella/hash de los registros de facturación.
- AEAT — especificación técnica del código QR de la factura (URL de cotejo, tamaño).
- Orden HAC/1177/2024 — formato XML del registro de facturación.
- AEAT — documentación del servicio web de remisión (WSDL, endpoints de preproducción/producción).
- RD 1007/2023 y RD 254/2025 — obligaciones del SIF y calendario de entrada en vigor.
