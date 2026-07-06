# Data Model — Gestión de horarios de trabajo y cumplimiento

> MySQL/MariaDB. Base compartida: toda tabla lleva `tenant_id` indexado + `BelongsToTenant`.
> Convención: `id` BIGINT, `timestamps`; `softDeletes` donde aplique. Horas en columnas `TIME`.

## Tablas nuevas

### `horarios` — plantilla de cuadrante reutilizable (feature 025)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| nombre | varchar(120) | único por `(tenant_id, nombre)` sobre no borrados |
| activo | boolean, default true | inactivo no se ofrece para asignar |
| softDeletes, timestamps | | soft delete solo si no tiene asignaciones (FR-006) |

Índice `(tenant_id, activo)`. `hasMany` `horario_tramos`, `hasMany` `asignaciones_horario`.
Horas previstas (por día y semanales) son **derivadas** de los tramos (no columna): método
`Horario::horasPrevistasSemana()` / `horasPrevistasDia(int $diaSemana)`.

### `horario_tramos` — tramo de trabajo de un horario (feature 025)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` (se afirma el scope igual que el padre) |
| horario_id | fk → horarios, `cascadeOnDelete` | |
| dia_semana | unsignedTinyInteger | 1=lunes … 7=domingo (ISO-8601, `Carbon::dayOfWeekIso`) |
| hora_inicio | time | |
| hora_fin | time | `hora_fin > hora_inicio` (validado; no cruza medianoche en v1) |
| timestamps | | |

Índice `(tenant_id, horario_id, dia_semana)`. **Validación (Request, no BD)**: dentro de un mismo
`(horario_id, dia_semana)` los tramos no se solapan; `hora_fin > hora_inicio`. Varios tramos por día
= turno partido; sin tramos un día = día libre (0 horas).

### `asignaciones_horario` — horario aplicable a un miembro con vigencia (feature 025)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| miembro_equipo_id | fk → miembros_equipo, `cascadeOnDelete` | quién |
| horario_id | fk → horarios, `restrictOnDelete` | qué horario (impide borrar horario asignado, FR-006) |
| vigente_desde | date | inicio de vigencia (inclusive) |
| vigente_hasta | date, nullable | fin de vigencia (inclusive); NULL = abierta/vigente |
| timestamps | | |

Índices: `(tenant_id, miembro_equipo_id, vigente_desde)`, `(tenant_id, horario_id)`.
**Invariante** (servicio + validación, FR-009): para un miembro, ningún par de asignaciones se
solapa; como mucho una con `vigente_hasta IS NULL`. Resolución del aplicable en fecha F: la fila con
`vigente_desde <= F AND (vigente_hasta IS NULL OR vigente_hasta >= F)`.

## Tabla existente modificada

### `alertas` (feature 024) — nuevo campo nullable para deduplicar alertas de jornada

| Campo | Tipo | Notas |
|-------|------|-------|
| referencia_fecha | date, nullable | **nuevo**: día evaluado que originó la alerta de jornada (ausencia/retraso). NULL en las alertas de `fichaje_fuera_de_rango` existentes |

Migración aditiva (columna nullable) — no toca las filas existentes. Único parcial lógico (a nivel
de servicio, no índice) para idempotencia del comando: no crear dos alertas con el mismo
`(tenant_id, miembro_equipo_id, tipo, referencia_fecha)`. Las alertas de jornada no usan
`distancia_metros` (queda NULL) ni `fichaje_id` (nullable ya; se deja NULL o se apunta al fichaje de
entrada tardía en el caso de retraso, opcional). **Nota**: revisar que `alertas.fichaje_id` sea
nullable; si hoy es `restrictOnDelete` no-nullable, la migración debe hacerlo nullable para las
alertas de jornada (que pueden no tener un fichaje asociado, caso ausencia).

## Enums

### `App\Enums\TipoAlerta` (extender)

```
FichajeFueraDeRango = 'fichaje_fuera_de_rango'   (existente)
AusenciaJornada     = 'ausencia_jornada'          (nuevo)
RetrasoJornada      = 'retraso_jornada'           (nuevo)
```

### `App\Enums\DiaSemana` (nuevo, opcional)

Enum `int` 1–7 con `label()` ("Lunes"…"Domingo") para las vistas; o usar int directo + helper. No
crítico; decisión menor de implementación.

## Value objects / servicios

### `App\Support\Cumplimiento\ResultadoDia` (nuevo, value object)

Inmutable, por miembro y día: `fecha`, `horas_previstas` (float horas), `horas_trabajadas`,
`incidencia` (bool, fichaje incompleto), `veredicto` (enum: `libre`, `ausencia`, `retraso`,
`parcial`, `cumplido`, `exceso`), `minutos_retraso` (por tramo, agregado), `diferencia_horas`
(exceso/déficit). Derivado, **no** persistido.

### `App\Support\Cumplimiento\ServicioCumplimiento` (nuevo)

- `horasTrabajadas(MiembroEquipo, Carbon $dia): array` — empareja el ledger `fichajes` (entrada/
  salida, resta pausas, aplica correcciones); marca incidencia si incompleto.
- `resolverHorario(MiembroEquipo, Carbon $dia): ?Horario` — vía `asignaciones_horario` vigente.
- `evaluarDia(MiembroEquipo, Carbon $dia): ResultadoDia` — combina previsto (tramos del día) vs.
  trabajado y clasifica (R6).
- `evaluarRango(MiembroEquipo, RangoFechas): Collection<ResultadoDia>` — reutiliza `App\Support\RangoFechas`.

### `App\Support\ConfigFichajes` (extender)

```
CLAVE_TOLERANCIA_RETRASO_MIN = 'fichajes.tolerancia_retraso_min'   default 5
CLAVE_TOLERANCIA_EXCESO_MIN  = 'fichajes.tolerancia_exceso_min'    default 15
```
Métodos `toleranciaRetrasoMin(int $tenantId): int` / `toleranciaExcesoMin(...)`. Alta en
`ConfiguracionSeeder` (grupo `fichajes`) + inputs en la tab de configuración de fichajes (3 pasos de
`docs/03-modelo-datos.md`).

## Relaciones (resumen)

```
tenants ──< horarios ──< horario_tramos
horarios ──< asignaciones_horario >── miembros_equipo   (M:N con vigencia)
miembros_equipo ──< fichajes            (024, lectura para cumplimiento)
miembros_equipo ──< alertas             (024, +tipos de jornada, +referencia_fecha)
```

## Reglas de validación (resumen)

- `horarios.nombre` único por tenant (no borrados); requerido.
- Tramo: `hora_fin > hora_inicio`; sin solape dentro de `(horario, dia_semana)`.
- Asignación: `vigente_desde` requerida; `vigente_hasta` (si se da) `>= vigente_desde`; sin solape
  con otras asignaciones del mismo miembro; al crear una abierta nueva, cerrar la anterior.
- Borrado de horario: bloqueado si tiene asignaciones (`restrictOnDelete` a nivel FK + chequeo con
  mensaje claro en el controller).
- Todo lo anterior tenant-scoped; resolución **manual** del modelo en `update`/`destroy` (sin
  binding implícito, memoria `project_tenant_route_binding`).
