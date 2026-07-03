# Data Model: Registro y aprobación de usuarios

## Entidad: User (tabla `users`) — modificada

Se extiende la tabla existente. Campos ya presentes: `id`, `tenant_id`, `name`, `email`,
`password`, `rol`, `activo`, `avatar_path`, timestamps.

### Campos nuevos

| Campo         | Tipo              | Null | Default      | Notas |
|---------------|-------------------|------|--------------|-------|
| `estado`      | string(20) / enum | no   | `pendiente`  | `pendiente` \| `aprobado` \| `rechazado` (enum `EstadoUsuario`) |
| `aprobado_por`| bigint FK users   | sí   | null         | Usuario que aprobó (auditoría mínima). `nullOnDelete` |
| `aprobado_en` | timestamp         | sí   | null         | Momento de la aprobación |

Índice sugerido: `(tenant_id, estado)` para las cards/listado.

### Enum `EstadoUsuario` (App\Enums)

- `Pendiente = 'pendiente'`
- `Aprobado = 'aprobado'`
- `Rechazado = 'rechazado'`

### Reglas de validación (registro)

- `name`: requerido, string, máx 255.
- `email`: requerido, email, único en `users` (scope global — un solo tenant).
- `password`: requerido, confirmado, política de contraseña por defecto de Laravel (mín. 8).

### Transiciones de estado

```
(alta registro) ──► pendiente
pendiente ──aprobar──► aprobado      (activo=true, aprobado_por/en seteados)
pendiente ──rechazar─► rechazado     (activo=false)
aprobado  ──desactivar► rechazado    (activo=false)
```

- Aprobar es **idempotente**: aprobar un `aprobado` no cambia nada ni error (FR-010).
- Un usuario no puede cambiar su propio estado (FR-011).

### Correspondencia estado ↔ `activo` (compuerta de login)

| `estado`    | `activo` | ¿Puede loguearse? |
|-------------|----------|-------------------|
| `pendiente` | false    | No                |
| `aprobado`  | true     | Sí (si tenant activo) |
| `rechazado` | false    | No                |

### Migración de datos existentes

Usuarios ya existentes (seed/creados antes): `estado = 'aprobado'` (ya tienen `activo=true`),
para no bloquear cuentas vigentes.

## Relaciones

- `User belongsTo Tenant` (ya existe).
- `User belongsTo aprobador (User, aprobado_por)` — opcional, para mostrar quién aprobó.

## Aislamiento (Principio I)

`User` NO usa el scope global `BelongsToTenant`. El aislamiento se aplica filtrando por
`tenant_id` del usuario autenticado en el controlador de la vista de usuarios. Los tests de
aislamiento crean ≥2 tenants y afirman que un usuario no ve/afecta usuarios del otro tenant.
