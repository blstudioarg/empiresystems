# HTTP Contracts — Control horario y fichajes

Rutas web (Blade + POST con CSRF), monolito Laravel. Todas bajo sesión autenticada del tenant. Roles:
`Usuario` (ficha + portal), `Admin` (ubicaciones, correcciones, informe global). Ninguna ruta expone
edición/borrado de fichajes (append-only).

## Fichar (rol Usuario y Admin)

### GET `/fichajes`
Pantalla de fichaje con mapa Leaflet. Renderiza estado actual (última acción, si hay jornada abierta),
las ubicaciones activas del tenant (centro+radio) para dibujar el/los círculo(s) y la config de
pausas/geofencing. No captura posición en servidor.

### POST `/fichajes`
Registrar un evento de fichaje. Cuerpo (validado por `FicharRequest`):

| Campo      | Tipo    | Req | Notas |
|------------|---------|-----|-------|
| tipo       | string  | sí  | `entrada`\|`salida`\|`inicio_pausa`\|`fin_pausa` (pausas solo si habilitadas) |
| latitud    | decimal | no  | del instante; ausente si el usuario no concede geo |
| longitud   | decimal | no  | del instante |
| precision  | int     | no  | metros reportados por el navegador |

Comportamiento:
- Solo un `user` con **perfil de miembro** puede fichar; un user sin miembro → 403 / aviso.
- La **hora** la fija el servidor (`now()`), ignora cualquier hora del cliente (FR-002).
- El backend calcula la distancia Haversine a la **ubicación de trabajo del miembro** → veredicto
  (Dentro/Fuera/SinUbicacion); guarda distancia + precisión, **no** lat/long (D4).
- Sin geo concedida → `SinUbicacion` (o política configurada); nunca fabrica posición (FR-012).
- Resultado `Fuera` (distancia > `distancia_max_metros` del miembro) → el servicio **crea una alerta**
  enlazada al fichaje (FR-013b), en la misma transacción.
- Geofencing bloqueante (config) + resultado `Fuera` → **422** con mensaje; no persiste (ni alerta).
- Reglas de secuencia inconsistentes (FR-006) → persiste marcando/avisando, no descarta (salvo
  bloqueo explícito).
- Éxito → **302** redirect a `/fichajes` con flash `success` (toastr). Persiste fila inmutable.

Respuestas: `302` (ok/redirect con flash), `403` (user sin perfil de miembro), `422` (bloqueado por
geofencing o validación de tipo).

## Mi jornada — portal del trabajador (rol Usuario)

### GET `/mi-jornada`
Lista los **propios** fichajes del usuario autenticado y su total de horas por periodo (filtro de
fechas). Controlador filtra por `tenant_id` **y** `auth()->id()` (nunca ve los de otros, FR-019).

### GET `/mi-jornada/exportar`
Descarga de los propios registros del periodo (formato legible/completo).

## Informe de jornada — administración (rol Admin)

### GET `/jornada`
Informe por empleado y periodo de toda la plantilla del tenant (selección de usuario + rango).
Paginación/agregación server-side. Total de horas calculado en backend (FR-004). Incluye traza de
correcciones.

### GET `/jornada/exportar`
Exporta los registros de un empleado/periodo de forma legible y completa, incluidas correcciones
(FR-018). Formato de entrega para trabajador/representantes/Inspección.

## Corrección de fichajes (rol Admin)

### POST `/fichajes/{fichaje}/corregir`
Crea un **evento de corrección** enlazado (`corrige_fichaje_id`), sin tocar el original (D6). Cuerpo
(validado por `CorregirFichajeRequest`):

| Campo        | Tipo    | Req | Notas |
|--------------|---------|-----|-------|
| tipo         | string  | sí  | tipo corregido |
| ocurrido_at  | datetime| sí  | hora corregida |
| motivo       | string  | sí  | **obligatorio** (FR-014); 422 si falta |

`{fichaje}` se resuelve en el cuerpo del controlador filtrando por `tenant_id` (no implicit binding —
memoria `project_tenant_route_binding`). Respuestas: `302` (ok), `403` (no Admin), `422` (sin motivo).

## Miembros de equipo (rol Admin)

### GET `/miembros-equipo`
Lista de miembros del tenant (con su user vinculado, ubicación de trabajo, distancia máx y distancia
casa-trabajo).

### GET `/miembros-equipo/crear` · GET `/miembros-equipo/{miembro}/editar`
Formulario con mapa Leaflet para situar las coordenadas de trabajo y de casa y fijar la distancia máx.

### POST `/miembros-equipo` · PUT `/miembros-equipo/{miembro}`
Crear/actualizar (validado por `MiembroEquipoRequest`): `user_id` (único, cuenta de login),
`puesto?`, `trabajo_direccion?`, `trabajo_latitud`, `trabajo_longitud`, `distancia_max_metros` (≥1),
`casa_direccion?`, `casa_latitud?`, `casa_longitud?`, `activo`. Al guardar, el backend recalcula
`distancia_casa_trabajo_metros` (Haversine). Resolución de `{miembro}` por query con `tenant_id`.

### DELETE `/miembros-equipo/{miembro}`
Da de baja el miembro (`dado_baja_at` + SoftDelete): conserva sus fichajes; sus datos de casa se
purgan pasado el plazo configurable (`miembros:purgar-casa`).

## Alertas (rol Admin)

### GET `/alertas`
Bandeja de alertas del tenant (miembro, fichaje, fecha, distancia, estado). Filtro por estado.
Controlador filtra por `tenant_id`.

### PATCH `/alertas/{alerta}`
Cambia el estado de una alerta (`vista`/`resuelta`); registra `resuelta_por`/`resuelta_at`. No borra.
Resolución de `{alerta}` por query con `tenant_id`. Respuestas: `302` (ok), `403` (no Admin).

## Autorización (resumen)

| Ruta | Usuario | Admin |
|------|---------|-------|
| `/fichajes` (GET/POST) | ✅ (si es miembro) | ✅ (si es miembro) |
| `/mi-jornada*` | ✅ (solo lo suyo) | ✅ (solo lo suyo) |
| `/jornada*` | ❌ 403 | ✅ |
| `/fichajes/{id}/corregir` | ❌ 403 | ✅ |
| `/miembros-equipo*` | ❌ 403 | ✅ |
| `/alertas*` | ❌ 403 | ✅ |
