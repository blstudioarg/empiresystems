# Data Model: Vista de plano en la Sala en modo servicio

**Feature**: 041-plano-sala-servicio | **Fecha**: 2026-08-20

## Resumen: no hay cambios de esquema

**Ninguna tabla se crea, modifica ni elimina.** No hay migración en esta feature.

Es una feature de presentación: pone a trabajar datos que ya existen y ya viajan a la vista. Este
documento existe para dejar por escrito **de qué depende** la vista nueva, de modo que un cambio
futuro en esas piezas sepa que rompería el plano de servicio.

---

## Datos que la vista consume (ya existentes)

### `pos_mesas` — geometría y ubicación

| Campo | Origen | Papel en esta feature |
|-------|--------|----------------------|
| `fila`, `columna` | feature 039 | Celda de origen del rectángulo. `null` = mesa sin sitio en la rejilla → va a la franja bajo el lienzo (FR-011). |
| `ancho_celdas`, `alto_celdas` | feature 040 | Ocupación en celdas. Determinan el tamaño dibujado. |
| `forma` | 039, reducida en 040 | `redonda` / `cuadrada` / `barra`. Decide aspecto y reparto de sillas, no tamaño. |
| `nombre` | feature 038 | Etiqueta de la mesa en el plano. |
| `zona_id` | feature 038 | Ámbito del plano: un lienzo es de una zona (FR-010). |

### Estado de servicio — **derivado, nunca almacenado**

| Dato | Cómo se obtiene | Invariante que esta feature hereda |
|------|-----------------|-----------------------------------|
| `estado` (`libre` / `ocupada`) | Existencia de una `pos_cuentas` abierta con ese `mesa_id` | **El estado de una mesa no se almacena.** Sigue siendo imposible que la sala mienta. |
| `olvidada` | El servidor compara los minutos abiertos con `ConfigPos::mesaOlvidadaMin` | **La vista nunca hace aritmética de fechas** (FR-008). Si lo hiciera, dependería del reloj de cada tablet y dos dispositivos mostrarían cosas distintas para la misma mesa. |
| `pendiente` | Calculado en servidor desde las líneas y los cobros parciales | Es el importe **por cobrar**, no el consumido. El plano lo muestra tal cual; no lo recalcula (Principio III). |
| `abierta_hace_min` | Calculado en servidor | Se muestra en formato compacto (D4), pero el número viene dado. |
| `abrir_url` | `route('pos.create', …)` en servidor | Destino al tocar la mesa. |

**Consecuencia de diseño**: la vista de plano y la de tarjetas consumen **exactamente el mismo
objeto de mesa**. Es lo que garantiza que las dos no puedan contradecirse (FR-006, SC-006).

---

## Único dato nuevo: la preferencia de vista

No es un dato de negocio y **no vive en la base de datos**.

| Aspecto | Decisión |
|---------|----------|
| Dónde | `localStorage` del navegador (D2 de [research.md](./research.md)) |
| Clave | `pos-sala-vista:<userId>` — el id del usuario en la clave permite que varias personas compartan la misma tablet sin pisarse la preferencia (FR-003) |
| Valores | `tarjetas` (por defecto) · `plano` |
| Si falta o es inválido | Se asume `tarjetas`: la vista que existe hoy, para que la feature no cambie lo que ve nadie sin pedirlo |
| Retención / RGPD | La **clave** incluye el id del usuario, así que no es correcto llamarla "no identificable". Aun así no constituye un tratamiento nuevo bajo el Principio II: el valor es una elección de interfaz, se guarda **en el dispositivo del propio usuario**, no viaja al servidor, no se registra en ningún log y se borra al limpiar el navegador. No necesita plazo de retención ni purga; el id ya es conocido por el propio navegador que tiene la sesión abierta. |

---

## Invariantes que esta feature NO puede romper

Están escritos aquí porque son la forma de detectar en revisión que algo se ha torcido:

- **I1** — Una mesa activa de la zona mostrada está **siempre** alcanzable en la vista de plano: o
  dibujada en el lienzo, o en la franja de mesas sin sitio (FR-011, SC-004).
- **I2** — El estado que muestra el plano para una mesa es **el mismo** que muestra su tarjeta en el
  mismo instante (FR-006, SC-006).
- **I3** — La vista de plano **no escribe nada**: ni posición, ni tamaño, ni forma, ni estado
  (FR-015, SC-007). No emite ninguna petición de escritura.
- **I4** — El editor de plano conserva su comportamiento íntegro (FR-020); es el único sitio donde
  el plano se modifica.
