# Research: Control de stock con proveedores, compras y kardex

Fase 0. No quedaban `[NEEDS CLARIFICATION]` en la spec (Q1 stock negativo resuelto por el usuario).
Aquí se consolidan las decisiones de diseño técnico y por qué, contra el código existente.

## D1 — Punto único de escritura de stock

- **Decisión**: un único servicio de dominio `RegistroMovimientoStock` es el **solo** lugar que crea
  filas en `movimientos_stock` y actualiza `articulos.stock_actual`. Compras, emisión y ajustes
  manuales lo invocan; nadie toca `stock_actual` directo.
- **Rationale**: garantiza la invariante SC-002/SC-006 (`stock_actual` == último `stock_resultante`)
  en un solo sitio testeable (Principio IV). Evita duplicar la lógica de cálculo del resultante.
- **Alternativas**: escribir stock desde cada servicio (compras, emisor) → rechazada: dispersa la
  invariante y multiplica los puntos de bug. Observers de Eloquent → rechazada: la lógica
  transaccional con lock explícito es más clara en un servicio que en un hook implícito.

## D2 — Concurrencia y atomicidad

- **Decisión**: dentro de `DB::transaction`, bloquear el artículo con `Articulo::whereKey($id)
  ->lockForUpdate()->first()` antes de leer `stock_actual`, calcular el resultante, crear el
  movimiento y guardar el nuevo `stock_actual`.
- **Rationale**: InnoDB serializa por fila las operaciones concurrentes sobre el mismo artículo, sin
  infra externa (Principio V). Cubre el edge case de concurrencia de la spec.
- **Alternativas**: recalcular `stock_actual` sumando todo el histórico en cada movimiento →
  rechazada por coste; el histórico es la fuente de verdad para auditar (SC-006), no para el camino
  caliente. Locks de aplicación (cache locks) → innecesarios teniendo lock de BD.

## D3 — Append-only del ledger

- **Decisión**: `MovimientoStock` no expone update/destroy en su controlador ni rutas; el modelo
  documenta el contrato append-only. Las correcciones son movimientos inversos (`ajuste`).
- **Rationale**: Additional Constraints de la constitución exige ledger append-only igual que
  `factura_eventos`. FR-002/SC-004.
- **Alternativas**: soft delete en movimientos → rechazada: rompería la reconstrucción del stock
  (SC-006) y el principio de inmutabilidad del kardex.

## D4 — Enganche del descuento al emitir

- **Decisión**: llamar a `RegistroMovimientoStock` desde `EmisorFacturas::emitir()`, dentro de la
  transacción existente, iterando `factura->lineas` con `articulo` producto+`gestion_stock`.
- **Rationale**: `EmisorFacturas::emitir()` ya es el punto único de transición borrador→emitida e
  invocado tanto por `FacturaController` como por `RegistroTicket` (POS). Un solo cambio cubre US3
  para ordinaria y simplificada (FR-015). No rompe inmutabilidad: el stock se mueve en el mismo acto
  que congela la factura.
- **Alternativas**: enganchar en el controlador → rechazada: dejaría el POS sin descuento y duplicaría
  el enganche.

## D5 — Reverso al anular / rectificar

- **Decisión**: el reverso de stock (entrada origen `factura`, motivo devolución) se dispara donde ya
  vive la anulación/rectificativa por sustitución. Se revisa `GeneradorRectificativa` y el flujo de
  anulación para inyectar el movimiento inverso de cada línea con stock.
- **Rationale**: FR-016. Mantiene la coherencia con la inmutabilidad: no se re-edita la factura
  original, solo se compensa el stock.
- **Alternativas**: recalcular stock al vuelo desde facturas vigentes → rechazada: el kardex debe
  reflejar el evento de devolución explícitamente.

## D6 — Stock negativo permitido

- **Decisión**: la emisión nunca se bloquea por stock insuficiente; `stock_resultante` puede ser
  negativo y se marca en la UI (listado de stock) como descuadre.
- **Rationale**: decisión explícita del usuario (spec, edge case resuelto). No choca con la
  inmutabilidad fiscal ni frena ventas.
- **Alternativas**: bloquear / pedir confirmación → descartadas por el usuario.

## D7 — Totales e impuesto soportado en compras

- **Decisión**: calcular base/cuota/total de la compra en backend desde líneas, reutilizando el
  patrón de `CalculadoraFactura` (o una variante reducida sin recargo/IRPF, ya que en compra
  soportada solo aplica el impuesto indirecto según `regimen_impositivo`).
- **Rationale**: Principio III. La compra no lleva recargo de equivalencia ni IRPF de salida.
- **Alternativas**: confiar en totales del cliente → prohibido por Principio III.

## D8 — Proveedores como espejo de clientes

- **Decisión**: `Proveedor` replica `Cliente` (campos, `BelongsToTenant`, `SoftDeletes`, selects
  provincia/ciudad encadenados, país libre default `ES`), sin `tipo`/recargo (no aplica).
- **Rationale**: consistencia de UX y de datos (FR-007/FR-008), reutiliza componentes existentes.
- **Alternativas**: tabla mínima sin domicilio → rechazada: el modelo de datos ya define domicilio y
  hace falta para la factura de proveedor.

## D9 — Baja de artículo con `gestion_stock` desactivada

- **Decisión**: desactivar `gestion_stock` conserva el histórico de movimientos y deja de generar
  nuevos; no se borra nada. `RegistroMovimientoStock` rechaza movimientos sobre artículos sin gestión
  o de tipo servicio (FR-004).
- **Rationale**: coherente con el edge case de la spec y con el ledger append-only.
