# Implementation Plan: Calendario de fichajes y horarios

**Branch**: `026-calendario-fichajes` | **Date**: 2026-07-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/026-calendario-fichajes/spec.md`

## Summary

Nuevo módulo `/calendario` bajo `can:gestiona-fichajes` en el dropdown de fichajes: una proyección
FullCalendar (vendorizado del banco del template, build 5.11.0) de los datos ya existentes de 024/025.
Vista mensual con veredicto de cumplimiento por día (color por `VeredictoCumplimiento`, solo días
pasados), vistas semana/día con tramos previstos vs. intervalos reales superpuestos, filtro por
miembro + vista agregada de equipo, y acciones (corregir fichaje, asignar horario) vía modales que
llaman a los endpoints existentes. **Cero tablas nuevas, cero escrituras nuevas**: un único endpoint
JSON de solo lectura (`GET /calendario/eventos`) calcula todo en backend reutilizando
`ServicioCumplimiento`, `ResolutorHorario` e `InformeJornada::eventosEfectivos`.

## Technical Context

**Language/Version**: PHP 8.x / Laravel 12

**Primary Dependencies**: FullCalendar 5.11.0 (vendorizado desde `template/.../public/vendor/fullcalendar-5.11.0`, build global UMD + locale `es`), jQuery/Bootstrap del template, toastr (patrón global). Sin dependencias de terceros nuevas fuera del banco del template.

**Storage**: MySQL/MariaDB — **sin tablas nuevas**; lectura de `fichajes`, `horarios`, `horario_tramos`, `asignaciones_horario`, `miembros_equipo` (024/025).

**Testing**: PHPUnit (`php artisan test`); Feature tests del endpoint JSON (contenido, autorización, aislamiento de tenant) + reutilización de la semántica ya testeada de `ServicioCumplimiento`.

**Target Platform**: Web (hosting compartido cPanel/Hostinger, Principio V) — el calendario es JS estático + un endpoint HTTP puntual; sin workers ni tiempo real.

**Project Type**: Monolito Laravel (web app multi-tenant single-database).

**Performance Goals**: Cargar y pintar un rango visible (mes) en <2 s con ≤50 miembros y ≤4 años de fichajes (SC-005). Carga por rango visible, nunca dataset completo.

**Constraints**: Cálculos 100% server-side (Principio III); ledger `fichajes` append-only intacto (FR-014/FR-016); rango del feed acotado en backend (máx. ~62 días) para impedir consultas desmedidas; orden de CSS del template (`@stack('styles')` antes de `style.css`).

**Scale/Scope**: Pyme: decenas de miembros, rangos de 1 mes/1 semana. Vista equipo = O(miembros × días) evaluaciones al vuelo por request — aceptable a esta escala (ver research D3).

## Constitution Check

*GATE: evaluado contra `.specify/memory/constitution.md` v1.2.0 — PASS (pre y post diseño).*

- **I. Aislamiento multi-tenant**: ✅ PASS. Sin tablas nuevas; todas las lecturas pasan por modelos con `BelongsToTenant` (`Fichaje`, `Horario`, `AsignacionHorario`, `MiembroEquipo`). El miembro del filtro se resuelve **manualmente** dentro del scope del tenant (memoria `project_tenant_route_binding`). Tests de no-fuga entre tenants para el endpoint de eventos (≥2 tenants).
- **II. Cumplimiento normativo España-First (incl. RGPD)**: ✅ PASS. No se crea ningún dato personal nuevo ni se amplía retención: es proyección de lectura de datos ya gobernados por 024 (registro 4 años, geo purgable). No expone coordenadas crudas: el detalle de fichaje muestra `resultado_ubicacion` (dentro/fuera/sin ubicación), igual que las vistas existentes.
- **III. Integridad server-side**: ✅ PASS. Veredictos, horas e intervalos se calculan en backend (`ServicioCumplimiento`/`InformeJornada`); el cliente solo pinta el JSON. Las escrituras (corrección/asignación) van a los servicios existentes que ya cumplen el principio.
- **IV. Test-first en lógica crítica**: ✅ PASS. Lo crítico (aislamiento del feed, autorización, coherencia veredicto-informe, agregado de equipo, regla "sin veredicto en futuro") se escribe test-first. La capa visual JS sigue flujo flexible.
- **V. Simplicidad / hosting compartido**: ✅ PASS. Sin colas, sin websockets, sin precálculo ni cachés; un GET por navegación de rango. FullCalendar sale del banco ya adquirido (no es dependencia nueva de terceros).

Sin violaciones → **Complexity Tracking vacío**.

## Project Structure

### Documentation (this feature)

```text
specs/026-calendario-fichajes/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/http.md    # Fase 1
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── CalendarioController.php          # NUEVO: index (vista) + eventos (feed JSON)
├── Support/Cumplimiento/
│   ├── ServicioCumplimiento.php          # EXTENDER: exponer intervalos del día (público)
│   └── ResultadoDia.php                  # existente (se consume tal cual)
routes/web.php                            # + GET /calendario, GET /calendario/eventos (can:gestiona-fichajes)
resources/views/
├── calendario/index.blade.php            # NUEVO: vista con #calendar, filtro miembro, modales
└── partials/sidebar.blade.php            # + entrada "Calendario" en dropdown fichajes (admin)
public/
├── vendor/fullcalendar/                  # NUEVO (vendorizado 5.11.0): main.min.js/css, locales/es.js
├── js/plugins-init/calendario.init.js    # NUEVO: init FullCalendar + filtro + modales
└── css/app-overrides.css                 # + clases de veredicto del calendario
tests/Feature/
└── CalendarioEventosTest.php             # NUEVO: feed, autorización, aislamiento, coherencia
```

**Structure Decision**: monolito Laravel existente; un controller nuevo de lectura, una vista, un
init JS y el vendor de FullCalendar. Las escrituras reutilizan `AsignacionHorarioController` y
`CorreccionFichajeController` sin tocarlos.

## Complexity Tracking

Sin violaciones constitucionales que justificar.
