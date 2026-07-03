# Contract: Emitir rectificativa

Reutiliza la acción de emisión de la feature 008 (`POST /facturas/{factura}/emitir`), extendida para
rectificativas. No se crea un endpoint nuevo.

## Endpoint

`POST /facturas/{factura}/emitir` — nombre de ruta `facturas.emitir`

- `{factura}`: id de la **rectificativa en borrador** a emitir.

## Precondiciones

- La factura está en estado `borrador` y es `es_rectificativa = true`.
- Tiene los datos fiscales mínimos del receptor (NIF, nombre/razón social, domicilio).
- **No** se exige `base_total > 0` (el delta por diferencias puede ser 0 o negativo).

## Comportamiento (transacción atómica)

1. `EmisorFacturas::validar()` — reglas anteriores (rama rectificativa: sin exigir importe > 0).
2. `NumeradorFacturas::siguienteNumero(serieRectificativa, hoy)` — correlativo de la **serie
   rectificativa**, reinicio anual, bajo `lockForUpdate`.
3. Congela `numero`, `numero_completo`, `fecha_expedicion = hoy`, `fecha_vencimiento`; `estado = emitida`.
4. Marca la factura original (`factura_rectificada`) como `estado = rectificada`.
5. Registra evento append-only `emitida` en la rectificativa (y, opcional, `rectificada` en la original).

Si cualquier paso falla, la transacción revierte: ni número huérfano ni original marcada sin
rectificativa válida.

## Respuestas

- **HTML**: redirect a `facturas.index` con flash `success` ("Factura rectificativa emitida...").
- **JSON**: `200` `{ "message": "...", "numero_completo": "R-2026-0001" }`.

## Errores

| Caso | HTTP (JSON) | HTML |
|------|-------------|------|
| No está en borrador / ya emitida | `422` `{ "message": "Solo se pueden emitir facturas en borrador." }` | redirect back + flash `error` |
| Faltan datos fiscales del receptor | `422` con el motivo | redirect back + flash `error` |
| Factura de otro tenant / inexistente | `404` | `404` |

## Invariantes verificables

- El número sale de la serie rectificativa (prefijo "R"), nunca de la ordinaria.
- Emitir la rectificativa no altera el `proximo_numero` de la serie ordinaria.
- Tras emitir, la original queda `rectificada` y vinculada; no puede volver a rectificarse.
