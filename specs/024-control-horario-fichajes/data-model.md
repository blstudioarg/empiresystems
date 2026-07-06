# Data Model — Control horario y fichajes

Fase 1. Tres tablas de negocio nuevas (`tenant_id` + `BelongsToTenant`, Principio I): `miembros_equipo`,
`fichajes` (ledger append-only) y `alertas`. Reutiliza `configuraciones` (clave/valor por tenant) para
flags y retención. Decimales/convenciones según `docs/03-modelo-datos.md`.

## Tabla `miembros_equipo`

Perfil de empleado, vinculado 1:1 a un `User` con login (D7). Editable por Admin (no es ledger).

| Columna                      | Tipo               | Notas |
|------------------------------|--------------------|-------|
| id                           | BIGINT PK          | |
| tenant_id                    | BIGINT FK, index   | Principio I |
| user_id                      | BIGINT FK, UNIQUE  | 1:1 con la cuenta de login; quien ficha |
| puesto                       | VARCHAR(120), null | cargo/rol laboral (opcional) |
| trabajo_direccion            | VARCHAR(255), null | dirección legible del centro |
| trabajo_latitud              | DECIMAL(10,7), null | centro de trabajo (perímetro de este miembro); nullable — un miembro puede no tener ubicación configurada todavía (edge case: sus fichajes se marcan `SinUbicacion`, sin alerta) |
| trabajo_longitud             | DECIMAL(10,7), null | |
| distancia_max_metros         | INT UNSIGNED       | tolerancia para fichar (> 0); más lejos = Fuera + alerta |
| casa_direccion               | VARCHAR(255), null | **dato personal** (D12), purgable tras baja |
| casa_latitud                 | DECIMAL(10,7), null| **dato personal**, purgable tras baja |
| casa_longitud                | DECIMAL(10,7), null| **dato personal**, purgable tras baja |
| distancia_casa_trabajo_metros| INT UNSIGNED, null | métrica derivada (Haversine casa↔trabajo); se conserva |
| activo                       | BOOLEAN            | default true; inactivo no ficha |
| dado_baja_at                 | DATETIME, null     | marca de baja → dispara la purga de datos de casa |
| timestamps                   | —                  | |
| softDeletes                  | —                  | conservar histórico ligado a sus fichajes |

Reglas: `distancia_max_metros` ≥ 1; `trabajo_latitud/longitud` válidas; `user_id` único por sistema.
`distancia_casa_trabajo_metros` se recalcula al cambiar casa o trabajo. Índice `(tenant_id, activo)`.

### Columnas de dato personal de casa (purga, D12)
`casa_direccion`, `casa_latitud`, `casa_longitud`. Al marcar `dado_baja_at`, el comando de purga las
pone a NULL pasado el plazo configurable; `distancia_casa_trabajo_metros` (no identificable) puede
conservarse como métrica histórica.

## Tabla `fichajes` (ledger append-only)

Evento inmutable de jornada. Único punto de escritura: `App\Services\RegistroFichajes`. Sin rutas de
edición/borrado, sin SoftDeletes. Única modificación admitida: nulificación de columnas de geo por el
comando de purga (D5).

| Columna              | Tipo                         | Notas |
|----------------------|------------------------------|-------|
| id                   | BIGINT PK                    | |
| tenant_id            | BIGINT FK, index             | Principio I |
| miembro_equipo_id    | BIGINT FK → miembros_equipo  | quién ficha (D7) |
| tipo                 | ENUM/string                  | `TipoEventoFichaje`: Entrada, Salida, InicioPausa, FinPausa |
| ocurrido_at          | DATETIME                     | **hora de servidor** (FR-002); para correcciones, la hora corregida |
| resultado_ubicacion  | ENUM/string, nullable        | `ResultadoUbicacionFichaje`: Dentro, Fuera, SinUbicacion (null tras purga geo) |
| distancia_metros     | INT UNSIGNED, nullable        | distancia calculada al trabajo del miembro (null tras purga geo) |
| precision_metros     | INT UNSIGNED, nullable       | precisión reportada por el navegador (null tras purga geo) |
| corrige_fichaje_id   | BIGINT FK → fichajes, null   | si es corrección, apunta al original (D6) |
| motivo               | VARCHAR(255), nullable       | obligatorio SOLO cuando `corrige_fichaje_id` no es null |
| registrado_por       | BIGINT FK → users, nullable  | corrector (Admin) en correcciones; null en fichaje propio |
| ip_origen            | VARCHAR(45), nullable        | registro de acceso (Principio II.b) |
| user_agent           | VARCHAR(255), nullable       | registro de acceso |
| timestamps           | —                            | `created_at` = persistencia real |

Índices: `(tenant_id, miembro_equipo_id, ocurrido_at)` (informe); `(tenant_id, ocurrido_at)` (purga geo);
`corrige_fichaje_id`.

### Columnas de geo (purga, D5)
`resultado_ubicacion`, `distancia_metros`, `precision_metros`. `fichajes:purgar-geo` las pone a NULL
cuando `ocurrido_at` supera el plazo de retención de geo del tenant. La fila permanece (jornada, 4 años).

### Estados / secuencia
Sin columna de estado: la jornada se deriva de los eventos por miembro y día (validación en
`RegistroFichajes`, no en BD). Entrada solo sin entrada abierta; Salida requiere entrada abierta;
pausas solo con entrada abierta (FR-006).

## Tabla `alertas`

Creada por `RegistroFichajes` al registrar un fichaje `Fuera` (D11). Estado gestionable, no se borra.

| Columna           | Tipo                        | Notas |
|-------------------|-----------------------------|-------|
| id                | BIGINT PK                   | |
| tenant_id         | BIGINT FK, index            | Principio I |
| miembro_equipo_id | BIGINT FK → miembros_equipo | a quién |
| fichaje_id        | BIGINT FK → fichajes        | fichaje que la disparó |
| tipo              | ENUM/string                 | `TipoAlerta`: FichajeFueraDeRango (extensible) |
| distancia_metros  | INT UNSIGNED                | distancia del fichaje que superó la tolerancia |
| estado            | ENUM/string                 | `EstadoAlerta`: Nueva, Vista, Resuelta |
| resuelta_por      | BIGINT FK → users, nullable | Admin que la resolvió |
| resuelta_at       | DATETIME, nullable          | |
| timestamps        | —                           | |

Índice `(tenant_id, estado)`.

## Enums nuevos

- `TipoEventoFichaje`: `Entrada`, `Salida`, `InicioPausa`, `FinPausa`.
- `ResultadoUbicacionFichaje`: `Dentro`, `Fuera`, `SinUbicacion`.
- `TipoAlerta`: `FichajeFueraDeRango`.
- `EstadoAlerta`: `Nueva`, `Vista`, `Resuelta`.
- (Reutiliza `UserRole`: `Admin` gestiona miembros/corrige/informa/alertas; `Usuario`+miembro ficha/portal.)

## Configuración por tenant (`configuraciones`)

| Clave                            | Tipo | Default | Uso |
|----------------------------------|------|---------|-----|
| `fichajes.retencion_geo_dias`    | int  | 30      | `RetencionGeoTenant` → purga de geo del fichaje (D5) |
| `fichajes.retencion_casa_dias`   | int  | 30      | `RetencionMiembroTenant` → purga datos de casa tras baja (D12) |
| `fichajes.geofencing_bloqueante` | bool | false   | bloquear fichaje Fuera del rango (D8) |
| `fichajes.registrar_pausas`      | bool | false   | habilita botones de pausa y su cálculo (D9) |

## Entidades de cálculo (no persistidas)

- **Jornada / Informe**: `App\Services\InformeJornada` agrupa eventos por miembro y periodo, empareja
  entradas/salidas (y pausas si aplica) y calcula horas efectivas server-side (FR-004).
- **Distancia casa-trabajo**: `App\Support\Haversine` entre coordenadas de casa y de trabajo del
  miembro; el resultado se persiste en `distancia_casa_trabajo_metros`.
