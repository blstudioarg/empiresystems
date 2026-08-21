# Contrato: la Sala y sus dos vistas

**Feature**: 041-plano-sala-servicio

Esta feature **no añade ni modifica ningún endpoint**. El contrato que documenta aquí es doble: el
del payload que la vista consume (que pasa a tener un segundo consumidor y por eso conviene fijarlo)
y el de la interfaz JS compartida entre el editor y la vista de servicio.

---

## 1. Payload de estado de la Sala (existente, sin cambios)

```
GET /pos/sala        (Accept: application/json)
```

Servido por `App\Http\Controllers\Pos\SalaController`. Aislamiento por `TenantScope` (Principio I),
sin cambios.

```jsonc
{
  "zonas": [
    { "id": 1, "nombre": "Comedor", "suplemento": "0.00", "total_mesas": 8, "version": 3 }
  ],
  "mesas": [
    {
      "id": 42,
      "nombre": "Mesa 4",
      "zona_id": 1,
      "estado": "ocupada",           // "libre" | "ocupada"
      "cuenta_id": 17,
      "pendiente": "23.50",          // POR COBRAR, no lo consumido
      "abierta_hace_min": 12,
      "olvidada": false,             // lo decide el SERVIDOR (umbral del tenant)
      "abrir_url": "/pos/crear?cuenta=17",
      "fila": 1,                     // null = sin sitio en la rejilla
      "columna": 3,                  // null = sin sitio en la rejilla
      "forma": "cuadrada",           // "redonda" | "cuadrada" | "barra"
      "ancho_celdas": 2,
      "alto_celdas": 1
    }
  ],
  "umbral_olvidada_min": 90
}
```

### Campos de los que depende la vista de plano

Los diez campos marcados arriba son **contrato**: la vista de plano deja de funcionar si alguno
desaparece o cambia de significado. Un test de backend los fija (`SalaPayloadPlanoTest`), porque hoy
solo los protege el uso que hace la vista de tarjetas, y esa no usa la geometría.

| Campo | Consumido por | Si falta |
|-------|---------------|----------|
| `fila`, `columna` | Posición en el lienzo | La mesa cae en la franja "sin sitio" aunque tuviera sitio |
| `ancho_celdas`, `alto_celdas` | Tamaño dibujado | La mesa se dibuja de una celda |
| `forma` | Aspecto y sillas | Aspecto por defecto |
| `estado`, `olvidada` | Color del borde | El plano mentiría sobre el estado de la sala |
| `pendiente`, `abierta_hace_min` | Texto de la mesa ocupada | Mesa ocupada sin importe |
| `abrir_url` | Destino al tocar | La mesa no lleva a ninguna parte |

---

## 2. `window.PosPlanoDibujo` — módulo compartido de dibujo (nuevo)

Extraído del editor (D1 de [research.md](../research.md)). **Funciones puras**: no tocan el DOM
salvo para producir cadenas de HTML, no guardan estado y no conocen ni el modo edición ni el de
servicio.

| Miembro | Firma | Devuelve |
|---------|-------|----------|
| `CELL`, `GAP`, `STEP`, `COLS`, `ROWS` | constantes | Geometría de la rejilla (96, 12, 108, 8, 6) |
| `dimensiones(ancho, alto)` | celdas → px | `{ width, height }`, con el encaje `n * STEP - GAP` |
| `aPx(rect)` | `{fila, columna, ancho, alto}` | `{ left, top, width, height }` |
| `aCeldas(pos, size)` | px → celdas | `{ fila, columna, ancho, alto }` |
| `sillasParaMesa(forma, ancho, alto)` | — | Lista de puntos `[x%, y%]` del perímetro |
| `claseEstado(mesa)` | — | `'libre' \| 'ocupada' \| 'olvidada'` — **una sola** implementación de la regla, compartida por tarjeta y plano |
| `mesaHtml(mesa, opciones)` | — | HTML de una mesa. `opciones.modo` es `'edicion'` o `'servicio'` |

**Invariante del módulo**: `mesaHtml` produce el **mismo contorno** (posición, tamaño, forma,
sillas, color de estado) en los dos modos. Lo único que cambia con `opciones.modo` es el contenido
interior y los controles: el modo edición añade el asa de arrastre; el modo servicio añade el
importe y el tiempo, y **nunca** añade asas.

**Por qué importa**: si el contorno se calculara por separado en cada modo, el plano que ve el
camarero dejaría de coincidir con el que colocó el encargado. Ese es exactamente el fallo que la
extracción evita.

---

## 3. Contrato de comportamiento de la vista de servicio

| Situación | Comportamiento exigido | Requisito |
|-----------|------------------------|-----------|
| Toque en mesa **libre** | Navega a `abrir_url` con `?mesa=<id>` — idéntico a su tarjeta | FR-013 |
| Toque en mesa **ocupada** | Navega a `abrir_url` (su cuenta) — idéntico a su tarjeta | FR-014 |
| Arrastre sobre una mesa o su borde | No ocurre nada | FR-015, SC-007 |
| Zona activa = "Todas" | Se resuelve a una zona concreta, reflejada en el filtro | FR-010 |
| Mesa con `fila`/`columna` a `null` | Aparece en la franja bajo el lienzo, como tarjeta | FR-011 |
| Zona sin mesas dibujables | Mensaje explicativo, no lienzo vacío mudo | FR-012 |
| Evento `pos-sala:actualizado` | El plano se repinta con el estado nuevo | FR-016 |
| Evento `pos-sala:zona-cambiada` | El plano carga la zona nueva | FR-010 |
| Usuario sin `ver-configuracion` | Ve y usa el plano; **no** ve "Editar plano" | FR-004, FR-020 |

**Peticiones de escritura emitidas por esta vista: ninguna** (I3 de [data-model.md](../data-model.md)).
