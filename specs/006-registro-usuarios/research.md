# Research: Registro y aprobación de usuarios

## Decisión 1 — Modelar el estado de aprobación vs. el campo `activo` existente

**Contexto**: `users` ya tiene `activo` (boolean, default true) y `rol` (string). El
`LoginController` ya bloquea el acceso con `Auth::attempt([... 'activo' => true])` y además
valida que el tenant esté activo.

**Decisión**: Añadir una columna `estado` (enum string: `pendiente` | `aprobado` | `rechazado`,
default `pendiente`) y **mantener `activo` como la compuerta efectiva de login**. Correspondencia:

| Acción                | `estado`    | `activo` |
|-----------------------|-------------|----------|
| Auto-registro         | `pendiente` | `false`  |
| Aprobar               | `aprobado`  | `true`   |
| Rechazar / desactivar | `rechazado` | `false`  |

**Rationale**: No tocar la lógica de gating del login (sigue siendo `activo=true`), evitando
regresiones. `estado` aporta la semántica que `activo` solo no da: distinguir "nunca aprobado"
de "desactivado tras aprobar", necesario para las cards y para el mensaje de login. Los usuarios
seed/existentes se migran a `estado=aprobado` (ya tienen `activo=true`).

**Alternativas descartadas**:
- Solo `activo` boolean: no distingue pendiente de desactivado → cards y mensajes ambiguos.
- Máquina de estados con tabla de historial: sobre-ingeniería para el MVP (Principio V).

## Decisión 2 — Aislamiento multi-tenant del modelo `User`

**Decisión**: NO aplicar el scope global `BelongsToTenant` de stancl al modelo `User`. En la
vista de usuarios, filtrar **manualmente** por `tenant_id` del usuario autenticado dentro del
controlador (`User::where('tenant_id', auth()->user()->tenant_id)`), igual que el patrón ya
usado en `ClienteController` (resolver en el cuerpo del controlador, no binding implícito).

**Rationale**: `User` es el modelo de autenticación; el login ocurre **antes** de que el
middleware `tenant.context` inicialice la tenancy, así que un scope global de tenant sobre
`User` rompería `Auth::attempt`. El filtrado manual en el controlador (que corre al final del
pipeline, con `auth` ya resuelto) garantiza el aislamiento sin tocar el login. Coincide con la
memoria del proyecto sobre el pitfall de route binding + TenantScope.

**Alternativas descartadas**:
- `use BelongsToTenant` en User: riesgo alto de romper login/registro y sesiones.

## Decisión 3 — Asociación de tenant en el auto-registro

**Decisión**: El registro público asigna `tenant_id` al **único tenant de la instalación**
(`Tenant::query()->first()` / tenant por defecto). El formulario público no pide ni elige
tenant.

**Rationale**: Clarificación del usuario (instalación de un solo tenant por ahora). YAGNI:
no se construye selección de tenant, subdominios ni alta de tenant hasta que el alcance lo pida.

**Alternativas descartadas**: subdominio / código de invitación / crear tenant nuevo — fuera de
alcance de esta fase.

## Decisión 4 — Autorización de la acción de aprobar/rechazar

**Decisión**: Cualquier usuario **autenticado** (por definición ya aprobado y activo, porque si
no no podría loguearse) del tenant puede aprobar/rechazar a otros de su mismo tenant. No se
añade gate por rol. Restricción: no puede aplicarse la acción a sí mismo (FR-011).

**Rationale**: Clarificación del usuario ("cualquier usuario aprobado"). El propio login ya
garantiza que solo usuarios aprobados y activos llegan a la vista, así que `middleware('auth')`
+ chequeo de `tenant_id` coincidente es suficiente.

## Decisión 5 — Página pública de registro y rate limiting

**Decisión**: Rutas de registro bajo el grupo `guest` (como login), vista basada en
`page-register` del template NexaDash sobre el layout `fullwidth` (igual que login). Aplicar
`throttle` al POST de registro para mitigar abuso del formulario público.

**Rationale**: Consistencia con el login existente y con las guías de front del proyecto
(layout fullwidth, toastr para notificaciones).

## Decisión 6 — Mensaje de login para cuentas pendientes

**Decisión**: Tras un intento fallido, si las credenciales son correctas pero el usuario está
en `estado=pendiente` o `rechazado`, mostrar un mensaje específico ("Tu cuenta aún no está
aprobada" / "no está habilitada") en vez del genérico de credenciales inválidas. Solo se revela
el estado cuando la contraseña es correcta, así que no hay enumeración de cuentas.

**Rationale**: Mejor UX (FR-004) sin comprometer seguridad, porque el mensaje específico exige
conocer la contraseña.
