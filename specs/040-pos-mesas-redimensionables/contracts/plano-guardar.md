# Contrato: guardado del plano de una zona (actualizado)

**Feature**: 040-pos-mesas-redimensionables

Actualiza el contrato definido en `specs/039-pos-plano-mesas/contracts/plano-sala.md`. La ruta, el
método, los permisos y el bloqueo optimista **no cambian**: solo cambia la forma de cada mesa dentro
del payload.

```
PUT|PATCH /pos/sala/zonas/{zona}/plano
```

- **Permisos**: los ya existentes (`ver-pos-sala` + el de configuración que exige la ruta). Sin
  permisos nuevos.
- **Aislamiento**: la zona se resuelve manualmente bajo el tenant activo (`PosZona::query()->findOrFail()`),
  nunca por route-model binding implícito.

## Request

```jsonc
{
  "version": 7,                 // bloqueo optimista de la zona; sin cambios
  "mesas": [
    {
      "id": 42,
      "fila": 1,                // celda de origen (fila superior del rectángulo)
      "columna": 3,             // celda de origen (columna izquierda del rectángulo)
      "ancho_celdas": 2,        // NUEVO
      "alto_celdas": 1,         // NUEVO
      "forma": "rectangular"    // solo aspecto y reparto de sillas
      // "tamano" ELIMINADO
    }
  ]
}
```

### Reglas de validación

| Campo | Regla |
|-------|-------|
| `version` | `required, integer` |
| `mesas` | `required, array` |
| `mesas.*.id` | `required, integer` — debe pertenecer a la zona |
| `mesas.*.fila` | `required, integer, min:0` |
| `mesas.*.columna` | `required, integer, min:0` |
| `mesas.*.ancho_celdas` | `required, integer, min:1, max:8` (`PosPlanoCeldas::COLUMNAS`) |
| `mesas.*.alto_celdas` | `required, integer, min:1, max:6` (`PosPlanoCeldas::FILAS`) |
| `mesas.*.forma` | `required, string, in:redonda,cuadrada,rectangular,barra` |

Tras la validación de formato, `PosPlanoReacomodo::validar()` comprueba los invariantes geométricos
G1/G2/G3 de [data-model.md](../data-model.md) y lanza `ValidationException` si alguno falla.

**No se acepta el payload antiguo**: un `tamano` enviado se ignora, y la ausencia de
`ancho_celdas`/`alto_celdas` produce un 422. Es una interfaz interna de una sola pantalla, con
cliente y servidor desplegados a la vez (D10 de research.md).

## Responses

| Código | Cuerpo | Cuándo |
|--------|--------|--------|
| `200` | `{ "message": "Plano guardado.", "version": 8 }` | Todo válido. La escritura ocurre en una transacción; la versión de la zona se incrementa. |
| `409` | `{ "message": "El plano se modificó desde otro dispositivo. Recárgalo antes de guardar." }` | `version` no coincide con la de la zona. Sin cambios respecto a hoy. |
| `422` | `{ "message": "...", "errors": { "mesas": ["..."] } }` | Formato inválido, mesa ajena a la zona, o violación de G1/G2/G3. **Ningún cambio se aplica** (FR-013). |

### Mensajes de error geométricos

| Situación | Mensaje |
|-----------|---------|
| G1 — ocupación menor que una celda | `La mesa {nombre} debe ocupar al menos una celda.` |
| G2 — se sale de la rejilla | `La mesa {nombre} se sale de la rejilla de la zona.` |
| G3 — solapamiento | `Las mesas {a} y {b} se solapan en el plano.` |

Los mensajes nombran la mesa (no solo coordenadas) porque quien los vería es el encargado editando
el plano, no un desarrollador leyendo un log.

## Datos que la vista recibe al cargar la Sala

`SalaController` incorpora `ancho_celdas` y `alto_celdas` a cada mesa del payload de
`window.posSalaData`, y deja de incluir `tamano`. Lo consume el editor de plano, que es la única
vista que dibuja el lienzo (FR-015). La rejilla de tarjetas de la Sala en modo servicio no usa estos
campos y no cambia.
