# Quickstart: Validar el perfil ampliado

## Prerrequisitos

- Entorno local funcionando (`php artisan serve` o equivalente ya usado en el proyecto).
- Al menos 2 tenants de prueba con usuarios, para las validaciones de aislamiento.
- Un usuario de prueba vinculado a un `MiembroEquipo` activo con algún `Fichaje` registrado, para
  validar la sección de empleado/fichajes.
- Un usuario de prueba con cuenta aprobada (con `aprobado_por`/`aprobado_en` seteados) y otro con
  cuenta pendiente.
- Colas configuradas igual que en el resto del proyecto (si el envío de correo se encola).

## Escenarios de validación (uno por User Story de spec.md)

1. **Rol/permisos reales** — Iniciar sesión con un usuario cuyo rol Spatie sea distinto al valor
   legado de `rol`. Entrar a `/perfil`. Verificar que el rol mostrado es el rol Spatie real y que
   el listado de permisos coincide con `CatalogoPermisos` para ese rol.
2. **Cambio de contraseña** — Desde `/perfil`, cambiar la contraseña con datos válidos. Cerrar
   sesión, iniciar sesión con la nueva contraseña (debe funcionar) y con la anterior (debe
   fallar). Si había otra sesión activa en otro navegador/dispositivo, verificar que quedó
   invalidada.
3. **Editar nombre/email** — Cambiar el nombre y verificar que se refleja de inmediato en el
   sidebar. Solicitar cambio de email con la contraseña actual; verificar que llega el correo con
   el enlace, que el login sigue funcionando con el email viejo hasta confirmar, y que al hacer
   clic en el enlace el email cambia y dicho enlace no puede reutilizarse. Probar también
   "cancelar" y "reenviar".
4. **Actividad reciente** — Generar algunos accesos (login correcto, uno fallido) y entrar a
   `/perfil`; verificar que aparecen los últimos 5 con resultado/IP/navegador/ubicación, y que el
   enlace "ver más" solo aparece para un usuario con permiso `ver-logs`.
5. **Empleado y fichajes** — Con el usuario vinculado a `MiembroEquipo`, fichar entrada desde el
   módulo de fichajes y luego entrar a `/perfil`: el estado mostrado debe coincidir con el que
   muestra `/fichajes`. Con un usuario sin vínculo, verificar que la sección no aparece.
6. **Aprobación de cuenta** — Con el usuario aprobado, verificar que se muestra el nombre del
   aprobador y la fecha. Con el usuario pendiente, verificar que se muestra el estado pendiente en
   su lugar.

## Validación de aislamiento multi-tenant (obligatoria, Principio I)

- Con dos tenants A y B, cada uno con un usuario que tiene actividad y fichajes propios: verificar
  que el usuario de A nunca ve en su perfil actividad, fichajes o datos de miembro de equipo
  pertenecientes a un usuario de B, aunque compartan el mismo id relativo o nombre.

## Resultado esperado

Todas las verificaciones anteriores pasan sin errores en consola/servidor, sin usar `alert()`
nativo (todo feedback vía `toastr`), y sin que ningún endpoint nuevo permita operar sobre un
usuario distinto al autenticado.
