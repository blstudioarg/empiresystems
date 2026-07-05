# Research: Pagos y cobros de facturas

Feature 010. Todas las incógnitas técnicas quedan resueltas abajo; no queda ningún
`NEEDS CLARIFICATION`.

## D1 — Estado de cobro: ¿columna `estado` o derivado aparte?

**Decisión**: El estado de cobro (`pendiente` / `parcial` / `cobrada`) es un **derivado calculado**
sobre `Factura` a partir de sus pagos vigentes. **No** se toca la columna `estado` de la factura ni
se usa el case `EstadoFactura::Pagada`.

**Rationale**: El Principio II declara la factura `emitida` **inmutable**. Cambiar su `estado` a
"pagada" al cobrarla mutaría el estado fiscal de un documento inmutable y mezclaría dos ejes
distintos: el ciclo de vida fiscal (borrador→emitida→rectificada/anulada) y el estado de cobro
(pendiente→parcial→cobrada). Mantenerlos separados evita ambigüedad y cumple la instrucción explícita
de la spec ("derivar el estado de cobro a partir de los pagos, no como columna editable a mano").

**Alternativas consideradas**:
- *Reusar `EstadoFactura::Pagada` mutando `estado`*: rechazada por violar inmutabilidad y por
  colisionar con `Rectificada`/`Anulada` (una factura puede estar cobrada y luego rectificada).
- *Persistir un `estado_cobro` en la tabla facturas*: rechazada por YAGNI y por riesgo de
  desincronización con la suma real de pagos; el derivado es siempre consistente (SC-003).

## D2 — Anulación de pagos: soft vs borrado físico

**Decisión**: Anulación **soft** mediante columna `anulado_at` (nullable datetime). Un pago vigente
es el que tiene `anulado_at IS NULL`. Se expone un scope `vigentes()`. No se usa `SoftDeletes` de
Laravel (que oculta la fila): el pago anulado **debe seguir siendo visible** en el historial (FR-006).

**Rationale**: Integridad financiera (Principio III) y el mismo criterio append-only que
`movimientos_stock`/`factura_eventos`: las correcciones dejan rastro, no borran. La spec pide
explícitamente que el pago anulado "no se borra físicamente" y que se distinga de uno vigente.

**Alternativas consideradas**:
- *`SoftDeletes` (`deleted_at`)*: rechazada porque el default scope ocultaría el pago y no podríamos
  listarlo como "anulado" sin `withTrashed()` en cada consulta; semánticamente "borrado" ≠ "anulado".
- *Borrado físico*: rechazada por auditabilidad (un cobro registrado y su reverso son historia).

## D3 — Regla de no exceder el total y exactitud de decimales

**Decisión**: Validación en backend dentro de `RegistroPagos::registrar()`, en transacción:
`sum(importes vigentes) + nuevoImporte <= factura.total`. Comparaciones en céntimos (enteros) para
evitar errores de coma flotante: se multiplica por 100 y se redondea, o se compara con
`bccomp`/`round(...,2)`. El saldo pendiente = `round(total - sumaVigentes, 2)` y debe poder llegar a
exactamente `0.00`.

**Rationale**: Principio III (server-side) y el edge case de múltiples cuotas con decimales que deben
saldar a 0,00 € sin arrastre. `DECIMAL(12,2)` en BD + comparación en céntimos elimina el arrastre.

**Alternativas consideradas**:
- *Comparar floats directamente*: rechazada por riesgo de `0.009999` residual.
- *Permitir sobrepago y marcar "a favor"*: fuera de alcance (la spec prohíbe superar el total).

## D4 — Rutas y resolución del modelo

**Decisión**: Rutas anidadas bajo factura para crear
(`POST /facturas/{factura}/pagos` → `PagoController@store`) y una ruta para anular
(`POST /pagos/{pago}/anular` → `PagoController@anular`). El controlador resuelve con
`Factura::findOrFail($factura)` / `Pago::findOrFail($pago)` **en el cuerpo**, no con implicit route
model binding.

**Rationale**: La memoria `project_tenant_route_binding` documenta que el implicit binding se salta
el `TenantScope`; la resolución manual pasa por el global scope y garantiza el aislamiento (un ID de
otro tenant devuelve 404). Es el patrón que ya usan `FacturaController` y el resto de controladores.

**Alternativas consideradas**:
- *Implicit binding `Route::post(... Pago $pago)`*: rechazada por la fuga de tenant documentada.
- *`apiResource`*: innecesario; solo hacen falta store + anular.

## D5 — Método de pago (enum)

**Decisión**: Reusar `App\Enums\FormaPago` (transferencia, tarjeta, efectivo, domiciliacion) como
`metodo` del pago. No se crea catálogo nuevo.

**Rationale**: La spec y `docs/03-modelo-datos.md` indican "mismo enum que `forma_pago`". Evita
duplicación (Principio V) y mantiene coherencia con la cabecera de factura.

**Alternativas consideradas**:
- *Enum `MetodoPago` separado*: rechazada por YAGNI; hoy son idénticos.

## D6 — Impacto en facturas rectificadas con pagos previos

**Decisión**: Fuera de alcance en esta feature (documentado en Assumptions de la spec). La regla
general se mantiene: solo `estado === emitida` admite nuevos pagos; una factura ya `rectificada` no
es `emitida`, así que no acepta pagos nuevos. El tratamiento del saldo previo tras rectificación se
difiere a una iteración futura.

**Rationale**: Evita acoplar esta feature con la lógica de rectificativas (009); YAGNI hasta que
aparezca el caso de uso real. No bloquea el MVP de cobros.
