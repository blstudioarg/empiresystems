# Implementation Plan: Cierre de caja del POS

**Branch**: `048-cierre-caja-pos` | **Date**: 2026-10-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/048-cierre-caja-pos/spec.md`

## Summary

Se añade el concepto de **sesión de caja** al POS: apertura con fondo, movimientos manuales de
efectivo, consulta en vivo (informe X) y cierre con arqueo ciego por denominaciones, que congela un
informe Z imprimible en 80 mm / A4 y queda en un histórico. Técnicamente: dos tablas nuevas
(`caja_sesiones`, `caja_movimientos`), una columna nullable en `ticket_pagos` (`caja_sesion_id`) que
`RegistroTicket` escribe al emitir —y que convierte "cobrar exige caja abierta" en una regla del
único punto de emisión—, un servicio de resumen compartido por X y Z, y una pantalla tablet-first
cuya pieza firma es la **bandeja del cajón** (fichas de billetes y monedas con su color real). El
cierre no toca ningún documento fiscal: solo lee.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12; JS vanilla + jQuery (sin build step, como el resto del POS)

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), `spatie/laravel-permission`, `barryvdh/laravel-dompdf`, Bootstrap 5 del template NexaDash, DataTables vendorizado, toastr

**Storage**: MySQL/MariaDB — 2 tablas nuevas + 1 columna nullable en `ticket_pagos` + 1 clave en `configuraciones`

**Testing**: PHPUnit (`php artisan test`), tests de feature con ≥ 2 tenants para aislamiento

**Target Platform**: hosting compartido cPanel (empiresass.gestionley.com), navegador de tablet en horizontal

**Project Type**: aplicación web monolítica Laravel (Blade + JS por vista)

**Performance Goals**: resumen en vivo y cierre < 500 ms con 500 tickets en la sesión (agregados SQL, no carga de modelos por ticket)

**Constraints**: sin dependencias nuevas; sin fuentes externas nuevas; cobrar sin caja abierta rechazado en servidor; arqueo ciego (el esperado no viaja al cliente antes de cerrar); cifras del cierre congeladas

**Scale/Scope**: ~1 sesión/día por tenant; hasta cientos de tickets por sesión; 1 pantalla nueva con 3 estados + 1 listado + 2 plantillas PDF + integración en el TPV

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cumplimiento | Estado |
|---|---|---|
| **I. Aislamiento multi-tenant** | `caja_sesiones` y `caja_movimientos` con `tenant_id` + `BelongsToTenant`; índice único `(tenant_id, abierta_marca)` acota por tenant; informe PDF y listados con resolución manual bajo scope (no binding implícito, memoria `project_tenant_route_binding`). Tests con 2 tenants: no se ve ni se cierra la caja de otro, la sesión abierta de A no atribuye tickets de B, PDF de otro tenant → 404. | ✅ |
| **II. Cumplimiento normativo** | No se crea ni modifica ningún documento fiscal, ni numeración, ni Verifactu: la columna nueva va en `ticket_pagos` (interna, no fiscal), no en `facturas` (research D1). El desglose por impuesto agrupa `factura_impuestos.tipo_impuesto`, agnóstico a IVA/IGIC/IPSI. RGPD: solo referencias a `users` y textos del negocio; conservación justificada como registro contable (research D10) — se documenta en docs/03 antes que en código. | ✅ |
| **III. Integridad financiera server-side** | Esperado, contado (desde el mapa de denominaciones), descuadre, totales y desgloses se calculan solo en servidor (`ResumenCaja`, `CierreCaja`); el cliente envía cantidades, nunca totales de confianza. Cierre y emisión serializados con `lockForUpdate` sobre la sesión abierta. | ✅ |
| **IV. Test-first en lógica crítica** | Aplica a aislamiento y a cálculo de importes: tests de aislamiento, de cálculo del esperado (incl. pago dividido y anulados), de unicidad de sesión abierta y del rechazo de cobro sin caja se escriben **antes** que los servicios y deben fallar primero (marcado en tasks). | ✅ |
| **V. Simplicidad / hosting compartido** | Sin dependencias ni infraestructura nueva; dompdf existente; JSON `resumen` en vez de tablas de desglose; una caja por tenant (multi-caja fuera de alcance); sin columnas generadas (research D2). | ✅ |

**Re-check post-diseño**: sin cambios — ninguna decisión de Phase 1 introduce violaciones.
Complexity Tracking vacío.

**Revisión obligatoria (Development Workflow)**: (1) scope de tenant ✅, (2) tests de aislamiento y
cálculo ✅ planificados, (3) inmutabilidad de facturas intacta y además se añade la inmutabilidad
del cierre ✅, (4) sin complejidad fuera del MVP ✅, (5) sin tabla de datos personales nueva que
exija purga (justificado en D10) ✅.

## Documentación leída y convenciones aplicadas (regla de oro, CLAUDE.md)

Leídas con la herramienta de lectura antes de este plan: `docs/04-front-guidelines.md` (completo),
`.specify/memory/constitution.md`, `docs/03-modelo-datos.md` (facturas, `ticket_pagos`, `pagos`,
`factura_eventos`, configuraciones, módulo POS 038), `docs/02-facturacion-espana.md` (simplificada),
y el código de `PosController`, `RegistroTicket`, `pos/index`, `pos/create`, `ConfigPos`,
`CatalogoMenu`, `CatalogoPermisos`.

Las convenciones de docs/04 que condicionan este plan están citadas una por una, con dónde se
aplican, en [contracts/ui-caja.md](contracts/ui-caja.md) ("Convenciones … que esta UI cumple").
Las más determinantes:

- **Entrada numérica táctil: teclado propio** → todos los importes y cantidades; se extrae la regla
  de tecleo compartida a `public/js/pos-teclado.js` en vez de duplicar `aplicarTecla()`.
- **Listados: SIEMPRE DataTable** + dropdown Acciones + override de paginación → histórico.
- **"Ver" documento SIEMPRE en modal** → informe Z.
- **Nueva entrada de menú ⇒ nuevo permiso** (5 pasos) → `ver-pos-caja`.
- **Cómo agregar un elemento de configuración** (docs/03, 3 pasos) → `pos.caja_umbral_descuadre`.
- **Dos vistas de los mismos datos: la decisión vive en un solo sitio** → `ResumenCaja` alimenta X,
  Z y PDF.

Skills de diseño: `frontend-design` aplicada (research D11). `ui-ux-pro-max` y `emil-design-eng` se
cargan al inicio de la fase de UI en `/speckit-implement` (tarea explícita en tasks).

## Project Structure

### Documentation (this feature)

```text
specs/048-cierre-caja-pos/
├── plan.md
├── research.md          # D1–D11
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── rutas.md         # HTTP
│   └── ui-caja.md       # pantallas, wireframes, convenciones docs/04
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
database/migrations/
├── 2026_10_03_100000_create_caja_sesiones_table.php
├── 2026_10_03_100100_create_caja_movimientos_table.php
└── 2026_10_03_100200_add_caja_sesion_id_to_ticket_pagos.php

app/
├── Models/CajaSesion.php, CajaMovimiento.php          # + TicketPago (fillable, relación)
├── Services/
│   ├── AperturaCaja.php        # abrir (unicidad, conteo → fondo)
│   ├── MovimientosCaja.php     # registrar entrada/salida contra la sesión abierta
│   ├── ResumenCaja.php         # cálculo compartido X/Z (agregados SQL)
│   ├── CierreCaja.php          # arqueo, umbral, congelado, concurrencia
│   └── RegistroTicket.php      # (mod) exige y atribuye sesión abierta
├── Support/DenominacionesEuro.php, ConfigPos.php (mod: umbral)
├── Exceptions/CajaCerradaException.php, CajaYaAbiertaException.php, ObservacionRequeridaException.php
├── Http/Controllers/Pos/CajaController.php             # index(estado), abrir, movimiento, cerrar
├── Http/Controllers/Pos/CajaCierreController.php       # histórico + informe PDF
├── Http/Controllers/PosController.php                  # (mod) 409 caja_cerrada, payload caja en create
├── Http/Controllers/Pos/CuentaController.php           # (mod) 409 caja_cerrada al cobrar
├── Http/Requests/AbrirCajaRequest.php, MovimientoCajaRequest.php, CerrarCajaRequest.php
├── Http/Requests/Concerns/ValidaConteoCaja.php          # regla del conteo por denominaciones
├── Support/CatalogoPermisos.php, CatalogoMenu.php      # (mod) ver-pos-caja, entrada "Caja"
└── Providers/AppServiceProvider.php                    # (mod) Gate abrir-caja

resources/views/
├── pos/caja.blade.php                  # estados A/B/C
├── pos/caja-cierres.blade.php          # histórico
├── pos/_caja-apertura.blade.php        # panel de fondo (reutilizado en caja y en modal del TPV)
├── pos/_caja-bandeja.blade.php         # bandeja de denominaciones
├── caja/informe-80mm.blade.php, caja/informe-a4.blade.php, caja/_informe-contenido.blade.php
├── pos/create.blade.php                # (mod) chip de caja + modal de apertura
├── configuracion/_tab_pos.blade.php    # (mod) umbral de descuadre
└── ayuda/pos-caja.blade.php, ayuda/pos-caja-cierres.blade.php, ayuda/pos-crear.blade.php (mod), ayuda/pos.blade.php (mod: nota que apunta a Caja)

public/
├── css/pos-caja.css
├── js/pos-teclado.js                   # regla de tecleo compartida (extraída de pos-cobro.js)
├── js/pos-caja-bandeja.js              # componente de conteo (fichas + teclado), apertura y cierre
├── js/pos-caja-apertura.js             # panel "Abrir caja", compartido caja/TPV
├── js/pos-caja-tpv.js                  # módulo PosApp: chip de caja + interceptar Cobrar con caja cerrada
├── js/plugins-init/pos-caja.init.js    # estados, movimientos, cierre, revelado, informe en papel
├── js/plugins-init/pos-caja-cierres.init.js
├── js/pos-cobro.js, js/pos-cuenta.js   # (mod) usan pos-teclado.js; 409 caja_cerrada → modal apertura
└── js/plugins-init/configuracion-pos.init.js (mod: añadir `caja_umbral_descuadre` al data del submit)

resources/ia/conocimiento/pos-caja.md (nuevo), pos.md (mod)
docs/03-modelo-datos.md, docs/04-front-guidelines.md, docs/00-vision.md (mod)

tests/
├── Concerns/ConCajaAbierta.php         # abre caja a cada tenant creado (tests de POS existentes)
├── Concerns/MontaCajaPos.php           # montaje de los tests de caja (abrir/vender/cerrar por HTTP)
├── Feature/Caja/  AperturaCajaTest, MovimientosCajaTest, CierreCajaTest, ResumenCajaTest,
│                  CajaTenantIsolationTest, CobrarSinCajaTest, CajaInformeTest, CajaPermisosTest
└── (mod) tests de POS existentes que emiten tickets: `use ConCajaAbierta` en setUp
```

**Structure Decision**: monolito Laravel existente; la caja vive dentro del módulo POS
(`Http/Controllers/Pos/`, vistas `pos/`, rutas `/pos/caja…`), con plantillas PDF en `caja/` igual
que los PDF de factura viven en `facturas/`.

## Riesgos y mitigación

| Riesgo | Mitigación |
|---|---|
| Tenants que ya usan el POS se encuentran con "caja cerrada" tras el despliegue | Apertura inline de un toque desde el propio TPV; guía in-app actualizada; nota en la base de conocimiento del asistente; avisar al cliente en la entrega. |
| Tests de POS existentes rompen al exigir caja | Trait `ConCajaAbierta` en su `setUp`, sin tocar aserciones (research D3). |
| `pos-cobro.js` es crítico y se toca para extraer el teclado | Extracción a comportamiento constante; verificar manualmente teclado de importe y de "Entregado" (docs/04 "Extracción de UI compartida"). |
| Doble cierre / cierre vs emisión simultáneos | `lockForUpdate` + `sesion_id` en el body del cierre + índice único. |

## Complexity Tracking

Sin violaciones de la constitución que justificar.
