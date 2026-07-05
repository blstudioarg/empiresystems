# Contrato — Configuración de email del tenant

## Vista: tab "Email" en Configuración

- Archivo: `resources/views/configuracion/_tab_email.blade.php`, registrada en
  `configuracion/index.blade.php` junto al resto de tabs.
- Campos del formulario (mapeo 1:1 con las claves `email.*`): host, puerto, cifrado (select
  `ssl`/`tls`), usuario, contraseña (vacía con placeholder si ya hay guardada), remitente,
  nombre del remitente, responder_a.
- Notificaciones: **toastr** vía flash de sesión (`->with('success'|'error', ...)`), nunca alerts
  ad-hoc (regla del proyecto).
- Botón "Enviar email de prueba" que hace POST al endpoint de prueba (puede ser AJAX con
  `window.showToast` sobre la respuesta JSON, o submit normal con flash).

## Endpoint: guardar configuración

```
PUT|PATCH /configuracion/email   → configuracion.email.update
Controller: ConfiguracionController@updateEmail
Request:    UpdateEmailRequest
```

**Body** (form): `smtp_host, smtp_port, smtp_encryption, smtp_usuario, smtp_password?,
remitente, remitente_nombre?, responder_a?`

**Comportamiento**:
- Valida según `UpdateEmailRequest` (ver data-model.md).
- Por cada clave, `Configuracion::updateOrCreate(['tenant_id','clave'], ['valor','tipo','grupo'=>'email'])`
  (mismo patrón que `updateFacturacion`).
- `smtp_password`: si viene relleno → cifrar con `Crypt::encryptString` y guardar; si viene vacío →
  **no** tocar la fila existente.
- Invalidar cache de config del tenant si aplica.

**Respuesta**:
- Web: `redirect()->route('configuracion.show')->with('success', 'Configuración de email guardada correctamente.')`.
- JSON (`wantsJson`): `{ "message": "..." }` 200.

## Endpoint: enviar email de prueba

```
POST /configuracion/email/prueba   → configuracion.email.prueba
Controller: ConfiguracionController@enviarPrueba
```

**Comportamiento**:
- Construye `TenantMailer` con la config guardada.
- Si la config está incompleta → captura `EmailNoConfiguradoException` → mensaje de error controlado.
- Envía un correo simple ("Email de prueba de <tenant>") a la dirección `remitente` (o al email del
  usuario autenticado).
- Captura `Symfony\Component\Mailer\Exception\TransportExceptionInterface` (y genéricas) → mensaje de
  error claro con el motivo, sin excepción cruda.

**Respuesta**:
- Éxito: flash/JSON `success` "Email de prueba enviado a <dirección>."
- Error: flash/JSON `error` con el motivo (credenciales/servidor).

## Errores comunes → mensajes

| Situación | Mensaje al usuario |
|-----------|--------------------|
| Config incompleta | "Configura primero los datos de tu servidor de correo." |
| Auth/host inválido | "No se pudo conectar con el servidor de correo. Revisa host, puerto y credenciales." |
| Éxito | "Email de prueba enviado a &lt;dirección&gt;." |
