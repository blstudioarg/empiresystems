# Mi perfil

Cada usuario tiene su propia página "Mi perfil" (autoservicio sobre su propia cuenta, no requiere
permiso especial). Ahí puede:

- Ver su **rol real y permisos efectivos** (agrupados por módulo), no un valor desactualizado.
  El Super Admin ve un aviso de acceso total en vez de un listado de permisos.
- Cambiar su **foto de perfil** y su **nombre** (se aplica de inmediato).
- Cambiar su **contraseña** (pide la actual + la nueva, mínimo 8 caracteres): al guardar se cierran
  automáticamente sus sesiones activas en otros dispositivos.
- Cambiar su **email**: pide la contraseña actual y envía un enlace de confirmación válido 24h al
  correo nuevo; el email actual sigue funcionando hasta confirmar. Puede cancelar o reenviar el
  enlace mientras la solicitud esté pendiente.
- Ver su **actividad reciente** (últimos 5 eventos: login, altas, modificaciones, etc.), con enlace
  a Logs de actividad completo solo si tiene ese permiso.
- Si está vinculado a un perfil de empleado (control de fichaje) activo, ver su **puesto, centro de
  trabajo y estado de jornada en vivo**, más sus últimos fichajes.
- Ver **quién aprobó su cuenta y cuándo**, o el estado "Pendiente de aprobación" si todavía no fue
  aprobada.

El asistente no puede cambiar la contraseña, el email ni el rol de un usuario: son acciones que
cada usuario hace por sí mismo desde su perfil, o que un administrador hace desde Usuarios/Roles.
