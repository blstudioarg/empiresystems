# Research: Vista de plano en la Sala en modo servicio

**Feature**: 041-plano-sala-servicio | **Fecha**: 2026-08-20

Decisiones técnicas previas a la implementación. Todo parte de código existente de las features
038 (Sala y tarjetas), 039 (plano arrastrable) y 040 (mesas redimensionables).

---

## D1 — El dibujo de la mesa se extrae a un módulo compartido, no se duplica

**Decisión**: extraer de `public/js/plugins-init/pos-sala-plano.init.js` las funciones **puras de
dibujo** —geometría de celdas a píxeles, reparto de sillas y el HTML de una mesa— a un archivo
nuevo `public/js/plugins-init/pos-plano-dibujo.js`, expuesto como `window.PosPlanoDibujo`. El
editor y la vista de servicio lo consumen los dos.

**Rationale**: es la decisión que más condiciona la feature. El dibujo tiene reglas sutiles que
costó afinar (el encaje `n * STEP - GAP`, las sillas derivadas del contorno, la clase `estirada`
que sustituyó a la forma `rectangular`, los tres estados por color de borde). Si la vista de
servicio copia ese código, las dos implementaciones **divergen en el primer arreglo** que se haga
en una sola de ellas — y ya llevamos tres arreglos de geometría en la 040. Una sola fuente para el
dibujo es lo que evita que el plano de servicio y el de edición dejen de parecerse.

Además `pos-sala-plano.init.js` ronda las 640 líneas, en el umbral que `docs/04-front-guidelines.md`
("Partición de un archivo JS grande…", feature 038) fija para partir: extraer el dibujo lo baja y
de paso cumple esa guía.

**Detalle importante**: `pos-sala-plano.init.js` empieza con `if (!state.puedeEditar) { return; }`.
El módulo de dibujo tiene que quedar **fuera** de ese guard —en su propio archivo— o un usuario sin
permiso de configuración se quedaría sin dibujo, que es justo el caso que la feature viene a servir.

**Alternativas consideradas**:

| Alternativa | Rechazada porque |
|-------------|------------------|
| Duplicar el render en un archivo nuevo | Garantiza divergencia. El siguiente bug de geometría se arreglaría en un sitio y no en el otro. |
| Meter el modo servicio dentro de `pos-sala-plano.init.js` | Ese archivo entero está bajo el guard de `puedeEditar`, y mezclar un modo de solo lectura con toda la maquinaria de arrastre en un archivo de 800 líneas es exactamente lo que la guía de partición desaconseja. |
| Renderizar el plano en servidor (Blade) | El estado de la sala ya llega por JSON y se repinta en cliente; renderizar la mitad en Blade obligaría a mantener dos caminos para lo mismo. |

---

## D2 — Preferencia de vista en `localStorage`, con la clave por usuario

**Decisión**: guardar la vista elegida en `localStorage` bajo la clave `pos-sala-vista:<userId>`,
con valores `tarjetas` (por defecto) y `plano`.

**Rationale**: es una preferencia de **interfaz**, no un dato de negocio: no se audita, no se
sincroniza, y perderla no rompe nada. El proyecto ya usa `localStorage` para preferencias de
interfaz (`public/js/theme-persist.js`).

Que sea **por dispositivo es una ventaja, no una limitación**: la tablet de sala quiere el plano y
el PC de caja probablemente quiera las tarjetas. Guardarlo en el servidor impondría la misma vista
en los dos.

El `<userId>` en la clave cumple FR-003 ("de forma independiente para cada usuario") cuando varias
personas comparten la misma tablet, que es el caso normal en hostelería.

**Alternativas consideradas**: columna en `users` o en la config del tenant (dato de negocio para
algo que no lo es, y una migración para una preferencia de UI); sin persistencia (obliga a repetir
el gesto decenas de veces por turno, FR-003).

---

## D3 — Selector de vista: par de botones en la cabecera, separado del filtro de zona

**Decisión**: un grupo de dos botones (tarjetas / plano) en `.pos-sala-acciones`, junto a
"Actualizar", con el estado activo marcado. Visible **siempre**, sin `@can`.

**Rationale**: FR-002 y FR-004. Va en la cabecera y no entre los filtros porque **no filtra nada**:
cambia cómo se ve lo mismo. `docs/04-front-guidelines.md` reserva `.pos-filtro` para el filtro por
zona, y reutilizarlo aquí mezclaría dos conceptos distintos en la misma fila.

El botón "Editar plano" se queda donde está y **sigue bajo `@can('ver-configuracion')`**: ver el
plano y cambiarlo son permisos distintos (FR-004 vs FR-020).

**Tamaño táctil**: mínimo 44px de alto, como el resto de controles de la Sala.

---

## D4 — Qué texto lleva cada mesa, y cómo cabe en una celda

**Decisión**: nombre siempre; en una mesa ocupada, además el importe pendiente y el tiempo en
**formato compacto** (`12′` en vez de `Hace 12 min`). El nombre se trunca con elipsis antes que
desbordar, y el bloque de texto nunca sale del contorno de la mesa.

**Rationale**: FR-007 pide la misma información que la tarjeta, y FR-009 que siga legible en una
mesa de una sola celda (96×96 px menos bordes). Con el formato largo de la tarjeta ("Hace 12 min")
no cabe; con el compacto sí, y no se pierde información.

La tarjeta puede permitirse el texto largo porque su ancho es fijo y generoso; la mesa del plano
mide lo que el encargado decidió. Es la misma información adaptada al espacio, no información
distinta.

**Alternativas consideradas**: omitir el tiempo en mesas de una celda (incumple FR-007); un tooltip
al pasar el dedo (en tablet no hay hover, y obligaría a un toque que además abriría la cuenta).

---

## D5 — Mesas sin posición: franja de tarjetas bajo el lienzo, reutilizando `.pos-mesa`

**Decisión**: bajo el lienzo, una franja con encabezado ("Sin sitio en el plano") que dibuja esas
mesas con **el mismo componente de tarjeta** que la vista de tarjetas (`.pos-mesa`), con su estado
y su mismo comportamiento al tocarlas.

**Rationale**: FR-011 y SC-004. Ninguna mesa activa puede quedar inalcanzable durante el servicio.
Reutilizar `.pos-mesa` significa **cero markup y cero CSS nuevos** para ese caso (Principio V), y
comunica sin explicar que esas mesas están fuera del plano.

El sistema **no** les asigna posición: dónde va una mesa es una decisión del encargado (y además
asignarla fallaría justo cuando la zona está llena, que es lo que produce el caso).

**Alternativas consideradas**: colocarlas automáticamente en la primera celda libre (decide por el
encargado y no funciona con la zona llena); un aviso que remita a la vista de tarjetas (obliga a
cambiar de vista en mitad del servicio).

---

## D6 — "Todas las zonas" no aplica en el plano: se resuelve a una zona concreta

**Decisión**: al cambiar a vista de plano con el filtro "Todas" activo, seleccionar la primera zona
disponible y **reflejarlo en el filtro** disparando el evento `pos-sala:zona-cambiada` que ya
existe. Mientras la vista de plano está activa, el botón "Todas" se muestra deshabilitado.

**Rationale**: FR-010. La rejilla de 8×6 es de **una** zona; "Todas" no tiene plano que dibujar.
Reutilizar el evento existente mantiene sincronizados el filtro, la vista de tarjetas y el plano
sin duplicar el estado de "qué zona está activa" (que ya vive en `pos-sala.init.js` y se expone
como `window.posSalaZonaActiva()`).

Deshabilitar el botón en vez de ocultarlo evita que la fila de filtros cambie de tamaño al alternar
de vista, que es desconcertante en pantalla táctil.

**Alternativas consideradas**: apilar un lienzo por zona (mucho scroll vertical en locales con
varias zonas, y el camarero está físicamente en una); dejar "Todas" mostrando el lienzo vacío
(parece un fallo).

---

## D7 — Interacción por delegación, y el destino se decide igual que en la tarjeta

**Decisión**: un único listener delegado en el contenedor del plano; al tocar, se lee el
`data-mesa-id` del elemento y se resuelve el destino con **la misma regla que ya usa la tarjeta**
(mesa libre → TPV con `?mesa=<id>`; mesa ocupada → su cuenta).

**Rationale**: FR-013/FR-014 exigen que las dos vistas lleven al mismo sitio; la forma de
garantizarlo es que compartan la decisión, no que la repitan. La delegación evita además tener que
volver a enganchar listeners en cada repintado.

**Sobre FR-017** (un refresco no debe desviar un toque en curso): hoy **no hay refresco automático**
en la Sala —se refresca con el botón "Actualizar" o tras una acción del propio usuario—, así que el
escenario es de riesgo bajo. La delegación lo cubre igualmente: el destino se calcula en el momento
del toque a partir del elemento tocado, no de un índice capturado antes.

---

## D8 — Sin cambios en el servidor

**Decisión**: la feature es de interfaz. El estado de la Sala (`SalaController`) ya entrega por cada
mesa `estado`, `olvidada`, `pendiente`, `abierta_hace_min`, `abrir_url`, `fila`, `columna`,
`ancho_celdas`, `alto_celdas` y `forma`. No hacen falta endpoints, columnas ni permisos nuevos.

**Rationale**: Principio V. Y refuerza FR-008: `olvidada` **ya** lo decide el servidor con el umbral
del tenant, así que el plano hereda gratis la regla de "la vista nunca hace aritmética de fechas"
(`docs/04-front-guidelines.md`, "Tarjeta de mesa y sus tres estados").

**Consecuencia**: no hay tests de backend nuevos que escribir para lógica nueva, porque no hay
lógica nueva. Sí conviene un test que **fije el contrato** del payload (que esos campos siguen
estando), para que un refactor futuro del controller no rompa la vista en silencio.

---

## D9 — Encaje del lienzo en pantalla: escalado proporcional, no scroll en dos ejes

**Decisión**: cuando el ancho disponible sea menor que el del lienzo (8 × 108 − 12 = 852 px más el
relleno), escalar el lienzo proporcionalmente para que **el ancho siempre quepa**, dejando como
mucho scroll vertical.

**Rationale**: FR-021. Un plano que obliga a arrastrar en horizontal y en vertical para encontrar
una mesa es peor que la rejilla de tarjetas que viene a mejorar. Escalar mantiene las proporciones
de la sala —que es lo que hace reconocible el plano— y no afecta a los toques.

**Nota**: esto aplica **solo a la vista de servicio**. El editor conserva su comportamiento actual
(FR-020): ahí el usuario está colocando mesas y necesita el tamaño real de la celda.

---

## D10 — Alcance de la comprobación automática

**Decisión**: el peso de la validación es manual (`quickstart.md`), más un test de backend que fija
el contrato del payload de la Sala (D8) y los tests de aislamiento que ya existen.

**Rationale**: la feature es de presentación y el proyecto no tiene infraestructura de test de JS
(sin bundler ni runner, Principio V), así que montarla para esta feature sería desproporcionado.
Lo que **sí** es crítico y automatizable —que la Sala no filtre mesas de otro tenant— ya está
cubierto por `tests/Feature/Pos/AislamientoMesasTest.php`, y esta feature no toca esa vía.
