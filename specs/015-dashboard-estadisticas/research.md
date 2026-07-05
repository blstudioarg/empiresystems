# Research: Dashboard de estadísticas

No quedaron `NEEDS CLARIFICATION` en el Technical Context del plan — el stack ya está fijado por
la constitución (Laravel 12 + Blade + MySQL) y no hay integraciones externas. Este documento deja
constancia de las decisiones técnicas puntuales que sí requerían elegir entre alternativas.

## Decisión 1: Librería de gráficos

- **Decision**: Vendorizar **Chart.js** (build UMD minificado, un solo archivo
  `public/vendor/chartjs/chart.umd.min.js`), igual que el resto de `public/vendor/*`
  (toastr, select2, datatables): sin gestor de paquetes ni paso de build.
- **Rationale**: Es la librería que usa el banco de templates NexaDash en su referencia más
  simple (`chart-chartjs.blade.php`) para exactamente los tipos de gráfico que pide la spec
  (línea/área, barras, donut/pie) con una única API consistente. Alternativas del banco
  (Chartist, Flot, Morris, Peity, Sparkline) están pensadas para casos más específicos
  (sparklines inline, gráficos minimalistas) y hubiera exigido mezclar 2-3 librerías para cubrir
  los 3 tipos de gráfico pedidos. Chart.js cubre los tres con una sola dependencia.
- **Alternatives considered**:
  - **Chartist.js**: más liviano pero sin soporte nativo de donut con leyenda + tooltips tan
    directo como Chart.js; hubiera requerido más JS a medida.
  - **ApexCharts** (no está en el banco de templates): más pesado, curva de configuración mayor,
    no aporta nada que Chart.js no resuelva para este alcance.
  - **Sin librería (SVG/Canvas a mano)**: descartado, complejidad injustificada (Principio V,
    YAGNI) para un caso ya resuelto por una librería estándar.

## Decisión 2: Estrategia de agregación de datos

- **Decision**: Todas las agregaciones (sumas mensuales, conteos por estado, ranking de
  clientes) se resuelven con `GROUP BY`/`SUM`/`COUNT` en SQL vía Eloquent Query Builder dentro de
  `DashboardEstadisticas`, no cargando colecciones completas de `Factura`/`Pago` a memoria PHP.
- **Rationale**: SC-004 exige carga en <2s con hasta unos pocos miles de facturas por tenant;
  agregar en SQL es lo único que escala sin optimizaciones adicionales y es coherente con
  Principio V (simplicidad, sin caching/colas necesarios a este volumen).
- **Alternatives considered**:
  - **Cachear el dashboard** (Redis/tabla de snapshots): descartado por ahora — Principio V
    (YAGNI) y la constitución no permite asumir infraestructura más allá de MySQL compartido sin
    justificar necesidad real; al no requerirse tiempo real ni un volumen que lo justifique, se
    recalcula en cada carga.
  - **Tabla de resumen mensual materializada**: descartado, mismo argumento de YAGNI — se
    revisita si en el futuro el volumen de datos lo exige.

## Decisión 3: Ubicación del cálculo de variación porcentual

- **Decision**: Extraer el cálculo de variación (mes actual vs. mes anterior, incluyendo el caso
  "base = 0 → sin datos previos") a un helper puro `App\Support\VariacionPorcentual`, reutilizable
  para las 3 métricas de KPI (facturado, cobrado, nº facturas) y testeable de forma aislada.
- **Rationale**: Evita triplicar la misma condición (`if ($anterior == 0) ... else ...`) en el
  servicio de agregación y facilita el test-first de los casos borde de Edge Cases de la spec sin
  necesitar datos de BD para cada caso.
- **Alternatives considered**: Calcular la variación inline en `DashboardEstadisticas` — funciona
  pero duplica lógica y mezcla responsabilidad de "traer datos" con "interpretar datos".

## Resumen de resolución de incógnitas

No quedan incógnitas abiertas: el resto de decisiones (formato de vista, período fijo por
sección, sin exportación/tiempo real) ya están fijadas como *Assumptions* en `spec.md` y no
requieren investigación técnica adicional.
