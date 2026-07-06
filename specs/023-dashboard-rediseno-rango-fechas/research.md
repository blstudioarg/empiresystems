# Research: Rediseño del Dashboard con filtro por rango de fechas

## R1 — Cómo calcular el facturado neto sin doble conteo

**Decisión**: Calcular la facturación del periodo en PHP: cargar las facturas **no simplificadas, no
rectificativas**, en estados de facturación (`Emitida`, `Pagada`, `Vencida`, `Rectificada`), con
`fecha_expedicion` dentro del rango, con eager loading de `rectificativa`, y sumar
`Factura::totalCobrable()` de cada una.

**Rationale**: `totalCobrable()` ya devuelve el importe efectivo neto de la operación (neto por
diferencias, total de la sustituta por sustitución) y vive en el modelo, ya probado por la feature de
cobros/rectificativas. Excluir las rectificativas del sumatorio evita el doble conteo; usar el neto de
la original imputa toda la operación al periodo de la original (clarificación resuelta). El `SUM(total)`
en SQL actual no puede hacer esto (ni excluir rectificativas, ni netear la sustitución).

**Alternativas consideradas**:
- *Seguir con SQL `SUM(total)` filtrando rectificativas por `es_rectificativa = false`*: corrige el
  doble conteo de sustitución pero **no** netea el caso diferencias (la original guarda 100 y el ajuste
  −25 vive en la rectificativa excluida → contaría 100, no 75). Rechazado por incorrecto.
- *Materializar un campo `importe_neto`*: añade estado desnormalizado y riesgo de desincronización;
  contradice Simplicidad. Rechazado.

**Nota de rendimiento**: el neteo carga facturas del periodo en memoria. Con eager load de
`rectificativa` no hay N+1. Para PYME es asumible; si algún tenant creciera mucho, se optimizaría con
una query dedicada, pero no se anticipa ahora (YAGNI).

## R2 — Modelo del rango de fechas y el periodo anterior comparable

**Decisión**: Value object `App\Support\RangoFechas` con: `desde` (Carbon), `hasta` (Carbon), `preset`
(enum: `mes`, `trimestre`, `anio`, `personalizado`), y método `anterior(): RangoFechas` que devuelve el
periodo inmediatamente anterior de **igual duración** (para las variaciones "vs. periodo anterior").
Factories estáticas: `mesEnCurso()`, `trimestreEnCurso()`, `anioEnCurso()`, `personalizado($desde,$hasta)`,
y `desdePeticion(array $filtros): RangoFechas` con fallback a `mesEnCurso()`.

**Rationale**: Centraliza el cálculo de límites (evita repetir `startOfMonth`/`endOf...` por widget) y
la lógica del periodo anterior en un solo sitio testeable. Los presets "en curso" van desde el inicio
del periodo natural **hasta hoy** (clarificación resuelta). Coherente con helpers existentes del
proyecto (`VencimientoFactura`, `TopeSimplificada`).

**Duración del periodo anterior**: se define como el mismo número de días que `[desde, hasta]`,
terminando el día antes de `desde`. Para "mes en curso" (1→hoy) el anterior es el mismo tramo del mes
pasado, consistente con la comparación actual mes-a-mes.

**Alternativas consideradas**:
- *Pasar `desde`/`hasta` sueltos a cada método del servicio*: disemina la lógica de límites y periodo
  anterior; más frágil de testear. Rechazado.
- *Persistir el rango en sesión/BD*: innecesario; el query param en la URL cumple FR-010 (persiste al
  recargar/compartir). Rechazado.

## R3 — Zona horaria y límites del rango

**Decisión**: Usar `now()` de la app (config `app.timezone`) para calcular "hoy" y los límites; comparar
contra `fecha_expedicion` (columna `date`) y `pagos.fecha` (`date`) con extremos **inclusivos**
(`whereBetween` sobre strings `Y-m-d`). El rango se expresa en fechas de calendario, sin horas, evitando
el problema de frontera por hora.

**Rationale**: Las columnas son `date` (sin hora); comparar por fecha de calendario es determinista y
coincide con el comportamiento actual de `resumenMensual`. Edge case de zona horaria resuelto al no
mezclar `datetime` con `date`.

## R4 — Validación del rango personalizado

**Decisión**: `DashboardFiltroRequest` (FormRequest) valida: `preset` en el enum; si
`preset=personalizado`, `desde` y `hasta` son fechas válidas y `hasta >= desde`. Ante rango inválido, en
vez de romper, el controller **cae al rango por defecto (mes en curso)** y adjunta un aviso (flash
`warning` vía toastr) — cumple FR-009/SC-004 (nunca error de página).

**Rationale**: El dashboard es una vista de solo lectura; degradar con gracia al mes en curso es mejor
UX que un 422. El aviso informa al usuario del rango inválido.

**Alternativas consideradas**: devolver 422/redirect con errores de formulario — más ruidoso para una
pantalla de dashboard. Rechazado a favor del fallback + aviso.

## R5 — Widgets nuevos: fuentes de datos

**Decisión**:
- **Gastos/compras del periodo**: `SUM(compras.total)` de compras **confirmadas** (no borrador/anulada)
  con `compras.fecha` en el rango. **Resultado** = facturado neto − gastos.
- **IVA repercutido vs. soportado**: repercutido = `SUM(cuota_impuesto_total)` de las facturas
  facturables del periodo (mismo criterio que el facturado, excluyendo rectificativas y neteando su
  efecto en el desglose); soportado = `SUM(compras.cuota_impuesto_total)` de compras confirmadas del
  periodo. Etiqueta adaptada al `regimen_impositivo` del tenant (IVA/IGIC/IPSI).
- **Ventas POS (métrica separada)**: `SUM(total)` de facturas `tipo = simplificada` emitidas en el
  rango (los tickets POS no se rectifican por el flujo de rectificativas, así que `SUM(total)` es
  correcto aquí). No se suma al facturado fiscal.

**Rationale**: Reutiliza las columnas ya materializadas (`base_total`, `cuota_impuesto_total`, `total`).
El IVA repercutido neto de rectificativas requiere el mismo neteo en PHP que el facturado (se calcula en
el mismo recorrido de facturas para no duplicar queries).

**Nota sobre estados de compra**: se cuentan las compras en estado que representa gasto real
(confirmada), excluyendo borrador y anulada — análogo a `ESTADOS_FACTURADOS` para facturas. El detalle
exacto del enum `EstadoCompra` se fija en data-model.

## R6 — Indicadores "a fecha de hoy" vs. filtrados por rango

**Decisión**: `pendiente_cobro` (saldo vivo total) y `alertas_stock` (stock actual) **no** se filtran
por rango — son fotos del estado actual. Se marcan en la UI como "a día de hoy". El resto (facturado,
cobrado, num_facturas, serie, comparativo, top clientes, gastos, IVA, ventas POS) se filtran por rango.

**Rationale**: Filtrar "pendiente de cobro" por un rango pasado no tiene sentido de negocio (el saldo es
un estado presente). Coherente con FR-012.

## R7 — Serie temporal con rangos amplios

**Decisión**: La serie de facturación se agrupa por **mes** cuando el rango abarca varios meses, y por
**día** cuando el rango es ≤ ~62 días (mes/trimestre corto). El servicio decide la granularidad según la
duración del rango y devuelve etiquetas ya formateadas.

**Rationale**: Evita cientos de puntos diarios en un rango anual (ilegible y costoso) y mantiene detalle
en rangos cortos. Resuelve el edge case "rango muy amplio".

**Alternativas consideradas**: siempre por mes (pierde detalle en rangos de días); siempre por día
(ilegible en años). Rechazadas a favor de granularidad adaptativa.

## R8 — Componentes de front (template NexaDash)

**Decisión**: Reutilizar del banco `template/Laravel-NexaDash-v1.0-28_May_2025/package/resources/views/`:
tarjetas KPI (`same-card`, ya en uso), charts de `chart-chartjs`/`chart-morris`/`chart-sparkline`, y el
**date range picker** de `form-pickers.blade.php`. Vendorizar el picker y sus dependencias UMD en
`public/vendor/` siguiendo el patrón descrito en CLAUDE.md (dependencias antes que el plugin; `@stack('styles')`
antes de `style.css`).

**Rationale**: El proyecto ya vendoriza chartjs/morris/raphael; no se introducen libs nuevas de negocio.
El picker es la única pieza nueva de front y viene del propio template. Se aplican las skills de diseño
(`frontend-design`, `ui-ux-pro-max`, `emil-design-eng`) en la fase de implementación de la vista.

**Riesgo conocido**: si el date range picker depende de moment.js u otra lib UMD, hay que vendorizarla
antes. Se verifica el `require(...)`/`define([...])` del wrapper antes de integrarlo (lección de
`jquery-asColorPicker` en CLAUDE.md).

**Hallazgos (T002)**: el picker de rango del banco es `bootstrap-daterangepicker`
(`public/vendor/bootstrap-daterangepicker/daterangepicker.js` en el template), inicializado sobre
`input.input-daterange-datepicker` vía `bs-daterange-picker-init.js`. Su wrapper UMD
(`root.daterangepicker = factory(root.moment, root.jQuery)` en modo browser-globals) requiere
`moment` y `jQuery` disponibles como globales **antes** de cargarlo. **Corrección**: el proyecto
(fuera del `template/` bank) **no** tenía `moment`/`daterangepicker` vendorizados todavía — solo
existen en `template/.../package/public/vendor/`; hay que copiarlos a `public/vendor/` como parte
de T020. Orden de carga: `moment.min.js` → jQuery (ya global) → `daterangepicker.js` (+
`daterangepicker.css` antes de `style.css`, según el orden de `@stack('styles')` de CLAUDE.md) →
init propio en `dashboard-charts.init.js`.
