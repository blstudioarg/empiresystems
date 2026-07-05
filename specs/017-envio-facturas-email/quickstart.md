# Quickstart — Validación del envío de facturas por email

Guía para validar la feature end-to-end. Detalles de campos/endpoints en `contracts/` y
`data-model.md`.

## Prerrequisitos

- Entorno de desarrollo Laravel corriendo (`php artisan serve`) con un tenant demo y su dominio.
- `APP_KEY` presente (necesaria para `Crypt`).
- Para pruebas automáticas: `Mail::fake()` (no requiere SMTP real).
- Para prueba manual real: credenciales SMTP válidas (p. ej. una cuenta de correo del hosting o un
  Mailtrap/servicio de captura).

## Escenario 1 — Configurar el correo del tenant (US1)

1. Entrar en `Configuración → Email`.
2. Rellenar host, puerto, cifrado, usuario, contraseña, remitente. Guardar.
3. **Esperado**: toast de éxito; recargar la pestaña muestra todo relleno salvo la contraseña
   (enmascarada). Guardar de nuevo sin tocar la contraseña **conserva** la anterior.
4. Verificar en BD que `email.smtp_password` NO está en claro (valor cifrado).

## Escenario 2 — Email de prueba (US1)

1. Con la config guardada, pulsar "Enviar email de prueba".
2. **Esperado (credenciales válidas)**: toast de éxito y correo recibido en la dirección remitente.
3. Cambiar el puerto/host a un valor inválido, reintentar.
4. **Esperado (inválidas)**: toast de error claro, **sin** stack trace ni excepción cruda.

## Escenario 3 — Enviar una factura emitida (US2)

1. Abrir una factura en estado **emitida** con cliente que tenga email.
2. Acción "Enviar por email" → confirmar destinatario (precargado con `cliente_email`) → enviar.
3. **Esperado**: toast de éxito; el cliente recibe el correo con el PDF adjunto y remitente del
   tenant; el listado marca la factura como **Enviada**; existe un `factura_eventos` con
   `tipo_evento = 'envio_email'` y `resultado = 'ok'`.

## Escenario 4 — Casos de error controlado

1. Tenant **sin** email configurado → intentar enviar factura → **Esperado**: aviso "configura tu
   correo", sin envío.
2. Factura en **borrador** → la acción de enviar no está disponible.
3. Factura simplificada sin `cliente_email` y sin informar destinatario → **Esperado**: error de
   validación pidiendo dirección.
4. Fallo del servidor SMTP durante el envío → **Esperado**: error controlado, la factura NO queda
   marcada como enviada.

## Escenario 5 — Aislamiento multi-tenant (Principio I)

1. Crear 2 tenants, cada uno con su config de email y sus facturas.
2. **Esperado**: el envío del tenant A usa exclusivamente las credenciales/remitente de A; una
   factura de B no es accesible ni enviable desde A (`findOrFail` → 404 por `TenantScope`).

## Comandos de test

```bash
php artisan test --filter=ConfiguracionEmailTest
php artisan test --filter=FacturaEnvioEmailTest
```

Aserciones clave: `Mail::assertSent(FacturaMail::class, ...)`, contraseña cifrada en BD,
`factura_eventos` creado con `resultado ok/error`, fuga entre tenants bloqueada, errores devueltos
como flash `error` (no excepción).
