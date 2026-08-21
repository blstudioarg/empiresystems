# Research: Forma y medidas configurables de la zona en el plano de sala

**Feature**: 042-plano-sala-forma-medidas | **Fecha**: 2026-08-21

Decisiones técnicas previas al diseño. Cada una resuelve una incógnita concreta del paso a "lienzo
por zona"; el hilo común es **no romper el sistema de coordenadas por celdas** del que dependen las
features 039, 040 y 041.

---

## D1 — Dónde vive la máscara de celdas

**Decisión**: una columna JSON `celdas_inactivas` en `pos_zonas`, con un array de claves
`"fila-columna"` (mismo formato de clave que ya usan `PosPlanoCeldas::celdasOcupadas()` y
`PosPlanoReacomodo::validar()`), normalizado y ordenado al guardar. Default `[]`.

**Rationale**: la máscara es un atributo de la zona que **siempre se lee y se escribe entera**, junto
con el resto del plano y en la misma transacción. No se consulta nunca por celda suelta, no se
indexa, no se relaciona con nada. Una columna JSON es exactamente eso. Además reutiliza la clave
`"f-c"` que ya circula por el código de colisión, así que la validación nueva es una comprobación de
pertenencia a un `array_flip`, no un modelo mental nuevo.

**Alternativas descartadas**:

- **Tabla `pos_zona_celdas`**: hasta 576 filas por zona para un dato que nunca se consulta por
  separado, más un N+1 en el payload de la Sala (que hoy resuelve todo en tres consultas), más un
  borrado en cascada que mantener. Complejidad sin beneficio (Principio V).
- **Bitmask en un `string`/`binary`**: más compacto, pero ilegible en la base de datos, dependiente
  del orden de recorrido y frágil al cambiar las medidas de la zona (el mismo bit significa otra
  celda si cambia el número de columnas). El coste de espacio del JSON es irrelevante a esta escala.
- **Un campo `forma` con un enum de plantillas** (rectangular / L / U): resuelve la demo y no la
  realidad; ninguna sala real encaja en tres plantillas.

**Nota de compatibilidad**: `$table->json(...)` en MariaDB se materializa como `LONGTEXT` con
constraint de JSON válido; con el cast `array` de Eloquent el comportamiento es idéntico y no exige
funciones JSON de MySQL en ninguna query (nunca filtramos por dentro del JSON). Compatible con el
hosting compartido (Principio V).

---

## D2 — Medidas por zona

**Decisión**: dos columnas `unsignedTinyInteger` en `pos_zonas`, `columnas` y `filas`, con default
`8` y `6` respectivamente. Límites `MIN = 4` y `MAX = 24` como constantes de `PosPlanoCeldas`.

**Rationale**: el default reproduce exactamente el lienzo fijo de hoy, así que la migración no
necesita backfill de datos y ninguna zona existente cambia (FR-015). `TINYINT UNSIGNED` cubre de
sobra el rango. El máximo de 24 acota el lienzo a 576 celdas: suficiente para un salón grande, y por
debajo del punto donde la capa de celdas del editor empezaría a pesar en una tablet (D5).

**Alternativas descartadas**: un único campo "tamaño" (pequeña/mediana/grande) — es el mismo error
que la feature 040 vino a corregir en las mesas; y medidas en metros con una escala — convertiría el
plano en un documento acotado, explícitamente fuera de alcance.

---

## D3 — `PosPlanoCeldas`: de constantes globales a geometría de una zona

**Decisión**: `PosPlanoCeldas::COLUMNAS` / `::FILAS` dejan de ser *la* rejilla y pasan a ser
`COLUMNAS_DEFECTO` / `FILAS_DEFECTO`, junto a `MIN`/`MAX`. Las funciones que hoy asumen la rejilla
(`primeraCeldaLibre`) pasan a recibir la `PosZona` y a consultar su geometría, y a **saltarse las
celdas inactivas** al buscar hueco.

**Rationale**: es el punto exacto donde hoy está cableada la suposición "todas las zonas son 8×6".
Dejar las constantes con el mismo nombre pero significado nuevo sería una trampa para el próximo
lector; renombrarlas hace que el compilador (y `grep`) señale todos los usos.

**Consecuencia buscada**: crear una mesa nueva en una zona recortada la coloca en la primera celda
**de suelo** libre, nunca en un hueco recortado.

---

## D4 — La geometría deja de ser global en el cliente

**Decisión**: `PosPlanoDibujo` deja de exponer `COLS`/`ROWS` como constantes de módulo y deja de
escribir `--plano-cols` / `--plano-rows` en `documentElement` al cargar. Pasa a exponer funciones que
reciben la geometría de la zona activa, y las variables CSS se escriben **en el elemento del lienzo**
al dibujar cada zona.

**Rationale**: hoy la geometría se fija una vez, en el `<html>`, porque era la misma para todo. Con
medidas por zona eso pasa a ser un bug latente: cambiar de zona dejaría el lienzo con las medidas de
la anterior. Escribirlas en el lienzo al dibujar hace que el dato viva donde se usa y desaparezca el
estado global.

**Restricción de la guía de front**: el módulo de dibujo se mantiene **fuera** del guard de permiso
del editor (`docs/04-front-guidelines.md`, "Dos vistas de los mismos datos", feature 041). La
geometría es contorno, y el contorno lo comparten editor y servicio (FR-012).

---

## D5 — Cómo se dibuja y se pinta el recorte

**Decisión**: el lienzo gana una **capa de celdas** por debajo de las mesas.

- En **modo servicio** solo se renderizan las celdas inactivas (marcadas como vacío), que suelen ser
  pocas.
- En **modo edición**, y **solo al activar el modo de recorte**, se renderiza la rejilla completa de
  celdas como blancos de toque; al salir del modo de recorte se desmonta.

**Rationale**: el suelo de hoy es un `background-image` de puntos sobre el lienzo, que no puede
"agujerearse" ni recibir eventos por celda. Hace falta un elemento real por celda para dos cosas:
marcarla como vacío y poder tocarla. Renderizar 576 divs permanentemente en una tablet es
innecesario; montarlos solo mientras se recorta acota el coste al único momento en que sirven.

**Alternativas descartadas**: calcular la celda tocada a partir de las coordenadas del puntero sobre
el lienzo (sin elementos) — evita los divs pero obliga a recrear a mano el hit-testing, el
`pointerenter` por celda y el estado de hover; el ahorro no compensa. Y un `<canvas>` — introduce un
sistema de dibujo paralelo al DOM que el resto del plano no usa.

---

## D6 — El gesto de recortar no puede confundirse con mover una mesa

**Decisión**: un **modo de recorte explícito** (un toggle en la barra del editor). Mientras está
activo, las mesas no se arrastran ni se redimensionan y el puntero pinta celdas; al desactivarlo, el
editor vuelve a su comportamiento normal. El pintado usa Pointer Events (`pointerdown` +
`pointerenter` con `setPointerCapture` y `touch-action: none`), de modo que un arrastre recorre
varias celdas en un solo gesto (FR-004).

**Rationale**: en una tablet, el dedo no distingue "arrastrar la mesa" de "pintar el suelo de
debajo"; sin un modo explícito, el primer gesto de recorte movería una mesa (FR-019). Además, el modo
da un sitio natural donde mostrar los controles de medidas y el aviso de cuántas celdas hay
recortadas.

**Alternativas descartadas**: pintar con un modificador de teclado — inexistente en tablet. Y un
diálogo aparte de "diseñar la sala" — parte en dos la edición del plano y obliga a saltar entre
pantallas para colocar una mesa donde quepa.

---

## D7 — Feedback de rechazo durante el gesto

**Decisión**: al intentar recortar una celda ocupada, la **mesa que lo impide** recibe la señal de
rechazo ya existente (`.plano-mesa-bloqueada`: sombra exterior + micro-desplazamiento de ~3px,
respetando `prefers-reduced-motion`), y el gesto continúa sobre las celdas que sí puede recortar. Al
**soltar**, si hubo celdas rechazadas, se emite **un solo** toast con el conteo agregado.

**Rationale**: es exactamente la regla que ya fija `docs/04-front-guidelines.md` ("Feedback de
bloqueo cuando el borde ya comunica estado", feature 040): el color del borde está reservado al
estado de la mesa, y un toast por celda durante un arrastre llenaría la pantalla. Reutilizar la clase
existente mantiene un único vocabulario visual de rechazo en toda la pantalla.

---

## D8 — Validación en servidor: la geometría viaja en el mismo guardado

**Decisión**: el payload de `PUT /pos/sala/zonas/{zona}/plano` gana `columnas`, `filas` y
`celdas_inactivas`. `PosPlanoReacomodo::validar()` pasa a recibir la **geometría propuesta** (no la
guardada) y añade dos invariantes a los tres actuales. El controlador persiste zona y mesas en la
misma transacción, con el mismo bloqueo optimista por `version`.

Invariantes verificados en servidor (todos, siempre, sobre la geometría propuesta):

| # | Invariante | Origen |
|---|-----------|--------|
| G1 | Toda mesa ocupa ≥ 1×1 celdas | feature 040 |
| G2 | Toda mesa cae dentro de la rejilla **propuesta** | 040, ampliado |
| G3 | Dos mesas no comparten celda | feature 040 |
| **G4** | **Ninguna mesa incluye una celda inactiva** | **042 (FR-006)** |
| **G5** | **La zona conserva ≥ 1 celda de suelo, y sus medidas están entre MIN y MAX** | **042 (FR-011)** |

**Rationale**: Principio III — el cliente nunca es la única barrera. Y validar contra la geometría
*propuesta* (no la persistida) es lo que permite que "encoger la zona" y "mover la mesa que
estorbaba" viajen en la misma petición y se validen como un todo coherente; validar contra la
guardada rechazaría guardados legítimos.

**Atomicidad**: se rechaza la petición **entera** ante cualquier fallo, sin cambios parciales
(FR-014), igual que hoy.

---

## D9 — Quién produce el mensaje con el conteo de mesas (FR-009)

**Decisión**: el **cliente** impide la reducción y muestra el conteo ("no puedes reducir a N
columnas: 3 mesas quedarían fuera") en el momento del intento, porque es quien tiene el contexto del
gesto. El **servidor** mantiene su mensaje por mesa ("La mesa X se sale de la rejilla de la zona") y
sigue siendo la barrera real.

**Rationale**: el conteo es una ayuda de interfaz para decidir qué mover; el mensaje del servidor es
una defensa contra payloads manipulados, donde nombrar la mesa concreta es más útil que contar. No se
duplica lógica: son dos audiencias distintas del mismo invariante.

---

## D10 — Migración sin backfill

**Decisión**: una única migración que añade `columnas` (default 8), `filas` (default 6) y
`celdas_inactivas` (default `[]`) a `pos_zonas`. **Sin backfill de datos.**

**Rationale**: los defaults reproducen el lienzo fijo actual, así que toda zona existente queda
exactamente igual que antes de migrar (FR-015, SC-007). Es la diferencia con la migración de la
feature 040, que sí necesitó lógica de conversión porque allí el dato nuevo no tenía un default
equivalente al comportamiento anterior.

**`down()`**: elimina las tres columnas. Es destructivo para el diseño de sala (se pierde el recorte)
pero no para ninguna mesa; se documenta en el docblock de la migración.

---

## D11 — Alcance del cambio en la vista de tarjetas

**Decisión**: ninguno. La rejilla de tarjetas de la Sala no representa el espacio físico y no le
afectan ni las medidas ni la forma.

**Rationale**: mantener el cambio dentro del plano acota la superficie de regresión a las tres
pantallas que ya lo dibujan (editor, servicio) y evita tocar el flujo operativo del camarero.
