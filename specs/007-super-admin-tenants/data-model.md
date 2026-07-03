# Modelo de datos — Panel Super Admin / dominios de tenant

Esta feature **no** añade columnas a `tenants`. Introduce la tabla `domains` (relación con Tenant)
y define reglas sobre entidades existentes.

## Tabla nueva: `domains` (grupo central, migración estándar de `stancl/tenancy`)

Estructura estándar del paquete (modelo `Stancl\Tenancy\Database\Models\Domain`):

| Campo       | Tipo         | Notas |
|-------------|--------------|-------|
| id          | bigint PK    | |
| domain      | varchar(255) | **único**; valor normalizado (minúsculas, sin esquema/path) — D6/D7 |
| tenant_id   | fk → tenants | índice; `onDelete cascade` (al borrar el tenant se borra su dominio) |
| timestamps  |              | |

- **Índice único** sobre `domain` → garantía de unicidad ante concurrencia (D7).
- `tenant_id` con `cascade`: eliminar un tenant elimina su dominio (solo ocurre cuando la
  eliminación está permitida, es decir, sin facturas emitidas — ver reglas).

> Nota: la tabla vive en el **grupo central** (no lleva scope de tenant); es el mapa host→tenant que
> el middleware consulta antes de saber qué tenant es.

## Relación en `Tenant`

- `Tenant hasMany Domain` (relación estándar de stancl vía trait `HasDomains` del modelo Tenant, o
  relación explícita `domains()`).
- **Regla de negocio (1:1)**: aunque la relación sea `hasMany`, esta feature restringe a **un**
  dominio por tenant. El helper de acceso conveniente `dominio` (singular) devuelve el único
  registro. Crear/editar mantiene esa cardinalidad.

## Entidades existentes implicadas (sin cambios de esquema)

- **`tenants`**: se reutiliza tal cual (`nombre_comercial`, `razon_social`, `nif`,
  `regimen_impositivo`, `email`, `activo`, `logo_path`, ...). El campo `activo` es la palanca de
  desactivación (bloquea login de sus usuarios) — ya existente.
- **`users`**: rol `super_admin` con `tenant_id` null (ya existente, feature 001). El gate
  login↔dominio (D3) compara `users.tenant_id` con el tenant del dominio.
- **`facturas`**: solo lectura. La regla de borrado consulta si el tenant objetivo tiene alguna
  factura en estado `emitida` (o cualquier estado no `borrador`). No se modifica.

## Reglas de validación (FormRequests)

### Alta (`StoreTenantRequest`)
- `dominio`: requerido, string, normalizado (D6), **único** contra `domains.domain`, formato de
  host válido (sin esquema, sin path, con al menos un punto o host válido).
- `nombre_comercial`: requerido, string.
- `razon_social`: requerido, string.
- `nif`: requerido, string; regla `NifEspanol` existente (`app/Rules/NifEspanol.php`) si aplica.
- `regimen_impositivo`: requerido, enum `RegimenImpositivo` (`iva`/`igic`/`ipsi`).
- `email`: requerido, email.
- `activo`: boolean (default true al crear).

### Edición (`UpdateTenantRequest`)
- Igual que alta, pero `dominio` único **ignorando** el propio dominio del tenant editado.
- Al cambiar el dominio, el anterior deja de resolver (se actualiza el registro `domains`).

## Reglas de estado / transiciones

- **Crear**: inserta `tenants` + su `domains` (1 fila). El hook `Tenant::created` existente ya crea
  la serie `F` por defecto — se conserva.
- **Editar**: actualiza `tenants` y/o el registro `domains` asociado.
- **Desactivar**: `tenants.activo = false` → login bloqueado para sus usuarios (comportamiento
  existente); el dominio sigue existiendo pero el middleware corta el acceso por `activo`.
- **Eliminar**:
  - Si el tenant tiene ≥1 factura `emitida` → **prohibido** (mensaje + oferta de desactivar).
  - Si no → elimina `tenants` (cascade borra su `domains`); el dominio deja de resolver (FR-019).

## Aislamiento / consulta cross-tenant (Principio I — excepción super_admin)

- El listado y el CRUD del panel operan en **contexto central** (sin tenant inicializado): consultan
  `tenants` y `domains` directamente (tablas centrales, sin global scope).
- La comprobación de facturas emitidas de un tenant concreto se hace filtrando **explícitamente** por
  `tenant_id` (o inicializando temporalmente ese tenant), porque en contexto central el global scope
  de `BelongsToTenant` no está activo. Cubierto por test.
