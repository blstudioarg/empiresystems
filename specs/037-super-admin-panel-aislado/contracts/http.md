# Contrato HTTP — rutas, middleware y comportamiento del bloqueo

## 1. Middleware `BloquearSuperAdminAreaTenant` (alias `sin_super_admin`)

**Registro** (`bootstrap/app.php`, junto a `tenant.context` y `super_admin`):

```php
'sin_super_admin' => BloquearSuperAdminAreaTenant::class,
```

**Aplicación** (`routes/web.php`): en el grupo del área de tenant, **después** de `auth`:

```php
Route::middleware(['tenant.context', 'auth', 'sin_super_admin'])->group(function () { … });
```

El grupo del panel (`['tenant.context', 'auth', 'super_admin']`) **no** lo lleva.

### Reglas

| Condición | Respuesta |
|---|---|
| Usuario no autenticado | No aplica (`auth` actúa antes) |
| Usuario **no** Super Admin | `next($request)` sin cambios |
| Super Admin + ruta en la allowlist | `next($request)` |
| Super Admin + petición que espera JSON (`$request->expectsJson()`) | `403` JSON `{"message": "…"}` |
| Super Admin + navegación | `302` a `route('super_admin.home')` + flash `warning` |

**Allowlist de nombres de ruta** (exhaustiva; cualquier añadido futuro debe justificarse en el PR):

- `logout`
- `profile.*` (perfil personal del propio usuario; ya contempla el caso "usuario sin tenant")
- `localidades.index` (catálogo geográfico compartido que usan los formularios del propio panel)

**Mensaje de bloqueo** (mismo texto en ambos formatos):
`Esa sección pertenece al área de empresa. Como Super Admin solo tenés acceso al panel de gestión de tenants.`

### Registro del intento (FR-008)

En cada bloqueo, antes de responder:

```php
Log::warning('super_admin.acceso_area_tenant_bloqueado', [
    'usuario_id' => $user->id,
    'usuario_email' => $user->email,
    'metodo' => $request->method(),
    'uri' => $request->fullUrl(),
    'ruta' => $request->route()?->getName(),
    'ip' => $request->ip(),
    'user_agent' => substr((string) $request->userAgent(), 0, 255),
]);
```

**No** se escribe en `logs_actividad` (research D4).

## 2. Ruta nueva

| Método | URI | Nombre | Controller | Middleware |
|---|---|---|---|---|
| GET | `/super_admin` | `super_admin.home` | `SuperAdmin\PanelController@index` | `tenant.context`, `auth`, `super_admin` |

Se registra **dentro** del grupo `super_admin` existente, antes del `Route::resource('tenants', …)`.

Respuesta: vista `super_admin.panel.index` con la clave `datos` (ver
[panel-home.md](./panel-home.md)). **No** expone variante JSON: la home no se recarga por AJAX.

## 3. Rutas afectadas sin cambio de firma

| Ruta | Cambio |
|---|---|
| `GET /` (`dashboard`) | Sigue igual para usuarios de tenant. Para el Super Admin ya no llega al controller (lo intercepta el middleware) → se **elimina** la rama `isSuperAdmin()` de `DashboardController@index`. |
| `POST /login` | Sin cambios. `redirect()->intended('/')` acaba en la home del panel por efecto del middleware. |
| `super_admin.tenants.*` | Sin cambios. |

## 4. Matriz de acceso esperada (base de los tests)

| Usuario | Host | Destino | Resultado esperado |
|---|---|---|---|
| Super Admin | central | `/super_admin` | 200 |
| Super Admin | central | `/` | 302 → `/super_admin` |
| Super Admin | central | `/clientes` | 302 → `/super_admin` + flash warning |
| Super Admin | central | `/clientes` con `Accept: application/json` | 403 JSON |
| Super Admin | central | `POST /clientes` | 403 (ningún dato modificado) |
| Super Admin | central | `/facturas/{id}/pdf` | 302 → `/super_admin` |
| Super Admin | central | `/perfil` | 200 |
| Super Admin | central | `POST /logout` | 302 → login |
| Super Admin | dominio de tenant | `/clientes` | Sin acceso a la pantalla del tenant |
| Usuario de tenant (con permiso) | su dominio | `/clientes` | 200 |
| Usuario de tenant | su dominio | `/super_admin` | 403 |
| Usuario de tenant | su dominio | `/super_admin/tenants` | 403 |
