# Implementation Plan: Control horario y fichajes con geolocalización

**Branch**: `024-control-horario-fichajes` | **Date**: 2026-07-05 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/024-control-horario-fichajes/spec.md`

## Summary

Módulo de fichaje de jornada (art. 34.9 ET) para la plantilla del tenant. Se introduce una entidad
**`miembros_equipo`** (perfil de empleado 1:1 con un `User` con login): cada miembro guarda su
ubicación de trabajo, su distancia máxima permitida para fichar y su dirección de casa (para calcular
la distancia casa-trabajo). El miembro ficha entrada/salida (y pausas opcionales) desde una pantalla
con mapa Leaflet + tiles OpenStreetMap (sin API key) que muestra su posición en vivo
(`navigator.geolocation.watchPosition`, solo cliente) y su perímetro como círculo. Al fichar, el
cliente envía lat/long del instante a un `POST`; el **backend** fija la hora (reloj de servidor),
calcula con **Haversine** la distancia a la ubicación de trabajo del miembro y persiste un **evento
inmutable** en el ledger append-only `fichajes` (patrón `movimientos_stock`/`factura_eventos`). Si la
distancia supera la tolerancia del miembro, el mismo servicio **crea una alerta** enlazada al fichaje.
El dato de geo se guarda **minimizado** (veredicto dentro/fuera + distancia + precisión; sin
coordenadas crudas). Las correcciones son eventos nuevos enlazados (nunca UPDATE). Informe de jornada
y total de horas se calculan en backend; administración gestiona miembros/alertas y consulta/exporta,
cada usuario ve lo suyo. Retención: la fila de jornada 4 años; el dato de geo del fichaje y la
dirección de casa (tras baja del miembro) se purgan antes según plazos configurables por tenant,
reutilizando el patrón `RetencionLogsTenant` + comandos programados en `withSchedule` (feature 021).
Sin biometría, sin rastreo continuo, sin envío API a Inspección (YAGNI hasta que el RD publique su
especificación).

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), Eloquent, Blade.
Frontend: **Leaflet** + tiles OpenStreetMap (vendorizar Leaflet en `public/vendor/`, sin CDN por CSP;
los tiles se sirven desde `*.tile.openstreetmap.org`). Sin paquetes PHP nuevos; Haversine a mano.

**Storage**: MySQL/MariaDB. Tablas nuevas: `miembros_equipo` (perfil de empleado 1:1 con user),
`fichajes` (ledger append-only) y `alertas`. Reutiliza `configuraciones` (clave/valor por tenant) para
los plazos de retención (geo y casa) y para los flags de geofencing/pausas.

**Testing**: PHPUnit (Feature + Unit), `RefreshDatabase`, factories. Test-first en: aislamiento
multi-tenant, cálculo Haversine dentro/fuera, inmutabilidad del ledger, separación de retenciones.

**Target Platform**: Hosting compartido tipo cPanel/Hostinger (Principio V). Requiere HTTPS para
`navigator.geolocation` (ya disponible).

**Project Type**: Web (monolito Laravel + Blade). Vistas nuevas de fichaje, informe y ubicaciones;
ítems de menú.

**Performance Goals**: Un fichaje es un `POST` puntual (< 15 s de flujo de usuario en móvil, SC-001);
Haversine sobre un puñado de ubicaciones por tenant es O(n) trivial, sin índices espaciales. Informe
de jornada con paginación/agregación server-side responde en < 2 s.

**Constraints**: Hora de referencia = servidor (Principio III, FR-002). Veredicto dentro/fuera se
decide en backend (FR-010), nunca el cliente. Append-only sin rutas de edición/borrado (FR-003).
Geo minimizada sin coordenadas crudas (FR-021). Aislamiento por tenant sin fugas (Principio I).

**Scale/Scope**: 3 tablas (`miembros_equipo`, `fichajes`, `alertas`), 3 modelos, ~4 enums
(`TipoEventoFichaje`, `ResultadoUbicacionFichaje`, `TipoAlerta`, `EstadoAlerta`), 1 servicio
`RegistroFichajes` (único punto de escritura + Haversine + creación de alerta), 1 servicio
`InformeJornada` (cálculo de horas), 2 comandos de purga (`fichajes:purgar-geo`,
`miembros:purgar-casa`), ~5 controladores (fichaje, informe/portal, miembros, alertas, corrección),
migraciones, factories, vistas Blade, JS Leaflet, rutas e ítems de menú. Sin envío a Inspección, sin
biometría, sin rastreo continuo.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: `miembros_equipo`, `fichajes` y `alertas` son
  tablas de negocio → `tenant_id` indexado + `BelongsToTenant` (como `MovimientoStock`). El
  controlador de informe/portal filtra además explícitamente por `tenant_id` y, en el portal, por el
  `miembro_equipo` del usuario autenticado (capa defensiva sobre el global scope; ver memoria
  `project_tenant_route_binding` — resolver miembro/fichaje/alerta en el cuerpo del controlador, no
  por implicit binding). Tests de no-fuga con ≥2 tenants obligatorios (US1/US2/US6). ✅
- **II. Cumplimiento Normativo España-First (incl. RGPD/LOPDGDD)**: fuente normativa
  `docs/07-control-horario-espana.md`. Geo minimizada (FR-021, sin coordenadas crudas); **dirección
  de casa** es dato personal (FR-022a) → minimización + purga tras baja del miembro. Dos/tres
  retenciones configurables + purga reutilizando `RetencionLogsTenant`/patrón `logs:purgar`
  (Principio II.a): geo del fichaje, y datos de casa del miembro. Registro de accesos conforme al
  patrón `logs_actividad` (Principio II.b, FR-023). Registro de jornada conservado 4 años, separado
  del plazo de geo (FR-022). Sin biometría (FR-013). No toca facturación/Verifactu. ✅
- **III. Integridad Financiera Server-Side**: no hay importes, pero el principio aplica análogamente:
  la hora del fichaje y el veredicto dentro/fuera se calculan **solo** en el backend
  (`RegistroFichajes`), el cliente nunca es fuente de verdad (FR-002, FR-010). El total de horas del
  informe se calcula server-side (FR-004). ✅
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: son lógica crítica → tests primero, en rojo:
  (a) aislamiento multi-tenant de fichajes/ubicaciones/informe; (b) Haversine dentro/fuera incl.
  borde del radio; (c) inmutabilidad del ledger (no edición/borrado); (d) separación de retenciones
  (purga de geo NO borra la fila de jornada). El resto (UI, mapa, textos) sigue el flujo estándar. ✅
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: sin VPS, sin PostGIS, sin websockets,
  sin colas persistentes; Haversine aritmético, `POST` puntual, purga vía scheduler sobre cron de
  cPanel. Reutiliza patrones existentes (servicio único de escritura tipo `RegistroMovimientoStock`,
  modelo `BelongsToTenant` append-only, config clave/valor, comando de purga). Única dependencia
  front nueva: Leaflet vendorizado (JS/CSS estáticos, sin backend) — justificada porque no existe
  alternativa en el banco del template para mapa interactivo, y es la opción gratis sin API key
  (frente a Google Maps) coherente con el Principio V. Tiles OSM son externos: requiere ajuste de
  CSP documentado en research (D3). ✅

**Resultado**: PASS. Sin violaciones que justificar (Complexity Tracking vacío).

## Project Structure

### Documentation (this feature)

```text
specs/024-control-horario-fichajes/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   └── http.md          # Fase 1
├── checklists/
│   └── requirements.md  # (de /speckit-specify)
└── tasks.md             # Fase 2 (/speckit-tasks — no lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── TipoEventoFichaje.php            # NUEVO: Entrada | Salida | InicioPausa | FinPausa
│   ├── ResultadoUbicacionFichaje.php    # NUEVO: Dentro | Fuera | SinUbicacion
│   ├── TipoAlerta.php                   # NUEVO: FichajeFueraDeRango (extensible)
│   └── EstadoAlerta.php                 # NUEVO: Nueva | Vista | Resuelta
├── Models/
│   ├── MiembroEquipo.php                # NUEVO: BelongsToTenant, 1:1 con User, datos RRHH
│   ├── Fichaje.php                      # NUEVO: BelongsToTenant, append-only, enlace a corrección
│   └── Alerta.php                       # NUEVO: BelongsToTenant, enlaza fichaje + miembro
├── Services/
│   ├── RegistroFichajes.php             # NUEVO: único punto de escritura + Haversine + alerta + hora servidor
│   └── InformeJornada.php               # NUEVO: cálculo de horas efectivas por miembro/periodo
├── Support/
│   ├── Haversine.php                    # NUEVO: distancia en metros entre dos coordenadas
│   ├── RetencionGeoTenant.php           # NUEVO: plazo de purga de geo del fichaje (patrón RetencionLogsTenant)
│   └── RetencionMiembroTenant.php       # NUEVO: plazo de purga de datos de casa tras baja
├── Console/Commands/
│   ├── PurgarGeoFichajes.php            # NUEVO: fichajes:purgar-geo (nulifica geo del fichaje)
│   └── PurgarCasaMiembros.php           # NUEVO: miembros:purgar-casa (nulifica datos de casa tras baja)
├── Http/
│   ├── Requests/
│   │   ├── FicharRequest.php            # NUEVO: valida lat/long/precisión/tipo del POST
│   │   ├── MiembroEquipoRequest.php     # NUEVO: user, trabajo, distancia máx, casa
│   │   └── CorregirFichajeRequest.php   # NUEVO: motivo obligatorio
│   └── Controllers/
│       ├── FichajeController.php        # NUEVO: pantalla de fichaje (GET) + registrar (POST)
│       ├── InformeJornadaController.php # NUEVO: informe admin + portal usuario + exportación
│       ├── MiembroEquipoController.php  # NUEVO: CRUD miembros (solo Admin)
│       ├── AlertaController.php         # NUEVO: listar + cambiar estado (solo Admin)
│       └── CorreccionFichajeController.php # NUEVO: crear corrección (solo Admin)
database/
├── migrations/
│   ├── 2026_07_05_000001_create_miembros_equipo_table.php       # NUEVO
│   ├── 2026_07_05_000002_create_fichajes_table.php              # NUEVO
│   └── 2026_07_05_000003_create_alertas_table.php               # NUEVO
└── factories/
    ├── MiembroEquipoFactory.php         # NUEVO
    ├── FichajeFactory.php               # NUEVO
    └── AlertaFactory.php                # NUEVO
routes/
└── web.php                              # MOD: rutas de fichaje, informe, portal, miembros, alertas, corrección
bootstrap/
└── app.php                              # MOD: $schedule->command('fichajes:purgar-geo')/('miembros:purgar-casa')->daily()

resources/views/
├── fichajes/
│   ├── index.blade.php                  # NUEVO: pantalla de fichaje con mapa
│   └── informe.blade.php                # NUEVO: informe admin
├── mi-jornada/
│   └── index.blade.php                  # NUEVO: portal del trabajador
├── miembros-equipo/
│   ├── index.blade.php / form.blade.php # NUEVO: gestión de miembros (mapa para trabajo/casa)
├── alertas/
│   └── index.blade.php                  # NUEVO: bandeja de alertas (Admin)
└── partials/
    └── header.blade.php                 # MOD: ítems de menú (Fichar, Mi jornada, admin: Jornada/Miembros/Alertas)

public/
├── vendor/leaflet/                      # NUEVO: leaflet.js + leaflet.css + marcadores (vendorizado)
└── js/plugins-init/
    ├── fichaje-mapa.init.js             # NUEVO: watchPosition + mapa + círculo perímetro + POST
    └── miembro-mapa.init.js             # NUEVO: elegir coords de trabajo/casa en mapa

tests/Feature/
├── FichajeRegistroTest.php              # US1: entrada/salida, hora servidor, inmutabilidad
├── FichajeGeofencingTest.php            # US1: Haversine dentro/fuera/borde, informativo vs bloqueante
├── FichajeTenantIsolationTest.php       # US1/US2 — Principio I
├── AlertaFichajeTest.php                # US6: alerta al superar distancia, no alerta si dentro, estados
├── InformeJornadaTest.php               # US2: total de horas, exportación
├── CorreccionFichajeTest.php            # US3: evento enlazado, motivo obligatorio, permiso
├── MiembroEquipoTest.php                # US4: CRUD, distancia máx, 1:1 con user, aislamiento
├── PortalMiJornadaTest.php              # US5: usuario ve solo lo suyo
├── PurgaGeoFichajesTest.php             # Retención: purga geo conserva la fila de jornada
└── PurgaCasaMiembrosTest.php            # Retención: purga casa tras baja conserva el miembro
tests/Unit/
└── HaversineTest.php                    # Distancia conocida entre coordenadas (fichaje y casa-trabajo)
```

**Structure Decision**: Monolito Laravel existente. Se replica el patrón de ledger append-only de
`MovimientoStock` (modelo `BelongsToTenant`, sin SoftDeletes, único punto de escritura vía servicio
`RegistroFichajes`, correcciones como eventos nuevos), el patrón de retención/purga de la feature 021
(`RetencionLogsTenant` → `RetencionGeoTenant`/`RetencionMiembroTenant`; `logs:purgar` →
`fichajes:purgar-geo`/`miembros:purgar-casa` en `withSchedule`) y la config clave/valor por tenant.
El `miembro_equipo` es un perfil 1:1 con `User` (login) — no se ensucia `users` con datos de RRHH. La
alerta la crea el propio `RegistroFichajes` en la misma transacción del fichaje. Leaflet se vendoriza
en `public/vendor/` (no CDN, por CSP), igual que el resto de plugins del banco del template.

## Complexity Tracking

> Sin violaciones de la constitución. Sección vacía a propósito.
