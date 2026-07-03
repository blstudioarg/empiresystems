# HTTP Contract: Registro y aprobación de usuarios

Convenciones del proyecto: rutas web (Blade + sesión), notificaciones vía toastr con flash de
sesión (`->with('success'|'error', ...)`), respuestas JSON para acciones AJAX cuando aplique.

## Público (middleware `guest`)

### GET `/registro` — `register.create`
Muestra el formulario de registro (vista basada en `page-register`, layout fullwidth).
- 200 → vista con formulario.
- Si ya hay sesión → redirige a `/` (middleware guest).

### POST `/registro` — `register.store`
Crea un solicitante en estado `pendiente`. Middleware: `guest`, `throttle`.
- Body: `name`, `email`, `password`, `password_confirmation`.
- Validación: ver data-model. Email único.
- Éxito → redirect a `/login` con flash `success`: "Solicitud registrada. Un administrador debe
  aprobar tu cuenta antes de poder iniciar sesión."
- Crea usuario con: `tenant_id` = tenant por defecto, `estado='pendiente'`, `activo=false`,
  `rol='usuario'`.
- 422 (validación) → vuelve al formulario con errores.

## Autenticado (middleware `auth`, `tenant.context`)

### GET `/usuarios` — `usuarios.index`
Lista de usuarios del tenant del usuario autenticado + cards resumen.
- 200 → vista `usuarios.index` con:
  - `usuarios`: colección scopeda por `tenant_id` (nombre, email, rol, estado, activo,
    aprobado_por/en, urls de acción).
  - `totales`: `{ total, pendientes, activos }` del tenant.
- Soporta `wantsJson()` para refresco AJAX (misma forma que `ClienteController`).

### PATCH `/usuarios/{usuario}/aprobar` — `usuarios.aprobar`
Aprueba un solicitante. Resolución manual del modelo (no binding implícito).
- Precondición: `usuario.tenant_id === auth.tenant_id`; si no → 404.
- No puede ser el propio usuario autenticado → 403/redirect con error.
- Efecto: `estado='aprobado'`, `activo=true`, `aprobado_por=auth.id`, `aprobado_en=now()`.
- Idempotente si ya está aprobado.
- Éxito → redirect/JSON con `success`: "Usuario aprobado. Ya puede iniciar sesión."

### PATCH `/usuarios/{usuario}/rechazar` — `usuarios.rechazar`
Rechaza/desactiva una cuenta.
- Mismas precondiciones de tenant y no-self.
- Efecto: `estado='rechazado'`, `activo=false`.
- Éxito → redirect/JSON con `success`: "Usuario rechazado."

## Cambio en login (`LoginController@store`)

Tras un intento fallido por `activo=false`: si existe un usuario con ese email cuya contraseña
coincide y `estado ∈ {pendiente, rechazado}`, devolver mensaje específico
("Tu cuenta aún no está aprobada" / "Tu cuenta no está habilitada") en vez del genérico.
El gating sigue siendo `activo=true` en `Auth::attempt`.
