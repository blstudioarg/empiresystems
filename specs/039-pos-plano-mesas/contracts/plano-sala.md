# Contrato: Guardado del plano de una zona

## `GET /pos/sala` (ampliación del contrato JSON existente, `SalaController::estado()`)

Cada elemento de `mesas` gana los campos del plano; cada elemento de `zonas` gana `version`.

```json
{
  "zonas": [
    { "id": 1, "nombre": "Comedor", "suplemento": "0.00", "total_mesas": 6, "version": 3 }
  ],
  "mesas": [
    {
      "id": 10, "nombre": "Mesa 3", "zona_id": 1, "estado": "libre",
      "cuenta_id": null, "pendiente": "0.00", "abierta_hace_min": null, "olvidada": false,
      "abrir_url": "https://.../pos/crear",
      "fila": 1, "columna": 2, "forma": "redonda", "tamano": "mediana"
    }
  ],
  "umbral_olvidada_min": 45
}
```

## `PUT /pos/sala/zonas/{zona}/plano`

Nuevo endpoint, `PlanoSalaController::update`. Middleware: `can:ver-configuracion`,
`modulo.hosteleria` (mismo grupo de acceso que el resto de POS hostelería).

**Request**:

```json
{
  "version": 3,
  "mesas": [
    { "id": 10, "fila": 1, "columna": 3, "forma": "redonda", "tamano": "mediana" },
    { "id": 11, "fila": 1, "columna": 2, "forma": "cuadrada", "tamano": "pequena" }
  ]
}
```

**Respuestas**:

- `200 OK` — `{ "message": "Plano guardado.", "version": 4 }` (el nuevo `version` tras el
  incremento, para que el cliente actualice su estado local sin recargar).
- `409 Conflict` — `{ "message": "El plano se modificó desde otro dispositivo. Recárgalo antes de guardar." }`
  cuando `version` recibido ≠ `version` actual de la zona (D5).
- `422 Unprocessable Entity` — payload inconsistente: `mesa_id` que no pertenece a la zona/tenant,
  `fila`/`columna` fuera de rango (0-5 / 0-7), o dos mesas del payload apuntando a la misma celda.
  El mensaje identifica cuál mesa/celda causó el rechazo. Ninguna mesa se persiste si hay un solo
  error (operación atómica, ver data-model.md).
- `404 Not Found` — la zona no existe o no pertenece al tenant activo (resuelta manualmente, no
  por binding implícito).

## Nota sobre el reacomodo por colisión (D3)

El reacomodo (mover la mesa desplazada a la celda libre más cercana) ocurre **solo en el
cliente**, como previsualización mientras el usuario sigue en modo edición: el payload que llega a
este endpoint ya trae la disposición final resuelta (sin colisiones) porque el cliente no permite
generar/enviar un estado con dos mesas en la misma celda. El servidor igual valida la ausencia de
colisión (paso 4 de la validación en data-model.md) como defensa en profundidad, no como quien
resuelve el reacomodo.
