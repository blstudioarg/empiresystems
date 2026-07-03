# Quickstart: Registro y aprobación de usuarios

Guía de validación end-to-end. Detalles de campos en [data-model.md](./data-model.md) y rutas en
[contracts/http.md](./contracts/http.md).

## Prerrequisitos

- App corriendo (`php artisan serve`) con al menos un tenant activo en la BD.
- Migraciones aplicadas (incluye la nueva columna `estado`).

## Escenario 1 — Registro crea cuenta pendiente y bloquea login (US1)

1. Sin sesión, ir a `/registro`. Completar nombre, email nuevo, contraseña + confirmación.
2. Enviar → redirige a `/login` con toast de "solicitud registrada".
3. Verificar en BD: usuario con `estado='pendiente'`, `activo=false`, `tenant_id` = tenant por
   defecto.
4. Intentar loguearse con esas credenciales → rechazado con mensaje de "cuenta no aprobada".

**Esperado**: cuenta creada, sin acceso.

## Escenario 2 — Aprobar habilita el login (US2)

1. Loguearse con un usuario ya aprobado del mismo tenant.
2. Ir a `/usuarios`; localizar el solicitante pendiente en la lista.
3. Pulsar "Aprobar" → toast de éxito; el estado pasa a `aprobado` y `activo=true`.
4. Cerrar sesión; loguearse con el solicitante recién aprobado → acceso concedido.

**Esperado**: aprobación en ≤2 acciones, login inmediato tras aprobar.

## Escenario 3 — Rechazar / no-self (US2, edge)

1. En `/usuarios`, rechazar un usuario → `estado='rechazado'`, `activo=false`; ya no puede
   loguearse.
2. Intentar aprobar/rechazarse a sí mismo → acción bloqueada con mensaje de error.

## Escenario 4 — Cards informativas y aislamiento (US3, FR-009)

1. En `/usuarios`, verificar que las cards (total / pendientes / activos) coinciden con los
   usuarios del tenant.
2. Con un segundo tenant y sus propios usuarios, confirmar que la lista y las cards de un tenant
   NO incluyen usuarios del otro.

## Comandos de test

```
php artisan test --filter=Usuario
php artisan test --filter=Registro
```

**Cobertura crítica (Principio IV, test-first en aislamiento)**:
- Registro crea pendiente + login bloqueado.
- Aprobar habilita login; idempotencia.
- No-self en aprobar/rechazar.
- Aislamiento entre ≥2 tenants en index, cards y acciones (no se puede aprobar usuario de otro
  tenant → 404).
