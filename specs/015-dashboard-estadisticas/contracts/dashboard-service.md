# Contrato interno: `DashboardEstadisticas::resumen()`

No hay API externa en esta feature (es una vista Blade servida por el propio panel). El
"contrato" relevante es la forma del array que el servicio de agregación entrega al controller y
que el controller pasa a la vista — para que Blade/JS nunca necesiten interpretar datos crudos ni
recalcular nada (Principio III).

## Firma

```php
App\Services\DashboardEstadisticas::resumen(?Carbon $ahora = null): array
```

`$ahora` es opcional (default `now()`), inyectable en tests para fijar "mes en curso" sin
depender del reloj del sistema.

## Forma del array devuelto

```php
[
    'kpis' => [
        'facturado_mes' => [
            'valor' => float,           // total facturado del mes en curso
            'variacion_pct' => ?float,  // null = "sin datos previos"
        ],
        'cobrado_mes' => [
            'valor' => float,
            'variacion_pct' => ?float,
        ],
        'pendiente_cobro' => [
            'valor' => float,           // no lleva variación (spec no la pide para este KPI)
        ],
        'num_facturas_mes' => [
            'valor' => int,
            'variacion_pct' => ?float,
        ],
    ],

    'serie_facturacion_12_meses' => [
        // 12 elementos, orden cronológico ascendente, uno por mes calendario
        ['etiqueta' => string, 'facturado' => float],
        // ...
    ],

    'comparativo_6_meses' => [
        // 6 elementos, orden cronológico ascendente
        ['etiqueta' => string, 'facturado' => float, 'cobrado' => float],
        // ...
    ],

    'distribucion_estados' => [
        // una entrada por cada valor de EstadoFactura presente en el histórico (0 si no hay ninguna)
        ['estado' => string, 'cantidad' => int],
        // ...
    ],

    'top_clientes' => [
        // 0 a 5 elementos, orden descendente por total_facturado
        ['cliente_id' => ?int, 'nombre' => string, 'total_facturado' => float],
        // ...
    ],

    'alertas_stock' => [
        'gestiona_stock' => bool, // false si el tenant no tiene ningún articulo con gestion_stock=true
        'items' => [
            ['articulo_id' => int, 'nombre' => string, 'stock_actual' => float, 'stock_minimo' => ?float],
            // ... array vacío si gestiona_stock=true pero no hay alertas
        ],
    ],

    'facturas_recientes' => [
        // 0 a 8 elementos, orden descendente por fecha_expedicion
        [
            'id' => int,
            'numero_completo' => string,
            'estado' => string,
            'cliente_nombre' => string,
            'total' => float,
            'fecha_expedicion' => string, // formato 'Y-m-d', la vista formatea a local
        ],
        // ...
    ],
]
```

## Garantías del contrato

1. **Nunca null en arrays de listas**: `serie_facturacion_12_meses`, `comparativo_6_meses`,
   `distribucion_estados` siempre tienen sus elementos completos (12, 6 y N-estados
   respectivamente) aunque el valor sea 0 — la vista no necesita rellenar huecos.
2. **`top_clientes`, `alertas_stock.items`, `facturas_recientes` pueden ser arrays vacíos** — la
   vista debe manejar el caso vacío con un mensaje, nunca asumir al menos un elemento.
3. **Todo `float` ya viene redondeado a 2 decimales** (importes) o son enteros (conteos) — ningún
   `number_format`/redondeo se hace en Blade ni en JS.
4. **`variacion_pct` es `null` exclusivamente cuando el valor base del mes anterior es 0** — la
   vista renderiza "Sin datos previos" en ese caso, nunca `Infinity`/`NaN`/división visible.
5. **Aislamiento de tenant**: el servicio no recibe `tenant_id` como parámetro — confía en que
   los modelos (`Factura`, `Pago`, `Cliente`, `Articulo`) ya filtran por el tenant activo vía
   `TenantScope`. Un test de contrato (`DashboardTest`) verifica que, con 2 tenants activos en la
   suite, `resumen()` ejecutado en el contexto de cada uno no incluye datos del otro.
