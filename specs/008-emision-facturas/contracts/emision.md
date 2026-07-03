# Contract — Acción de emisión de factura

Interfaz HTTP que expone esta feature. Ruta protegida por auth + contexto de tenant (igual que el
resto de `facturas.*`). Todas las operaciones ocurren bajo el global scope de tenant.

## POST `/facturas/{factura}/emitir`

**Nombre de ruta**: `facturas.emitir`

**Autorización**: usuario autenticado del tenant propietario de la factura (resuelto bajo scope de
tenant; una factura de otro tenant → 404).

**Precondición**: `factura.estado == borrador` y validaciones de dominio (D4) superadas.

**Comportamiento**: valida → asigna número correlativo del año en curso (bajo lock) → fija
`fecha_expedicion = hoy` y recalcula vencimiento → `estado = emitida` → crea evento `emitida`. Todo
en una transacción; los importes NO se recalculan.

### Respuestas

| Caso | HTTP (web) | HTTP (JSON) | Efecto |
|------|-----------|-------------|--------|
| Éxito | 302 → `facturas.index` con flash `success` | 200 `{ message, numero_completo }` | factura emitida y numerada |
| Factura no borrador (ya emitida / re-emisión) | 302 back con flash `error` | 422 `{ message }` | sin cambios, sin consumir número |
| Sin líneas / base 0 | 302 back con flash `error` | 422 `{ message }` | sin cambios |
| Datos fiscales del receptor incompletos | 302 back con flash `error` | 422 `{ message }` | sin cambios |
| Factura de otro tenant / inexistente | 404 | 404 | — |

Notas:
- Notificaciones vía toastr (flash → `partials/flash-toastr`), nunca alerts ad-hoc (CLAUDE.md).
- Idempotencia práctica: reintentar emitir una factura ya emitida devuelve 422 y conserva su número.

## Cambios en contratos existentes (`facturas.index` JSON)

Cada fila del listado añade indicadores para que el JS renderice acciones según estado:

```jsonc
{
  "id": 12,
  "identificador": "F-2026-0001",   // numero_completo ?? "Borrador"
  "estado": "emitida",              // "borrador" | "emitida"
  "es_borrador": false,             // NUEVO: gobierna qué acciones se muestran
  "emitir_url": null,               // NUEVO: presente solo si es_borrador
  "edit_url": null,                 // null si !es_borrador
  "delete_url": null,               // null si !es_borrador
  "pdf_url": "/facturas/12/pdf"     // siempre
}
```

Reglas de UI (FR-009, FR-012):
- `es_borrador == true` → mostrar **Editar**, **Eliminar**, **Emitir**.
- `es_borrador == false` → mostrar solo **Ver/PDF**; sin editar/eliminar/emitir.
- `identificador` muestra `numero_completo` en emitidas y `"Borrador"` en borradores.

## Guardas de inmutabilidad (rutas existentes, sin cambio de firma)

- `PUT/PATCH /facturas/{factura}` (`update`) y `DELETE /facturas/{factura}` (`destroy`): ya abortan
  403 si `estado != borrador` (reforzado por tests de inmutabilidad).
- `GET /facturas/{factura}/editar` (`edit`): idem 403 en no-borrador.
