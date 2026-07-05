# Quickstart / Validación: Email Marketing

Guía para validar la feature end-to-end. Detalles de datos en [data-model.md](./data-model.md) y
de endpoints en [contracts/](./contracts/).

## Prerequisitos

- Tenant con SMTP configurado (Configuración → Email; feature 017). Para pruebas, usar
  `Mail::fake()` en los tests o Mailtrap/Mailpit en local.
- Al menos 3 clientes en el tenant, uno de ellos **sin email** (para validar FR-015).
- Migraciones aplicadas: `php artisan migrate`.

## Escenarios de validación

### 1. Envío de campaña con progreso y resultado por destinatario (US1)

1. Ir a `GET /campanas/crear`.
2. Escribir asunto y cuerpo; seleccionar los 3 clientes (incluido el que no tiene email).
3. Enviar. El JS trocea en tandas de 8 y llama a `POST /campanas/{id}/enviar-tanda`.
4. **Esperado**: barra de progreso avanza; al final el resumen muestra 2 enviados y 1 fallido
   ("sin email"). En BD, `campana_destinatarios` refleja ese estado por fila; `campanas.estado`
   = `finalizada`.

### 2. Tenant sin SMTP (FR-007)

1. Con un tenant sin SMTP, intentar enviar una campaña.
2. **Esperado**: se impide el envío con mensaje que remite a Configuración → Email (HTTP 422 en
   `enviar-tanda`, o bloqueo previo en `store`).

### 3. Plantillas reutilizables (US2)

1. `GET /plantillas-email` → crear plantilla activa con título/asunto/cuerpo.
2. En `GET /campanas/crear`, seleccionar la plantilla → asunto y cuerpo se precargan y son
   editables.
3. Desactivar la plantilla → deja de aparecer en el selector de campañas.

### 4. Reintentar solo fallidos (US3, SC-004)

1. Provocar una campaña con ≥1 fallido (p. ej. cliente sin email o SMTP que rechaza).
2. `GET /campanas/{id}` → ver desglose por destinatario.
3. Pulsar "reintentar fallidos" → `POST /campanas/{id}/reintentar` repone a `pendiente` solo los
   fallidos; el JS los reenvía.
4. **Esperado**: los ya `enviado` no se reenvían (0 duplicados); los fallidos se reprocesan.

### 5. Aislamiento multi-tenant (Principio I / SC-005) — test-first

Feature tests con ≥2 tenants que afirman:
- Un usuario del tenant A no ve campañas/plantillas del tenant B ni sus destinatarios.
- No puede añadir como destinatario un cliente del tenant B ni enviar tanda a destinatarios de B.

## Comandos

```bash
php artisan migrate
php artisan test --filter=Campana
php artisan test --filter=PlantillaEmail
```

**Criterios de aceptación cubiertos**: SC-001..SC-005 y FR-001..FR-015 (ver spec.md).
