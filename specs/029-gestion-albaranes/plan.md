# Implementation Plan: Gestión de Albaranes de Entrega

**Branch**: `029-gestion-albaranes` | **Date**: 2026-07-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/029-gestion-albaranes/spec.md`

## Summary

Añadir el **albarán de entrega** como documento no fiscal que se inserta entre el presupuesto
aceptado (o el cliente directo) y la factura, con dos responsabilidades que hoy no cubre ningún
módulo existente: (1) mover stock en el momento real de la entrega, permitiendo entregas parciales
de un mismo presupuesto en varios albaranes; y (2) consolidar N albaranes entregados de un mismo
cliente en una única factura borrador, sin duplicar el movimiento de stock que ya ocurrió al
entregar.

**Enfoque técnico**: mismo patrón que `presupuestos` (tabla + línea propias, `BelongsToTenant`,
servicio dedicado que reutiliza `CalculadoraFactura` para los importes), pero con dos puntos de
integración quirúrgicos sobre servicios ya existentes en vez de duplicarlos: (a) `RegistroMovimientoStock`
gana un nuevo origen `Albaran` y una columna `albaran_id`, reutilizado tal cual para el movimiento
de salida al entregar y el de entrada al anular; (b) `EmisorFacturas::moverStock()` gana un guard
que omite el movimiento de stock cuando la factura proviene de albaranes (relación
`factura.albaranes()` no vacía), porque ese stock ya se movió al confirmar cada albarán. Ningún
servicio existente se reescribe: se extiende su firma/casuística de forma aditiva.

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: motor de facturación existente (`CalculadoraFactura`, `EmisorFacturas`),
`RegistroMovimientoStock` (feature 014-control-stock), `spatie/laravel-permission` (feature 027,
para el permiso `ver-albaranes`). Sin dependencias nuevas de terceros.

**Storage**: MySQL/MariaDB. Tablas nuevas: `albaranes`, `albaran_lineas`. Modifica `movimientos_stock`
(nueva columna `albaran_id` nullable) y `presupuesto_lineas` (nueva columna
`cantidad_entregada`, para el saldo pendiente de entrega). Reutiliza `clientes`, `presupuestos`,
`presupuesto_lineas`, `articulos`, `facturas`.

**Testing**: PHPUnit/Pest. Test-first obligatorio (Principio IV) en: aislamiento multi-tenant,
movimiento de stock al entregar/anular, tope de cantidad pendiente por línea de presupuesto, y
consolidación N→1 sin doble movimiento de stock.

**Target Platform**: Aplicación web Laravel sobre hosting compartido cPanel/Hostinger (igual que el
resto del proyecto).

**Project Type**: Web (monolito Laravel con Blade + assets del template NexaDash).

**Performance Goals**: Confirmar un albarán (con movimiento de stock) y convertir hasta varias
decenas de albaranes en una factura son operaciones síncronas dentro de un único request, sin
timeout perceptible para el usuario.

**Constraints**: Sin colas persistentes ni workers dedicados (Principio V). Cálculo de importes
100% backend reutilizando `CalculadoraFactura` (Principio III). El movimiento de stock sigue siendo
exclusivamente a través de `RegistroMovimientoStock` (único punto de escritura del inventario, ya
establecido).

**Scale/Scope**: 1 módulo nuevo, 2 tablas nuevas (+2 columnas en tablas existentes), 1 controlador,
2 puntos de integración aditivos sobre servicios existentes (stock, emisión de factura).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cumplimiento en este plan |
|-----------|---------------------------|
| **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)** | `albaranes` y `albaran_lineas` llevan `tenant_id` indexado + `BelongsToTenant` (global scope), igual que el resto de tablas de negocio. Tests de no-fuga con ≥2 tenants (SC-005). |
| **II. Cumplimiento Normativo España-First** | El albarán **no** es documento fiscal: no lleva numeración de serie de factura, no entra en el encadenamiento/huella Verifactu, no tiene desglose fiscal obligatorio propio (reutiliza el de la línea espejo de presupuesto/factura solo para poder facturarse después sin recalcular). La factura resultante de convertir albaranes sí es fiscal y conserva inmutabilidad/numeración correlativa del motor existente (`EmisorFacturas`), sin cambios en esa garantía. |
| **III. Integridad Financiera Server-Side** | Las líneas de albarán calculan sus importes con `CalculadoraFactura`, igual que presupuestos; el cliente nunca es fuente de verdad. La consolidación N→1 en factura suma importes ya calculados en backend, sin reintroducción manual (FR-010, SC-003). |
| **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)** | Tests escritos primero para: (a) aislamiento tenant, (b) movimiento de stock exacto al entregar y su reverso exacto al anular (SC-002, SC-004), (c) tope de cantidad pendiente por línea de presupuesto (SC-001), (d) que la conversión a factura no duplica movimiento de stock y rechaza clientes mixtos (SC-003, FR-009). |
| **V. Simplicidad y Compatibilidad Hosting Compartido** | Sin entidad "Pedido" intermedia (fuera de alcance, ver spec Assumptions). Sin firma de conformidad del receptor en esta versión. Reutiliza `CalculadoraFactura`, `RegistroMovimientoStock` y `EmisorFacturas` en vez de duplicar lógica (YAGNI). Todo síncrono. |

**Resultado**: PASS. Sin violaciones que requieran Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/029-gestion-albaranes/
├── plan.md              # Este archivo
├── spec.md              # Especificación
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md         # Fase 1
├── contracts/
│   └── albaranes.md     # Fase 1 (rutas HTTP del módulo)
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Albaran.php                     # BelongsToTenant
│   └── AlbaranLinea.php                # BelongsToTenant
├── Enums/
│   ├── EstadoAlbaran.php               # borrador, entregado, facturado, anulado
│   └── OrigenMovimientoStock.php       # + caso Albaran (existente, se amplía)
├── Services/
│   ├── RegistroAlbaran.php             # crea/edita albarán usando CalculadoraFactura (único punto)
│   ├── EntregadorAlbaran.php           # borrador→entregado: dispara RegistroMovimientoStock (salida) + descuenta cantidad_entregada en presupuesto_lineas
│   ├── AnuladorAlbaran.php             # entregado→anulado: dispara RegistroMovimientoStock (entrada) + repone cantidad_entregada
│   ├── ConversorAlbaranesFactura.php   # N albaranes (mismo cliente, entregados, no facturados) → Factura borrador
│   ├── RegistroMovimientoStock.php     # existente: +parámetro albaran, +origen Albaran (aditivo, sin romper firma actual)
│   └── EmisorFacturas.php              # existente: moverStock() gana guard "si factura.albaranes() no vacío, no mover stock"
├── Http/
│   ├── Controllers/
│   │   └── AlbaranController.php       # index/create/store/show/edit/update/estado/convertir
│   └── Requests/
│       └── StoreAlbaranRequest.php / UpdateAlbaranRequest.php
└── Support/
    └── CatalogoPermisos.php            # + permiso ver-albaranes

database/migrations/
├── ...create_albaranes_table.php
├── ...create_albaran_lineas_table.php
├── ...add_albaran_id_to_movimientos_stock_table.php
└── ...add_cantidad_entregada_to_presupuesto_lineas_table.php

resources/views/
└── albaranes/ (index — cards + datatable con selección múltiple, create, show)

routes/web.php    # grupo resource protegido por permiso ver-albaranes

tests/
└── Feature/Albaranes/ (aislamiento, entrega parcial y tope, movimiento de stock entregar/anular,
    conversión N→1 sin doble movimiento, rechazo de clientes mixtos)
```

**Structure Decision**: Monolito Laravel existente, mismo patrón que `presupuestos` (feature 028):
servicio dedicado como único punto de escritura de la lógica crítica, controlador fino. Se integra
en el sidebar (grupo CRM, junto a Leads/Oportunidades/Presupuestos) y en el sistema de permisos de
la feature 027 con el permiso nuevo `ver-albaranes` añadido a `App\Support\CatalogoPermisos`. Los
dos servicios existentes que se tocan (`RegistroMovimientoStock`, `EmisorFacturas`) se amplían de
forma aditiva (nuevo caso de enum, nuevo parámetro opcional, nuevo guard), sin cambiar su
comportamiento para los flujos que ya usan (compras, facturas sin albarán de origen).

## Complexity Tracking

> No aplica — el Constitution Check pasa sin violaciones. No se introduce complejidad fuera del
> alcance del MVP: no hay entidad "Pedido" nueva, no hay firma de conformidad, no hay colas, y los
> dos servicios existentes que se tocan se amplían de forma aditiva en vez de reescribirse.
