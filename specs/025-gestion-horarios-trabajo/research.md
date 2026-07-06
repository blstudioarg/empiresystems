# Research — Gestión de horarios de trabajo y cumplimiento

Decisiones de diseño previas a la implementación. Las ambigüedades de negocio ya se resolvieron en
`spec.md` (Clarifications, sesión 2026-07-06); aquí se fijan las decisiones **técnicas** y de
integración con la feature 024.

## R1 — Modelo de horario: plantilla + tramos por día de la semana

**Decisión**: `horarios` (cabecera con nombre) `hasMany` `horario_tramos`, cada tramo con
`dia_semana` (0–6), `hora_inicio` y `hora_fin` (columnas `TIME`). Varios tramos por `(horario, día)`
= turno partido. Ningún tramo un día = día libre.

**Rationale**: es la forma normalizada natural y hace trivial calcular las horas previstas
(`SUM(hora_fin − hora_inicio)` agrupado por día) y renderizar la semana. Evita serializar tramos en
JSON (que impediría validar solapes en base y complicaría el informe).

**Alternativas descartadas**:
- Tramos en una columna JSON del horario → pierde integridad referencial y validación por fila.
- Horario semanal fijo de un solo tramo por día → no soporta turnos partidos (rechazado por el
  usuario en Clarifications).

**Convención de `dia_semana`**: 1=lunes … 7=domingo (ISO-8601, coincide con `Carbon::dayOfWeekIso`)
para no depender del locale y alinear con el arranque de semana en lunes habitual en España.

## R2 — Asignación con vigencia y resolución del horario aplicable

**Decisión**: `asignaciones_horario` (`miembro_equipo_id`, `horario_id`, `vigente_desde` date,
`vigente_hasta` date nullable). El horario aplicable de un miembro en una fecha F = la asignación con
`vigente_desde <= F AND (vigente_hasta IS NULL OR vigente_hasta >= F)`. Invariante de no solape: como
mucho una asignación "abierta" (`vigente_hasta IS NULL`) por miembro, y ningún rango se cruza.

**Cierre automático (FR-010)**: al crear una asignación nueva con `vigente_desde = D`, la asignación
abierta previa (si existe) se cierra con `vigente_hasta = D − 1 día` en la misma transacción. Si `D`
cae dentro de un rango cerrado existente → se rechaza (solape), no se parte.

**Rationale**: patrón estándar de "slowly changing dimension" tipo 2; conserva histórico sin
reescribir el pasado, imprescindible para FR-016 (comparar cada día contra su horario vigente). La
resolución por fecha es una consulta indexada por `(tenant_id, miembro_equipo_id, vigente_desde)`.

**Alternativas descartadas**:
- `miembros_equipo.horario_id` plano → reescribe el pasado, rompe el informe histórico.
- Versionado por copia del horario completo en cada cambio → duplica datos; la vigencia por
  asignación es más simple y suficiente.

## R3 — Cálculo de horas trabajadas desde el ledger de fichajes

**Decisión**: un `ServicioCumplimiento` empareja, por miembro y día natural, los eventos del ledger
`fichajes` en orden por `ocurrido_at`: `Entrada`→`Salida` define un intervalo trabajado; los pares
`InicioPausa`→`FinPausa` se **restan**. Solo se consideran fichajes **vigentes** (aplicando las
correcciones vía `corrige_fichaje_id`: un fichaje corregido se sustituye por su corrección, mismo
criterio que ya usa el informe de jornada de 024).

- **Fichaje incompleto** (Entrada sin Salida, o secuencia impar): el día se marca
  `incidencia = true` y NO se computa jornada completa (FR-015a) — no se infiere una salida.
- **Pausas** (`fichajes.registrar_pausas`): si el tenant no registra pausas, no hay eventos de pausa
  y no se descuenta nada; si los registra, se descuentan.

**Rationale**: reutiliza exactamente la semántica del ledger de 024 (append-only, correcciones
enlazadas) sin duplicar lógica de emparejamiento. El "día natural" usa la fecha de `ocurrido_at`
(hora de servidor), consistente con cómo 024 ya agrupa la jornada.

**Nota de zona horaria**: el emparejamiento agrupa por fecha de `ocurrido_at` tal como se guarda
(hora de servidor); coherente con el resto del módulo, que no maneja TZ por tenant todavía.

## R4 — Dónde vive la asignación en la UI

**Decisión**: dos superficies:
1. **CRUD de horarios** en su propia vista `horarios/index.blade.php` (DataTable + modal), enlazada
   desde el menú del módulo de fichajes/jornada.
2. **Asignación** desde la ficha del miembro (modal de `miembros-equipo`, que ya se abre por fila):
   un selector de horario + `vigente_desde`, mostrando el horario vigente actual y un enlace al
   histórico. La creación de la asignación llama a `AsignacionHorarioController`.

**Rationale**: el horario es un catálogo del tenant (vive solo, como `bancos`/`unidades`); la
asignación es un acto sobre un miembro concreto (vive en su ficha). Separarlos respeta el modelo
mental y el patrón de catálogos del proyecto.

**Alternativas descartadas**: meter la edición de tramos dentro del modal de miembro → mezcla
catálogo con asignación y rompe la reutilización de plantillas.

## R5 — Generación de alertas: comando programado diario idempotente

**Decisión**: `EvaluarCumplimientoJornada` (comando artisan) registrado en `bootstrap/app.php`
(`withSchedule` → `->daily()`), junto a las purgas de 024. Evalúa el **día anterior** para todos los
tenants/miembros con horario vigente ese día y crea alertas `AusenciaJornada`/`RetrasoJornada` en la
tabla `alertas`. **Idempotencia**: antes de crear, comprueba que no exista ya una alerta del mismo
`(tipo, miembro, día)` — se añade una referencia de día a la alerta (ver data-model, campo
`referencia_fecha` o reuso de metadatos) para poder deduplicar y re-ejecutar sin duplicar (FR-019).

**Recorrido multi-tenant**: el comando itera los tenants (como ya hacen `logs:purgar` /
`fichajes:purgar-geo`) e inicializa el contexto de cada uno, respetando el global scope.

**Rationale**: una ausencia no tiene evento disparador (es la **falta** de un fichaje), así que hace
falta un proceso que "mire" los días previstos sin actividad. El cron diario es la única opción
compatible con hosting compartido (Principio V) y ya existe la infra (`schedule:run`).

**Alternativas descartadas**:
- Evaluar al vuelo al abrir el informe → sin notificación proactiva (rechazado en Clarifications).
- Tabla persistida de resultados de cumplimiento → superficie extra innecesaria; el informe se
  calcula al vuelo (FR-019a) y solo las **alertas** se persisten (ya hay tabla `alertas`).

## R6 — Clasificación de cumplimiento por día

**Decisión**: el `ServicioCumplimiento` produce por día un `ResultadoDia` con: `horas_previstas`,
`horas_trabajadas`, `incidencia` (bool), y un veredicto:
- **Día libre**: `horas_previstas == 0` → no cuenta como ausencia; fichajes ese día = "trabajado
  fuera de horario" (informativo).
- **Ausencia**: `horas_previstas > 0` y **cero** fichajes de entrada ese día.
- **Retraso**: para **cada tramo** previsto, si la primera Entrada dentro de la ventana del tramo
  llega más tarde que `hora_inicio + tolerancia_retraso` (o no hay entrada para ese tramo) → marca
  de retraso con magnitud (FR-015, por tramo).
- **Cumplimiento parcial**: fichó pero `horas_trabajadas < horas_previstas − tolerancia` → déficit.
- **Exceso**: `horas_trabajadas > horas_previstas + tolerancia_exceso` → exceso con la diferencia.

**Rationale**: cubre las 4 clasificaciones acordadas. El retraso por tramo (no solo el primero) fue
la decisión explícita del usuario; emparejar la Entrada al tramo se hace por cercanía a la ventana
`[hora_inicio, hora_fin]` del tramo.

**Umbrales** (`ConfigFichajes`, grupo `fichajes`, por tenant):
- `fichajes.tolerancia_retraso_min` (default 5) — minutos de gracia antes de marcar retraso.
- `fichajes.tolerancia_exceso_min` (default 15) — minutos por encima de lo previsto antes de marcar
  exceso. (El déficit de cumplimiento parcial usa la misma tolerancia en negativo.)

## R7 — Extensión del enum `TipoAlerta` sin romper lo existente

**Decisión**: añadir `AusenciaJornada = 'ausencia_jornada'` y `RetrasoJornada = 'retraso_jornada'` a
`App\Enums\TipoAlerta`, con sus `label()`. La columna `alertas.tipo` es `varchar(30)`; los valores
nuevos caben. `AlertaController@index` ya lista todas las alertas del tenant → las nuevas aparecen
sin cambios de esquema. Se revisa que la vista de alertas muestre `distancia_metros` solo cuando
aplica (las de jornada no la usan) — ver data-model.

**Rationale**: reutiliza la bandeja y el ciclo nueva/vista/resuelta (FR-020). Cambio aditivo, sin
migración de la tabla `alertas` salvo, si hace falta, un campo nullable para el día de referencia
(evaluado en data-model).

## R8 — Autorización

**Decisión**: el CRUD de horarios, la asignación y el informe viven bajo el gate
`can:gestiona-fichajes` ya existente (grupo de rutas de administración de jornada en `web.php`). "Mi
jornada" (turno esperado) sigue accesible a cualquier miembro autenticado, como ya está.

**Rationale**: gestionar cuadrantes y ver el cumplimiento de la plantilla es tarea de administración;
ver el propio turno es del trabajador. Reutiliza la política ya definida en 024.
