# Quickstart: validar el dashboard de estadísticas

## Prerrequisitos

- Entorno local funcionando (`php artisan serve` + tenant de prueba con dominio resuelto, igual
  que el resto del proyecto).
- Un tenant con datos de prueba que cubran: facturas en varios estados y meses, pagos parciales y
  completos, al menos un cliente con varias facturas, y al menos un artículo con
  `gestion_stock = true` y `stock_actual < stock_minimo`.
- Un segundo tenant (para el test de aislamiento) con datos propios y distintos.

## Setup de datos de prueba

```powershell
php artisan tinker
```

```php
// Dentro de tinker, en el contexto de un tenant de prueba:
// crear facturas emitidas en el mes actual y en el mes anterior con Factory,
// registrar pagos parciales sobre alguna de ellas,
// y bajar el stock_actual de un articulo por debajo de su stock_minimo.
```

(Alternativa: usar los seeders/factories ya existentes del proyecto —
`database/factories/FacturaFactory.php`, `PagoFactory` si existe, `ArticuloFactory` — en vez de
crear todo a mano.)

## Ejecutar

1. Entrar como usuario del tenant de prueba y navegar a `/`.
2. Verificar visualmente:
   - Las 4 cards de KPI muestran importes/nº coherentes con lo que se ve en `/facturas` y
     `/pagos` filtrando por el mes en curso.
   - El gráfico de evolución (12 meses) y el comparativo (6 meses) muestran los mismos importes
     agregados por mes.
   - La distribución de estados coincide con contar manualmente `/facturas` por cada estado.
   - El ranking de clientes muestra como máximo 5, ordenados de mayor a menor.
   - La sección de stock muestra el artículo bajo mínimo preparado en el setup.
   - Las últimas facturas emitidas enlazan correctamente al detalle de cada una.

## Casos de borde a probar manualmente

- **Tenant nuevo sin datos**: crear un tenant sin ninguna factura/pago/artículo y confirmar que
  `/` no lanza error 500 y muestra estados vacíos explicativos en cada sección.
- **Mes anterior sin actividad, mes actual con actividad**: confirmar que la variación porcentual
  se muestra como "Sin datos previos para comparar", no como un número extremo o `Infinity`.
- **Tenant sin ningún artículo con `gestion_stock = true`**: confirmar que la sección de stock
  indica que no aplica, distinto del caso "gestiona stock pero no hay alertas".

## Validación automatizada (referencia para tasks.md)

```powershell
php artisan test --filter=DashboardTest
php artisan test --filter=VariacionPorcentualTest
```

- `DashboardTest` (Feature): aislamiento multi-tenant (2 tenants, cada uno ve solo lo suyo) +
  valores agregados correctos contra fixtures conocidas + estado vacío para tenant sin datos.
- `VariacionPorcentualTest` (Unit): base=0 → null; ambos 0 → null; caso normal positivo/negativo.

## Resultado esperado

Todos los criterios de éxito de `spec.md` (SC-001 a SC-004) verificables: lectura del estado
financiero en <5s a simple vista, cifras coincidentes con los listados existentes, tenant vacío
sin errores, carga completa en <2s con datos de volumen típico.
