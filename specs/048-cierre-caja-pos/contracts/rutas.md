# Contratos HTTP — 048 Cierre de caja

Todas en el grupo `tenant.context + auth` de `routes/web.php`, bajo `->middleware('can:ver-pos-caja')`,
salvo la excepción indicada. JSON con `Accept: application/json`; acciones sin form serializado
mandan `X-CSRF-TOKEN` (docs/04 "CSRF en peticiones AJAX sin formulario"). Importes como string
`"123.45"` en JSON.

## GET `/pos/caja` — `pos.caja`

- HTML: la pantalla de caja (estado cerrada / abierta).
- JSON (`wantsJson`): estado actual.

```json
// caja abierta
{
  "abierta": true,
  "sesion": {
    "id": 12, "fondo_inicial": "150.00",
    "abierta_por": "Ana López", "abierta_at": "2026-10-03T08:02:11+02:00",
    "abierta_dia_anterior": false
  },
  "en_vivo": {
    "num_tickets": 34, "total_vendido": "612.40", "ticket_medio": "18.01",
    "por_metodo": [{"metodo": "efectivo", "label": "Efectivo", "importe": "240.10", "tickets": 15}, …],
    "movimientos": [{"tipo": "salida", "importe": "30.00", "motivo": "Pago proveedor pan", "usuario": "Ana López", "at": "…"}]
  }
}
// caja cerrada
{ "abierta": false, "ultimo_cierre": { "id": 11, "cerrada_at": "…", "total_facturado": "540.00", "descuadre": "0.00", "informe_url": "…" } | null }
```

**Nunca** incluye `efectivo_esperado` mientras la sesión está abierta (research D6). Las fechas se
emiten ya convertidas a la zona del tenant (`enZonaTenant()`).

## POST `/pos/caja/abrir` — `pos.caja.abrir`

Body: `{ "fondo_inicial": "150.00" }` **o** `{ "conteo": {"5000": 2, "1000": 5} }`.
Si llega `conteo`, el servidor calcula `fondo_inicial` desde él e ignora cualquier total enviado.

- 201 `{ message: "Caja abierta.", sesion: {...} }`
- 409 `{ message: "Ya hay una caja abierta.", codigo: "caja_ya_abierta", sesion: {...} }`
- 422 validación (`fondo_inicial` ≥ 0 y ≤ 99 999,99; cantidades enteras ≥ 0; claves del catálogo
  `DenominacionesEuro`)

**Excepción de permiso**: esta ruta admite `can:ver-pos-caja` **o** `can:ver-pos-crear`. Un cajero
que vende tiene que poder abrir la caja desde el TPV (FR-020, apertura inline). Se resuelve con un
`Gate::define('abrir-caja', fn (User $u) => $u->can('ver-pos-caja') || $u->can('ver-pos-crear'))`
en `AppServiceProvider` (junto al `Gate::before` del super admin) y la ruta lleva
`->middleware('can:abrir-caja')`. `RutasPermisosTest` la cubre aparte (no es un permiso del
catálogo).

## POST `/pos/caja/movimientos` — `pos.caja.movimientos.store`

Body: `{ "tipo": "entrada"|"salida", "importe": "30.00", "motivo": "Pago proveedor pan" }`

- 201 `{ message: "Salida registrada.", movimiento: {...}, en_vivo: {...} }`
- 409 `{ message: "No hay ninguna caja abierta.", codigo: "caja_cerrada" }`
- 422 validación (`importe` > 0, `motivo` requerido ≤ 160)

## POST `/pos/caja/cerrar` — `pos.caja.cerrar`

Body:

```json
{ "sesion_id": 12, "conteo": {"5000": 3, "2000": 1, "100": 7}, "observacion": null }
// o bien
{ "sesion_id": 12, "efectivo_contado": "148.00", "observacion": "Se pagó un café a un proveedor sin registrar" }
```

`sesion_id` identifica la sesión que el usuario está contando: si ya no es la abierta (otro
dispositivo la cerró), no se cierra nada.

- 200 cierre hecho:
  ```json
  { "message": "Caja cerrada.", "sesion_id": 12,
    "resultado": { "efectivo_esperado": "150.00", "efectivo_contado": "148.00", "descuadre": "-2.00", "estado": "falta" },
    "informe": { …mismo contenido que resumen + cifras… },
    "informe_url_ticket": "…?formato=ticket", "informe_url_a4": "…?formato=a4" }
  ```
- 422 `{ codigo: "observacion_requerida", message: "Hay una diferencia de 7,40 €. Escribe qué pasó para poder cerrar.", resultado: {...} }`
  — no cierra; devuelve el resultado provisional para que la pantalla lo revele y pida la observación.
- 409 `{ codigo: "caja_ya_cerrada", message: "Esta caja ya la cerró Ana López a las 22:14.", informe_url_ticket: "…" }`
- 409 `{ codigo: "caja_cerrada", message: "No hay ninguna caja abierta." }`
- 422 validación (falta `conteo` y `efectivo_contado`). Si llegan los dos, manda el conteo: el total se recalcula desde él, igual que en la apertura

`estado`: `cuadra` (descuadre = 0), `sobra` (> 0), `falta` (< 0).

## GET `/pos/caja/cierres` — `pos.caja.cierres`

- HTML: histórico (DataTable).
- JSON: `{ data: [ { id, abierta_at, cerrada_at, abierta_por, cerrada_por, num_tickets, total_facturado, efectivo_esperado, efectivo_contado, descuadre, estado, informe_url_ticket, informe_url_a4 } ] }`
  Solo sesiones **cerradas**, más recientes primero. Client-side (decenas/centenares de filas por
  tenant al año: un cierre por día).

## GET `/pos/caja/sesiones/{sesion}/informe?formato=ticket|a4` — `pos.caja.informe`

PDF inline (`stream`). Resolución manual `CajaSesion::where('estado','cerrada')->findOrFail($sesion)`
bajo `TenantScope`. 404 para sesiones abiertas o de otro tenant.

## Cambios en endpoints existentes

| Endpoint | Cambio |
|----------|--------|
| `POST /pos` (`pos.store`) | Si no hay caja abierta → **409** `{ codigo: "caja_cerrada", message: "La caja está cerrada. Ábrela para poder cobrar." }`. Resto intacto. |
| `POST /pos/cuentas/{cuenta}/cobrar` | Igual que arriba. La cuenta no se modifica. |
| `GET /pos/crear` (`pos.create`) | La vista recibe `cajaAbierta` (bool) y `puedeAbrirCaja`; el TPV muestra el estado de caja y ofrece abrirla. |
| `PUT /configuracion/pos` | Acepta `caja_umbral_descuadre` (numeric, 0–9 999,99). |
