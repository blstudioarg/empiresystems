# Contrato HTTP — Panel Super Admin y resolución por dominio

Todas las rutas del panel viven bajo el prefijo `super_admin`, en un grupo restringido a los
`central_domains` (config/tenancy.php) y protegido por `EnsureSuperAdmin` (auth + rol super_admin +
`tenant_id` null). Fuera de esas condiciones: 403 (autenticado no-superadmin) o redirect a login (no
autenticado).

## Rutas del panel (dominio central)

| Método      | URI                              | Nombre                     | Acción            | Descripción |
|-------------|----------------------------------|----------------------------|-------------------|-------------|
| GET         | `/super_admin/tenants`           | `super_admin.tenants.index`   | `index`   | Lista de tenants (nombre comercial, dominio, NIF, estado) |
| GET         | `/super_admin/tenants/crear`     | `super_admin.tenants.create`  | `create`  | Formulario de alta |
| POST        | `/super_admin/tenants`           | `super_admin.tenants.store`   | `store`   | Crea tenant + dominio |
| GET         | `/super_admin/tenants/{tenant}/editar` | `super_admin.tenants.edit` | `edit` | Formulario de edición |
| PUT/PATCH   | `/super_admin/tenants/{tenant}`  | `super_admin.tenants.update`  | `update`  | Actualiza tenant y/o dominio |
| DELETE      | `/super_admin/tenants/{tenant}`  | `super_admin.tenants.destroy` | `destroy` | Elimina (o bloquea si tiene facturas emitidas) |

> `{tenant}` se resuelve en contexto central (sin scope de tenant). Se puede usar binding implícito
> sobre `Tenant` porque `tenants` es central; verificar que el binding no arrastra el global scope.

### Comportamiento por acción

- **index**: 200 con la lista. Redirige a login si no autenticado; 403 si autenticado no-superadmin;
  404/redirect si se accede desde un dominio no central.
- **store**:
  - 302 + flash `success` si crea OK; el nuevo dominio queda operativo de inmediato.
  - 422 (validación) si dominio duplicado/ inválido o faltan campos obligatorios.
- **update**:
  - 302 + flash `success` si actualiza OK.
  - 422 si el nuevo dominio choca con otro tenant o es inválido.
- **destroy**:
  - 302 + flash `success` si el tenant no tiene facturas emitidas → eliminado, dominio deja de
    resolver.
  - 302 + flash `error` (o 409) si tiene facturas emitidas → NO se elimina; mensaje ofreciendo
    desactivar. La desactivación se hace vía `update` (`activo=false`), no vía `destroy`.

## Middleware de resolución de tenant por dominio (aplica al grupo web de negocio)

Evolución de `SetTenantContext` (D2). Comportamiento por host:

| Host                                   | Resultado |
|----------------------------------------|-----------|
| En `central_domains`                   | Contexto central. Sin tenant. Solo válido para área super_admin (auth/guest de login del propio central según corresponda). |
| Corresponde a `domains.domain` (tenant activo)   | `tenancy()->initialize($tenant)`. Login exige `user.tenant_id == tenant.id`. |
| Corresponde a un tenant `activo=false` | Acceso cortado (logout/redirect login), igual que hoy. |
| No central y sin registro en `domains` | 404 / abort controlado (FR-005). No expone ningún tenant. |

### Gate login↔dominio (LoginController, D3)

- Si hay tenant resuelto por dominio y el usuario autenticado tiene `tenant_id != tenant.id` →
  se rechaza la autenticación (mismo tratamiento que credenciales inválidas / cuenta no usable).
- `super_admin` (`tenant_id` null) solo autentica válidamente en contexto central.

## Notificaciones

Flash de sesión (`success`/`error`) → toastr vía `partials/flash-toastr.blade.php` (ya global). Sin
alerts Bootstrap ad-hoc (CLAUDE.md).

## Vistas

- `super_admin/tenants/index.blade.php`: DataTable + columna de acciones como **dropdown único**
  (Ver/Editar, y Eliminar separado por divisor) — docs/04-front-guidelines.md. Confirmación de
  borrado con el modal genérico (`window.confirmDelete`), nunca `confirm()` nativo.
- `super_admin/tenants/form.blade.php`: formulario de alta/edición (dominio + datos fiscales),
  controles "sm" por defecto, sin `mb-3` en columnas del `.row` (gutter global).
