# Research: Plano de sala arrastrable (POS)

## D1 — Librería de arrastre

**Decision**: jQuery UI `draggable` + `droppable`, del mismo bundle ya vendorizado en
`public/vendor/jqueryui/js/jquery-ui.min.js` (usado hoy para `sortable` en el editor de menú,
feature 036).

**Rationale**: `docs/04-front-guidelines.md` fija explícitamente "no `SortableJS` ni otra
dependencia nueva mientras el banco del template ya traiga una solución" para el único precedente
de arrastre documentado en el proyecto. El bundle `jquery-ui.min.js` es el UI completo (no un build
recortado solo con `sortable`), así que `draggable`/`droppable` ya están disponibles sin vendorizar
nada nuevo. Investigación externa confirmó que librerías dedicadas a "floor planner" (Syncfusion
Diagram, Archilogic SDK) son soluciones para planos arquitectónicos completos, sobredimensionadas
para este caso; y que `interact.js` (alternativa moderna) no aporta nada que jQuery UI no resuelva
aquí, a costa de sumar una dependencia nueva que viola el Principio V (simplicidad/hosting
compartido).

**Alternatives considered**:
- `interact.js` — más moderno, pero dependencia nueva sin necesidad; rechazado por Principio V.
- Canvas/SVG a mano (sin librería) — control total, pero reimplementa gestos táctiles
  (drag-start/move/end, inercia, distancia mínima) que jQuery UI ya resuelve; rechazado por YAGNI.
- Plugin "jQuery UI Draggable Collision (UIDC)" — resolvería colisión de fábrica, pero es un
  plugin de terceros poco mantenido; rechazado, la colisión se resuelve con lógica propia simple
  al ser una rejilla discreta (ver D2).

## D2 — Modelo de posición: celdas discretas, no píxeles libres

**Decision**: cada mesa tiene `fila`/`columna` (enteros, 0-indexados) dentro de una rejilla fija
de 8 columnas × 6 filas por zona (Clarifications 2026-08-11).

**Rationale**: el requisito de negocio ("si soltás sobre una mesa ocupada, la otra se acomoda al
costado", FR-005) es exactamente el comportamiento natural de una rejilla con celdas discretas —
no física de colisión sobre coordenadas continuas. Con celdas discretas, "sin solapamiento" es una
propiedad garantizable por construcción (una mesa por celda, un `UNIQUE` en `(tenant_id, zona_id,
fila, columna)` a nivel de base de datos) en vez de algo que hay que verificar en cada frame de
animación.

**Alternatives considered**:
- `pos_x`/`pos_y` en píxeles/porcentaje libres — más fiel visualmente a un plano "real", pero
  exige resolver colisión con física continua (AABB overlap, iteración de reajuste), muy por
  encima del alcance de v1 y sin beneficio claro dado que el propio ejemplo de referencia (imagen
  de GoTPD) ya muestra mesas alineadas en filas/columnas prolijas.

## D3 — Reacomodo por colisión: distancia euclídea a la celda libre más cercana

**Decision**: al soltar una mesa sobre una celda ocupada, el sistema busca la celda libre de la
misma zona con menor distancia euclídea respecto a la celda que la mesa desplazada ocupaba antes
(Clarifications 2026-08-11), y la reubica ahí. Si no hay ninguna celda libre en la zona, se cancela
la operación (FR-006) y la mesa arrastrada vuelve a su posición anterior.

**Rationale**: decisión ya tomada explícitamente con el usuario vía `/speckit-clarify`; euclídea
en línea recta se percibe más natural que un barrido "lectura" (fila por fila) porque el
movimiento resultante es visualmente el más corto posible desde cualquier dirección de arrastre.

**Alternatives considered**: barrido tipo lectura (izquierda→derecha, fila por fila) — más
predecible de implementar pero puede mover la mesa desplazada "lejos" visualmente si su fila está
llena aunque haya una celda libre muy cercana en la fila de abajo; descartado por el usuario.

## D4 — Persistencia: guardado explícito por botón, batch por zona

**Decision**: el arrastre solo actualiza el DOM/estado en memoria del cliente. Un único botón
"Guardar plano" envía de una vez la disposición completa de la zona activa (todas las mesas con su
`fila`/`columna`/`forma`/`tamano` vigentes) a un endpoint dedicado
(`PUT /pos/sala/zonas/{zona}/plano`), que persiste en una transacción.

**Rationale**: mismo criterio ya documentado para `sortable` en `docs/04-front-guidelines.md`
("Guardado por botón único, nunca por evento de arrastre... El servidor no confía en el orden
recibido: reconstruye la lista final a partir de su propio catálogo/fuente de verdad"). Aplicado
aquí: el servidor valida que el payload recibido sea un subconjunto consistente de las mesas
reales de esa zona bajo el tenant activo (recalcula, no confía ciegamente en fila/columna
recibidas: si dos mesas del payload declaran la misma celda, o una celda fuera de la rejilla
8×6, la petición se rechaza en vez de "arreglarse" silenciosamente en el servidor — a diferencia
del reacomodo del cliente, que es solo una previsualización).

**Alternatives considered**: persistir cada movimiento individual (`PATCH` por mesa al soltar) —
generaría N peticiones por reordenamiento completo y ventanas de inconsistencia visible entre
mesas movidas; rechazado, coincide con lo que la convención ya documentada prohíbe explícitamente.

## D5 — Concurrencia: bloqueo optimista por zona

**Decision**: se añade `version` (entero) a `pos_zonas`. El guardado del plano exige el `version`
vigente en el payload; si no coincide con el actual, el servidor responde 409 (mismo patrón que
`PosCuenta::version` + `CuentaController::conflictoDeVersion()`, feature 038) y el cliente informa
al usuario en vez de sobrescribir.

**Rationale**: FR-011 exige detectar guardados concurrentes en conflicto. El proyecto ya resuelve
exactamente este problema (dos dispositivos editando el mismo recurso) para `pos_cuentas` con un
entero `version` + comparación explícita antes de persistir; reutilizar el mismo patrón evita
inventar un mecanismo nuevo (Principio V, YAGNI) y mantiene consistencia de código entre módulos
hermanos del mismo feature 038.

**Alternatives considered**: timestamps (`updated_at`) como proxy de versión — funciona pero es
menos explícito que un contador dedicado; se prefiere el patrón ya establecido en el código
existente antes que introducir una variante.

## D6 — Permiso para el modo edición del plano

**Decision**: el botón "Editar plano" y el endpoint de guardado se gatean con el mismo permiso
`ver-configuracion` que ya protege todo el grupo de rutas `configuracion.pos.*` (mesas/zonas),
no un permiso nuevo.

**Rationale**: Assumptions del spec ya fija esto explícitamente ("El permiso para entrar en modo
edición del plano es el mismo que ya gobierna la gestión de configuración del POS"). La ruta de
Sala (`pos.sala`) hoy se gatea con `can:ver-pos-sala` + `modulo.hosteleria` (routes/web.php); el
nuevo endpoint de guardado de plano añade además `can:ver-configuracion` para que solo quien puede
tocar la configuración del POS pueda reordenar el salón, aunque cualquier camarero con acceso a
Sala siga viendo el plano en modo solo-lectura.

**Alternatives considered**: permiso nuevo dedicado (`gestionar-pos-plano`) — más granular, pero
el spec ya descartó explícitamente esta opción a favor de reutilizar el permiso existente.

## D7 — Migración de mesas existentes sin fila/columna/forma/tamano

**Decision**: la migración añade las columnas nuevas con defaults (`forma` = `cuadrada`, `tamano`
= `mediana`, `fila`/`columna` nulas). Un paso de backfill dentro de la misma migración asigna
`fila`/`columna` a las mesas existentes recorriéndolas en el orden actual (`orden`, `nombre`) y
colocándolas en las primeras celdas libres de la rejilla 8×6 de su zona (Assumptions del spec).

**Rationale**: evita que una mesa exista sin posición válida en el plano (Edge Case del spec:
"una mesa nueva sin posición previa DEBE aparecer en el lienzo... nunca superpuesta"); el mismo
criterio de backfill se reutiliza también en el momento de crear una mesa nueva sin plano
(`PosMesaController::store` gana el mismo cálculo de "primera celda libre").
