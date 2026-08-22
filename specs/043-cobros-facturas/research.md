# Phase 0 — Research: Módulo de Cobros de facturas

Todas las incógnitas del Technical Context quedan resueltas aquí. No queda ningún
`NEEDS CLARIFICATION`.

---

## D1 — DataTable server-side vs. client-side

**Decisión**: DataTable **server-side** (`serverSide: true`, `processing: true`), patrón
`LogActividadController::index()` + `logs-datatable.init.js`.

**Rationale**: SC-004 fija 5.000 facturas por tenant respondiendo en <2 s. El patrón dominante
del proyecto (clientes, artículos, facturas) carga el dataset completo y filtra en el navegador;
con 5.000 filas que además exigen calcular `totalCobrable()` y `montoCobrado()` por fila, eso son
miles de consultas por petición. `logs` ya estableció el precedente server-side en el proyecto
para datasets grandes, con su `draw`/`recordsTotal`/`recordsFiltered`.

**Alternativas descartadas**:
- *Client-side como facturas*: más simple y consistente con la mayoría, pero incumple SC-004 y
  arrastra el N+1 de los importes.
- *Paginación propia sin DataTables*: rompería "Listados: SIEMPRE DataTable".

**Consecuencias**: los filtros viajan en `ajax.data` (nunca construyendo `ajax.url` con una
función — memoria `feedback_datatables_ajax_url_function`); la exportación a Excel queda fuera de
alcance, pero si algún día entra deberá usar la variante `ids:` de `initExportacionExcel`
documentada para tablas server-side.

---

## D2 — Cómo obtener saldo, estado de cobro y días de retraso en SQL

**Decisión**: una única clase `App\Support\ConsultaCobros` construye el `Builder` con tres
expresiones derivadas, y **nadie más escribe ese SQL**:

- `cobrado` = `COALESCE((SELECT SUM(importe) FROM pagos WHERE pagos.factura_id = facturas.id AND
  pagos.anulado_at IS NULL), 0)` — espejo de `Factura::montoCobrado()`.
- `total_cobrable` = `CASE` sobre un `LEFT JOIN facturas AS r ON r.factura_rectificada_id =
  facturas.id AND r.estado = 'emitida'`: sustitución ⇒ `r.total`; diferencias ⇒
  `facturas.total + r.total`; sin rectificativa emitida ⇒ `facturas.total` — espejo de
  `Factura::totalCobrable()`.
- `saldo_pendiente` = `total_cobrable - cobrado`.
- `dias_retraso` = `DATEDIFF(CURDATE(), facturas.fecha_vencimiento)` solo cuando hay vencimiento
  pasado **y** `saldo_pendiente > 0`; en cualquier otro caso, `NULL`.
- `estado_cobro` se deriva de `cobrado` vs `total_cobrable` en céntimos, con el mismo orden de
  comparación que `Factura::estadoCobro()` (`<=0` pendiente, `>=total` cobrada, resto parcial).

**Rationale**: es la única forma de cumplir FR-011/FR-012/FR-015 sin materializar columnas nuevas.
Concentrarlo en una clase con un test de paridad convierte una duplicación peligrosa en una
duplicación vigilada.

**Alternativas descartadas**: ver la tabla de Complexity Tracking del plan (calcular en PHP;
materializar columna).

**Riesgo asumido y su red**: `ConsultaCobrosParidadTest` (Unit) compara, para un dataset mixto
(emitida sin cobros, parcial, cobrada, rectificada por sustitución, rectificada por diferencias,
sin `fecha_vencimiento`, vencida), la fila devuelta por `ConsultaCobros` contra
`totalCobrable()`, `montoCobrado()`, `saldoPendiente()` y `estadoCobro()` del modelo. Este test se
escribe **antes** que la clase (Principio IV).

---

## D3 — Qué facturas entran en el listado

**Decisión**: exactamente las que cumplen `Factura::admiteCobros()`, traducido a SQL como:
`tipo != 'simplificada'` **y** `es_rectificativa = false` **y** (`estado = 'emitida'` **o**
(`estado = 'rectificada'` **y** existe rectificativa emitida)).

**Rationale**: `admiteCobros()` ya es la regla del producto (documentada en el propio docblock del
modelo: *"la original rectificada es SIEMPRE el documento de cobro"*). Reproducirla evita inventar
un criterio nuevo (FR-004/FR-016).

**Sobre las simplificadas (tickets del POS)**: se excluyen, igual que hace hoy
`FacturaController::index()`. Sus cobros son de caja (`ticket_pagos`, circuito propio) y el
supuesto de la spec ya lo declara fuera de alcance.

**Alternativa descartada**: listar toda factura emitida incluyendo rectificativas — mostraría dos
filas cobrables para una misma operación y contradice el modelo.

---

## D4 — Criterio de fecha de cada métrica

**Decisión** (FR-008/FR-009):

| Métrica | Criterio | Depende del rango |
|---|---|---|
| Pendiente de cobro | Σ `saldo_pendiente` de las facturas que admiten cobro, a día de hoy | **No** (instantánea) |
| Cobrado en el periodo | Σ `pagos.importe` vigentes con `pagos.fecha` dentro del rango | **Sí** (evento) |
| Vencido | Σ `saldo_pendiente` de las vencidas a día de hoy | **No** (instantánea) |
| Facturas pendientes | Nº de facturas con `saldo_pendiente > 0` | **No** (instantánea) |

**Rationale**: mezclar los cuatro bajo el rango haría que "pendiente" bajara al estrechar el
periodo, que es justo lo contrario de lo que el usuario espera de una deuda. La guía de front ya
resolvió este problema de comunicación en informes comerciales con `.criterio-badge`, y aquí se
reutiliza esa misma pieza (`.criterio-evento` / `.criterio-instantanea`) en lugar de un tooltip.

**Alternativa descartada**: someter las cuatro al rango (más "coherente" en apariencia, engañoso
en el fondo).

---

## D5 — Selector de rango de fechas

**Decisión**: reutilizar `App\Support\RangoFechas::desdePeticion()` + `App\Enums\PresetRango`
(mes/trimestre/año/personalizado) y el `bootstrap-daterangepicker` ya vendorizado, con el mismo
markup de botones-radio de `informes-comerciales/index.blade.php`.

**Rationale**: `RangoFechas` ya cubre el edge case de rango inválido (`hasta < desde` ⇒ cae a mes
en curso, sin lanzar) que la spec pide, y el defecto "mes en curso" coincide con el supuesto.
Cero dependencias nuevas (Principio V).

**Nota sobre el edge case "rango inválido"**: el servidor nunca falla (cae a mes en curso); el
aviso al usuario lo da el propio daterangepicker, que no permite seleccionar un fin anterior al
inicio. El listado y las cards comparten un único rango, así que no pueden desincronizarse.

**Alternativa descartada**: dos inputs `date` sueltos — perdería los presets y duplicaría lógica
de validación ya resuelta.

---

## D6 — Reutilización del modal de cobros ya existente

**Decisión**: extraer el modal de `facturas/index.blade.php` (bloque `#cobrosModal`, incluido su
`#cobros-table`, el aviso `#cobroContextoRectificada` y el formulario `#registrarCobroForm`) a
`resources/views/partials/_cobros_modal.blade.php`, y las ~150 líneas equivalentes de
`facturas-datatable.init.js` a `public/js/plugins-init/cobros-modal.js`, que expone
`window.initCobrosModal({ tabla, onCambio })`.

**Rationale**: SC-005 (cero discrepancias entre las dos pantallas) y SC-008 (facturas no cambia de
comportamiento). El JS extraído se carga en las dos vistas; `onCambio` es el único punto de
variación: en facturas recarga su DataTable, en cobros recarga la tabla **y** las cards.

**Alternativa descartada**: copiar el markup — divergencia garantizada en el primer cambio.

**Cómo se verifica que no rompe facturas**: la suite existente de facturas/pagos debe quedar en
verde **sin modificarla** (SC-008). Si un test de facturas hay que tocarlo, la extracción cambió
comportamiento y está mal hecha.

---

## D7 — Endpoint de registro y anulación de cobros

**Decisión**: **no se crean endpoints nuevos**. Se reutilizan `facturas.pagos.index`,
`facturas.pagos.store` y `pagos.anular` tal cual, que ya responden JSON con `saldo_pendiente` y
`estado_cobro` cuando `wantsJson()`.

**Rationale**: FR-004. `RegistroPagos` ya valida importe > 0 y ≤ saldo, y lanza
`PagoInvalidoException` que el controller traduce a 422 con mensaje — exactamente lo que piden
FR-019 y SC-007.

**Consecuencia sobre permisos**: esas tres rutas viven hoy en el grupo `can:ver-facturas`. Un
usuario con `ver-cobros` pero **sin** `ver-facturas` no podría registrar ni anular desde la
pantalla nueva. **Decisión**: mover las tres rutas de pagos a un grupo `can:ver-cobros` propio, y
dar `ver-cobros` al rol "Administrador" vía `PermisosSeeder` (paso 2 de la guía de menú). Como
`ver-cobros` es un permiso **nuevo**, no hay roles personalizados que pierdan acceso… salvo por
esas tres rutas que sí existían bajo `ver-facturas`: por eso hace falta la **migración de datos**
que describe la guía ("Dividir, mover o quitar un permiso ya existente"), concediendo `ver-cobros`
a todo rol personalizado que ya tuviera `ver-facturas`. Sin ella, un rol a medida con acceso a
facturas dejaría de poder cobrar — una regresión silenciosa en producción.

**Efecto colateral sobre el módulo de Facturas**: a partir de este cambio, las acciones de cobro
de `facturas/index.blade.php` exigen `ver-cobros`. Un tenant podría, a futuro, dar `ver-facturas`
sin `ver-cobros` a un rol a medida. Para que eso no se traduzca en botones que devuelven 403, la
vista de facturas **oculta** sus acciones de cobro cuando falta el permiso (tarea T033a). El
enforcement real sigue siendo el middleware; ocultar es solo UX.

**Alternativa descartada**: duplicar las rutas bajo los dos permisos — Laravel no admite dos rutas
con el mismo método+URI literal (la segunda pisa a la primera en silencio, trampa ya documentada
en la guía de front para exportar/importar).

---

## D8 — Vista previa de la factura

**Decisión**: iframe a `route('facturas.pdf', $factura)` dentro de un modal
`modal-dialog-centered modal-xl`, con `src` reseteado en `hidden.bs.modal`.

**Rationale**: es el patrón obligatorio de la guía ("Ver un documento: SIEMPRE en modal"), y
además cumple FR-028 sin esfuerzo: al no navegar, los filtros y la página del DataTable siguen
intactos.

**Cuidado con el permiso**: `facturas.pdf` vive bajo `can:ver-facturas`. Un usuario con solo
`ver-cobros` recibiría un 403 al abrir el modal. **Decisión**: la acción "Ver factura" del
dropdown solo se ofrece si el usuario tiene `ver-facturas`; el controller expone `pdf_url` en el
JSON **solo** en ese caso (`$request->user()->can('ver-facturas') ? route(...) : null`), y el JS
omite el `<li>` cuando viene `null` — mismo patrón condicional que ya usan `emitir_url`/`edit_url`
en `FacturaController::index()`.

---

## D9 — Búsqueda de texto en server-side

**Decisión**: `search.value` busca sobre `facturas.numero_completo` y sobre el nombre del cliente,
resolviendo el segundo con un `whereHas('cliente', ...)` sobre `nombre` y `razon_social`.

**Rationale**: FR-013. Es lo que el usuario teclea de verdad. `LogActividadController` ya
estableció la forma de tratar `search.value` en este proyecto.

**Alternativa descartada**: buscar también por importe — ruido, y con `LIKE` sobre decimales da
resultados sorprendentes.

---

## D10 — Filtro "solo vencidas" vs. filtro de estado de cobro

**Decisión**: son **dos controles independientes que se combinan con AND**, no un cuarto valor del
selector de estado. "Vencida" es ortogonal a pendiente/parcial (una parcial también puede estar
vencida); meterla en el mismo selector obligaría al usuario a elegir entre dos preguntas
distintas.

**Rationale**: FR-011 pide explícitamente que los filtros se combinen.

**En la UI**: el estado de cobro va como grupo de botones (patrón ya usado en el filtro por tipo
de `facturas/index.blade.php`), y "solo vencidas" como un checkbox aparte junto a él.
