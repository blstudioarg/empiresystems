# Data Model: Vista de perfil de usuario

> Sin migraciones ni tablas nuevas. Esta feature solo **lee** entidades existentes y añade
> presentación (labels de enum). Se documentan los campos que consume la vista.

## Entidades leídas

### User (`app/Models/User.php`) — existente

Campos consumidos por la vista de perfil (todos ya presentes):

| Campo          | Tipo                | Uso en la vista                                   |
|----------------|---------------------|---------------------------------------------------|
| `name`         | string              | Título de la cabecera                             |
| `email`        | string              | Meta (icono sobre)                                |
| `rol`          | `UserRole` (enum)   | Meta (icono usuario) → `rol->label()`             |
| `estado`       | `EstadoUsuario`     | Badge de estado de cuenta → `estado->label()`     |
| `avatar_path`  | string\|null        | Foto → `avatarUrl()` (fallback ya implementado)   |
| `created_at`   | datetime            | "Miembro desde" / fecha de alta                   |
| `tenant`       | relación BelongsTo  | Empresa (nombre); puede ser null → "Sin empresa"  |
| `aprobado_en`  | datetime\|null      | (opcional) fecha de aprobación                    |

Resolución: siempre `auth()->user()` (Principio I). Sin binding por ruta.

### Tenant — existente

Se lee solo el nombre de la empresa para mostrarlo como contexto. Relación `user->tenant`.

## Cambios de presentación (no de datos)

### `UserRole::label(): string`

Mapa Español:

| case         | value          | label            |
|--------------|----------------|------------------|
| `SuperAdmin` | `super_admin`  | Super Admin      |
| `Admin`      | `admin`        | Administrador    |
| `Usuario`    | `usuario`      | Usuario          |

### `EstadoUsuario::label(): string` y `badgeClass(): string`

| case        | value       | label      | badge (indicativo)     |
|-------------|-------------|------------|------------------------|
| `Pendiente` | `pendiente` | Pendiente  | warning                |
| `Aprobado`  | `aprobado`  | Aprobado   | success                |
| `Rechazado` | `rechazado` | Rechazado  | danger                 |

> Los nombres exactos de clase de badge se ajustan a las utilidades del template en implementación.

## Reglas / invariantes

- La vista es de **solo lectura** salvo la foto de perfil.
- Ningún dato de otro usuario es accesible desde esta vista (sin id en URL).
- Valores de reserva obligatorios: empresa → "Sin empresa"; fecha ausente → "—".
