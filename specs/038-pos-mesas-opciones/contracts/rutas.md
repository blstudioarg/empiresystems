# Phase 1 — Contrato de interfaz: rutas y respuestas

Interfaz HTTP que la feature expone. Todas las rutas viven en el grupo autenticado de
`routes/web.php` y **todas** llevan las dos capas de control de acceso descritas en
[research.md](../research.md) D6:

```
->middleware(['can:<permiso>', 'modulo.hosteleria'])
```

Regla transversal (memoria del proyecto, `project_tenant_route_binding`): **ningún modelo se resuelve
por binding implícito**. El controller recibe el identificador crudo y hace la búsqueda bajo el
`TenantScope`. Un binding implícito devolvería registros de otro tenant.

---

## Permisos nuevos

| Clave | Etiqueta | Módulo |
|---|---|---|
| `ver-pos-sala` | Sala | POS |
| `ver-pos-opciones` | Opciones de artículo | POS |

Alta en `App\Support\CatalogoPermisos::PERMISOS` + re-ejecutar `PermisosSeeder` en cada entorno.
Contabilidad de tests a actualizar: `CatalogoPermisosTest` (cuenta `claves()` y
`clavesUsuarioBase()`) y `RutasPermisosTest::mapaRutas()`.

---

## Sala

| Método | Ruta | Nombre | Devuelve |
|---|---|---|---|
| GET | `/pos/sala` | `pos.sala` | Vista, o JSON con el estado de la sala si `wantsJson` |

**JSON de la sala** (una sola consulta, sin N+1 — SC-001 exige < 2 s con 20 cuentas abiertas):

```json
{
  "zonas": [
    { "id": 1, "nombre": "Comedor", "suplemento": "0.00", "total_mesas": 8 }
  ],
  "mesas": [
    {
      "id": 12, "nombre": "Mesa 4", "zona_id": 1,
      "estado": "ocupada",
      "cuenta_id": 87,
      "pendiente": "34.50",
      "abierta_hace_min": 18,
      "olvidada": false,
      "abrir_url": "/pos/crear?cuenta=87"
    }
  ],
  "umbral_olvidada_min": 45
}
```

`pendiente` es el importe **por cobrar**, no el consumido (FR-028). `olvidada` lo calcula el
servidor comparando con el umbral configurado: la vista no hace aritmética de fechas.

---

## Cuentas

| Método | Ruta | Nombre | Notas |
|---|---|---|---|
| POST | `/pos/cuentas` | `pos.cuentas.store` | Abrir cuenta (con o sin mesa) |
| GET | `/pos/cuentas/{cuenta}` | `pos.cuentas.show` | Recuperar cuenta completa (JSON) |
| PUT | `/pos/cuentas/{cuenta}` | `pos.cuentas.update` | Guardar líneas y contexto |
| POST | `/pos/cuentas/{cuenta}/anular` | `pos.cuentas.anular` | FR-020, confirmación en UI |
| POST | `/pos/cuentas/{cuenta}/transferir` | `pos.cuentas.transferir` | FR-034 |
| POST | `/pos/cuentas/{cuenta}/unir` | `pos.cuentas.unir` | FR-035 |
| POST | `/pos/cuentas/{cuenta}/cobrar` | `pos.cuentas.cobrar` | Cobro total o parcial |

### `PUT /pos/cuentas/{cuenta}` — bloqueo optimista

La petición **debe** incluir la versión que el cliente cargó:

```json
{ "version": 3, "mesa_id": 12, "comensales": 2, "lineas": [ ... ] }
```

| Situación | Respuesta |
|---|---|
| Versión coincide | `200` con la cuenta actualizada y `version` incrementada |
| Versión obsoleta | **`409`** con `{ "message": "...", "cuenta": { ...estado actual... } }` |

El `409` es el único mecanismo que cumple FR-024. La UI muestra el aviso con `showToast` y recarga
la cuenta; **nunca** reintenta en silencio, porque eso es exactamente sobrescribir los cambios del
otro camarero.

### `POST /pos/cuentas/{cuenta}/cobrar`

Un solo endpoint para cobro total y parcial: el parcial es un total con menos unidades.

```json
{
  "version": 3,
  "lineas": [ { "cuenta_linea_id": 55, "cantidad": 1 } ],
  "pagos": [ { "metodo": "efectivo", "importe": "19.50" } ],
  "receptor": { "cliente_nif": "..." }
}
```

- `lineas` omitido o vacío ⇒ **cobro total** de todo lo pendiente.
- `lineas` presente ⇒ cobra solo esas unidades. Requiere `pos.cobro_dividido_activo`; si el módulo
  lo tiene apagado, `422`.

| Situación | Respuesta |
|---|---|
| Éxito | `201` con `{ id, numero_completo, cuenta_cerrada: bool, pendiente: "12.00" }` |
| Supera el tope de la simplificada | `422` (excepción `TicketFueraDeTopeException` ya existente) |
| Los pagos no cuadran con el total | `422` (`PagoTicketDescuadradoException` ya existente) |
| Se piden más unidades de las pendientes | `422` — FR-029 |
| Selección vacía en cobro parcial explícito | `422` — no se emite un ticket sin líneas |
| Versión obsoleta | `409` |

`cuenta_cerrada` le dice al cliente si volver a la Sala o seguir en la cuenta con lo pendiente.

**Toda la operación va en una transacción** que envuelve la llamada a `RegistroTicket`, la
actualización de `cantidad_saldada`, la creación de `pos_cobros`/`pos_cobro_lineas` y los
movimientos de stock de las opciones vinculadas. Si algo falla, no se emite documento ni se marca
nada como saldado (Principio III).

---

## Zonas y mesas (dentro de Configuración → POS)

CRUD estándar, patrón "alta/edición en modal + AJAX" de `docs/04-front-guidelines.md`. Solo
`index, store, update, destroy` — sin `create`/`edit`, que ese patrón no necesita.

| Método | Ruta | Nombre |
|---|---|---|
| GET/POST/PUT/DELETE | `/configuracion/pos/zonas[/{zona}]` | `configuracion.pos.zonas.*` |
| GET/POST/PUT/DELETE | `/configuracion/pos/mesas[/{mesa}]` | `configuracion.pos.mesas.*` |
| PUT | `/configuracion/pos` | `configuracion.pos.update` |

`destroy` responde **`422` con mensaje legible** cuando hay dependencias (zona con mesas, mesa con
cuenta abierta) en vez de dejar reventar la FK — patrón ya documentado para borrado con `RESTRICT`.

`PUT /configuracion/pos` responde `422` si se intenta apagar el módulo con cuentas abiertas
(FR-006), indicando cuántas hay.

**Estas rutas llevan `can:ver-configuracion`, no los permisos nuevos**: configurar el módulo es
tarea de administración, no de sala. Y **no** llevan el middleware de módulo activo — si lo
llevaran, apagar el módulo dejaría al administrador sin forma de volver a encenderlo.

---

## Opciones y grupos

| Método | Ruta | Nombre |
|---|---|---|
| GET/POST/PUT/DELETE | `/pos/opciones[/{opcion}]` | `pos.opciones.*` |
| GET/POST/PUT/DELETE | `/pos/opciones/grupos[/{grupo}]` | `pos.opcion-grupos.*` |
| GET | `/articulos/{articulo}/opciones` | `articulos.opciones.index` |
| PUT | `/articulos/{articulo}/opciones` | `articulos.opciones.sync` |

El par GET/PUT sobre un artículo es el sub-listado editable del patrón "Select dinámico con CRUD
inline": el listado de opciones asignadas no cabe en `data-*` de un botón, así que se pide aparte.

`PUT` recibe la lista completa asignada con su precio y orden:

```json
{ "grupos": [ { "grupo_id": 3, "orden": 0 } ],
  "opciones": [ { "opcion_id": 9, "precio": "1.50", "orden": 0 } ] }
```

`DELETE` de una opción o grupo en uso responde `422` con el número de artículos afectados (FR-047).

---

## Cambios en rutas existentes

| Ruta | Cambio |
|---|---|
| `GET /pos/crear` | Acepta `?cuenta={id}` opcional para precargar una cuenta abierta |
| `POST /pos` | **Sin cambios.** La venta directa sigue funcionando igual (FR-021) |

Que `POST /pos` no cambie es la garantía técnica de SC-012: con el módulo apagado, el camino de
venta directa es literalmente el mismo código de antes de esta feature.

---

## Catálogo del POS: opciones por artículo

`GET /pos/crear` añade a cada artículo del catálogo un `tiene_opciones` booleano, para que el front
sepa si abrir el modal de selección o añadir directo (FR-042/FR-043) **sin una consulta por toque**.
Con el módulo o la capacidad de opciones apagados, el campo siempre es `false`.
