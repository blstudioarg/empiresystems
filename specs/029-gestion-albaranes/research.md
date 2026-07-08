# Research: Gestión de Albaranes de Entrega

**Feature**: 029 | **Fecha**: 2026-07-08 | **Fase**: 0

Las decisiones de producto ya se cerraron en `spec.md` (origen dual del albarán, anulación con
reversión de stock). Aquí se resuelven las de implementación — en particular, cómo integrar el
albarán con los tres servicios existentes que toca (cálculo de importes, movimientos de stock,
emisión de facturas) sin duplicarlos ni romper su comportamiento actual.

---

## D1 — Cálculo de importes del albarán vía `CalculadoraFactura`

**Decisión**: Igual que `RegistroPresupuesto` (feature 028), un servicio nuevo `RegistroAlbaran`
invoca `App\Services\CalculadoraFactura::calcular(...)` para las líneas del albarán, tanto si viene
de un presupuesto (copiando régimen/tipo impositivo de sus líneas) como si es directo a cliente
(tomando el régimen del tenant/cliente, mismo criterio que un presupuesto nuevo).

**Rationale**: Tercera reutilización del mismo motor puro de cálculo (ya usado por facturas y
presupuestos); mantiene los importes idénticos al céntimo entre albarán → factura consolidada
(SC-003) sin mantener una tercera implementación.

**Alternativas descartadas**: calcular en el momento de convertir a factura en vez de en el propio
albarán — descartado porque el albarán necesita mostrar sus propios importes (aunque no sean
fiscales) desde que se crea, y porque la conversión a factura debe limitarse a sumar importes ya
calculados, no recalcular (Principio III).

---

## D2 — Seguimiento de cantidad pendiente de entrega por línea de presupuesto

**Decisión**: Añadir `cantidad_entregada` (decimal, default 0) a `presupuesto_lineas`. Al confirmar
un albarán como `entregado`, `EntregadorAlbaran` incrementa `cantidad_entregada` en cada
`presupuesto_linea` referenciada por sus líneas (`albaran_lineas.presupuesto_linea_id`). Al generar
un nuevo albarán desde el mismo presupuesto, `RegistroAlbaran` valida que
`cantidad_solicitada <= presupuesto_linea.cantidad - presupuesto_linea.cantidad_entregada` (FR-003).
Al anular un albarán entregado, `AnuladorAlbaran` decrementa `cantidad_entregada` de vuelta.

**Rationale**: Es la pieza de lógica que no existe en ningún módulo actual — presupuesto y factura
son documentos "todo de una vez", nunca parciales. Guardar el acumulado en la propia
`presupuesto_linea` (en vez de recalcularlo sumando albaranes en cada validación) es más simple y
más barato en hosting compartido; el ledger de trazabilidad real sigue siendo
`albaran_lineas.presupuesto_linea_id`, así que el acumulado siempre se puede reconstruir/auditar si
hiciera falta.

**Alternativas descartadas**: calcular la cantidad pendiente sumando dinámicamente todos los
`albaran_lineas` de un presupuesto en cada validación — más "puro" pero más caro por consulta y sin
beneficio real, dado que el acumulado cacheado se mantiene consistente porque solo lo escriben
`EntregadorAlbaran`/`AnuladorAlbaran` (mismo criterio que `articulos.stock_actual` como caché de
lectura sobre `movimientos_stock` como fuente de verdad, ya establecido en el proyecto).

---

## D3 — Movimiento de stock: extender `RegistroMovimientoStock`, no duplicarlo

**Decisión**: `RegistroMovimientoStock::registrar()` gana un parámetro opcional `?Albaran $albaran
= null` (al final de la firma, sin romper las llamadas existentes desde `EmisorFacturas` y
`RegistroCompra`), y `MovimientoStock` gana una columna `albaran_id` nullable. El enum
`OrigenMovimientoStock` ya define `Devolucion` (hoy usado solo por rectificativas de factura); se
añade el caso `Albaran` para el movimiento de salida al entregar. El movimiento de entrada al
anular reutiliza `OrigenMovimientoStock::Devolucion` con `tipo: Entrada`, igual que ya hace
`EmisorFacturas::moverStock()` para las rectificativas — mismo significado semántico ("stock que
vuelve porque se revierte una salida"), no un origen nuevo.

**Rationale**: `RegistroMovimientoStock` es, por diseño del proyecto ("único punto de escritura del
inventario"), el sitio obligado para cualquier movimiento nuevo — crear un servicio paralelo
violaría ese invariante y el Principio V (YAGNI). Extender la firma con un parámetro opcional
preserva la compatibilidad de `EmisorFacturas`/`RegistroCompra` sin tocar una línea de su código.

**Alternativas descartadas**: un `origen` genérico `OrigenMovimientoStock::Ajuste Manual` para
albaranes — descartado porque pierde trazabilidad (no queda claro desde qué albarán se movió el
stock); la spec exige poder auditar el movimiento hasta su albarán de origen (Key Entities,
"Movimiento de stock por albarán").

---

## D4 — Consolidación N albaranes → 1 factura, sin duplicar movimiento de stock

**Decisión**: Reutilizar el mismo campo que ya usa `Presupuesto` (`convertido_a_factura_id`) en
`Albaran`: varios albaranes pueden apuntar al mismo `factura_id` (relación N:1 natural, sin tabla
pivote). `ConversorAlbaranesFactura::convertir(Collection $albaranes): Factura` valida que todos
sean del mismo cliente y estén `entregado`/no facturados (FR-009), crea una `Factura` en borrador
copiando las líneas de todos los albaranes (cada `factura_linea` nueva queda identificable por su
`albaran_id` de origen para trazabilidad), y marca cada albarán como `facturado`.

Para que la emisión posterior de esa factura **no** vuelva a mover stock, se añade una relación
`Factura::albaranes()` (`hasMany(Albaran::class, 'convertido_a_factura_id')`) y un guard en
`EmisorFacturas::moverStock()`: si `$factura->albaranes()->exists()`, el método retorna sin generar
movimientos (el stock ya se movió en `EntregadorAlbaran` al confirmar cada albarán). Si la factura
no viene de albaranes (camino directo presupuesto→factura de la feature 028, o factura manual), el
comportamiento de `moverStock()` no cambia en absoluto.

**Rationale**: Reutiliza el patrón 1:1 ya probado de presupuesto→factura extendiéndolo a N:1 sin
tabla intermedia (una FK nullable en el lado "muchos" alcanza). El guard en `moverStock()` es el
único cambio de comportamiento sobre código existente en toda la feature, y es aditivo: una
condición extra al principio del método, sin tocar la rama de rectificativas ni la de facturas
ordinarias sin albarán.

**Alternativas descartadas**:
- *Tabla pivote `factura_albaranes`*: innecesaria porque la relación real es "N albaranes se
  facturan juntos una sola vez" — un albarán nunca pertenece a más de una factura, así que la FK
  simple en `albaranes` basta (Principio V, YAGNI).
- *Que `ConversorAlbaranesFactura` marque las líneas para que `moverStock()` las salte una por
  una*: más frágil (depende de un flag por línea en vez de una comprobación a nivel de factura) y
  más difícil de auditar; el guard a nivel de cabecera es más simple y más fácil de testear.

---

## D5 — Integración con permisos y sidebar (feature 027)

**Decisión**: Añadir el permiso `ver-albaranes` a `App\Support\CatalogoPermisos` (módulo "CRM",
junto a `ver-leads`/`ver-oportunidades`/`ver-presupuestos` de la feature 028), entrada nueva en el
sidebar dentro del mismo grupo CRM, protegida por ese permiso.

**Rationale**: Mismo patrón ya establecido por la feature 027 (una vista = un permiso) y replicado
por la 028 para su propio grupo CRM; no se justifica una excepción.

---

## Resumen de decisiones

| ID | Decisión | Reutiliza |
|----|----------|-----------|
| D1 | Importes del albarán vía `CalculadoraFactura` | Motor de facturación |
| D2 | `cantidad_entregada` cacheada en `presupuesto_lineas` | Patrón `articulos.stock_actual` como caché sobre ledger |
| D3 | `RegistroMovimientoStock` + parámetro `albaran` opcional + nuevo origen `Albaran` | Único punto de escritura del inventario (feature 014) |
| D4 | `albaranes.convertido_a_factura_id` (N:1) + guard aditivo en `EmisorFacturas::moverStock()` | Patrón `presupuestos.convertido_a_factura_id` (028) |
| D5 | Permiso `ver-albaranes` en el grupo CRM del sidebar | `CatalogoPermisos` (027) |

Sin `NEEDS CLARIFICATION` pendientes. Listo para Fase 1.
