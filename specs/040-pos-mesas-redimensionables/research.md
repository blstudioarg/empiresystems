# Research: Mesas redimensionables por celdas en el plano del POS

**Feature**: 040-pos-mesas-redimensionables | **Fecha**: 2026-08-19

Decisiones técnicas previas a la implementación. Todas parten de código ya existente de la
feature 039 (`public/js/plugins-init/pos-sala-plano.init.js`, `app/Support/PosPlanoReacomodo.php`,
`app/Support/PosPlanoCeldas.php`, `resources/views/pos/sala.blade.php`).

---

## D1 — Mecanismo de arrastre del borde: jQuery UI `resizable`

**Decisión**: usar el widget `resizable` de jQuery UI, ya presente en el bundle vendorizado
(`public/vendor/jqueryui/js/jquery-ui.min.js`, v1.11.1, cabecera del archivo: incluye
`resizable.js`), con las opciones `grid`, `containment`, `minWidth`/`minHeight` y `handles`.

**Rationale**:

- **Cero dependencias nuevas** (Principio V). El archivo ya se carga en `sala.blade.php:365` para
  el `draggable` del plano; `resizable` viaja en el mismo bundle y no añade ni un byte.
- **Coherencia con la feature 039**: `docs/04-front-guidelines.md` fija jQuery UI como la solución
  de arrastre de esta app ("sin librería nueva mientras el banco ya traiga solución").
- La opción `grid: [STEP, STEP]` da el encaje de FR-003 **gratis**: el widget solo emite tamaños
  que son múltiplos del paso.
- La opción `containment: '#pos-plano-canvas'` cubre FR-006 (no salirse de la rejilla) sin lógica
  propia.

**Aritmética del encaje** (importante, es la fuente de errores de un píxel): el lienzo usa
`CELL = 96` y `GAP = 12`, con `STEP = CELL + GAP = 108`. Una mesa de `n` celdas de ancho mide
`n * STEP - GAP` px. Como `grid` incrementa en `STEP`, partiendo de `CELL` (n=1) se obtiene
`CELL + STEP` (n=2), `CELL + 2*STEP` (n=3)… es decir exactamente `n * STEP - GAP`. El encaje sale
consistente sin corregir nada, siempre que el tamaño inicial se pinte con la misma fórmula.

**Alternativas consideradas**:

| Alternativa | Rechazada porque |
|-------------|------------------|
| Handlers propios con Pointer Events (~80 líneas) | Reimplementa encaje, contención y handles que el widget ya resuelve, y diverge del patrón de arrastre documentado. Su única ventaja real (soporte táctil) se cubre con D5, mucho más barato. |
| CSS `resize: both` nativo | No encaja a rejilla, no permite bloquear por colisión, no funciona en táctil y solo ofrece la esquina inferior derecha. |
| Vendorizar una librería de canvas/plano (interact.js, konva) | Dependencia nueva y desproporcionada para un caso que el bundle actual ya cubre. |

---

## D2 — Bloqueo por colisión: clamp en el evento `resize`, no `revert` al soltar

**Decisión**: validar en cada `resize` (no en `stop`). Si el rectángulo candidato solapa otra mesa,
se **reescriben `ui.size` y `ui.position` con el último rectángulo válido conocido**, de modo que el
borde se detiene visualmente en el límite mientras el usuario sigue arrastrando.

**Rationale**: FR-007 pide que el borde "no avance", no que salte hacia atrás al soltar. Validar en
`stop` produciría un rebote desconcertante (la mesa crece durante todo el arrastre y luego vuelve).
Validar en `resize` es el equivalente exacto de cómo se comporta un editor de planos real.

**Detalle de implementación**: `resizable` reescribe el DOM **después** del callback `resize`, así
que mutar `ui.size.width/height` y `ui.position.left/top` dentro del handler es suficiente; no hace
falta tocar el elemento a mano. Se mantiene una variable `ultimoValido = {fila, columna, ancho, alto}`
que se actualiza solo cuando el candidato pasa la validación.

**El clamp es por eje, no global** (FR-004): al arrastrar una esquina, el candidato puede ser
inválido en horizontal y válido en vertical. Revertir `ui.size` entero bloquearía también la
dirección libre, incumpliendo el escenario 3 de la User Story 2. El handler debe evaluar por
separado el eje X (`columna`, `ancho`) y el eje Y (`fila`, `alto`), reteniendo de `ultimoValido`
solo el eje que falla. Como la colisión AABB no es separable en general, el orden es: probar el
candidato completo; si falla, probar el candidato con X revertido; si falla, con Y revertido; si
falla, revertir ambos. Cuatro comprobaciones sobre 48 celdas, coste despreciable.

**Cuidado con `containment` + `grid`**: jQuery UI aplica la contención sobre el tamaño ya encajado y
en algunas versiones permite que el último paso rebase el contenedor. No se confía en `containment`
como única barrera de FR-006: la comprobación de rejilla (G2) va también en el clamp, igual que la
de solapamiento. `containment` queda como ayuda visual, no como garantía.

**Nota sobre bordes norte/oeste**: al arrastrar por arriba o por la izquierda, el widget cambia
`ui.position` además de `ui.size`, lo que **mueve la celda de origen** de la mesa (coherente con la
suposición del spec). El candidato debe recalcularse desde `ui.position`, no desde la posición
guardada, o la validación comprobaría un rectángulo que no es el que se está dibujando.

**Alternativas consideradas**: `revert` al soltar (rebote visual, rechazado); permitir el solape y
avisar al guardar (contradice FR-005 y deja el plano en el estado incoherente que la feature viene a
eliminar).

---

## D3 — Geometría de colisión: solapamiento de rectángulos (AABB), en cliente y servidor

**Decisión**: sustituir la comparación de celdas puntuales por solapamiento de rectángulos alineados
a ejes. Dos mesas colisionan si y solo si:

```
a.columna < b.columna + b.ancho  &&  b.columna < a.columna + a.ancho  &&
a.fila    < b.fila    + b.alto   &&  b.fila    < a.fila    + a.alto
```

En el cliente esto reemplaza el cuerpo de `celdaLibre()`; en el servidor, `PosPlanoReacomodo::validar()`
deja de construir un mapa de claves `"fila-columna"` con una entrada por mesa y pasa a **marcar todas
las celdas del rectángulo** (bucle doble), detectando el duplicado igual que hoy. Marcar celdas
(coste máximo 48 por zona) se prefiere a comparar todos los pares porque conserva la forma actual del
código y detecta a la vez solapamiento y salida de rejilla.

**Rationale**: el cliente da la respuesta interactiva y el servidor es la barrera real (Principio III:
el cliente nunca es la única barrera). Ambos aplican la misma regla, con implementaciones separadas
por lenguaje.

**Reglas de validación server-side** (FR-013), todas rechazando la petición completa:

1. `ancho >= 1` y `alto >= 1`.
2. `columna + ancho <= PosPlanoCeldas::COLUMNAS` y `fila + alto <= PosPlanoCeldas::FILAS`.
3. Ninguna celda del rectángulo aparece en dos mesas del payload.

---

## D4 — Reacomodo al **mover** con rectángulos: buscar hueco, no celda

**Decisión**: `celdaLibreMasCercana()` pasa a ser `huecoMasCercano(fila, columna, ancho, alto, ignorarId)`:
recorre las posiciones de origen candidatas en orden de distancia creciente y devuelve la primera donde
el rectángulo **completo** cabe (dentro de rejilla y sin solapar). Si no hay ninguna, el movimiento se
cancela con el mismo aviso que ya existe hoy.

**Rationale**: FR-008 conserva explícitamente el comportamiento actual de desplazamiento al mover (que
es distinto del bloqueo al redimensionar, FR-007). La diferencia es deliberada: mover una mesa sobre
otra es una intención clara del usuario ("ponla aquí"), mientras que agrandar contra una vecina no
significa "aparta a la vecina".

**Consecuencia a tener presente**: con rectángulos, "no hay hueco" es mucho más frecuente que antes
(una barra de 3×1 necesita tres celdas contiguas). El aviso existente pasa de ser un caso extremo a
uno plausible, así que su texto debe explicar el motivo real ("no hay espacio suficiente para la mesa
desplazada"), no solo decir que se canceló.

---

## D5 — Soporte táctil: reenvío de eventos `touch` propio, sin vendorizar `touch-punch`

**Decisión**: añadir en `pos-sala-plano.init.js` un reenviador mínimo que traduce `touchstart` /
`touchmove` / `touchend` sobre las asas (la de arrastre y las de redimensionado) a los
`mousedown`/`mousemove`/`mouseup` que espera el widget `mouse` de jQuery UI, más `touch-action: none`
en las asas para que el navegador no se quede el gesto como scroll.

**Rationale**: jQuery UI **1.11.1 no tiene soporte táctil** — su widget `mouse` escucha
`mousedown`/`mousemove` y los navegadores móviles solo emiten eventos de ratón emulados para *taps*,
no durante un arrastre. Esto significa que **el arrastre de posición de la feature 039 probablemente
tampoco funciona hoy en tablet** (a verificar en la validación, ver `quickstart.md`): no es una
regresión que introduzca esta feature, pero FR-019 y SC-005 la ponen en el camino crítico, y la Sala
es precisamente la pantalla de tablet.

Se prefiere un reenviador propio (~20 líneas, en nuestro archivo) a vendorizar `jquery.ui.touch-punch`:
evita una dependencia de terceros sin mantenimiento desde 2014 (Principio V) y el arreglo beneficia
también al arrastre existente.

**Alternativas consideradas**: actualizar jQuery UI a una versión con Pointer Events (arriesgado, el
bundle da servicio a datepicker, dialog, autocomplete y sortable en otras pantallas — cambio de
alcance muy superior al de esta feature); dejar el redimensionado como función solo de escritorio
(incumple FR-019).

---

## D6 — Zona de agarre de las asas y convivencia con el arrastre de posición

**Decisión**: el redimensionado se agarra **por los bordes** (asas `n, e, s, w, ne, nw, se, sw` del
widget) con un grosor de agarre de **16 px**, mientras el movimiento se sigue agarrando **solo por el
asa circular existente** (`.plano-mesa-handle`, esquina superior derecha, ya obligatoria por las guías
de front). No compiten: el interior de la mesa no arrastra nada.

**Rationale**: SC-005 exige acertar el borde al primer intento en tablet. 16 px es el mínimo razonable
para un dedo sin invadir el interior de una celda de 96 px. Como el arrastre de posición ya está
confinado a su asa, no hay ambigüedad de gesto que resolver.

**Riesgo conocido**: el asa circular vive en `top:-.5rem; right:-.5rem`, es decir **encima de la
esquina `ne`** de redimensionado. Se resuelve dando al asa de arrastre un `z-index` superior y
desplazando ligeramente el asa `ne`, o renunciando a la esquina `ne` (siete asas en vez de ocho), que
es la opción más simple y la preferida.

---

## D7 — Feedback de bloqueo: sombra/animación, nunca color de borde

**Decisión**: cuando el crecimiento se bloquea, marcar la mesa con una clase temporal que aplica un
resalte por **sombra exterior** (`box-shadow`) y un micro-desplazamiento de rechazo, respetando
`prefers-reduced-motion` (que la vista ya contempla).

**Rationale**: FR-009. `docs/04-front-guidelines.md` ("Tarjeta de mesa y sus tres estados") reserva el
**color del borde** para el estado de la mesa (gris libre / verde ocupada / ámbar olvidada). Teñir el
borde de rojo al chocar haría que una mesa ocupada pareciera cambiar de estado durante el arrastre.
`box-shadow` está libre: hoy solo se usa para la elevación genérica y para el estado "arrastrando".

**Descartado**: un toast por cada bloqueo (se dispararía decenas de veces en un solo arrastre).

---

## D8 — Retirada del atributo `tamano` y conversión de datos

**Decisión**: una única migración que (a) añade `ancho_celdas` y `alto_celdas` (enteros sin signo,
default 1), (b) rellena los valores derivando de `forma` — `rectangular` → 2×1, `barra` → 3×1, resto
1×1 —, (c) **corrige** las mesas cuyo rectángulo resultante se saldría de la rejilla o pisaría a otra
mesa, reduciendo su ancho hasta que quepa (mínimo 1), y (d) elimina la columna `tamano`.

**Rationale**: FR-011 y FR-012. El paso (c) es imprescindible: el mapeo de (b) es exactamente el que
reproduce el dibujo actual, pero el dibujo actual **ya se solapa** (ese es el defecto que la feature
corrige), así que aplicarlo a ciegas dejaría datos que la propia validación de D3 rechazaría — un
plano imposible de guardar hasta que el usuario lo arreglara a mano.

**Orden determinista del paso (c)**: recorrer las mesas de cada zona por `fila`, luego `columna`,
luego `id`. La primera mesa en el orden conserva su ocupación; las siguientes ceden. Así la conversión
es reproducible y no depende del orden que devuelva la base de datos.

**Sobre el `down()`**: revierte reintroduciendo `tamano` con su default (`mediana`) y descartando la
ocupación en celdas. Es una pérdida de información asumida y declarada — no hay forma de representar
un 3×2 en el modelo viejo. Dado que en desarrollo hay datos de demo con valor de presentación
(regla del proyecto), la migración **no** se ejecuta con `migrate:fresh`.

**Alternativas consideradas**: conservar `tamano` como escala visual dentro del rectángulo (dos
conceptos de tamaño solapados, descartado por el usuario en Clarifications); dejar `tamano` en la
tabla sin usar (deuda muerta que el siguiente lector interpretará mal).

---

## D9 — Sillas derivadas del contorno

**Decisión**: `sillasParaForma(forma)` pasa a `sillasParaMesa(forma, ancho, alto)`, que genera puntos
a lo largo del perímetro en función de las celdas: aproximadamente **dos plazas por celda de lado**
para las formas con sillas alrededor (redonda, cuadrada, rectangular) y **solo en el lado largo** para
la barra, que es como funciona una barra real.

**Rationale**: FR-014 y SC-006. Es la pieza que hace que el tamaño signifique algo al leer el plano.

**Explícitamente fuera de alcance**: derivar de aquí la **capacidad** de la mesa (número de comensales
como dato de negocio). Las sillas son una señal visual; convertirlas en un dato afectaría al modelo de
cuentas y no es lo que se pidió.

---

## D10 — Contrato de guardado: campos nuevos, endpoint sin cambios

**Decisión**: `PUT` al endpoint existente de guardado del plano, con cada mesa del payload llevando
ahora `ancho_celdas` y `alto_celdas` y **sin** `tamano`. Se mantienen el bloqueo optimista por
`version` y el guardado explícito por botón.

**Rationale**: FR-018. No hay razón para un endpoint nuevo: es el mismo batch atómico por zona con dos
campos más. La regla de validación de `tamano` (`in:pequena,mediana,grande`) se sustituye por reglas
`integer|min:1|max:<dimensión de la rejilla>` sobre los campos nuevos.

**Compatibilidad**: no se contempla soportar payloads antiguos. Es una interfaz interna de una sola
pantalla, servida por assets que se despliegan a la vez que el backend; mantener las dos formas sería
complejidad sin cliente que la necesite (Principio V).
