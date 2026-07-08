# Contrato HTTP — Albaranes

**Feature**: 029 | Rutas en `routes/web.php`, protegidas por permiso `ver-albaranes` y global
scope de tenant. El cálculo de importes es 100% backend (`CalculadoraFactura`, Principio III). El
movimiento de stock es 100% backend (`RegistroMovimientoStock`, único punto de escritura).

| Método | Ruta | Nombre | Permiso | Descripción |
|--------|------|--------|---------|-------------|
| GET | `/albaranes` | `albaranes.index` | ver-albaranes | Listado con cards informativas + datatable, selección múltiple |
| GET | `/albaranes/crear` | `albaranes.create` | ver-albaranes | Formulario (desde presupuesto aceptado o directo a cliente) |
| POST | `/albaranes` | `albaranes.store` | ver-albaranes | Crea albarán (borrador), calcula totales |
| GET | `/albaranes/{albaran}` | `albaranes.show` | ver-albaranes | Ficha con líneas, totales y trazabilidad (presupuesto/factura) |
| GET | `/albaranes/{albaran}/editar` | `albaranes.edit` | ver-albaranes | Edición (solo si borrador) |
| PUT | `/albaranes/{albaran}` | `albaranes.update` | ver-albaranes | Recalcula (solo si borrador) |
| DELETE | `/albaranes/{albaran}` | `albaranes.destroy` | ver-albaranes | Baja lógica (solo si borrador; no si entregado/facturado) |
| PUT | `/albaranes/{albaran}/estado` | `albaranes.estado` | ver-albaranes | `entregado` / `anulado` |
| POST | `/albaranes/convertir` | `albaranes.convertir` | ver-albaranes | N albaranes seleccionados → Factura borrador |

## POST `/albaranes` y PUT `/albaranes/{albaran}` (`Store/UpdateAlbaranRequest`)

- `presupuesto_id` (nullable, exists del tenant, debe estar `aceptado`) **o** `cliente_id`
  (requerido si no hay presupuesto)
- `fecha_entrega` (nullable en borrador; requerida al pasar a `entregado`)
- `lineas[]` (requerido, ≥1):
  - Si `presupuesto_id` presente: `{ presupuesto_linea_id, cantidad, ... }` — `cantidad` acotada al
    servidor a `presupuesto_linea.cantidad - presupuesto_linea.cantidad_entregada` (FR-003); si el
    cliente envía más, `422` con el máximo disponible informado.
  - Si es directo a cliente: `{ articulo_id?, concepto, unidad?, cantidad, precio_unitario,
    descuento_porcentaje?, tipo_impositivo }` (mismas columnas que una línea de presupuesto).

**Cálculo**: el servidor invoca `CalculadoraFactura::calcular(regimen, aplicaRecargo, lineas)` y
persiste base/cuota/recargo por línea + totales de cabecera, igual que presupuestos. `PUT` solo
permitido si `estado = borrador`; si no, `422`/`403`.

## PUT `/albaranes/{albaran}/estado`

- `estado` (requerido, in: `entregado`, `anulado`)

**Transiciones válidas** (FR-004/FR-006/FR-007):
- `borrador → entregado`: fija `fecha_entrega` (si no venía ya seteada), y dispara
  `EntregadorAlbaran` dentro de una transacción: por cada línea con artículo `producto` +
  `gestion_stock`, `RegistroMovimientoStock::registrar(tipo: Salida, origen: Albaran, albaran: ...)`;
  además incrementa `cantidad_entregada` en cada `presupuesto_linea` referenciada.
- `entregado → anulado`: solo si `convertido_a_factura_id` es `null` (FR-007; si ya está facturado,
  `403`). Dispara `AnuladorAlbaran`: por cada línea con stock, `RegistroMovimientoStock::registrar(
  tipo: Entrada, origen: Devolucion, albaran: ...)`, y decrementa `cantidad_entregada` de vuelta en
  las líneas de presupuesto correspondientes.

Transición inválida → `422`. Estado `facturado` no se fija por aquí (lo pone la conversión).

## POST `/albaranes/convertir`

- `albaran_ids[]` (requerido, ≥1, exists del tenant)

**Precondiciones** (FR-008/FR-009), validadas dentro de una transacción con bloqueo de fila:
1. Todos los albaranes referenciados existen, pertenecen al tenant activo, y están en estado
   `entregado` con `convertido_a_factura_id = null`. Si alguno no cumple → `422` indicando cuáles
   se excluyen (o rechazo total, según se decida en la request de validación — por defecto: rechazo
   total con detalle, para que el usuario decida conscientemente qué reintenta).
2. Todos los albaranes son del **mismo** `cliente_id`. Si no → `422` ("deben ser del mismo
   cliente").
3. Todos comparten el mismo `regimen_impositivo` (debería ser siempre así dentro de un tenant, pero
   se valida igual como red de seguridad).

**Efecto** (`ConversorAlbaranesFactura`, transacción):
1. Crea una única `Factura` en estado `borrador` con las líneas de **todos** los albaranes
   seleccionados, cada `factura_linea` con importes **congelados** (no releídos del catálogo) y
   `albaran_id` de origen para trazabilidad.
2. Enlaza cada albarán: `albaranes.convertido_a_factura_id = factura.id`.
3. Marca cada albarán `estado = facturado` (terminal, solo lectura).
4. **No** genera movimiento de stock (ya ocurrió en `EntregadorAlbaran` al entregar cada uno) ni
   consume numeración de serie ni Verifactu (eso ocurre al **emitir** la factura después, con
   `EmisorFacturas`, que se salta el movimiento de stock para esta factura vía
   `Factura::albaranes()->exists()`).

**Respuesta**: `302` → `facturas.edit` de la factura borrador creada, con flash `success` indicando
cuántos albaranes se consolidaron.

**Guarda de concurrencia**: la comprobación de estado/cliente de cada albarán se hace dentro de la
transacción con bloqueo de fila para impedir que dos usuarios conviertan el mismo albarán en dos
facturas distintas a la vez (SC-004).
