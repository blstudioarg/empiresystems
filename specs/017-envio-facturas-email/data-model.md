# Data Model — Envío de facturas por email

**Sin migraciones nuevas.** Se reutilizan `configuraciones` y `factura_eventos`.

## Claves de configuración (tabla `configuraciones`, grupo `email`)

Todas tenant-scoped (`tenant_id` = tenant activo). Resolución tenant→global estándar. El catálogo y
los defaults se centralizan en `App\Support\EmailTenant` (constantes `CLAVE_*` / `DEFAULT_*`), como
hace `AparienciaTenant`.

| clave | tipo | requerida | default | notas |
|-------|------|-----------|---------|-------|
| `email.smtp_host` | string | sí | `''` | ej. `smtp.hostinger.com` |
| `email.smtp_port` | integer | sí | `465` | típico `465` (ssl) / `587` (tls) |
| `email.smtp_encryption` | string | sí | `ssl` | `ssl` \| `tls` |
| `email.smtp_usuario` | string | sí | `''` | usuario de autenticación SMTP |
| `email.smtp_password` | string | sí | `''` | **cifrada** con `Crypt::encryptString`; nunca en claro al front |
| `email.remitente` | string | sí | `''` | dirección `From` (email válido) |
| `email.remitente_nombre` | string | no | `''` | nombre visible del `From` |
| `email.responder_a` | string | no | `''` | `Reply-To` opcional (email válido si se informa) |

**Config "completa"** (habilita el envío) = `smtp_host`, `smtp_port`, `smtp_encryption`,
`smtp_usuario`, `smtp_password` y `remitente` no vacíos. `EmailTenant::estaConfigurado($tenantId)`
resuelve este booleano; el `TenantMailer` lanza `EmailNoConfiguradoException` si no se cumple.

### Reglas de validación (UpdateEmailRequest)

- `smtp_host`: required, string, max 255.
- `smtp_port`: required, integer, entre 1 y 65535.
- `smtp_encryption`: required, in `ssl,tls`.
- `smtp_usuario`: required, string, max 255.
- `smtp_password`: nullable, string (vacío = conservar la anterior; required solo si aún no hay
  ninguna guardada).
- `remitente`: required, email.
- `remitente_nombre`: nullable, string, max 255.
- `responder_a`: nullable, email.

### Regla de la contraseña (no sobrescribir con vacío)

Al guardar: si `smtp_password` viene relleno → `Crypt::encryptString($valor)` y persistir. Si viene
vacío → no tocar la fila `email.smtp_password` existente. En la vista, el `<input type="password">`
se renderiza siempre vacío con placeholder `••••••••` cuando ya hay una guardada.

## Evento de envío (tabla `factura_eventos`)

Una fila por envío (incluye reenvíos). Modelo `App\Models\FacturaEvento` (ya existe, append-only).

| campo | valor |
|-------|-------|
| `tenant_id` | tenant activo |
| `factura_id` | factura enviada |
| `tipo_evento` | `'envio_email'` |
| `detalle` (json) | `{ "destinatario": "...", "resultado": "ok" \| "error", "error": "<msg opcional>" }` |
| `ocurrido_at` | timestamp del intento |
| `huella` | null (no participa del encadenamiento Verifactu) |

**Derivado — "factura enviada"**: existe ≥1 `factura_eventos` con `tipo_evento = 'envio_email'` y
`detalle->resultado = 'ok'` para esa factura. Se expone como `Factura::fueEnviada()` (o accessor
`enviada`) para el badge del listado. Se recomienda registrar también los intentos fallidos
(`resultado = error`) para diagnóstico, sin que cuenten como "enviada".

## Estados y transiciones

El envío **no** cambia el estado fiscal de la factura (`estado` sigue igual: `emitida`, `pagada`…).
Precondición: `estado ∈ {emitida, pagada, vencida, rectificada}` — cualquier estado ≠ `borrador` y
≠ `anulada`. En `borrador` la acción de enviar no se ofrece.

## Entidades tocadas (resumen)

- **`Configuracion`** (existente): +8 claves grupo `email`. Sin cambios de esquema.
- **`FacturaEvento`** (existente): nuevo valor de `tipo_evento`. Sin cambios de esquema.
- **`Factura`** (existente): +método `fueEnviada()` / relación ya existente `eventos()`. Sin cambios
  de esquema.
