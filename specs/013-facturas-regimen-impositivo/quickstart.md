# Quickstart: Validación del formulario por régimen impositivo

**Feature**: 013-facturas-regimen-impositivo | **Date**: 2026-07-04

Guía para validar de punta a punta que el formulario de factura y el POS respetan el régimen del
tenant. Referencia el contrato ([contracts/regimen-view-contract.md](contracts/regimen-view-contract.md))
y el modelo de datos ([data-model.md](data-model.md)).

## Prerrequisitos

- Entorno local levantado (`php artisan serve` o equivalente) y base de datos migrada + seeded.
- Al menos dos tenants de prueba con regímenes distintos:
  - Tenant A → `regimen_impositivo = iva`
  - Tenant B → `regimen_impositivo = igic`
  - (Opcional) Tenant C → `regimen_impositivo = ipsi`
- Un cliente marcado como en recargo de equivalencia en el Tenant A y otro en el Tenant B.

## Escenario 1 — Factura en tenant IGIC (US1)

1. Entrar como Tenant B (IGIC), ir a **Facturas → Nueva**.
2. **Esperado**: la columna de impuesto se titula **"IGIC %"**; el selector de tipo por línea ofrece
   `0, 3, 7, 9.5, 15, 20`; la línea nueva arranca en `7`.
3. Añadir una línea (cantidad 1, precio 100, tipo 7 %). **Esperado**: preview muestra base 100,00 €,
   IGIC 7% = 7,00 €, total 107,00 €, **sin fila de recargo**.
4. Emitir. **Esperado**: factura creada con `regimen_impositivo = igic`; el PDF muestra "IGIC 7%".

## Escenario 2 — Recargo solo en IVA (US2)

1. Tenant A (IVA), cliente en recargo, nueva factura, línea al 21 %.
   **Esperado**: preview muestra "Recargo eq. 5,2%" además del IVA.
2. Tenant B (IGIC), cliente en recargo, nueva factura, cualquier línea.
   **Esperado**: **no** aparece ninguna fila ni importe de recargo.

## Escenario 3 — POS/ticket respeta el régimen (US3)

1. Tenant B (IGIC), ir a **POS → Nuevo ticket**, añadir un artículo.
2. **Esperado**: las etiquetas de impuesto usan "IGIC" (no "IVA incl."); el desglose corresponde al
   tipo IGIC del artículo; se emite el ticket correctamente.

## Escenario 4 — IPSI entrada libre (US4)

1. Tenant C (IPSI), nueva factura. **Esperado**: la columna se titula "IPSI %"; el tipo por línea es
   un campo numérico **libre** (se puede escribir p. ej. 4); no aparece recargo.

## Escenario 5 — No regresión en IVA (SC-003)

1. Tenant A (IVA), nueva factura. **Esperado**: comportamiento idéntico al actual (tipos 0/4/10/21,
   default 21, recargo 5,2/1,4/0,5 con cliente en recargo).

## Pruebas automatizadas a ejecutar

```
php artisan test --filter=FacturaEmisionIgicTest
php artisan test --filter=FacturaRecargoRegimenTest
php artisan test --filter=Tenant   # aislamiento (incluye régimen aplicado por tenant)
```

**Cobertura mínima esperada** (Principio IV, test-first):
- Emisión IGIC: base/cuota/total correctos y `tipo_impuesto = igic` en el desglose.
- Recargo ausente al emitir bajo IGIC/IPSI aunque el cliente esté en recargo; presente bajo IVA.
- Aislamiento: el régimen aplicado en una emisión es el del tenant activo, no el de otro tenant.

## Criterio de aceptación del quickstart

Todos los escenarios 1–5 se comportan como "Esperado" y las tres suites de test están en verde.
