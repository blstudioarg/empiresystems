# HTTP Contract — Gestión de horarios de trabajo y cumplimiento

Rutas nuevas y modificadas. Todas bajo el middleware de tenant ya existente; las de administración
bajo `can:gestiona-fichajes` (grupo de `routes/web.php`, feature 024). "Mi jornada" accesible a
cualquier miembro autenticado. Respuestas AJAX en JSON cuando `wantsJson()`, siguiendo el patrón del
proyecto (DataTable server-fed por `data`, errores 422 con `errors` por campo).

## Horarios (catálogo del tenant) — `can:gestiona-fichajes`

```
GET    /horarios                 horarios.index    Listado (HTML) / JSON {data:[...]} para DataTable
POST   /horarios                 horarios.store    Crea horario + tramos
PUT    /horarios/{horario}       horarios.update   Edita horario + reemplaza tramos
DELETE /horarios/{horario}       horarios.destroy  Borra (bloqueado si tiene asignaciones → 422)
```

**store/update payload**:
```json
{
  "nombre": "Jornada mañana",
  "activo": true,
  "tramos": [
    { "dia_semana": 1, "hora_inicio": "09:00", "hora_fin": "13:00" },
    { "dia_semana": 1, "hora_inicio": "15:00", "hora_fin": "19:00" },
    { "dia_semana": 2, "hora_inicio": "09:00", "hora_fin": "17:00" }
  ]
}
```
- `dia_semana` 1–7 (ISO). Días sin tramo = día libre (se omiten del array).
- **422** si: nombre duplicado por tenant, `hora_fin <= hora_inicio`, o solape dentro de
  `(dia_semana)`.
- `index` JSON incluye por horario: `id`, `nombre`, `activo`, `horas_semana` (derivado),
  `num_asignaciones`, y los `tramos` (para repoblar el modal de edición sin segundo endpoint).

## Asignación de horario a miembro — `can:gestiona-fichajes`

```
GET    /miembros-equipo/{miembro}/horarios   asignaciones-horario.index   Histórico de asignaciones (JSON)
POST   /miembros-equipo/{miembro}/horarios   asignaciones-horario.store   Asigna horario con vigente_desde
DELETE /asignaciones-horario/{asignacion}    asignaciones-horario.destroy Elimina una asignación (corrección de error de carga)
```

**store payload**:
```json
{ "horario_id": 12, "vigente_desde": "2026-08-01" }
```
- Cierra automáticamente la asignación abierta anterior (`vigente_hasta = vigente_desde − 1 día`).
- **422** si: `horario_id` no es del tenant o inactivo, `vigente_desde` solapa un rango cerrado
  existente, o el miembro no es del tenant.
- `index` JSON: lista de `{ id, horario: {id,nombre}, vigente_desde, vigente_hasta, es_vigente }`
  ordenada desc por `vigente_desde`.

## Mi jornada (turno esperado) — miembro autenticado

```
GET    /mi-jornada     mi-jornada.index   (extendido) añade bloque "turno esperado"
```
- El controller inyecta a la vista: `turno_hoy` (tramos previstos de hoy según horario vigente, o
  `null` si día libre / sin horario) y `turno_semana` (map día→tramos).
- Estado vacío claro si el miembro no tiene horario vigente (FR-013).

## Informe de jornada (cumplimiento) — `can:gestiona-fichajes`

```
GET    /jornada             jornada.index      (extendido) añade columnas de cumplimiento
GET    /jornada/exportar    jornada.exportar   (extendido) incluye cumplimiento
```
- Query params: rango de fechas (reutiliza `RangoFechas::desdePeticion(...)`, mismo patrón que el
  dashboard 023) y opcional `miembro_equipo_id`.
- Por miembro y día del rango, el informe muestra `horas_previstas`, `horas_trabajadas`, y el
  veredicto (`libre`/`ausencia`/`retraso`/`parcial`/`cumplido`/`exceso`) calculado **al vuelo**
  (FR-019a), usando el horario vigente **de cada día** (FR-016).
- Estados vacíos: miembro sin horario, día libre, tenant sin datos (FR-017, SC-006).

## Configuración de fichajes (umbrales) — `can:gestiona-fichajes`

```
PUT/PATCH /configuracion/fichajes   configuracion.fichajes.update   (extendido)
```
- Añade `tolerancia_retraso_min` (default 5) y `tolerancia_exceso_min` (default 15) al formulario ya
  existente de la tab de fichajes.

## Comando programado (no HTTP)

```
php artisan jornada:evaluar-cumplimiento    (registrado en bootstrap/app.php withSchedule ->daily())
```
- Evalúa el **día anterior** para todos los tenants/miembros con horario vigente ese día.
- Crea alertas `AusenciaJornada`/`RetrasoJornada` en `alertas` con `referencia_fecha` = día evaluado.
- **Idempotente**: no duplica si ya existe alerta `(tenant, miembro, tipo, referencia_fecha)`.
- Salida: resumen de alertas creadas por tenant (para logs de la ejecución).
