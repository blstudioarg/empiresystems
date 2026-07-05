# HTTP Contracts: Pagos y cobros de facturas

Todas las rutas viven bajo el grupo `['tenant.context', 'auth']` de `routes/web.php`. El modelo se
resuelve manualmente en el controlador (`findOrFail`), nunca por implicit binding (evita fuga de
tenant — memoria `project_tenant_route_binding`). Recurso de otro tenant → **404**.

## POST `/facturas/{factura}/pagos` → `PagoController@store`

Registra un cobro contra una factura emitida (FR-001, US1).

**Nombre de ruta**: `facturas.pagos.store`

**Request** (`StorePagoRequest`):

| Campo | Reglas |
|-------|--------|
| fecha | `required, date` |
| importe | `required, numeric, gt:0` |
| metodo | `required, in:transferencia,tarjeta,efectivo,domiciliacion` |
| referencia | `nullable, string, max:100` |

**Respuestas**:

| Situación | HTML | JSON |
|-----------|------|------|
| OK | 302 redirect back con flash `success` | 201 `{ "message": "...", "id": <pagoId>, "saldo_pendiente": "71.00", "estado_cobro": "parcial" }` |
| Factura en `borrador` (FR-012) | 302 back con flash `error` | 422 `{ "message": "Solo se pueden registrar pagos de facturas emitidas." }` |
| Importe supera saldo pendiente (FR-003) | 302 back con flash `error` | 422 `{ "message": "El pago excede el saldo pendiente de la factura." }` |
| Validación de campos | 302 back con errores | 422 `{ "errors": {...} }` (formato Laravel) |
| Factura de otro tenant / inexistente | 404 | 404 |

Reglas de negocio (factura emitida, no exceder total) se verifican en `RegistroPagos::registrar`,
que lanza `PagoInvalidoException` → 422. La validación de forma (importe>0, metodo válido) está en
`StorePagoRequest`.

## POST `/pagos/{pago}/anular` → `PagoController@anular`

Anula (soft) un pago vigente y recalcula saldo/estado de la factura (FR-007, FR-009, US3).

**Nombre de ruta**: `pagos.anular`

**Request**: sin cuerpo (solo CSRF).

**Respuestas**:

| Situación | HTML | JSON |
|-----------|------|------|
| OK | 302 redirect back con flash `success` | 200 `{ "message": "Pago anulado.", "saldo_pendiente": "121.00", "estado_cobro": "pendiente" }` |
| Pago ya anulado (FR-008) | 302 back con flash `error` | 422 `{ "message": "El pago ya estaba anulado." }` |
| Pago de otro tenant / inexistente | 404 | 404 |

## Lectura del estado de cobro (US2)

No añade endpoint nuevo dedicado: el estado de cobro y el saldo pendiente se exponen en el **listado
de facturas ya existente** (`FacturaController@index`, respuesta JSON de la datatable). Se añaden a
cada fila:

```json
{
  "estado_cobro": "parcial",
  "saldo_pendiente": "71.00",
  "monto_cobrado": "50.00"
}
```

El historial de pagos de una factura (fecha, importe, método, referencia, vigente/anulado) se sirve
junto al detalle de la factura donde se muestre; el formato exacto de esa vista es de presentación y
no fija contrato de API en esta feature.

## Notas de notificaciones

Todos los mensajes al usuario en flujo HTML usan flashes de sesión (`->with('success'|'error', ...)`)
que el partial `flash-toastr` convierte en toastr. Desde JS/AJAX se usa `window.showToast(...)`.
No introducir alerts Bootstrap ad-hoc (CLAUDE.md).
