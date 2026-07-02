# Data Model: Autenticación de usuarios (login multi-tenant)

**Feature**: 001-user-auth · **Date**: 2026-07-02
**Fuente de verdad global**: `docs/03-modelo-datos.md` (capa CENTRAL)

Ambas tablas son **centrales** (no llevan `tenant_id` scope). Convenciones del proyecto:
`id` BIGINT autoincrement, `timestamps`.

---

## Tabla `tenants` (nueva migración)

Subconjunto de la tabla definida en docs/03-modelo-datos.md — solo lo que esta feature necesita,
más la columna `data` que exige el paquete de tenancy. El resto de columnas fiscales
(regímenes, IRPF, logo, Verifactu…) las agregarán las features de facturación cuando las usen.

| Campo | Tipo | Reglas |
|-------|------|--------|
| id | bigint PK autoincrement | |
| nombre_comercial | varchar | requerido |
| razon_social | varchar | nullable (se completará en features fiscales) |
| nif | varchar(15) | nullable, único si presente |
| email | varchar | nullable |
| activo | boolean | default `true`; en `false` bloquea el login de sus usuarios (FR-008) |
| data | json | nullable — requerida por `VirtualColumn` de stancl; no se usa de momento |
| timestamps | | |

**Modelo**: `App\Models\Tenant` extiende `Stancl\Tenancy\Database\Models\Tenant`; int autoincrement
(`$incrementing = true`, `$keyType = 'int'`); `getCustomColumns()` = todas las columnas de arriba.

## Tabla `users` (migración de alteración sobre la existente)

Columnas nuevas sobre la tabla estándar de Laravel:

| Campo | Tipo | Reglas |
|-------|------|--------|
| tenant_id | fk → tenants, nullable, índice | `null` **solo** para super_admin (FR-006/FR-007) |
| rol | varchar(20) | valores del enum `UserRole`: `super_admin` \| `admin` \| `usuario`; default `usuario` |
| activo | boolean | default `true`; en `false` no puede autenticarse (FR-008) |

Ya existentes y usadas: `name`, `email` (único global), `password` (cast `hashed`, FR-013),
`remember_token` (FR-005).

**Modelo `App\Models\User`**: agrega `tenant_id`, `rol`, `activo` a `$fillable`; cast `rol` →
`UserRole::class`, `activo` → `boolean`; relación `tenant(): BelongsTo`; helper `isSuperAdmin()`.

**Invariantes** (se validan en seeder/factories y por convención; no hay CHECK en DB):
- `rol = super_admin` ⇔ `tenant_id IS NULL`.
- `rol ∈ {admin, usuario}` ⇒ `tenant_id NOT NULL`.

## Enum `App\Enums\UserRole`

```
super_admin — global, sin tenant, gestionará todos los tenants (features futuras)
admin       — administra su tenant
usuario     — opera dentro de su tenant
```

## Estados y transiciones de sesión

```
[guest] --login OK (user activo + tenant activo|null)--> [authenticated]
        --login FAIL (credencial/inactivo/tenant inactivo)--> [guest] (+contador throttle)
[authenticated] --request--> middleware SetTenantContext:
                    tenant_id != null  -> tenancy()->initialize(tenant)   [contexto tenant]
                    tenant_id == null  -> sin inicializar                  [contexto central]
                    tenant inactivo    -> logout forzado -> [guest]
[authenticated] --logout--> sesión invalidada + token regenerado --> [guest]
```

## Datos iniciales (AuthSeeder, idempotente)

| Registro | Valores clave |
|---|---|
| User super_admin | email `admin@empiresystems.es`, rol `super_admin`, tenant_id `null`, activo |
| Tenant demo | nombre_comercial "Empresa Demo SL", activo |
| User admin demo | email `demo@empiresystems.es`, rol `admin`, tenant → demo, activo |

Contraseñas de desarrollo definidas en el seeder y documentadas en README (cambiar en producción).

## Factories (para tests)

- `TenantFactory`: tenant activo por defecto; estado `inactive()`.
- `UserFactory` (existente, extendida): por defecto `usuario` activo con tenant nuevo; estados
  `superAdmin()` (rol super_admin, tenant null), `admin()`, `inactive()`.
