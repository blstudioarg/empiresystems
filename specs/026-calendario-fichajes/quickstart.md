# Quickstart — Validación del calendario de fichajes (026)

## Prerrequisitos

- Migraciones de 024/025 aplicadas; tenant con usuario admin (`gestiona-fichajes`).
- Al menos 2 miembros activos, un horario con turno partido asignado (p. ej. L-V 09:00–13:00 y
  15:00–19:00) y fichajes variados del mes en curso (ver escenarios).
- Assets vendorizados: `public/vendor/fullcalendar/` presente.

## Suite automatizada

```bash
php artisan test --filter=CalendarioEventos
php artisan test   # suite completa antes de cerrar
```

## Escenarios manuales (E1–E6)

**E1 — Mes con veredictos (US1)**: sembrar para el miembro A: un día con entrada 09:20 (retraso),
un día sin fichajes (ausencia), un día 09:00–18:30 (exceso), un día 09:00–13:00 (parcial), un
sábado (libre) y un día con entrada sin salida (incidencia). Abrir `/calendario`, seleccionar A →
cada día pinta su color/etiqueta y coincide con `/jornada` del mismo rango. Días futuros: solo
turno previsto, sin veredicto.

**E2 — Semana previsto vs. real (US2)**: vista semana → los dos tramos del turno partido aparecen
como bloques `previsto`, y los intervalos fichados superpuestos como `real`, distinguibles; el día
con entrada-sin-salida no dibuja intervalo real y señala incidencia.

**E3 — Vista de equipo y drill-down (US3)**: seleccionar "Todo el equipo" → los días con
incumplimientos muestran recuentos agregados; clic en un día → lista de miembros afectados; saltar
al calendario individual de uno de ellos (≤2 interacciones, SC-003).

**E4 — Acciones (US4)**: desde el detalle de un día, corregir un fichaje con motivo → toast de
éxito, el calendario recarga y el veredicto del día cambia; verificar en `/jornada` que el
resultado es idéntico al flujo existente (evento enlazado, original intacto). Asignar un horario
nuevo con vigencia futura → los días futuros pintan el nuevo turno; intentar un solape → toast de
error del backend, sin estado inconsistente.

**E5 — Autorización y aislamiento**: con un usuario sin `gestiona-fichajes`, `/calendario` y
`/calendario/eventos` → 403; con un segundo tenant, el feed nunca devuelve datos del primero
(cubierto también por tests).

**E6 — Estados vacíos y límites**: miembro sin horario (fichajes como fuera de horario, sin
errores), tenant sin miembros (estado vacío orientativo), rango > 62 días vía URL → 422, navegación
de varios meses seguidos fluida (<2 s por rango, SC-005).

## Verificación visual

Confirmar con el usuario antes de usar herramienta de navegador (CLAUDE.md); preferencia Oculo.
Revisar: cascada CSS correcta (calendar CSS antes de `style.css`), tema claro/oscuro, leyenda de
veredictos, y paginación/estilos coherentes con `docs/04-front-guidelines.md`.
