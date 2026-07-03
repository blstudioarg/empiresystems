# Contract — HTTP routes (Facturas)

Todas bajo `middleware(['auth', 'tenant.context'])`. Resolución de modelos **manual** con
`findOrFail` bajo `TenantScope` (no binding implícito — ver memoria `project_tenant_route_binding`).

| Método | URI | Nombre | Controller | Descripción |
|--------|-----|--------|------------|-------------|
| GET | `/facturas` | `facturas.index` | `FacturaController@index` | HTML (DataTable) o JSON (`wantsJson`) con listado del tenant |
| GET | `/facturas/crear` | `facturas.create` | `FacturaController@create` | Vista full-page de creación |
| POST | `/facturas` | `facturas.store` | `FacturaController@store` | Crea factura en borrador (recalcula server-side) |
| GET | `/facturas/{factura}/editar` | `facturas.edit` | `FacturaController@edit` | Vista full-page precargada (solo si borrador) |
| PUT/PATCH | `/facturas/{factura}` | `facturas.update` | `FacturaController@update` | Actualiza borrador (recalcula) |
| DELETE | `/facturas/{factura}` | `facturas.destroy` | `FacturaController@destroy` | Elimina borrador |
| GET | `/facturas/{factura}/pdf` | `facturas.pdf` | `FacturaController@pdf` | Devuelve el PDF (dompdf), inline |

## `index` JSON (para DataTable)
Cada fila:
```json
{
  "id": 12,
  "identificador": "Borrador",       // o "F-2026-0001" una vez con número
  "estado": "borrador",
  "cliente": "ACME S.L.",
  "fecha_expedicion": "2026-07-03",
  "total": "1210.00",
  "edit_url":   "/facturas/12/editar",
  "delete_url": "/facturas/12",
  "pdf_url":    "/facturas/12/pdf"
}
```
Más un bloque `totales` (nº facturas, suma de importes) análogo a `clientes.index`.

## `store` / `update` payload (form)
```json
{
  "cliente_id": 3,
  "fecha_expedicion": "2026-07-03",
  "fecha_operacion": null,
  "fecha_vencimiento": "2026-08-02",
  "forma_pago": "transferencia",
  "irpf_porcentaje": 15,
  "notas": "…",
  "lineas": [
    {
      "articulo_id": 8,
      "concepto": "Consultoría",
      "unidad": "hora",
      "cantidad": 10,
      "precio_unitario": 50,
      "descuento_porcentaje": 0,
      "tipo_impositivo": 21
    }
  ]
}
```
Reglas: ver `data-model.md` › Validaciones. Los importes (base/cuotas/total) **no** se aceptan del
cliente; se recalculan con `CalculadoraFactura`. `regimen_impositivo` y `aplica_recargo` se
congelan en servidor (del tenant y del cliente respectivamente).

## Respuestas
- `store`: 201 JSON `{ "message": "...", "id": <id> }` o redirect a `facturas.index` con flash.
- `update`/`destroy`: 200/302 con mensaje (patrón clientes).
- Validación fallida: 422 con errores por campo (incluye errores por línea: `lineas.0.cantidad`).
- Acceso a factura de otro tenant / editar-borrar de no-borrador: 404/403.

## Autorización
- Solo el tenant propietario opera sus facturas (TenantScope).
- `edit`/`update`/`destroy`: solo si `estado == borrador` → si no, 403.
- `pdf`: solo tenant propietario → cross-tenant 404.

## Nota — acción "ver"
No hay ruta `show` de solo lectura en esta feature. La acción "ver" del listado abre la factura en
`facturas.edit` (vista de creación en modo edición), ya que todas las facturas de esta feature son
borradores editables. Una vista de solo lectura se añadirá cuando existan facturas emitidas.
