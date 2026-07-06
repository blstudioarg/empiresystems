# HTTP Contracts — Facturae emisión y recepción

Endpoints nuevos, dentro del grupo existente `['tenant.context', 'auth']` de `routes/web.php`.
Siguen las convenciones de nombres de las rutas actuales de `facturas`/`compras`/`configuracion`.
Toda ruta resuelve el modelo **acotado por el tenant activo** (Principio I) — nunca por binding
implícito que salte el `TenantScope` (ver memoria del proyecto sobre route binding).

## Emisión (US1)

### GET `/facturas/{factura}/facturae` → `FacturaeController@descargar`
Genera (si no existe) y descarga el XML Facturae 3.2.2 firmado de una factura **emitida**.

- **Precondición**: `factura.estado = emitida`; tenant con certificado válido configurado.
- **200**: descarga `application/xml` (nombre `{numero_completo}.xsig`/`.xml`). Si ya se generó
  antes, devuelve el mismo archivo conservado sin regenerar (FR-006).
- **422**: factura no emitida (FR-008), o certificado ausente/caducado (FR-007), o NIF de
  emisor/receptor inválido (FR-021) — mensaje claro del motivo; no se genera archivo inválido.

### POST `/facturas/{factura}/facturae` → `FacturaeController@generarYEnviar`
Genera el Facturae firmado, lo conserva asociado a la factura y lo **envía automáticamente por
email** al cliente (adjuntando XML + PDF) vía el flujo de la feature 017 (FR-006a).

- **Body**: `destinatario` (email, opcional; por defecto el email del cliente de la factura).
- **200/302**: éxito con flash `success` (toastr). Registra `FacturaEvento` `envio_facturae`.
- **422**: mismas precondiciones que arriba (no emitida / sin certificado / NIF inválido).
- **502/aviso**: si el email falla o el cliente no tiene email, el XML **queda generado y
  conservado** y se informa que el envío quedó pendiente/omitido (no se pierde la generación).

### POST `/facturas/{factura}/facturae/reenviar` → `FacturaeController@reenviar`
Reenvía por email el Facturae ya generado (sin regenerarlo).

- **Body**: `destinatario` (email, requerido).
- **200/302**: éxito; registra evento. **422**: si aún no se ha generado el Facturae.

## Recepción (US2 / US3)

### POST `/compras/importar-facturae` → `CompraFacturaeController@importar`
Sube un XML Facturae recibido de un proveedor y crea una `compra` con `origen=facturae`.

- **Body**: `archivo` (file, XML Facturae; requerido).
- **200/302**: compra creada; redirige a `compras.show`. Guarda `archivo_recibido_path`, vuelca
  cabecera/líneas/desglose, asocia proveedor por NIF, `estado_b2b=recibida`.
  - Si la **firma no es verificable** pero el XML/importes son válidos: crea la compra y muestra
    **aviso visible** de firma (FR-016a).
- **409 / aviso duplicado**: si ya existe compra con mismo proveedor+número+fecha (FR-018): no crea
  otra; informa posible duplicado.
- **422**: archivo no es Facturae válido (XML malformado / esquema incorrecto) o importes
  incoherentes (FR-016) — no crea compra; mensaje claro.
- **Proveedor inexistente**: la respuesta indica que hay que crear el proveedor con los datos del
  XML antes de completar (flujo de confirmación); el sistema ofrece crearlo (FR-015).

### GET `/compras/{compra}/facturae` → `CompraFacturaeController@descargar`
Descarga el XML original recibido de una compra con `origen=facturae` (FR-017).

- **200**: `application/xml`. **404/422**: la compra no tiene XML recibido.

### PATCH `/compras/{compra}/estado-b2b` → `CompraFacturaeController@cambiarEstadoB2b`
Cambia el estado de ciclo B2B de una compra recibida (US3).

- **Body**: `estado_b2b` ∈ {`recibida`,`aceptada`,`rechazada`,`pagada`} (requerido).
- **200/302**: guarda `estado_b2b` y `estado_b2b_fecha = now()` (FR-019).
- **422**: valor inválido, o compra sin `origen=facturae`.

El **listado** `GET /compras` (existente) añade filtro/columna por `estado_b2b` (FR-020).

## Configuración del certificado del emisor

### PATCH `/configuracion/certificado` → `ConfiguracionController@updateCertificado`
Sube/reemplaza el certificado del tenant para firmar Facturae.

- **Body**: `certificado` (file `.p12`/`.pfx`, requerido), `password` (string, requerido).
- **200/302**: valida que la password abre el `.p12`, que hay clave privada usable y que no está
  caducado; guarda el archivo (disco privado) + password **cifrada** (`configuraciones`); muestra
  titular y fecha de caducidad (FR-010/011/012).
- **422**: `.p12` ilegible, password incorrecta, sin clave privada, o certificado caducado —
  mensaje claro; no se guarda un certificado inservible.

### POST `/configuracion/certificado/verificar-vies` → `ConfiguracionController@verificarVies` (o en el flujo de factura)
Verifica un NIF-IVA contra VIES para una entrega intracomunitaria (US4, FR-022).

- **Body**: `nif_iva` (string, requerido), `pais` (código país, requerido).
- **200**: `{ valido: bool, nombre?: string, verificado: bool }`. Si VIES está indisponible:
  `{ verificado: false }` con aviso, sin bloquear (resultado cacheado por NIF-IVA).

## Notas transversales

- Todas las respuestas de éxito/erro usan **flash de sesión** → toastr (patrón del proyecto), o
  JSON `{ message }` cuando `wantsJson()`.
- Ningún endpoint acepta importes desde el cliente: los datos de la factura provienen del modelo ya
  emitido (Principio III).
- Descargas de XML (emitido y recibido) y el `.p12` viven en el disco privado `documentos`,
  servidos solo al tenant propietario (Principio I).
