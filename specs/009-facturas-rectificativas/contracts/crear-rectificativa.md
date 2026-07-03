# Contract: Crear rectificativa

Acción que genera una factura rectificativa en **borrador** a partir de una factura ordinaria
**emitida**. No asigna número fiscal (eso ocurre al emitir).

## Endpoint

`POST /facturas/{factura}/rectificar`  — nombre de ruta `facturas.rectificar`

- `{factura}`: id de la factura **original** a rectificar. Se resuelve manualmente en el controller
  bajo el scope del tenant activo (no binding implícito — ver memoria `project_tenant_route_binding`).
- Autenticación: sesión de usuario del tenant (mismo middleware que el resto de `facturas.*`).

## Request (body)

| Campo | Tipo | Requerido | Reglas |
|-------|------|-----------|--------|
| `tipo_rectificacion` | string | sí | `in:sustitucion,diferencias` |
| `motivo_rectificacion` | string | sí | no vacío; máx. razonable (p. ej. 1000) |

Validado por `StoreRectificativaRequest`.

## Precondiciones

- La factura original existe y pertenece al tenant activo.
- La original está en estado `emitida` (no `borrador`, `anulada` ni `rectificada`).

## Comportamiento

1. Valida precondiciones (estado + unicidad). Si fallan → rechazo (ver Errores).
2. `GeneradorRectificativa::generar()` crea una factura `borrador` con `tipo = rectificativa`,
   `es_rectificativa = true`, `factura_rectificada_id = original.id`, `motivo`, `modalidad`, snapshot
   del receptor + `regimen_impositivo` + `aplica_recargo` copiados de la original, `serie_id` = serie
   rectificativa del tenant, y las líneas de la original copiadas como punto de partida.
3. Redirige al editor del borrador creado para ajustar líneas (`facturas.edit`).

## Respuestas

- **HTML**: redirect a `facturas.edit` de la rectificativa con flash `success`.
- **JSON** (`Accept: application/json`): `201` `{ "message": "...", "id": <rectificativa_id> }`.

## Errores

| Caso | HTTP (JSON) | HTML |
|------|-------------|------|
| Original no emitida | `422` `{ "message": "Solo se pueden rectificar facturas emitidas." }` | redirect back + flash `error` |
| Original ya rectificada | `422` `{ "message": "Esta factura ya fue rectificada." }` | redirect back + flash `error` |
| Validación de body | `422` (errores de validación estándar) | redirect back + errores |
| Original de otro tenant / inexistente | `404` | `404` |

Notificaciones UI siempre vía flash + `partials/flash-toastr` / `window.showToast` (CLAUDE.md), nunca
alertas ad-hoc.
