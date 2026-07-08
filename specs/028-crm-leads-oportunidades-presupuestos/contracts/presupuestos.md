# Contrato HTTP — Presupuestos

**Feature**: 028 | Rutas en `routes/web.php`, protegidas por permiso `ver-presupuestos` y global
scope de tenant. El cálculo de importes es 100% backend (`CalculadoraFactura`, Principio III).

| Método | Ruta | Nombre | Permiso | Descripción |
|--------|------|--------|---------|-------------|
| GET | `/presupuestos` | `presupuestos.index` | ver-presupuestos | Listado con estado |
| GET | `/presupuestos/crear` | `presupuestos.create` | ver-presupuestos | Formulario (desde oportunidad/cliente/lead) |
| POST | `/presupuestos` | `presupuestos.store` | ver-presupuestos | Crea presupuesto (borrador), calcula totales |
| GET | `/presupuestos/{presupuesto}` | `presupuestos.show` | ver-presupuestos | Ficha con líneas y totales |
| GET | `/presupuestos/{presupuesto}/editar` | `presupuestos.edit` | ver-presupuestos | Edición (solo si borrador) |
| PUT | `/presupuestos/{presupuesto}` | `presupuestos.update` | ver-presupuestos | Recalcula (solo si borrador, FR-018) |
| DELETE | `/presupuestos/{presupuesto}` | `presupuestos.destroy` | ver-presupuestos | Baja lógica (no si facturado) |
| PUT | `/presupuestos/{presupuesto}/estado` | `presupuestos.estado` | ver-presupuestos | enviar/aceptar/rechazar/caducar |
| POST | `/presupuestos/{presupuesto}/convertir` | `presupuestos.convertir` | ver-presupuestos | Presupuesto aceptado → Factura borrador |
| GET | `/presupuestos/{presupuesto}/pdf` | `presupuestos.pdf` | ver-presupuestos | PDF de la oferta |
| POST | `/presupuestos/{presupuesto}/enviar` | `presupuestos.enviar` | ver-presupuestos | Envía por email (reutiliza `TenantMailer`, opcional) |

## POST `/presupuestos` y PUT `/presupuestos/{presupuesto}` (`Store/UpdatePresupuestoRequest`)

- `oportunidad_id` (nullable, exists del tenant)
- `cliente_id` **o** `lead_id` (receptor; al menos uno)
- `fecha_emision` (requerido, date)
- `fecha_validez` (nullable, date ≥ emisión; default `presupuesto.dias_validez`)
- `irpf_porcentaje` (nullable, decimal)
- `lineas[]` (requerido, ≥1): `{ articulo_id?, concepto, unidad?, cantidad, precio_unitario,
  descuento_porcentaje?, tipo_impositivo }`

**Cálculo**: el servidor invoca `CalculadoraFactura::calcular(regimen, aplicaRecargo, irpf, lineas)`
y persiste `base`, `cuota_impuesto`, `tipo_recargo`, `cuota_recargo` por línea + totales de cabecera.
El cliente **nunca** envía importes calculados. `PUT` solo permitido si `estado = borrador`
(FR-018); si no, `422`/`403`.

## PUT `/presupuestos/{presupuesto}/estado`

- `estado` (requerido, in: `enviado`, `aceptado`, `rechazado`, `caducado`)

**Transiciones válidas** (FR-019):
- `borrador → enviado` (fija `fecha_envio`)
- `enviado → aceptado` (habilita conversión; marca la oportunidad `ganada` si estaba abierta, FR-020)
- `enviado → rechazado` / `enviado → caducado`

Transición inválida → `422`. Estado `facturado` no se fija por aquí (lo pone la conversión).

## POST `/presupuestos/{presupuesto}/convertir`

**Precondición**: `estado = aceptado`. En otro estado → `403`/`422` (FR-022).

**Efecto** (`ConversorPresupuestoFactura`, transacción):
1. Crea `Factura` en estado `borrador` con líneas e importes **congelados** del presupuesto (no
   releídos del catálogo).
2. Enlaza `facturas ← presupuestos.convertido_a_factura_id`.
3. Marca el presupuesto `estado = facturado` (terminal, solo lectura).
4. **No** consume numeración de serie ni Verifactu ni mueve stock (eso ocurre al **emitir** la
   factura después, con `EmisorFacturas`).

**Respuesta**: `302` → `facturas.edit` de la factura borrador creada, con flash `success`.

**Guarda de concurrencia**: la comprobación `estado = aceptado → facturado` se hace dentro de la
transacción con bloqueo de fila para impedir doble facturación simultánea (SC-005).
