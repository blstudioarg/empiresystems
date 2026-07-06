# Research — Facturae emisión y recepción (Fase 0)

Resuelve las incógnitas técnicas del plan. Cada decisión indica alternativa descartada y encaje con
la constitución (sobre todo Principio V — hosting compartido — y Principio II — normativa).

## R1. Librería de generación/firma/importación de Facturae

- **Decision**: Usar **`josemmo/facturae-php`** (`composer require josemmo/facturae-php`).
- **Rationale**:
  - PHP **puro**, sin dependencias nativas más allá de `openssl` y `dom`/`libxml` (extensiones
    estándar presentes en el hosting objetivo) → cumple Principio V sin VPS ni servicio externo.
  - Genera **Facturae 3.2.2** y aplica **firma XAdES-EPES** directamente desde un **certificado
    PKCS#12** (`.p12`/`.pfx`) + contraseña — exactamente el modelo confirmado en Clarifications.
  - Incluye **lectura/importación** de Facturae (`FacturaeFile`/parser), cubriendo el lado de
    recepción sin una segunda librería.
  - Licencia **MIT** (compatible con el proyecto), mantenida y ampliamente usada en el ecosistema
    español (FACe/FACeB2B).
- **Alternatives considered**:
  - *Construir el XML + firma XAdES a mano* (DOM + `xmlseclibs`): más control pero reimplementar la
    firma XAdES-EPES es costoso y propenso a errores de cumplimiento; rechazado por Principio IV/V.
  - *Servicio/API externa de firma o e-invoicing SaaS*: añade dependencia de red, coste y datos
    saliendo del sistema; choca con Principio V y con "cálculos/firma en backend propio".
  - *`martinlaregina/Facturae-PHP`* (fork): mismo origen, menos mantenido; se prefiere el upstream.
- **Notas de integración**: la librería trabaja con su propio modelo de factura; el
  `GeneradorFacturae` mapea `Factura`+`FacturaLinea`+`FacturaImpuesto` del dominio a ese modelo,
  incluyendo régimen impositivo congelado, recargo de equivalencia, IRPF y las menciones especiales.

## R2. Almacenamiento del certificado del tenant (.p12 + contraseña)

- **Decision**: El **archivo `.p12`/`.pfx`** se guarda en el disco privado `documentos`
  (particionado por tenant, vía `AlmacenArchivos`); la **contraseña** se guarda **cifrada** en la
  tabla `configuraciones` (grupo `certificado`) con `Crypt`, replicando el patrón de
  `App\Support\EmailTenant` para la password SMTP. Un helper `App\Support\CertificadoTenant`
  centraliza lectura/escritura y expone metadatos (titular, caducidad, estado).
- **Rationale**: reutiliza infraestructura ya probada (disco privado por tenant + `Crypt`), mantiene
  el material sensible fuera del front (la password nunca vuelve en claro), y respeta el aislamiento
  por tenant. No se guarda la password en claro ni el `.p12` en un disco público.
- **Alternatives considered**:
  - *Guardar el `.p12` como blob en base de datos*: innecesario y complica backups/consultas; el
    disco privado ya cifra a nivel de acceso y es el patrón del gestor documental (feature 019).
  - *Pedir el certificado en cada emisión*: mala UX y contrario a "configurar una vez"; rechazado.
- **Validación**: al subirlo se comprueba con `openssl`/la librería que (a) la contraseña abre el
  `.p12`, (b) contiene clave privada usable para firmar, (c) no está caducado; se advierte la fecha
  de caducidad. Un certificado caducado/inválido bloquea la generación (FR-007).

## R3. Firma XAdES-EPES en hosting compartido

- **Decision**: Firmar **en servidor** dentro del `GeneradorFacturae`, delegando en
  `josemmo/facturae-php` (que usa `openssl`). Sin timestamp TSA en v1 (XAdES-EPES no lo exige; el
  sello de tiempo RFC3161 queda como mejora futura, no requerido por el alcance).
- **Rationale**: `openssl` está disponible en el hosting objetivo; no se requiere `soap` ni
  binarios externos. Mantiene todo el proceso en backend (Principio III).
- **Alternatives considered**: *firma en cliente/navegador con el certificado del usuario* —
  incompatible con "firma en servidor" confirmada y con la conservación server-side del XML firmado.

## R4. Verificación VIES (NIF-IVA intracomunitario)

- **Decision**: Consultar VIES por **HTTP** (cliente `Http` de Laravel) contra el servicio de la
  Comisión Europea, con `timeout` corto; cualquier fallo/timeout devuelve resultado "no verificado"
  **sin bloquear irreversiblemente** (FR-022). Cachear el resultado por NIF-IVA un plazo corto para
  no repetir llamadas. Es una acción **explícita** del usuario para operaciones intracomunitarias
  (E5), no una llamada en cada emisión.
- **Rationale**: evita depender de la extensión `soap` (no siempre presente en hosting compartido);
  el patrón `Http` + `Http::fake()` en tests ya es el del proyecto (ver `GeolocalizadorIp`). VIES es
  best-effort por naturaleza (servicio de terceros con caídas frecuentes).
- **Alternatives considered**: *`SoapClient` contra el WSDL de VIES* — dependencia de `soap` y más
  frágil en tests; rechazado por Principio V.

## R5. Validación NIF/CIF/NIE (dígito de control)

- **Decision**: Implementar `App\Support\ValidadorIdentificacionFiscal` como **lógica pura**
  (sin dependencias): NIF persona física (8 dígitos + letra módulo 23), NIE (`X/Y/Z`+7+letra),
  NIF de entidad/antiguo CIF (letra tipo + 7 + carácter de control dígito o letra según tipo). Se
  aplica al emisor (datos del tenant) y al receptor antes de generar el Facturae (FR-021).
- **Rationale**: algoritmo estable y bien definido (ver `docs/02-facturacion-espana.md` §4); es
  lógica crítica y barata de testear exhaustivamente (Principio IV, test-first). No amerita
  librería externa.
- **Alternatives considered**: *paquete Composer de validación fiscal* — dependencia innecesaria
  para un algoritmo de pocas líneas; rechazado por Principio V/simplicidad.

## R6. Envío automático por email del Facturae

- **Decision**: Reutilizar `TenantMailer` + `App\Mail\FacturaMail` (feature 017). El servicio
  `EnvioFacturae` genera el XML, lo conserva y, tras ello, adjunta el **XML Facturae** (además del
  PDF ya existente) al `FacturaMail` y lo envía al email del cliente. Se registra el envío como
  `FacturaEvento` (`tipo_evento = 'envio_facturae'`), igual que el envío de PDF actual. Si el
  cliente no tiene email o el SMTP no está configurado/falla, la generación se conserva y el envío
  queda omitido con aviso (FR-006a), sin romper la operación.
- **Rationale**: no reinventa el canal de email; mantiene la trazabilidad por `factura_eventos`.
- **Alternatives considered**: *enviar en un job en cola* — el proyecto no asume worker de colas en
  hosting compartido; se envía en request como el envío de PDF actual, con degradación ante fallo.

## R7. Prerrequisito de esquema — columnas documentadas pero no creadas

- **Hallazgo**: `docs/03-modelo-datos.md` describe `calificacion_operacion`/`causa_exencion`/
  `mencion_legal` en `factura_lineas` y `origen`/`formato_recepcion`/`archivo_recibido_path`/
  `estado_b2b`/`estado_b2b_fecha` en `compras`, pero las migraciones actuales
  (`create_factura_lineas_table`, `create_compras_table`) **no** las incluyen.
- **Decision**: Esta feature crea ambas migraciones de tipo `ALTER TABLE` (columnas nuevas,
  nullable/con default, sin romper datos existentes). El desglose de impuestos (`factura_impuestos`)
  no necesita cambios: la calificación/exención vive a nivel de línea y se agrega al desglose.
- **Rationale**: son el sustrato de datos que consume el generador de Facturae; sin ellas no se
  puede reflejar ISP/exención. Se materializa aquí porque es donde se usan por primera vez.

## R8. Detección de duplicados en recepción

- **Decision**: Antes de crear la compra al importar, buscar una compra existente del mismo tenant
  con mismo **proveedor (NIF) + número de documento + fecha**; si existe, avisar de posible
  duplicado y no crear otra automáticamente (FR-018).
- **Rationale**: evita duplicar asientos de compra por reimportar el mismo XML. Coincide con la
  clave natural de una factura de proveedor.
- **Alternatives considered**: *hash del XML* — frágil (dos envíos del mismo documento pueden diferir
  en bytes/firma); la clave de negocio es más robusta.
