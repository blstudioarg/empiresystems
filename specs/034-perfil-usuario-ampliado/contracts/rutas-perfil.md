# Contrato de rutas: Perfil ampliado

Todas las rutas viven bajo el grupo ya existente en `routes/web.php` (autenticado, con tenancy
inicializada), junto a `profile.show` / `profile.avatar.update`. Ninguna requiere permiso
adicional (el perfil es autoservicio sobre el propio usuario, exceptuado de la regla de "nueva
entrada de menú ⇒ nuevo permiso" según `docs/04-front-guidelines.md`).

| Método | Ruta | Nombre | Acción | Request | Respuesta éxito | Respuesta error |
|---|---|---|---|---|---|---|
| GET | `/perfil` | `profile.show` | Ver perfil ampliado | — | Vista con rol/permisos, actividad reciente (5), datos de empleado/fichaje si aplica, info de aprobación, estado de solicitud de email pendiente si existe | — |
| PUT | `/perfil/contrasena` | `profile.password.update` | Cambiar contraseña propia | `contrasena_actual`, `password`, `password_confirmation` | 200/redirect + toast éxito; sesiones de otros dispositivos invalidadas | 422 validación (actual incorrecta / no coincide / min:8) |
| PUT | `/perfil/nombre` | `profile.nombre.update` | Cambiar nombre visible | `name` | 200/redirect + toast éxito, nombre reflejado en sidebar/cabecera | 422 validación (requerido, longitud) |
| POST | `/perfil/email` | `profile.email.solicitar` | Solicitar cambio de email | `contrasena_actual`, `nuevo_email` | 200/redirect + toast "revisa tu correo"; email enviado a `nuevo_email` | 422 (password incorrecta / email duplicado en tenant / formato inválido) |
| GET | `/perfil/email/confirmar/{user}` (firmada) | `profile.email.confirmar` | Confirmar cambio de email vía enlace | firma de URL + `pending_email_token` | Redirect a `/perfil` + toast éxito, `email` actualizado | Redirect a `/perfil` + toast error si firma inválida o vencida |
| DELETE | `/perfil/email/pendiente` | `profile.email.cancelar` | Cancelar solicitud pendiente | — | 200/redirect + toast, columnas `pending_email_*` limpiadas | 404 si no hay solicitud pendiente |
| POST | `/perfil/email/pendiente/reenviar` | `profile.email.reenviar` | Reenviar verificación | — | 200/redirect + toast, nuevo token/vencimiento, enlace anterior invalidado | 404 si no hay solicitud pendiente |

**Autorización**: todas operan implícitamente sobre `auth()->user()` — ningún endpoint acepta un
id de usuario distinto al autenticado (excepto el `{user}` de la ruta firmada de confirmación,
que existe solo para poder generar la URL firmada por usuario; el controller además valida que
`Auth::id() === $user->id` antes de aplicar el cambio, para que un enlace no pueda usarse desde la
sesión de otro usuario).

**Multi-tenant**: ninguna ruta recibe ni permite un `tenant_id` explícito; todas las consultas se
derivan del usuario autenticado y su tenant activo, con los modelos subyacentes (`LogActividad`,
`Fichaje`, `MiembroEquipo`) ya scoped vía `BelongsToTenant`.
