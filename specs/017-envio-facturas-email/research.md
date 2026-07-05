# Research — Envío de facturas por email (SMTP por tenant)

Todas las decisiones abiertas quedaron resueltas en la conversación de diseño previa a la spec. Se
consolidan aquí con su justificación.

## D1 — Modelo de remitente: SMTP propio por tenant, sin global ni fallback

- **Decisión**: cada tenant configura su propia cuenta SMTP; los emails de factura salen siempre de
  esa cuenta. Si no está configurada, la acción de enviar no se ofrece / se bloquea con error
  controlado.
- **Rationale**: la factura debe llegar al cliente con la identidad de la empresa-tenant (imagen y
  entregabilidad correctas: SPF/DKIM del propio dominio del tenant). Un SMTP de plataforma mandando
  "en nombre de" dominios ajenos degrada la entregabilidad y complica el cumplimiento. En hosting
  compartido cada tenant ya suele tener su cuenta de correo del hosting.
- **Alternativas descartadas**: (a) SMTP global único — mala imagen/entregabilidad; (b) híbrido con
  fallback al global — más superficie de código y de fallo para v1, se puede añadir después sin romper
  este diseño.

## D2 — Almacenamiento de la config: tabla `configuraciones`, grupo `email`

- **Decisión**: reutilizar el almacén clave-valor existente con `grupo = 'email'`. Claves:
  `email.smtp_host`, `email.smtp_port`, `email.smtp_encryption`, `email.smtp_usuario`,
  `email.smtp_password`, `email.remitente`, `email.remitente_nombre`, `email.responder_a`.
- **Rationale**: el patrón ya existe, ya está tenant-scoped y ya tiene resolución tenant→global. El
  doc de modelo de datos ya anticipa el grupo `email` y la clave `email.remitente`. Cero migraciones.
- **Alternativas descartadas**: columnas nuevas en `tenants` — reservadas por convención para valores
  1:1 tipo archivo (logos); la config SMTP es multi-campo y encaja mejor en clave-valor.

## D3 — Cifrado de la contraseña

- **Decisión**: `Crypt::encryptString()` al guardar, `Crypt::decryptString()` solo al construir el
  mailer. En la vista, el campo password se muestra vacío con placeholder; se actualiza únicamente si
  el usuario escribe uno nuevo (campo vacío = conservar el anterior).
- **Rationale**: `APP_KEY` de Laravel ya gestiona el cifrado simétrico; evita guardar credenciales en
  claro en BD. Nunca se devuelve la contraseña al front.
- **Alternativas descartadas**: hashing (no sirve, hay que recuperar el valor original para el SMTP);
  guardar en claro (inaceptable).

## D4 — Construcción del mailer en runtime

- **Decisión**: servicio `TenantMailer` que lee las claves del tenant activo, valida completitud
  (host, puerto, usuario, password, remitente presentes; si falta algo lanza
  `EmailNoConfiguradoException`), hace `Config::set('mail.mailers.tenant_smtp', [...])` con transporte
  `smtp` y devuelve `Mail::mailer('tenant_smtp')`. El `from` (remitente + nombre) y el `reply-to`
  (responder_a, si existe) se aplican en el `Mailable`.
- **Rationale**: patrón oficial de Laravel para mailers dinámicos; no muta el `.env` ni el mailer
  `default` (que queda intacto para lo transaccional del SaaS a futuro). Aísla por tenant al leer solo
  `tenant()`.
- **Alternativas descartadas**: mutar `config('mail.mailers.smtp')` global — contaminaría el mailer
  por defecto y sería sensible al orden entre requests.

## D5 — Envío síncrono (no cola)

- **Decisión**: enviar dentro del request. Documentado como deuda técnica; migrar a
  `ShouldQueue` + `queue:work` cuando el volumen lo pida.
- **Rationale**: el hosting compartido no garantiza worker/supervisor; un email con un PDF adjunto
  completa holgadamente dentro del timeout del request. Simplicidad (Principio V).
- **Alternativas descartadas**: cola con driver `database` + cron — más piezas móviles de las
  necesarias para el volumen actual; se adopta cuando escale.

## D6 — Generación del PDF adjunto

- **Decisión**: reutilizar `Pdf::loadView('facturas.pdf', ['factura' => $factura])` (misma vista que
  `FacturaController::pdf`) y adjuntar con `->attachData($pdf->output(), $nombre.'.pdf', ['mime' => 'application/pdf'])`.
- **Rationale**: garantiza que el adjunto es idéntico al PDF descargable; una sola fuente de verdad.
- **Alternativas descartadas**: regenerar el PDF con otro layout — duplicaría lógica.

## D7 — Trazabilidad: `factura_eventos`

- **Decisión**: registrar cada envío como fila en `factura_eventos` con
  `tipo_evento = 'envio_email'` y `detalle` json `{ destinatario, resultado }` + `ocurrido_at`. El
  listado de facturas marca "enviada" si existe al menos un evento `envio_email` con resultado OK.
- **Rationale**: log append-only ya existente y coherente con el modelo Verifactu; sin tabla nueva
  (Principio V). El reenvío añade eventos nuevos sin borrar los previos.
- **Alternativas descartadas**: columna `enviada_at` en `facturas` — perdería el historial de
  múltiples envíos y tocaría una tabla fiscal inmutable.

## D8 — Resolución de la factura en el endpoint de envío

- **Decisión**: ruta `POST /facturas/{factura}/enviar` con parámetro **string** resuelto en el cuerpo
  del controller (`Factura::findOrFail($factura)`), no por implicit route binding.
- **Rationale**: el implicit binding se salta el `TenantScope` (pitfall documentado del proyecto,
  memoria `project_tenant_route_binding`), abriendo fuga entre tenants. `FacturaController::pdf` ya
  aplica exactamente este patrón.

## D9 — Email de prueba

- **Decisión**: endpoint `POST /configuracion/email/prueba` que arma el `TenantMailer` con la config
  ya guardada y manda un correo simple a la dirección del remitente (o al email del usuario
  autenticado). Devuelve éxito/error como flash/JSON.
- **Rationale**: valida host/puerto/credenciales sin depender de una factura real; reduce fricción de
  onboarding. Cualquier `TransportException` se captura y se traduce a mensaje claro.
- **Alternativas descartadas**: probar solo al primer envío real de factura — peor experiencia de
  diagnóstico.
