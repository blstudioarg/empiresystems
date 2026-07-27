# Research: Ampliación del perfil de usuario

## 1. Rol y permisos reales

**Decision**: Usar `auth()->user()->isSuperAdmin() ? [mensaje de acceso total] : auth()->user()->getRoleNames()->first()` para el rol, y `auth()->user()->getAllPermissions()->pluck('name')` mapeado contra `App\Support\CatalogoPermisos::porModulo()` para obtener etiqueta + módulo legibles, agrupando el listado por módulo en la vista.

**Rationale**: Es exactamente el patrón ya usado en `partials/sidebar.blade.php:36` para decidir qué rol mostrar; `CatalogoPermisos` ya expone `etiqueta` y `modulo` en español por cada `clave` de permiso (`app/Support/CatalogoPermisos.php:18-52`), evitando construir un mapeo nuevo.

**Alternatives considered**: Mostrar las claves técnicas crudas (`ver-facturas`) — rechazado, viola la guía de que el perfil debe ser comprensible para un usuario no técnico (FR-002). Seguir mostrando el enum legado `rol` — rechazado, es la causa del bug detectado.

## 2. Cambio de contraseña self-service

**Decision**: Formulario con `contrasena_actual`, `password` (confirmed), validado con `Hash::check()` contra la contraseña actual y reglas `['required','confirmed','min:8']` (mismas que `RegisterRequest`). Al éxito: `Hash::make()` + guardar, y `DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete()`.

**Rationale**: Reutiliza exactamente las reglas de password ya vigentes (`RegisterRequest.php:22`), evitando introducir un estándar de complejidad nuevo no usado en el resto del sistema. El borrado selectivo de `sessions` es viable porque el driver de sesión ya es `database` (`config/session.php:21`, `.env:30`), sin necesitar Fortify ni middleware de reautenticación no presentes en el proyecto.

**Alternatives considered**: `Auth::logoutOtherDeviceSessions()` (helper de Laravel) — requiere el middleware `auth.session` configurado sobre `web` de una forma específica y reautenticar la contraseña vía `Auth::once()`; con sesiones en BD, el borrado directo de filas es más simple y ya decidido por el usuario en la fase de clarify. `Password::defaults()` con reglas de complejidad nuevas — rechazado por ahora (fuera de alcance, no vigente en el resto del sistema; se podría proponer como mejora futura transversal, no específica de esta feature).

## 3. Edición de nombre y verificación de cambio de email

**Decision**: Nombre: update directo sin fricción. Email: 3 columnas nuevas en `users` (`pending_email`, `pending_email_token`, `pending_email_expires_at`); al solicitar el cambio (con contraseña actual confirmada) se genera un token aleatorio, se guardan las 3 columnas y se envía un Mailable con una URL firmada (`URL::temporarySignedRoute`) de vencimiento 24h a `pending_email`. Al visitar el enlace, si la firma es válida y no venció, se copia `pending_email` a `email`, se limpian las 3 columnas. Si el usuario solicita un nuevo cambio o cancela, se sobrescriben/limpian las columnas.

**Rationale**: Decisión ya tomada explícitamente en `/speckit-clarify` (enlace firmado temporal, no `MustVerifyEmail`). `URL::temporarySignedRoute` es el mecanismo estándar de Laravel para enlaces con vencimiento sin infraestructura adicional (no requiere tabla de tokens separada, ya que la firma es criptográfica sobre la URL + parámetros); es coherente con Principio V (simplicidad, hosting compartido).

**Alternatives considered**: Tabla `password_reset_tokens`-style dedicada para "email_change_tokens" — rechazada por syntetizar una tabla nueva de negocio para un dato transitorio que cabe en 3 columnas de `users`, sin necesidad de índices/relaciones adicionales. Framework `MustVerifyEmail` — descartado explícitamente en clarify por estar pensado para verificación inicial de cuenta y no estar implementado hoy en el proyecto.

## 4. Actividad reciente propia

**Decision**: Reutilizar `App\Services\RegistradorActividad` (o consulta directa a `LogActividad::where('usuario_id', auth()->id())->latest('ocurrido_at')->limit(5)->get()`), enriquecido igual que en `LogActividadController` con `App\Support\AgenteUsuario::label()` y `App\Support\GeolocalizadorIp::ubicacion()` (cacheada). Enlace "ver más" hacia `route('logs.index')` visible solo si `auth()->user()->can('ver-logs')`.

**Rationale**: `LogActividad` ya usa `BelongsToTenant`, así que el aislamiento de tenant es automático; solo se agrega el filtro por usuario. Reutilizar el mismo enriquecimiento (navegador/ubicación) evita duplicar lógica de formateo ya usada en `/logs`.

**Alternatives considered**: Duplicar la lógica de `AgenteUsuario`/`GeolocalizadorIp` en el controller de perfil — rechazado, viola DRY sin necesidad; se importan los mismos Support classes.

## 5. Datos de empleado y fichajes

**Decision**: Si `auth()->user()->miembroEquipo` existe y `activo`, mostrar `puesto`, `trabajo_direccion` (o centro asignado) y llamar a la misma lógica de `FichajeController::estadoActual($miembro->id)` para el estado (`abierta`/`cerrada`/`en_pausa`), más los últimos eventos vía la misma consulta que usa `FichajeController::eventosHoy()` (o un límite equivalente de últimos N eventos, no solo "hoy"). Si no hay miembro de equipo, o `dado_baja_at` no es null, se omite la sección (o se indica "vínculo inactivo" sin estado en vivo).

**Rationale**: Evita reimplementar la derivación de estado (que ya está duplicada en `FichajeController` y `RegistroFichajes::validarSecuencia()` según la investigación previa) creando una tercera copia; se extrae/reutiliza el método existente en vez de triplicar la lógica. `MiembroEquipo` y `Fichaje` ya usan `BelongsToTenant`.

**Alternatives considered**: Duplicar la lógica de estado directamente en `ProfileController` — rechazado, generaría una tercera fuente de verdad divergente para el mismo cálculo (riesgo detectado explícitamente en la investigación previa). Se decide durante el diseño de datos si conviene extraer `estadoActual()` a un servicio compartido (p. ej. `RegistroFichajes::estadoActual()`) para que tanto `FichajeController` como `ProfileController` lo consuman — ver `data-model.md`.

## 6. Info de aprobación de cuenta

**Decision**: Usar directamente `User::aprobador()` (ya definida, `belongsTo(User::class, 'aprobado_por')`) y `aprobado_en`, ya presentes en el modelo (`app/Models/User.php`).

**Rationale**: Cero trabajo adicional de datos, ya existe la relación y las columnas (migración `2026_07_03_120000_add_estado_to_users_table.php`).

**Alternatives considered**: N/A, no hay decisión que tomar.

## 7. Formato de UI y componentes reutilizables

**Decision**: Toastr (`window.showToast`) para feedback de las acciones de escritura nuevas; `window.withButtonLoading` en los submits AJAX de cambio de contraseña/nombre/email; `window.confirmDelete` para la acción "cancelar solicitud de cambio de email" (es una acción que descarta una solicitud en curso, tratable como reversible-menor, a decidir en diseño si amerita confirmación o alcanza con feedback de toastr simple, dado que no es destructiva de datos permanentes).

**Rationale**: Cumple `docs/04-front-guidelines.md` (notificaciones, estados de carga en botones AJAX), evita reintroducir alerts nativos.

**Alternatives considered**: Ninguna, son convenciones ya establecidas y no negociables del proyecto.
