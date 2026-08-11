# Feature Specification: Plano de sala arrastrable (POS)

**Feature Branch**: `039-pos-plano-mesas`

**Created**: 2026-08-11

**Status**: Draft

**Input**: User description: "POS: editor de plano de sala con drag-and-drop para ordenar mesas, al estilo del editor de planos de GoTPD. Cada mesa tiene forma y tamaño configurables; el modo edición se activa desde la Sala operativa; cada zona tiene su propio lienzo; sin solapamiento libre (la mesa ocupante se desplaza al hueco libre más cercano); guardado explícito por botón."

## Documentación consultada (regla de oro del proyecto)

- `docs/04-front-guidelines.md`, sección "listas jerárquicas arrastrables" (excepción feature 036,
  líneas ~524-560): fija el patrón de arrastre reutilizable en esta app — **jQuery UI**
  (vendorizado en `public/vendor/jqueryui/`, sin librería nueva mientras el banco ya traiga
  solución), **asa de arrastre obligatoria** (no arrastrable por toda la superficie), y
  **guardado por botón único, nunca por evento de arrastre** (el servidor reconstruye el orden
  final desde su propia fuente de verdad, no confía ciegamente en lo recibido). Esta feature
  traslada el mismo criterio de `sortable` a `draggable`/`droppable` (posicionamiento 2D en vez de
  lista lineal), por ser el caso de uso más cercano documentado hasta ahora.
- `docs/03-modelo-datos.md`, sección "POS con mesas y opciones — módulo de hostelería (feature
  038)": confirma que `pos_zonas`/`pos_mesas` ya existen, que el estado libre/ocupada de una mesa
  **no se almacena** (se deriva de `pos_cuentas` en estado `abierta`), y que las tablas del módulo
  usan prefijo `pos_` y aislamiento por `tenant_id`. Esta feature añade atributos de plano
  (posición, forma, tamaño) a `pos_mesas` sin tocar ese cálculo derivado de ocupación.

## Clarifications

### Session 2026-08-11

- Q: ¿Qué tamaño de lienzo (filas × columnas) por zona es razonable como límite v1? → A: 8 columnas × 6 filas (48 celdas) por zona.
- Q: Al reacomodar la mesa desplazada (FR-005), ¿cómo se define "la posición libre más cercana"? → A: Distancia euclídea a la celda ocupada (la celda libre físicamente más próxima en línea recta).
- Q: ¿Qué escala usamos para el "tamaño" de una mesa? → A: 3 tamaños discretos (pequeña/mediana/grande).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Distribuir las mesas de una zona igual que en el local real (Priority: P1)

Como encargado de sala, quiero arrastrar cada mesa hasta la posición del plano que refleja dónde
está físicamente en el comedor, para que el personal reconozca de un vistazo qué mesa es cuál
cuando abre la Sala en la tablet, sin tener que memorizar un orden numérico abstracto.

**Why this priority**: Es el valor central de la feature — sin esto, el resto (formas, tamaños,
guardado) no tiene sentido. Es el único user story que, por sí solo, ya sustituye el grid
automático actual por algo útil.

**Independent Test**: Entrar a la Sala, activar "Editar plano", arrastrar una mesa a otra posición
dentro de la misma zona, pulsar "Guardar plano", recargar la página y comprobar que la mesa
aparece en la nueva posición.

**Acceptance Scenarios**:

1. **Given** el modo edición está activo y la zona "Comedor" tiene 6 mesas en sus posiciones
   guardadas, **When** el usuario arrastra la mesa "Mesa 3" a una celda vacía del lienzo,
   **Then** la mesa se mueve visualmente a esa celda y queda marcada como cambio pendiente (no
   persistido) hasta pulsar "Guardar plano".
2. **Given** hay cambios de posición pendientes sin guardar, **When** el usuario pulsa "Guardar
   plano", **Then** el sistema persiste las nuevas posiciones de todas las mesas de esa zona y
   confirma el guardado.
3. **Given** el usuario sale del modo edición sin pulsar "Guardar plano", **When** vuelve a entrar
   a la Sala, **Then** las mesas aparecen en sus últimas posiciones guardadas (los cambios sin
   guardar se descartan).

---

### User Story 2 - Elegir forma y tamaño acordes al mobiliario real (Priority: P2)

Como encargado de sala, quiero que cada mesa del plano tenga la forma (redonda, cuadrada,
rectangular, barra) y el tamaño que tiene en el local real, para que el plano sea un espejo fiel
del comedor y no una rejilla genérica de cajas iguales.

**Why this priority**: Complementa el valor de la Historia 1 (reconocimiento visual), pero el
plano ya es funcional con una forma/tamaño por defecto si esto no estuviera. Es una mejora de
fidelidad, no el núcleo de la interacción de arrastre.

**Independent Test**: En modo edición, cambiar la forma y el tamaño de una mesa existente,
guardar, recargar y comprobar que el plano refleja la forma/tamaño elegidos.

**Acceptance Scenarios**:

1. **Given** una mesa sin forma configurada (mesa creada antes de esta feature), **When** se
   muestra el plano, **Then** se ve con una forma y tamaño por defecto razonables (ver
   Assumptions), nunca vacía o rota.
2. **Given** el modo edición está activo, **When** el usuario cambia la forma de "Mesa 5" de
   cuadrada a redonda y su tamaño de 2 a 4 comensales, **Then** el lienzo actualiza la
   representación de inmediato (sin recargar) y el cambio queda pendiente de "Guardar plano" igual
   que un cambio de posición.

---

### User Story 3 - No perder mesas por solapamiento accidental (Priority: P1)

Como encargado de sala, quiero que si suelto una mesa encima de otra por error (o porque el
espacio ya está ocupado), el sistema reacomode la mesa que ya estaba ahí en vez de dejarlas
superpuestas o perder una de las dos, para no tener que reconstruir el plano a mano tras un
descuido.

**Why this priority**: Sin esto, la interacción de arrastre (Historia 1) es frágil: cualquier
solapamiento accidental deja el plano en un estado confuso o inconsistente. Es tan crítico como la
Historia 1 porque protege la integridad del propio arrastre.

**Independent Test**: En modo edición, arrastrar una mesa y soltarla exactamente sobre la posición
de otra mesa ya colocada; comprobar que ambas mesas quedan visibles, sin superponerse, y que la
mesa desplazada aterriza en el hueco libre más cercano.

**Acceptance Scenarios**:

1. **Given** "Mesa 1" ocupa una celda del lienzo, **When** el usuario suelta "Mesa 2" sobre esa
   misma celda, **Then** "Mesa 1" se desplaza automáticamente a la celda libre más cercana dentro
   de la misma zona y "Mesa 2" queda en la celda soltada; ninguna mesa desaparece ni queda oculta
   detrás de otra.
2. **Given** el lienzo de una zona está completamente lleno de mesas (sin celdas libres),
   **When** el usuario intenta soltar una mesa sobre otra, **Then** el sistema impide el
   solapamiento y devuelve la mesa arrastrada a su posición anterior, con un aviso de que no hay
   espacio libre en esa zona.

---

### User Story 4 - Cada zona con su propio plano independiente (Priority: P2)

Como encargado de sala, quiero que el plano de "Terraza" sea independiente del de "Comedor", para
poder editar la disposición de una zona sin afectar accidentalmente a las demás.

**Why this priority**: Ya está implícito en el modelo de datos de zonas existente (feature 038);
formalizarlo como historia de usuario asegura que el comportamiento de arrastre/guardado respete
ese límite, pero no es una interacción nueva en sí misma.

**Independent Test**: Editar y guardar el plano de la zona "Comedor"; cambiar a la pestaña
"Terraza" y comprobar que sus mesas conservan sus posiciones previas, sin ningún cambio heredado
de "Comedor".

**Acceptance Scenarios**:

1. **Given** el usuario está en modo edición sobre la pestaña "Comedor", **When** cambia a la
   pestaña "Terraza", **Then** el modo edición y los cambios pendientes de "Comedor" no se
   trasladan a "Terraza" (cada zona gestiona su propio estado de edición y guardado).

---

### Edge Cases

- ¿Qué pasa si dos usuarios editan el plano de la misma zona a la vez desde dos dispositivos y
  ambos guardan? El sistema debe evitar que el segundo guardado sobrescriba silenciosamente el
  primero sin que el usuario lo sepa (ver FR-011).
- ¿Qué pasa con una mesa que tiene una cuenta abierta (ocupada) mientras se reordena el plano?
  Debe poder moverse igual que cualquier otra mesa: el plano es una propiedad física de la mesa,
  no de su estado de ocupación.
- ¿Qué pasa si se crea una mesa nueva mientras el plano ya tiene posiciones guardadas? Debe
  aparecer en el lienzo en una celda libre por defecto, nunca superpuesta a una existente ni fuera
  del lienzo visible.
- ¿Qué pasa si se elimina una mesa? Su celda queda libre para futuras mesas; no deja "huecos
  fantasma" que bloqueen el reacomodo automático.
- ¿Qué pasa si el lienzo de una zona no tiene ninguna mesa todavía? Se muestra vacío con una
  indicación de cómo añadir la primera mesa (mismo mensaje que ya existe hoy en la Sala cuando no
  hay mesas configuradas).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir activar y desactivar un "modo edición del plano" desde la
  pantalla de Sala operativa, mediante un control explícito (botón/interruptor), visible solo para
  usuarios con permiso para gestionar la configuración del POS.
- **FR-002**: En modo edición, el sistema DEBE permitir arrastrar cualquier mesa de la zona activa
  a una nueva posición dentro del lienzo de esa misma zona.
- **FR-003**: El sistema DEBE representar cada mesa en el lienzo con la forma configurada
  (redonda, cuadrada, rectangular o barra) y el tamaño configurado (número de comensales o
  dimensión relativa), de forma visualmente distinguible entre formas.
- **FR-004**: El sistema DEBE permitir configurar/editar la forma y el tamaño de una mesa desde el
  propio modo edición del plano (sin necesitar salir a otra pantalla).
- **FR-005**: El sistema NO DEBE permitir que dos mesas de la misma zona queden solapadas en la
  misma posición tras soltar un arrastre: si el destino está ocupado, la mesa que ya estaba ahí se
  reubica automáticamente en la posición libre más cercana (distancia euclídea, en línea recta,
  respecto a su celda original) dentro de la misma zona.
- **FR-006**: Si no existe ninguna posición libre en la zona para reubicar a la mesa desplazada, el
  sistema DEBE cancelar la operación (la mesa arrastrada vuelve a su posición anterior) y avisar al
  usuario del motivo.
- **FR-007**: Los cambios de posición, forma y tamaño realizados durante el modo edición DEBEN
  quedar como cambios pendientes (solo reflejados en la pantalla) hasta que el usuario pulse
  explícitamente "Guardar plano"; ningún cambio se persiste por el solo hecho de soltar una mesa.
- **FR-008**: Al pulsar "Guardar plano", el sistema DEBE persistir de una sola vez todas las
  posiciones, formas y tamaños pendientes de la zona activa.
- **FR-009**: Si el usuario abandona el modo edición (o navega a otra zona/pantalla) sin pulsar
  "Guardar plano", el sistema DEBE descartar los cambios pendientes y mostrar la última disposición
  guardada la próxima vez que se entre a esa zona.
- **FR-010**: Cada zona DEBE tener su propio plano independiente: reordenar/guardar mesas en una
  zona no debe alterar la disposición guardada de ninguna otra zona.
- **FR-011**: El sistema DEBE detectar guardados concurrentes en conflicto sobre el mismo plano
  (dos ediciones simultáneas desde dispositivos distintos) e informar al usuario en vez de
  sobrescribir silenciosamente los cambios del otro dispositivo.
- **FR-012**: Fuera del modo edición, el plano DEBE mostrarse en modo solo-lectura, con el mismo
  código de color de estado (libre/ocupada/mesa olvidada) que ya usa la pantalla de Sala actual,
  sin controles de arrastre visibles.
- **FR-013**: El sistema DEBE seguir permitiendo abrir una mesa (iniciar o continuar una cuenta)
  tocándola desde el plano en modo solo-lectura, igual que hoy permite hacerlo desde las tarjetas
  de la Sala.
- **FR-014**: Una mesa nueva sin posición previa DEBE aparecer en el lienzo de su zona en una
  posición libre por defecto, nunca superpuesta a una mesa existente.
- **FR-015**: Al eliminar una mesa, su posición en el plano DEBE quedar disponible para que otras
  mesas puedan reubicarse ahí (no debe bloquear futuros reacomodos).

### Key Entities *(include if feature involves data)*

- **Mesa (existente, ampliada)**: además de su nombre y zona, gana una posición dentro del lienzo
  de su zona (fila y columna dentro de una rejilla de 8×6 celdas por zona, que garantiza ausencia
  de solapamiento por construcción), una forma (redonda/cuadrada/rectangular/barra) y un tamaño
  discreto (pequeña/mediana/grande). La forma/tamaño/posición son propiedades físicas del
  mobiliario, independientes de si la mesa está libre u ocupada.
- **Zona (existente)**: sigue agrupando mesas; para esta feature actúa además como el contenedor
  del lienzo — el espacio de posiciones válidas es propio de cada zona y no se comparte entre
  zonas.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un encargado de sala puede reorganizar y guardar la disposición completa de una zona
  de 10 mesas en menos de 3 minutos la primera vez que usa el editor, sin ayuda externa.
- **SC-002**: El 100% de los intentos de soltar una mesa sobre una posición ocupada terminan en un
  plano sin mesas solapadas (ninguna mesa queda oculta o inaccesible tras el reacomodo).
- **SC-003**: Tras guardar un plano, la disposición se mantiene idéntica en el 100% de las
  siguientes cargas de la pantalla de Sala (no hay pérdida ni reordenamiento espontáneo).
- **SC-004**: El personal operativo puede identificar una mesa concreta en el plano por su
  posición/forma en menos tiempo del que tardaba con el grid automático anterior (medido de forma
  cualitativa: el personal reconoce la mesa sin leer su nombre/número).
- **SC-005**: Ningún cambio realizado en modo edición se persiste accidentalmente si el usuario no
  pulsa "Guardar plano" (verificado en el 100% de los casos de prueba de abandono sin guardar).

## Assumptions

- El "tamaño" de una mesa se modela como una escala discreta de 3 valores (pequeña/mediana/grande),
  no como dimensiones libres en centímetros ni número exacto de comensales — coherente con que el
  plano usa una rejilla de celdas discretas, no coordenadas libres.
- Mesas creadas antes de esta feature (sin forma/tamaño/posición previos) reciben forma cuadrada,
  tamaño mediano y se colocan automáticamente en las primeras celdas libres del lienzo de su zona,
  en el mismo orden en que aparecían por el campo `orden` existente.
- El permiso para entrar en modo edición del plano es el mismo que ya gobierna la gestión de
  configuración del POS (mesas/zonas), no un permiso nuevo independiente.
- El lienzo de cada zona tiene un tamaño fijo de 8 columnas × 6 filas (48 celdas), suficiente para
  salas de hostelería típicas; no hay scroll infinito ni redimensión del lienzo en v1. Si una zona
  necesitara más de 48 mesas, queda fuera de alcance de esta versión.
- La detección de guardado concurrente (FR-011) usa el mismo criterio de bloqueo optimista por
  versión que ya emplea `pos_cuentas` en el módulo (feature 038), aplicado ahora al plano de la
  zona.
- Fuera de v1 queda: mover una mesa de una zona a otra arrastrándola entre pestañas, formas
  personalizadas más allá de las cuatro listadas, y redimensionar el lienzo por zona desde la
  propia pantalla de edición (el tamaño del lienzo se asume suficiente por defecto).
