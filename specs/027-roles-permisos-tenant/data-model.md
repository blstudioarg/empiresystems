# Data Model: Sistema de roles y permisos por tenant

**Feature**: 027-roles-permisos-tenant | **Date**: 2026-07-06

Las tablas las provee la migración publicada de spatie/laravel-permission v6 con teams
activado (`team_foreign_key = 'tenant_id'`). No se crean tablas propias.

## Tablas (migración de spatie, adaptada)

### permissions (catálogo global — SIN tenant_id)
| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| name | VARCHAR | clave estable, ej. `ver-facturas`; UNIQUE con guard_name |
| guard_name | VARCHAR | `web` |
| timestamps | | |

El catálogo (clave → etiqueta → módulo) vive en código: `App\Support\CatalogoPermisos`
(fuente de verdad, ver research.md D3). La tabla solo persiste las claves; etiqueta y módulo
se resuelven desde el catálogo PHP para la UI (sin columnas extra → sin desvíos del esquema
estándar de spatie).

### roles (por tenant)
| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT NULL, indexado | team FK; UNIQUE(tenant_id, name, guard_name) — cumple FR-012 |
| name | VARCHAR | ej. `Administrador`, `Ventas` |
| guard_name | VARCHAR | `web` |
| timestamps | | |

- El UNIQUE compuesto de spatie teams da unicidad de nombre **dentro** del tenant y permite
  repetirlo entre tenants (FR-012).
- `tenant_id` referencia `tenants.id` (BIGINT). FK con `cascadeOnDelete` (al borrar un tenant
  caen sus roles).
- El aislamiento lo aplica spatie internamente vía `PermissionRegistrar::getPermissionsTeamId()`
  (seteado en `SetTenantContext`); el controller de roles añade además el filtro explícito
  como defensa en profundidad (Principio I).

### model_has_roles (asignación usuario–rol, por tenant)
| Columna | Tipo | Notas |
|---|---|---|
| role_id | BIGINT FK | |
| model_type / model_id | morph | siempre `App\Models\User` |
| tenant_id | BIGINT, parte de la PK | team FK |

Modelo operativo: **un rol por usuario** (assumption de la spec) — la UI usa
`syncRoles([$rol])`, aunque el esquema soporte varios.

### role_has_permissions
| Columna | Tipo | Notas |
|---|---|---|
| permission_id | BIGINT FK | |
| role_id | BIGINT FK | el tenant viene implícito por el rol |

### model_has_permissions
Sin uso en esta feature (no se asignan permisos directos a usuarios); la tabla existe por la
migración estándar de spatie.

## Cambios en modelos existentes

### App\Models\User
- `use HasRoles` (trait de spatie).
- Sin cambios de columnas. `rol` (enum) se conserva: `SuperAdmin` sigue siendo la marca del
  usuario central; para usuarios de tenant deja de ser fuente de verdad de acceso
  (research.md D7).

### Reglas de integridad (server-side)
- **RN-01 (FR-006)**: no se puede eliminar un rol con usuarios asignados.
- **RN-02 (FR-006, anti-lockout)**: ninguna operación (editar rol, eliminar rol, reasignar
  usuario) puede dejar al tenant sin ≥1 usuario activo con `ver-roles` y `ver-usuarios`.
  El rol "Administrador" no puede perder esos dos permisos ni ser eliminado.
- **RN-03 (FR-007)**: alta de tenant = transacción única: tenant + dominio + usuario admin +
  rol Administrador (todos los permisos) + asignación.
- **RN-04 (FR-001)**: seeder idempotente; re-ejecutarlo nunca duplica ni borra asignaciones,
  y sincroniza el rol Administrador de cada tenant con el catálogo completo.
- **RN-05 (FR-012)**: nombre de rol único por tenant (UNIQUE de BD + validación de request
  con mensaje claro).

## Migración de datos (tenants existentes — FR-008)
Migración Laravel (una sola vez, tras las tablas de spatie):
1. Sembrar catálogo de permisos (llama al seeder).
2. Por cada tenant: crear rol `Administrador` (todos los permisos) y rol `Usuario` (sin
   permisos de gestión); asignar según `users.rol` actual (`admin` → Administrador,
   `usuario` → Usuario). Usuarios centrales (super admin, `tenant_id` NULL) se omiten.
3. Limpiar cache de spatie.

## Datos personales / retención (Principio II RGPD)
Roles y permisos no son datos personales (son configuración de acceso); el pivote
usuario–rol es dato organizativo ligado al ciclo de vida del usuario y cae en cascada con
él. No se requiere plazo de retención/purga nuevo. Los accesos denegados (403) ya quedan
cubiertos por `logs_actividad` (feature 021).
