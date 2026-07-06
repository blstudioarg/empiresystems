# Quickstart / Validación: Dashboard con filtro por rango

Guía para validar la feature end-to-end. Detalle de cálculo en [contracts/dashboard.md](./contracts/dashboard.md)
y [data-model.md](./data-model.md).

## Prerrequisitos

- App corriendo con un tenant de prueba y usuario logueado.
- Datos sembrados: al menos una factura emitida, una compra confirmada, un pago, y (opcional) un ticket POS.

## Validación automatizada (tests)

Ejecutar la suite del dashboard y rectificativas/pagos (que comparten la lógica de neteo):

```
php artisan test --filter="Dashboard|Rango|PagoRectificativa"
```

Escenarios clave que deben cubrir los tests (test-first):

1. **Facturado neto — sustitución no duplica**: factura 100 € rectificada por sustitución a 75 €;
   `kpis.facturado.valor == 75.0`. **[SC-001]**
2. **Facturado neto — diferencias netea**: factura 100 € rectificada por diferencias (delta −25);
   `kpis.facturado.valor == 75.0`. **[SC-001]**
3. **Exclusiones**: borradores, anuladas y simplificadas no suman al facturado. **[FR-002, FR-004]**
4. **Rango por defecto = mes en curso**: `resumen(RangoFechas::mesEnCurso())` reproduce el comportamiento
   histórico del dashboard (equivalencia con los tests actuales adaptados).
5. **Presets**: trimestre/año en curso van desde el inicio del periodo natural hasta hoy; una factura
   fuera del rango no cuenta.
6. **Rango personalizado**: una factura dentro de `[desde, hasta]` cuenta; fuera, no.
7. **Rango inválido**: `GET /?preset=personalizado&desde=2026-05-01&hasta=2026-04-01` responde 200 con
   mes en curso + aviso. **[SC-004]**
8. **Ventas POS separadas**: una simplificada aparece en `kpis.ventas_pos` y **no** en `kpis.facturado`.
9. **Gastos / resultado / IVA**: compra confirmada suma a `kpis.gastos`, `kpis.resultado` = facturado −
   gastos, y a `impuestos.soportado`; factura suma a `impuestos.repercutido`. **[SC-005]**
10. **Indicadores a hoy**: `pendiente_cobro` y `alertas_stock` no cambian al variar el rango. **[FR-012]**
11. **Aislamiento multi-tenant**: dos tenants con datos distintos; cada `resumen()` solo ve lo suyo. **[FR-018]**
12. **Tenant/periodo sin datos**: todos los widgets en cero/estado vacío, sin error. **[SC-006]**

## Validación manual (UI)

1. Abrir `/` → ver el dashboard en **mes en curso** por defecto.
2. Cambiar el selector a **Trimestre** y **Año** → KPIs, serie y rankings se recalculan.
3. Elegir **Personalizado**, fijar un rango de dos días con una única factura dentro → solo esa cuenta.
4. Introducir un rango con "desde" posterior a "hasta" → aviso claro, sin romper, vuelve a mes en curso.
5. Recargar la página con un rango aplicado (URL con query params) → se conserva el rango. **[FR-010]**
6. Con un tenant sin compras/stock → widgets de gastos/IVA/stock muestran estado vacío. **[FR-017]**
7. Verificar que la cifra de "Facturado" del dashboard coincide con la card "Importe total" del listado
   de facturas para el mismo periodo (misma lógica de neteo).

## Criterios de aceptación (resumen)

- [ ] Facturado neto correcto (75 €, no 175 €) en sustitución y diferencias. **[SC-001, SC-002]**
- [ ] Filtro de rango recalcula todo y persiste en URL. **[SC-003, FR-010]**
- [ ] Rango inválido nunca produce error de página. **[SC-004]**
- [ ] Métricas nuevas visibles: gastos/resultado, IVA repercutido/soportado, ventas POS. **[SC-005]**
- [ ] Tenant/periodo vacío sin errores. **[SC-006]**
- [ ] Aislamiento multi-tenant verificado por test. **[FR-018]**
