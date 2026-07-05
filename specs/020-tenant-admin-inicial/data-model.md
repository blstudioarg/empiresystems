# Data Model: Alta de usuario administrador al crear un tenant

No se crean ni modifican tablas ni migraciones. Se reutilizan las tablas `tenants`, `domains` y
`users` tal como existen hoy.

## Tenant (sin cambios de esquema)

Sin cambios respecto al modelo actual (`app/Models/Tenant.php`). Su alta sigue dando lugar,
automáticamente vía `Tenant::booted()`, a las 3 series por defecto (F/R/S) — comportamiento ya
existente que esta feature no toca (FR-008).

## User (sin cambios de esquema, nuevo caso de uso de creación)

Campos relevantes ya existentes en `users` y usados por esta feature:

| Campo | Tipo | Valor en esta feature |
|-------|------|------------------------|
| `tenant_id` | `bigint` FK | id del `Tenant` recién creado en la misma transacción |
| `name` | `string` | derivado del email del admin (parte local) o valor fijo "Administrador" — ver nota |
| `email` | `string` | valor de `admin_email` del formulario |
| `password` | `string` (hashed) | `Hash::make(admin_password)` |
| `rol` | `UserRole` enum | `UserRole::Admin` |
| `estado` | `EstadoUsuario` enum | `EstadoUsuario::Aprobado` |
| `activo` | `bool` | `true` |
| `aprobado_por` / `aprobado_en` | nullable | `null` (no hay un usuario humano que lo apruebe; nace ya aprobado) |

Nota sobre `name`: el formulario de alta de tenant no pide un nombre de persona para el
administrador (la spec solo menciona email y contraseña); se usa un valor por defecto legible
("Administrador") para no añadir un campo no solicitado por la spec (Principio V). El
administrador puede cambiar su propio nombre luego desde su perfil, como cualquier usuario.

### Validación (`StoreTenantRequest`)

Campos nuevos añadidos a las reglas existentes:

- `admin_email`: `required`, `email`, `max:255` (sin `unique` — ver research.md D4).
- `admin_password`: `required`, `string`, `min:8` (sin `confirmed` — ver research.md D3).

`UpdateTenantRequest` no cambia: la edición de un tenant no crea ni modifica su administrador.

## Relaciones y aislamiento

- `User.tenant_id` → `Tenant.id`: relación ya existente (`User::tenant()`).
- Ningún global scope nuevo: `User` sigue sin `TenantScope` (igual que hoy), y el filtrado por
  tenant en consultas de negocio sigue siendo manual (patrón ya usado en `UsuarioController` y en
  `TenantController::destroy`).
- Aislamiento verificado por tests: un admin creado para el tenant A no debe poder autenticarse
  ni ser listado en el contexto del tenant B (SC-004).

## Transacción atómica (FR-005)

Orden dentro de `DB::transaction()` en `TenantController::store()`:

1. `Tenant::create(...)` → dispara `booted()` → siembra series F/R/S.
2. `Domain::create(...)` asociado al tenant.
3. `User::create(...)` con `tenant_id` del tenant recién creado, `rol=admin`,
   `estado=aprobado`, `activo=true`.

Si cualquiera de los tres pasos lanza una excepción, MySQL revierte los tres (ninguna fila
persiste), consistente con el edge case "Fallo parcial" de la spec.
