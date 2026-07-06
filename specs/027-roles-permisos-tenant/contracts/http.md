# Contratos HTTP: Sistema de roles y permisos por tenant

**Feature**: 027-roles-permisos-tenant | Guard: `web` (sesión). Todas las rutas bajo
`['tenant.context', 'auth']` + middleware `can:ver-roles` salvo indicación.

## Gestión de roles

### GET /roles — `roles.index`
- **HTML**: vista con cards + datatable + modal.
- **JSON** (`wantsJson`):
```json
{
  "data": [
    {
      "id": 1,
      "name": "Administrador",
      "es_administrador": true,
      "permisos": ["ver-dashboard", "ver-facturas", "..."],
      "num_permisos": 17,
      "num_usuarios": 2,
      "update_url": "...", "delete_url": "..."
    }
  ],
  "catalogo": [
    { "modulo": "Facturas", "permisos": [ { "name": "ver-facturas", "label": "Facturas" } ] }
  ],
  "totales": { "roles": 3, "usuarios_con_rol": 8, "permisos_catalogo": 17 }
}
```
- Solo roles del tenant activo (aislamiento verificado por test con 2 tenants).

### POST /roles — `roles.store`
- Body: `{ "name": "Ventas", "permisos": ["ver-clientes", "ver-facturas"] }`
- Validación: `name` requerido, único por tenant (RN-05); `permisos[]` requerido (≥1), cada
  uno debe existir en el catálogo.
- 201 JSON `{ "message": "Rol creado correctamente." }` / redirect con flash `success`.
- 422 con errores de validación.

### PUT/PATCH /roles/{rol} — `roles.update`
- Mismo body que store. El rol se resuelve **manualmente en el controller filtrando por
  tenant activo** (no implicit binding — pitfall conocido de TenantScope) → 404 si es de
  otro tenant.
- Reglas: si `es_administrador`, `permisos` debe seguir conteniendo `ver-roles` y
  `ver-usuarios` y el nombre no cambia (RN-02) → 422 si se viola.
- 200 `{ "message": "Rol actualizado correctamente." }`.

### DELETE /roles/{rol} — `roles.destroy`
- 409 si tiene usuarios asignados (RN-01) o si es el rol Administrador (RN-02), con
  `{ "message": "<motivo>" }`.
- 200 `{ "message": "Rol eliminado correctamente." }`.

## Asignación de rol a usuario (en gestión de usuarios existente)

### PATCH /usuarios/{usuario}/rol — `usuarios.rol.update` (middleware `can:ver-usuarios`)
- Body: `{ "role_id": 3 }` (o `null` para dejar sin rol).
- Usuario y rol resueltos filtrando por tenant activo → 404 cruzado.
- 422 si la operación produce lockout (RN-02): `{ "message": "El tenant quedaría sin ningún administrador..." }`.
- 200 `{ "message": "Rol asignado correctamente." }`.

### GET /usuarios (existente) — se extiende el payload JSON con `rol_asignado: { id, name } | null` y `roles_disponibles` para el select.

## Enforcement en rutas existentes (FR-004)

Cada grupo/sección del sidebar queda envuelto en `->middleware('can:<permiso>')` según la
tabla del catálogo (research.md D3). Cambios destacados:
- El grupo actual `can:gestiona-fichajes` pasa a `can:ver-jornada` (D5).
- `fichajes.*`, `mi-jornada.*`, `perfil`, `logout`: sin permiso (secciones personales).
- Respuesta sin permiso: **403** (JSON `{ "message": ... }` si `wantsJson`, página de error
  si HTML). Nunca redirect a login (usuario ya autenticado).

## Sidebar (contrato de UI)
- Cada entrada se envuelve en `@can('<permiso>')`; grupos sin entradas visibles no se
  renderizan (FR-003).
- Badge de rol del usuario: nombre del rol spatie; para super admin, el label del enum.
