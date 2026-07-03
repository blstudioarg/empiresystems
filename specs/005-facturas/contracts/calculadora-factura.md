# Contract — `CalculadoraFactura` (servicio de cálculo)

Única fuente de verdad de importes (Principio III). Determinista y sin efectos secundarios (no
persiste). Cubierto por tests unitarios **antes** de implementar (Principio IV).

## Entrada
```
calcular(
  regimen: RegimenImpositivo,      // congelado de la factura
  aplicaRecargo: bool,             // congelado del cliente; solo surte efecto si regimen == iva
  irpfPorcentaje: ?float,          // manual; null/0 => sin retención
  lineas: array<{
     cantidad: float,
     precioUnitario: float,        // sin impuesto
     descuentoPorcentaje: ?float,  // 0..100
     tipoImpositivo: float         // válido según régimen
  }>
): ResultadoCalculo
```

## Salida (`ResultadoCalculo`)
```
{
  lineas: array<{ base, cuotaImpuesto, tipoRecargo, cuotaRecargo }>,   // por línea, alineadas al input
  impuestos: array<{ tipoImpuesto, porcentaje, baseImponible, cuota }>, // desglose agrupado (=> factura_impuestos)
  baseTotal, cuotaImpuestoTotal, cuotaRecargoTotal, irpfCuota, total
}
```

## Reglas (deben quedar fijadas por tests)
1. `baseLinea = round(cantidad * precioUnitario, 2)`; si hay descuento,
   `baseLinea = round(baseLinea * (1 - descuento/100), 2)`.
2. `cuotaImpuesto = round(baseLinea * tipoImpositivo / 100, 2)`.
3. **Recargo**: solo si `regimen == iva` y `aplicaRecargo`. `tipoRecargo` según el tipo de IVA:
   `21 → 5.2`, `10 → 1.4`, `4 → 0.5`, `0 → 0`. `cuotaRecargo = round(baseLinea * tipoRecargo/100, 2)`.
   En IGIC/IPSI el recargo es siempre 0.
4. **Desglose** `impuestos`: agrupar líneas por (tipo de impuesto indirecto del régimen, porcentaje)
   sumando `baseImponible` y `cuota`. Añadir filas separadas para `recargo` (si aplica) y para
   `irpf` (si aplica), con su base y cuota.
5. **IRPF**: `irpfCuota = round(baseTotal * irpf/100, 2)`; si `irpf` null/0 → 0.
6. **Totales de cabecera** = suma de las cuotas **ya redondeadas** por línea/grupo:
   - `baseTotal = Σ baseLinea`
   - `cuotaImpuestoTotal = Σ cuotaImpuesto`
   - `cuotaRecargoTotal = Σ cuotaRecargo`
   - `total = baseTotal + cuotaImpuestoTotal + cuotaRecargoTotal − irpfCuota`
   El total DEBE cuadrar exactamente con la suma del desglose `impuestos` (menos IRPF).

## Casos de test mínimos (Unit)
- Una línea 21% IVA sin recargo ni IRPF → base/cuota/total correctos.
- Varias líneas con tipos distintos (21/10/4) → desglose con 3 filas, totales sumados.
- Recargo de equivalencia bajo IVA (21→5.2) presente; ausente si régimen IGIC.
- IRPF 15% resta del total; IRPF null → sin fila IRPF.
- Descuento por línea (incl. 100% → base 0) sin romper totales.
- Redondeo: el total coincide con la suma del desglose a 2 decimales.
- Régimen IGIC con tipo 7% válido; recargo siempre 0.
- Régimen IPSI con tipo libre (p. ej. 8%): cuota calculada normal, recargo 0.

---

# Contract — `NumeradorFacturas` (numeración, diseñada; se usa al emitir)

Asigna el correlativo de una serie sin huecos ni duplicados. **No** se invoca en esta feature
(solo se implementa y testea), pero queda listo para la emisión.

## Comportamiento
```
siguienteNumero(serie: Serie): { numero: int, numeroCompleto: string }
```
- Dentro de una transacción: `Serie::where('id', serie->id)->lockForUpdate()->first()`, leer
  `proximo_numero`, formatear `numero_completo` con `formato`, incrementar y persistir
  `proximo_numero`.
- Ante concurrencia, dos llamadas devuelven números **consecutivos distintos** (el lock serializa).

## Casos de test mínimos (Unit/Feature)
- Llamadas secuenciales → 1, 2, 3… sin huecos.
- Formato aplicado (`F-2026-0001`).
- Concurrencia simulada (dos transacciones) → no duplica ni salta números.

> **Nota de fiabilidad del test de concurrencia**: `lockForUpdate` solo se comporta como bloqueo
> real sobre MySQL/MariaDB. Si la suite corre sobre SQLite, el test de concurrencia NO es
> significativo; marcarlo para ejecutarse solo bajo MySQL (o `skip` fuera de MySQL). El test
> secuencial (sin huecos) sí es válido en cualquier driver.
