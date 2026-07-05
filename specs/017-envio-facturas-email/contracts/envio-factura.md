# Contrato — Envío de factura por email

## Endpoint

```
POST /facturas/{factura}/enviar   → facturas.enviar
Controller: FacturaController@enviar
```

- `{factura}` se recibe como **string** y se resuelve en el cuerpo del controller con
  `Factura::findOrFail($factura)` (respeta `TenantScope`). **NO** usar implicit route binding
  (pitfall de fuga entre tenants).

**Body** (form): `destinatario` (email, requerido).

## Precondiciones (validadas en backend)

1. La factura existe y pertenece al tenant activo (garantizado por `TenantScope`).
2. `estado ≠ borrador` y `≠ anulada` (solo emitida en adelante). Si no → error controlado.
3. El tenant tiene email configurado (`EmailTenant::estaConfigurado`). Si no → error controlado
   "Configura primero tu correo".
4. `destinatario` es un email válido y no vacío. En simplificada sin `cliente_email` el usuario debe
   informarlo; si falta → error de validación.

## Comportamiento

1. Resolver factura + eager loads (mismos que `pdf`: `lineas, impuestos, cliente, tenant, ...`).
2. Generar PDF con `Pdf::loadView('facturas.pdf', ['factura' => $factura])->output()`.
3. Construir `TenantMailer` → `Mail::mailer('tenant_smtp')`.
4. Enviar `new FacturaMail($factura, $pdfBytes)` a `destinatario`.
   - `FacturaMail` fija `from(remitente, remitente_nombre)` y `replyTo(responder_a)` si existe,
     cuerpo `emails.factura`, adjunto `<numero_completo>.pdf`.
5. Registrar `FacturaEvento` (`tipo_evento = 'envio_email'`, `detalle = {destinatario, resultado}`).
   - Éxito → `resultado: 'ok'`.
   - Fallo de transporte → capturar, registrar `resultado: 'error'` + motivo, y devolver error
     controlado (la factura **no** queda marcada como enviada).

## Front (listado de facturas)

- Acción "Enviar por email" en la fila/menú de acciones, visible solo si `estado ≠ borrador`.
  Si el tenant no tiene email configurado, la acción avisa (deshabilitada o mensaje al pulsar).
- Prompt/modal para confirmar/editar el `destinatario` (precargado con `cliente_email`).
- **Badge "Enviada"** en la fila cuando `Factura::fueEnviada()` es true.
- Notificaciones con toastr (flash o `window.showToast`).

## Respuestas

| Resultado | Web | JSON |
|-----------|-----|------|
| Enviada | redirect back `->with('success', 'Factura enviada a <dir>.')` | `{message}` 200 |
| Sin email config | redirect back `->with('error', 'Configura primero tu correo.')` | `{message}` 422 |
| Estado inválido | redirect back `->with('error', 'Solo se pueden enviar facturas emitidas.')` | 422 |
| Destinatario inválido | error de validación estándar | 422 |
| Fallo SMTP | redirect back `->with('error', '<motivo>')` | `{message}` 502 |
