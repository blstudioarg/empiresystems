# Quickstart — Validación del módulo de fichajes

Guía de validación end-to-end. Prerrequisitos: entorno del proyecto ya funcionando (Laravel 12,
MySQL/MariaDB, tenant de prueba sembrado). El detalle de modelo/rutas está en `data-model.md` y
`contracts/http.md`; aquí solo los escenarios de comprobación.

## Setup

```bash
php artisan migrate                 # crea miembros_equipo, fichajes y alertas
php artisan test --filter=Fichaje   # suite de la feature (debe estar verde)
```

Sembrar mínimos para prueba manual: un tenant, un usuario `Admin` y uno `Usuario`, y un
`miembro_equipo` vinculado al usuario `Usuario` con ubicación de trabajo conocida, `distancia_max_metros`
(p. ej. 100 m) y una dirección de casa (para la métrica).

## Escenario 1 — Fichar entrada dentro del perímetro (US1)

1. Autenticarse como `Usuario`, abrir `/fichajes` sobre **HTTPS**.
2. Conceder permiso de geolocalización → el mapa muestra el punto en vivo y el círculo del perímetro.
3. Pulsar "Fichar entrada".
4. **Esperado**: redirect con toast de éxito; nueva fila en `fichajes` con `tipo=Entrada`,
   `ocurrido_at` = hora de servidor, `resultado_ubicacion=Dentro`, `distancia_metros` ≤ tolerancia,
   sin lat/long crudas almacenadas. No se crea alerta.

## Escenario 2 — Fichar fuera del rango + alerta (US1/US6, geofencing informativo)

1. Simular una posición más lejos que `distancia_max_metros` del miembro (DevTools › Sensors).
2. Fichar.
3. **Esperado (default informativo)**: fichaje registrado con `resultado_ubicacion=Fuera` y
   `distancia_metros` > tolerancia; NO se pierde. Se crea **una** fila en `alertas`
   (`tipo=FichajeFueraDeRango`, `estado=Nueva`) enlazada al fichaje. Con
   `fichajes.geofencing_bloqueante=true`: respuesta 422, no persiste fichaje ni alerta.
4. Como `Admin`, abrir `/alertas` → aparece; marcar como resuelta → cambia de estado sin borrarse.
   Un fichaje dentro de la distancia NO genera alerta.

## Escenario 3 — Sin permiso de geo (FR-012)

1. Denegar el permiso de ubicación y fichar.
2. **Esperado**: fichaje con `resultado_ubicacion=SinUbicacion`; nunca una posición inventada.

## Escenario 4 — Inmutabilidad (US1/FR-003)

1. Intentar editar/borrar un fichaje (no debe existir ruta).
2. **Esperado**: no hay endpoint; a nivel de servicio, el único punto de escritura es
   `RegistroFichajes`. Test `FichajeRegistroTest` afirma que no hay update/delete de eventos.

## Escenario 5 — Corrección con traza (US3)

1. Como `Admin`, `POST /fichajes/{id}/corregir` con `motivo`.
2. **Esperado**: fila nueva con `corrige_fichaje_id` al original, `motivo`, `registrado_por`=Admin;
   el original intacto. Sin `motivo` → 422. Como `Usuario` → 403.

## Escenario 6 — Informe y exportación (US2)

1. Como `Admin`, abrir `/jornada`, elegir usuario y rango.
2. **Esperado**: total de horas calculado en backend; exportación con todos los eventos y sus
   correcciones. Con dos tenants sembrados, nunca aparecen registros del otro (Principio I).

## Escenario 7 — Portal del trabajador (US5)

1. Como `Usuario`, abrir `/mi-jornada`.
2. **Esperado**: ve solo sus registros; intentar ver los de otro usuario → 403.

## Escenario 8 — Retención de geo vs jornada (FR-021/FR-022)

1. Fijar `fichajes.retencion_geo_dias=30`; crear un fichaje con `ocurrido_at` de hace 40 días.
2. Ejecutar `php artisan fichajes:purgar-geo`.
3. **Esperado**: en esa fila, `distancia_metros`/`precision_metros`/`resultado_ubicacion` a NULL; la
   **fila sigue existiendo** (jornada intacta, 4 años). Un fichaje de hace 10 días conserva su geo.

## Escenario 9 — Retención de datos de casa tras baja (FR-022a)

1. Fijar `fichajes.retencion_casa_dias=30`; dar de baja un miembro (`dado_baja_at`) hace 40 días.
2. Ejecutar `php artisan miembros:purgar-casa`.
3. **Esperado**: `casa_direccion`/`casa_latitud`/`casa_longitud` a NULL; el miembro y su
   `distancia_casa_trabajo_metros` (métrica) permanecen; sus fichajes intactos.

## Escenario 10 — Aislamiento multi-tenant (Principio I)

1. Con dos tenants (miembros, fichajes y alertas en cada uno), repetir escenarios 2, 6 y 7.
2. **Esperado**: 0 % de fugas; `FichajeTenantIsolationTest` en verde.
