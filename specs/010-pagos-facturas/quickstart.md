# Quickstart / Validación: Pagos y cobros de facturas

Guía para validar la feature de extremo a extremo. Referencia:
[spec.md](./spec.md) · [plan.md](./plan.md) · [data-model.md](./data-model.md) ·
[contracts/http.md](./contracts/http.md).

## Prerrequisitos

```bash
php artisan migrate            # aplica create_pagos_table
php artisan test --filter=Pago # suite de la feature (debe quedar en verde)
```

## Escenarios de aceptación a verificar

Corresponden a los Acceptance Scenarios de la spec. Cada uno tiene su test automatizado; esta lista
es la checklist de validación manual/CI.

### US1 — Registrar cobro (P1)

- [ ] Factura emitida total 121,00 € + pago de 121,00 € → factura queda `cobrada`, saldo 0,00 €.
- [ ] Factura emitida total 121,00 € + pago parcial 50,00 € → saldo 71,00 €, estado `parcial`.
- [ ] Intentar pagar una factura en `borrador` → rechazado (422 / flash error), sin crear pago.
- [ ] Factura con saldo 30,00 € + intento de pago 50,00 € → rechazado, sin crear pago.
- [ ] Pago con importe 0 o negativo → rechazado por validación.

### US2 — Ver estado de cobro e historial (P1)

- [ ] Factura emitida sin pagos → estado `pendiente`, saldo = total.
- [ ] Factura con dos pagos parciales → historial muestra ambos; saldo = total − suma.
- [ ] Listado de facturas distingue visualmente `pendiente` / `parcial` / `cobrada`.
- [ ] Múltiples cuotas con decimales que suman el total → saldo llega exactamente a 0,00 € (sin
      arrastre de redondeo).

### US3 — Anular pago (P2)

- [ ] Anular un pago vigente → queda marcado anulado (no borrado), saldo vuelve al valor previo.
- [ ] Intentar anular un pago ya anulado → rechazado (422 / flash error).
- [ ] Tras anular, el estado de cobro se recalcula (p. ej. `cobrada` → `pendiente`).

### Aislamiento multi-tenant (Principio I)

- [ ] Usuario del tenant A no puede registrar pago contra factura del tenant B → 404.
- [ ] Usuario del tenant A no puede anular un pago del tenant B → 404.
- [ ] Registrar/anular pagos en el tenant A no altera saldos ni pagos del tenant B.

## Comando de validación final

```bash
php artisan test --filter=Pago
```

Todos los archivos `PagoRegistroTest`, `PagoSaldoEstadoTest`, `PagoAnulacionTest` y
`PagoTenantIsolationTest` deben pasar en verde, y no debe haber regresiones en la suite de facturas
(008/009):

```bash
php artisan test
```
