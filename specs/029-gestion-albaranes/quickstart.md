# Quickstart / Validación — Gestión de Albaranes de Entrega (029)

Guía para validar de extremo a extremo el nuevo tramo **Presupuesto aceptado (o Cliente) →
Albarán(es) → Factura consolidada**. Presupone el proyecto corriendo con al menos un tenant activo,
un usuario con el permiso `ver-albaranes`, y feature 028 (presupuestos) ya implementada.

## Prerrequisitos

- Migraciones aplicadas: `php artisan migrate` (crea `albaranes`, `albaran_lineas`; añade
  `cantidad_entregada` a `presupuesto_lineas`, `albaran_id` a `movimientos_stock` y a
  `factura_lineas`).
- Seeder de permisos re-ejecutado para registrar `ver-albaranes` y asignarlo al rol Administrador.
- Al menos un `articulo` tipo `producto` con `gestion_stock = true` y stock inicial > 0 (para
  observar el movimiento de stock).
- Un presupuesto ya `aceptado` (feature 028) con al menos una línea de ese artículo con
  cantidad ≥ 2 (para poder probar entrega parcial).

## Escenario 1 — Entrega parcial desde un presupuesto aceptado (US1, crítico)

1. Anotar el `stock_actual` del artículo antes de empezar.
2. `/albaranes/crear?presupuesto_id=...` → generar un albarán con **menos** cantidad que la total
   de la línea del presupuesto (p. ej. 40 de 100). Confirmar como `entregado`.
   **Esperado**: `stock_actual` baja exactamente en 40; la línea del presupuesto muestra 60
   pendientes de entrega.
3. Intentar generar un segundo albarán sobre la misma línea pidiendo 70 unidades (más de lo
   pendiente). **Esperado**: rechazado, informando que el máximo disponible es 60 (FR-003).
4. Generar el segundo albarán con las 60 restantes y confirmarlo. **Esperado**: `stock_actual` baja
   otras 60 (100 en total acumulado); la línea del presupuesto queda en 0 pendiente; el presupuesto
   ya no ofrece generar más albaranes sobre esa línea.

## Escenario 2 — Albarán directo a cliente, sin presupuesto (US2)

1. Desde la ficha de un cliente existente, crear un albarán directo con líneas de artículos (sin
   `presupuesto_id`).
2. Confirmarlo como `entregado`. **Esperado**: mismo efecto de stock que un albarán derivado de
   presupuesto (Escenario 1, paso 2); el albarán no aparece vinculado a ningún presupuesto en su
   ficha.

## Escenario 3 — Consolidar varios albaranes en una factura (US3, crítico)

1. Tener al menos 2 albaranes `entregado`, no facturados, del **mismo** cliente (pueden ser los del
   Escenario 1 y 2 si comparten cliente, o crear dos ad-hoc).
2. Anotar el `stock_actual` actual del artículo (ya reflejando las entregas).
3. En `/albaranes`, seleccionar ambos y ejecutar "Convertir a factura".
   **Esperado**: se crea una única factura borrador con las líneas de ambos albaranes, cada una
   identificable por su albarán de origen; el total de la factura coincide con la suma de los
   totales de los albaranes; ambos albaranes quedan en estado `facturado`.
4. **Validación clave (SC-003)**: verificar que `stock_actual` del artículo **no cambió** respecto
   al paso 2 — la conversión a factura no debe mover stock otra vez.
5. Emitir esa factura por el flujo normal de facturas. **Esperado**: consume numeración correlativa
   y genera Verifactu con normalidad, pero **sigue sin mover stock** (ya se movió al entregar los
   albaranes).
6. Repetir el paso 3 mezclando a propósito un albarán de **otro** cliente en la selección.
   **Esperado**: la conversión se rechaza, informando que deben ser del mismo cliente (FR-009).

## Escenario 4 — Anular un albarán entregado antes de facturar (US4)

1. Confirmar un albarán como `entregado` (anotar `stock_actual` antes y después).
2. Anularlo. **Esperado**: `stock_actual` vuelve exactamente al valor previo a la entrega; si el
   albarán venía de un presupuesto, la cantidad vuelve a quedar pendiente de entrega en esa línea
   (FR-006).
3. Intentar anular un albarán ya `facturado` (de los del Escenario 3). **Esperado**: rechazado
   (FR-007).

## Comandos de validación automatizada

```bash
# Tests de aislamiento multi-tenant (Principio I / SC-005)
php artisan test --filter=AlbaranTenantScope

# Entrega parcial: tope de cantidad pendiente por línea de presupuesto (SC-001)
php artisan test --filter=AlbaranEntregaParcial

# Movimiento de stock exacto al entregar y su reverso exacto al anular (SC-002/SC-004)
php artisan test --filter=AlbaranMovimientoStock

# Consolidación N→1 sin duplicar movimiento de stock + rechazo de clientes mixtos (SC-003)
php artisan test --filter=ConversorAlbaranesFactura
```

**Criterio de aceptación global**: los tests anteriores en verde + los 4 escenarios manuales
completados cubren FR-001..FR-014 y SC-001..SC-005.
