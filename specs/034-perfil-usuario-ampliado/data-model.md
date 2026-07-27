# Data Model: Ampliación del perfil de usuario

## User (ampliación)

Tabla existente `users`. Se agregan 3 columnas nuevas (migración nueva, no se toca ninguna
columna existente):

| Columna | Tipo | Nullable | Descripción |
|---|---|---|---|
| `pending_email` | `string(255)` | sí | Correo propuesto, pendiente de verificación. `null` si no hay solicitud en curso. |
| `pending_email_token` | `string(64)` | sí | Token aleatorio incluido en la firma del enlace de verificación (defensa adicional a la firma de Laravel; permite invalidar un enlace reenviado). |
| `pending_email_expires_at` | `timestamp` | sí | Vencimiento del enlace (ahora + 24h al solicitar). Un enlace con esta fecha en el pasado se trata como caducado aunque la firma siga siendo criptográficamente válida. |

**Validaciones**:
- `pending_email` único a nivel de tenant activo entre `email` y `pending_email` de otros
  usuarios (no se permite reservar un correo que otro usuario del tenant ya usa o tiene pendiente).
- Al confirmar: `email = pending_email`; limpiar las 3 columnas.
- Al cancelar, reenviar, o al iniciarse una nueva solicitud: limpiar/regenerar las 3 columnas
  (nunca queda más de una solicitud vigente por usuario).

**Relaciones ya existentes reutilizadas** (sin cambios):
- `tenant()` — BelongsTo `Tenant`.
- `miembroEquipo()` — HasOne `MiembroEquipo`.
- `aprobador()` — BelongsTo `User` (self), vía `aprobado_por`.

**Nota RGPD (Principio II)**: estas 3 columnas son estado transitorio de un único flujo activo
por usuario (no un ledger acumulativo), se sobrescriben o limpian en cada operación y se
consideran caducadas por lectura tras 24h — no requieren el patrón de purga programada de
`RetencionLogsTenant`/`logs:purgar` (ver `plan.md`, Constitution Check).

## Rol / Permiso (solo lectura, sin cambios de esquema)

El perfil consume, sin persistir nada nuevo:
- `auth()->user()->getRoleNames()` (Spatie) → nombre del rol dinámico.
- `auth()->user()->getAllPermissions()->pluck('name')` → claves de permiso, resueltas a
  etiqueta/módulo vía `App\Support\CatalogoPermisos::porModulo()`.

## Registro de actividad (solo lectura, sin cambios de esquema)

Consulta sobre `LogActividad` (tabla `logs_actividad`, ya existente) filtrada por
`usuario_id = auth()->id()`, ordenada por `ocurrido_at` descendente, límite 5 para la vista de
perfil (FR-013). Reutiliza el enriquecimiento ya usado en `/logs` (`AgenteUsuario::label()`,
`GeolocalizadorIp::ubicacion()`).

## Miembro de equipo / Fichaje (solo lectura, sin cambios de esquema)

- `MiembroEquipo` (tabla `miembros_equipo`, ya existente): se consumen `puesto`,
  `trabajo_direccion` (o el campo de centro de trabajo asignado equivalente), `activo`,
  `dado_baja_at`.
- `Fichaje` (tabla `fichajes`, ya existente, ledger append-only): se consume el último evento no
  corregido para derivar el estado (`abierta`/`cerrada`/`en_pausa`) y los N eventos más recientes.

**Decisión de diseño**: extraer la lógica de derivación de estado — hoy duplicada entre
`FichajeController::estadoActual()` y `RegistroFichajes::validarSecuencia()` — a un único método
reutilizable (p. ej. `RegistroFichajes::estadoActual(int $miembroId): string`), y hacer que
`FichajeController` y el nuevo `ProfileController` consuman esa misma fuente. Esto no es un
requisito funcional nuevo del perfil, sino una limpieza necesaria para no introducir una tercera
copia de la misma lógica al construir esta feature (evaluar alcance exacto en tasks.md).

## Aprobación de cuenta (solo lectura, sin cambios de esquema)

Consume `aprobado_por` (vía relación `aprobador()`) y `aprobado_en`, ya presentes en `User`.

## State transitions — Solicitud de cambio de email

```text
[sin solicitud] --(solicitar cambio, password OK)--> [pendiente, vence en 24h]
[pendiente] --(confirmar enlace válido y no vencido)--> [aplicado] --> [sin solicitud]
[pendiente] --(cancelar)--> [sin solicitud]
[pendiente] --(reenviar)--> [pendiente, nuevo token, vence en 24h] (invalida el enlace anterior)
[pendiente] --(vence sin confirmar)--> [caducado, tratado como sin solicitud al leer]
```

No hay transición para "editar el pending_email sin invalidar el anterior": cualquier nueva
solicitud reemplaza por completo a la anterior (nunca coexisten dos solicitudes).
