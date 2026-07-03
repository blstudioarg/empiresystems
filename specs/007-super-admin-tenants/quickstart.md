# Quickstart — Validación del panel Super Admin y resolución por dominio

Guía de validación end-to-end. No incluye código de implementación; valida el comportamiento.

## Prerrequisitos

- App corriendo en local. `config/tenancy.php` → `central_domains` incluye el host central de
  desarrollo (p. ej. `localhost`, `127.0.0.1`).
- Migración de la tabla `domains` aplicada (`php artisan migrate`).
- Un usuario `super_admin` (rol super_admin, `tenant_id` null) sembrado.
- Para probar dominios de tenant en local: mapear hosts de prueba en `hosts` (p. ej.
  `a.localhost`, `b.localhost`) o usar dominios `*.test` apuntando a la app, y añadirlos como
  dominios de los tenants de prueba.

## Escenario 1 — Acceso al panel (guard de rol + dominio central)

1. Sin autenticar, visitar `http://localhost/super_admin/tenants` → **redirige a login**.
2. Autenticar como usuario NO super_admin (usuario de un tenant) y visitar la misma URL → **403**.
3. Autenticar como `super_admin` desde el dominio central → **200**, se ve el listado.

**Esperado**: solo super_admin en dominio central entra (FR-007/008/009, SC-003).

## Escenario 2 — Crear tenant con dominio

1. Como super_admin, abrir "crear tenant", completar dominio `a.localhost` + datos fiscales válidos,
   guardar.
2. Verificar que aparece en el listado con su dominio, NIF y estado activo.
3. Visitar `http://a.localhost/` → la app reconoce el tenant recién creado (contexto = ese tenant).

**Esperado**: alta instantánea, dominio operativo sin pasos manuales en la app (FR-011/013, SC-002).

## Escenario 3 — Dominio duplicado

1. Como super_admin, intentar crear otro tenant con dominio `a.localhost` (ya usado).
2. Verificar **422** con mensaje "dominio ya en uso".
3. (Concurrencia) El índice único de `domains.domain` impide dos filas iguales aunque se fuerce.

**Esperado**: unicidad garantizada (FR-002/012, SC-005).

## Escenario 4 — Resolución por dominio y aislamiento

1. Crear tenant A (`a.localhost`) y B (`b.localhost`), cada uno con datos de negocio propios
   (p. ej. un cliente en cada uno).
2. Visitar `http://a.localhost/clientes` autenticado como usuario de A → ve solo clientes de A.
3. Visitar `http://b.localhost/clientes` → ve solo clientes de B.
4. Visitar un host no registrado (`z.localhost`) no central → **404/abort**, sin datos.

**Esperado**: el host fija el tenant; sin fuga entre A y B (FR-001/005, SC-001).

## Escenario 5 — Gate login↔dominio

1. Con un usuario que pertenece al tenant B, intentar iniciar sesión desde `http://a.localhost/login`.
2. Verificar que el login **se rechaza** (no puede entrar en el dominio de otro tenant).
3. El mismo usuario en `http://b.localhost/login` entra correctamente.

**Esperado**: un usuario solo autentica en el dominio de su tenant (FR-006, SC-006).

## Escenario 6 — Editar dominio de un tenant

1. Editar el tenant A y cambiar su dominio de `a.localhost` a `a2.localhost`.
2. Verificar que `http://a2.localhost/` resuelve al tenant A y `http://a.localhost/` ya no.

**Esperado**: el nuevo dominio resuelve, el anterior deja de hacerlo (FR-015).

## Escenario 7 — Eliminar tenant (regla de facturas)

1. Crear tenant C sin facturas → eliminarlo (con confirmación) → desaparece del listado, su dominio
   deja de resolver.
2. En un tenant D con al menos una factura `emitida`, intentar eliminarlo → **se impide**, mensaje
   ofreciendo desactivar. Ninguna factura se pierde.
3. Desactivar D (`activo=false`) → el login de sus usuarios queda bloqueado.

**Esperado**: borrado permitido sin facturas; bloqueado con facturas emitidas (FR-017/018/019,
SC-004).

## Comandos de test

```bash
php artisan test --filter=TenantDomainResolutionTest
php artisan test --filter=TenantCrudTest
```

Cubren (test-first, Principio IV): resolución por host, aislamiento por dominio con ≥2 tenants, gate
login↔dominio, guard super_admin y bloqueo de borrado por facturas emitidas.
