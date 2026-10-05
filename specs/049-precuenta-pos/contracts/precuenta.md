# Contrato: endpoints de precuenta

Ambas rutas viven en el grupo existente `['can:ver-pos-sala', 'modulo.hosteleria']` de
`routes/web.php` (research D8). Resolución manual del modelo bajo `TenantScope` (`findOrFail` en el
cuerpo del controlador, no binding implícito — memoria `project_tenant_route_binding`).

## `POST /pos/cuentas/{cuenta}/precuentas` → `pos.cuentas.precuentas.store`

Emite una precuenta de la cuenta entera pendiente.

**Request** (JSON, header `X-CSRF-TOKEN`):

```json
{ "version": 7 }
```

`version` obligatorio, entero: la versión de la cuenta que el TPV acaba de guardar.

**201 Created**:

```json
{
  "precuenta": {
    "id": 12,
    "total": "48.40",
    "reimpresion": false,
    "emitida_en": "2026-10-05T21:14:03+02:00",
    "pdf_url": "/pos/precuentas/12/pdf"
  },
  "cuenta": { "...": "payload de la cuenta, con precuenta.estado = 'vigente'" }
}
```

**Errores**:

| Código | Cuándo | Body |
|---|---|---|
| 404 | la cuenta no existe o es de otro tenant | estándar |
| 409 | `version` no coincide con la de la cuenta (otro dispositivo la modificó) | igual que `update`: `message` + `cuenta` actual |
| 422 | la cuenta no está abierta | `{ "message": "Esta cuenta ya no está abierta." }` |
| 422 | pendiente = 0 | `{ "message": "No hay nada pendiente para la precuenta." }` |
| 422 | `version` ausente o no entera | validación estándar |

**Efectos**: inserta una fila en `pos_precuentas`. **No** crea facturas, no toca numeración, ni
Verifactu, ni stock, ni `pos_cobros`, ni `cantidad_saldada`, ni `pos_cuentas.version` (FR-009).

## `GET /pos/precuentas/{precuenta}/pdf` → `pos.precuentas.pdf`

Devuelve el PDF de 80 mm (`application/pdf`, `inline`) generado **desde la foto guardada** en la
fila (research D3), nunca desde la cuenta viva. 404 si es de otro tenant.

## Cambios en endpoints existentes

- `GET /pos/cuentas/{id}`, `PUT /pos/cuentas/{id}`, `POST …/transferir`, `POST …/unir`: el payload de
  la cuenta incluye el bloque `precuenta` con `estado`, `ultima` y `total_actual` (ver data-model).
- `GET /pos/sala` (JSON): cada mesa incluye `precuenta_pedida` y `precuenta_hace_min`; `olvidada` es
  `false` cuando `precuenta_pedida` es `true`.
- `POST /pos/cuentas/{id}/cobrar`: **sin cambios** de contrato ni de comportamiento.
