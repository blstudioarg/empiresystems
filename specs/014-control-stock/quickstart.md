# Quickstart / Validación: Control de stock

Guía para validar la feature de punta a punta. No incluye implementación; ver `data-model.md` y
`contracts/rutas.md` para el detalle.

## Prerrequisitos

- Entorno Laravel del proyecto levantado con un tenant demo (seeders existentes).
- Al menos un artículo `producto` con `gestion_stock=true` (crear desde `/articulos` o factory).

## Setup de pruebas automáticas

```bash
php artisan migrate                       # aplica las 4 migraciones nuevas
php artisan test --filter=Stock           # corre la suite de stock (test-first)
php artisan test --filter=ProveedorCrud
```

Los tests deben escribirse **antes** de la implementación de la lógica de stock (Principio IV) y
fallar primero.

## Escenarios de validación (mapeados a la spec)

### V1 — Kardex y ajuste manual (US1 / FR-001..006)
1. Crear artículo producto con `gestion_stock=true`, `stock_actual=10`.
2. `POST /stock/ajuste` entrada 5 motivo "inventario" → movimiento `ajuste`, `stock_resultante=15`, `stock_actual=15`.
3. `POST /stock/ajuste` salida 3 motivo "rotura" → `stock_resultante=12`, `stock_actual=12`.
4. Verificar que no hay ruta para editar/borrar el movimiento (append-only).
5. Intentar ajuste sobre un artículo servicio → rechazado.

### V2 — Proveedores (US4 / FR-007..008)
1. `POST /proveedores` con NIF y domicilio → aparece en el selector de compras.
2. `DELETE /proveedores/{id}` con compras asociadas → baja lógica, compras intactas.
3. Con 2 tenants, tenant A no ve proveedores de B.

### V3 — Compra que repone stock (US2 / FR-009..014)
1. Crear compra borrador con línea de 20 uds del artículo → sin movimientos aún.
2. `POST /compras/{id}/confirmar` → movimiento `entrada` origen `compra`, `stock_actual += 20`.
3. Intentar editar la compra confirmada → rechazado (inmutable).
4. `POST /compras/{id}/anular` → movimiento inverso, stock vuelve.
5. Línea de concepto libre → no genera movimiento pero suma a totales.

### V4 — Salida al emitir (US3 / FR-015..016)
1. Emitir factura con línea de 4 uds (artículo con stock=30) → movimiento `salida` origen `factura`, `stock_actual=26`.
2. Factura en borrador → sin descuento.
3. Anular/rectificar por anulación → movimiento inverso, stock devuelto.
4. Emitir ticket POS simplificado con artículo con stock → descuenta igual.
5. Emitir con cantidad > stock → permitido, `stock_resultante` negativo marcado (no bloquea).

### V5 — Alertas de stock mínimo (US5 / FR-017)
1. Artículo `stock_minimo=5`, `stock_actual=5` → aparece en `/stock` como a reponer.
2. `stock_actual=3` → aparece como bajo mínimo.
3. `stock_actual` por encima o sin mínimo → no aparece.

## Invariantes a afirmar en tests

- INV-1: `stock_actual` == último `stock_resultante` tras cada escenario.
- INV-2: suma del histórico (±según tipo) == `stock_actual`.
- INV-3: movimientos inmutables (sin update/delete).
- INV-4: 0 fugas entre ≥2 tenants en proveedores, compras y movimientos.
