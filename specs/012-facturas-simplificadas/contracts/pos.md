# Contratos HTTP — Módulo POS (facturas simplificadas)

Rutas nuevas bajo el grupo autenticado con `tenant.context` (mismo grupo que `facturas.*`). Prefijo
`/pos`, nombres `pos.*`. Todo opera bajo el tenant activo (Principio I).

## GET `/pos` → `pos.index`
Listado de facturas simplificadas del tenant.
- **HTML** (navegador): devuelve la vista `pos/index.blade.php` (DataTable).
- **JSON** (`Accept: application/json` / `wantsJson`): estructura análoga a `facturas.index`:
  ```json
  {
    "data": [
      {
        "id": 1,
        "identificador": "S-2026-0001",
        "estado": "emitida",
        "cualificada": true,
        "receptor": "María López" ,
        "fecha_expedicion": "2026-07-04",
        "total": "121.00",
        "pdf_ticket_url": "/pos/1/pdf?formato=ticket",
        "pdf_a4_url": "/pos/1/pdf?formato=a4"
      }
    ],
    "totales": { "total": 1, "importe_total": "121.00" }
  }
  ```
- **Filtro**: sólo `tipo = simplificada` del tenant. No incluye ordinarias ni rectificativas.

## GET `/pos/crear` → `pos.create`
Vista "Crear ticket" (tablet-first). Provee catálogo de artículos e info necesaria para la captura
rápida (análogo a `facturas.create` pero reducido: sin selección obligatoria de cliente ni cuenta
bancaria). Receptor opcional.

## POST `/pos` → `pos.store`
Crea y **emite** un ticket simplificado en una operación atómica.
- **Request** (`StoreTicketRequest`):
  ```json
  {
    "lineas": [
      { "articulo_id": 5, "concepto": "Café", "unidad": "ud",
        "cantidad": 2, "precio_unitario": 1.50,
        "descuento_porcentaje": null, "tipo_impositivo": 10 }
    ],
    "receptor": {
      "cliente_id": null,
      "cliente_nif": null, "cliente_nombre": null, "cliente_razon_social": null,
      "cliente_direccion": null, "cliente_cp": null, "cliente_ciudad": null,
      "cliente_provincia": null, "cliente_pais": "ES"
    },
    "notas": null
  }
  ```
  - `lineas`: requerido, ≥1, cada línea con `cantidad > 0`, `precio_unitario ≥ 0`, `tipo_impositivo`
    válido para el régimen del tenant.
  - `receptor`: **opcional** (simplificada simple lo omite). Si se informa para cualificada, exigir al
    menos `cliente_nif` + domicilio (`cliente_direccion`).
- **Respuesta OK** (201, JSON): `{ "message": "...", "id": 1, "numero_completo": "S-2026-0001" }`.
  En HTML: redirect a `pos.index` con flash `success`.
- **Errores**:
  - `422` validación de request (líneas vacías, receptor incompleto en cualificada).
  - `422` **tope superado** (`TicketFueraDeTopeException`): mensaje claro indicando que ese importe
    requiere una factura ordinaria. No se crea ni emite nada.
- **Garantías**: importes y total calculados server-side; número asignado con bloqueo por serie/año;
  evento `emitida` append-only; ticket resultante inmutable.

## GET `/pos/{factura}/pdf` → `pos.pdf`
Devuelve el PDF del ticket.
- **Query** `formato`: `ticket` (80 mm, default) | `a4`.
- `ticket` → plantilla `facturas/ticket-80mm.blade.php`, papel 80 mm de ancho, alto variable.
- `a4` → plantilla A4 (reutiliza/variante de `facturas/pdf.blade.php`).
- Contenido: mínimo de la simplificada; si cualificada, además receptor + desglose de cuota.
- Sólo tickets del tenant activo (implícito por scope; resolver el modelo en el cuerpo del controlador,
  no por binding implícito — ver `[[project_tenant_route_binding]]`).

## Cambio en contrato existente
### GET `/facturas` → `facturas.index`
- La respuesta JSON y los totales **excluyen** `tipo = simplificada`. El resto del contrato no cambia.
