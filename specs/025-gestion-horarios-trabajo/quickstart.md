# Quickstart / Validación — Gestión de horarios de trabajo y cumplimiento

Guía para validar la feature end-to-end. Detalles de esquema en [data-model.md](./data-model.md) y
de endpoints en [contracts/http.md](./contracts/http.md).

## Prerrequisitos

- Módulo de control horario (feature 024) operativo: al menos un `MiembroEquipo` 1:1 con un `User`,
  y capacidad de registrar `fichajes`.
- Usuario con permiso `gestiona-fichajes` (administración) y un usuario miembro para "Mi jornada".
- Migraciones de la feature aplicadas (`php artisan migrate`).
- Seed de configuración con los umbrales por defecto (`php artisan db:seed --class=ConfiguracionSeeder`).

## Suite automatizada

```bash
php artisan test --filter="Horario|Asignacion|Cumplimiento|MiJornada"
```

Debe cubrir (test-first, Principio IV):
- **Aislamiento multi-tenant**: horarios, asignaciones e informe de un tenant no ven los de otro.
- **Horario vigente por fecha**: cambio de horario a mitad de rango → cada fecha resuelve el vigente
  de entonces (SC-002/SC-004).
- **Cálculo de horas**: emparejamiento entrada/salida, descuento de pausas, fichaje incompleto =
  incidencia (FR-015a).
- **Clasificación**: retraso por tramo, ausencia (0 fichajes), parcial (déficit), exceso, día libre
  (SC-003).
- **Comando idempotente**: dos ejecuciones sobre el mismo día no duplican alertas (FR-019).

## Validación manual (escenarios)

### E1 — Definir un horario con turno partido (US1)
1. Como admin, ir a `/horarios` → "Agregar horario".
2. Nombre "Jornada mañana"; tramos L-V 09:00–13:00 y 15:00–19:00; sábado/domingo sin tramos.
3. Guardar. **Esperado**: aparece en el listado con `horas_semana` = 40; editable; un tramo con
   fin ≤ inicio o dos tramos solapados el mismo día → error 422 claro.

### E2 — Asignar con vigencia y cambiar de horario (US2)
1. Abrir la ficha de un miembro → asignar "Jornada mañana" con `vigente_desde` = 2026-01-01.
2. Asignar otro horario con `vigente_desde` = 2026-03-01.
3. **Esperado**: la primera asignación se cierra en 2026-02-28; para una fecha de febrero el horario
   aplicable es el primero, para marzo el segundo; el histórico muestra ambas; un `vigente_desde`
   que solape un rango cerrado → error.

### E3 — Turno esperado en Mi jornada (US3)
1. Como el miembro asignado, abrir `/mi-jornada`.
2. **Esperado**: se ve el turno previsto de hoy (o "día libre") y la semana; un miembro sin horario
   ve un estado vacío sin error.

### E4 — Informe de cumplimiento (US4)
1. Con un miembro de horario L-V 09:00–17:00, registrar fichajes: un día 09:20–17:00 (retraso),
   otro día sin fichar (ausencia), otro 09:00–18:30 (exceso), otro 09:00–13:00 (parcial).
2. Como admin, abrir `/jornada` con el rango que cubre esos días.
3. **Esperado**: horas previstas vs. trabajadas por día; marcas correctas de retraso, ausencia,
   exceso y parcial; un día que cae en día libre no cuenta como ausencia; un rango que cruza un
   cambio de horario compara cada día contra su horario vigente.

### E5 — Alertas de incumplimiento (US5)
1. Ejecutar `php artisan jornada:evaluar-cumplimiento` (o esperar al cron diario) tras un día con
   ausencia y un retraso por encima del umbral.
2. Abrir `/alertas`.
3. **Esperado**: aparecen alertas "Ausencia de jornada" y "Retraso de jornada" gestionables
   (nueva/vista/resuelta), conviviendo con las de "Fichaje fuera de rango"; re-ejecutar el comando
   no las duplica.

### E6 — Estados vacíos (SC-006)
1. Tenant recién creado sin horarios ni fichajes.
2. **Esperado**: `/horarios`, `/mi-jornada` y `/jornada` muestran estados vacíos claros, sin errores.

## Verificación visual (con confirmación del usuario)

Las vistas nuevas (`/horarios`, bloque de Mi jornada, columnas del informe) se revisan con las
skills de diseño del proyecto y, si el usuario lo autoriza, con una de las herramientas de navegador
(preferentemente Oculo para el flujo guiado), sin lanzarlas de forma autónoma (CLAUDE.md).
