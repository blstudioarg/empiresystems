# Feature Specification: Forma y medidas configurables de la zona en el plano de sala

**Feature Branch**: `042-plano-sala-forma-medidas`

**Created**: 2026-08-21

**Status**: Draft

**Input**: User description: "Sala del POS: forma y medidas configurables de la zona (opción A, máscara de celdas). La rejilla del plano deja de ser fija 8×6: cada zona define sus propias medidas (columnas × filas) desde el editor del plano, y además el encargado puede recortar la planta «despintando» celdas para que la sala deje de ser un rectángulo (formas en L, en U, esquinas cortadas, huecos de pilares o patios). Las celdas inactivas no admiten mesas ni se dibujan como suelo. Se conserva el sistema de coordenadas actual (fila/columna, mesa = rectángulo de celdas de la feature 040) y sus invariantes."

## Documentación consultada (regla de oro del proyecto)

- **`docs/04-front-guidelines.md`, "Dos vistas de los mismos datos: el estado y el destino se
  comparten, no se repiten (feature 041)"** (líneas ~1338-1366): la forma de la planta es parte del
  **contorno** del plano, no del contenido interior, y por tanto es invariante entre el editor y la
  vista de servicio. Se dibuja desde el módulo compartido (`pos-plano-dibujo.js`), **nunca** detrás
  del guard de permiso del editor, o el camarero sin permiso de configuración vería una sala
  rectangular distinta de la que colocó el encargado (FR-012).
- **`docs/04-front-guidelines.md`, "Feedback de bloqueo cuando el borde ya comunica estado
  (feature 040)"** (líneas ~1300-1314): el color del borde de una mesa está reservado para su
  estado (libre / ocupada / olvidada). El rechazo de "no puedo desactivar esta celda porque hay una
  mesa encima" no puede teñir ese borde; y como el pintado de celdas es un gesto continuo (arrastre
  sobre varias celdas), tampoco puede resolverse con un toast por celda (FR-010).
- **`docs/04-front-guidelines.md`, "Tarjeta de mesa y sus tres estados (Sala del POS, feature 038)"**
  (líneas ~1260-1272): la rejilla de tarjetas de la Sala no representa el espacio físico; esta
  feature no la toca. Solo cambia el plano.
- **`docs/04-front-guidelines.md`, "Partición de un archivo JS grande en módulos con estado
  compartido (feature 038)"** (líneas ~1232-1258): la geometría de la zona (medidas + máscara) es
  estado compartido entre el editor y el dibujo; vive en un único sitio y se muta en el sitio, no se
  reasigna por módulo.
- **`docs/04-front-guidelines.md`, "Alta inline en un listado: confirmación explícita, nunca por
  `blur`"** (líneas ~1315-1337): cambiar las medidas de la zona es una edición de un registro que ya
  existe, no un alta; se aplica al confirmar el campo, y se persiste con el guardado explícito del
  plano que ya existe (FR-013), nunca automáticamente.
- **`docs/04-front-guidelines.md`, "Ayuda contextual"** (líneas ~981-1016): la Sala del POS ya tiene
  guía in-app (`resources/views/ayuda/pos-sala.blade.php`); al cambiar cómo se define el lienzo de
  una zona, la guía entra en el mismo cambio (FR-016).
- **`docs/03-modelo-datos.md`, módulo POS (features 038/039/040)**: `pos_zonas` y `pos_mesas` viven
  bajo `tenant_id` con prefijo `pos_`; `pos_zonas` ya tiene el contador `version` del bloqueo
  optimista. Esta feature añade atributos de geometría a `pos_zonas` y no cambia el cálculo derivado
  del estado ocupada/olvidada.
- **`.specify/memory/constitution.md`**: Principio I (toda query bajo el scope de tenant, con tests
  de aislamiento), Principio III (el servidor revalida siempre la geometría recibida; el cliente
  nunca es la única barrera), Principio IV (test-first sobre la validación de geometría), Principio
  V (sin build step ni dependencias nuevas; la rejilla ya se dimensiona por variables CSS
  `--plano-cols` / `--plano-rows`, así que hacerla variable no exige un motor nuevo).

## Contexto: qué está roto hoy

Hoy **todas las zonas de todos los tenants comparten una rejilla fija de 8 columnas × 6 filas**, y
esa rejilla siempre es un rectángulo lleno. Las consecuencias son dos:

1. **Las medidas no encajan.** Una terraza de cuatro mesas desperdicia media pantalla de suelo
   vacío; un comedor grande no cabe y obliga a partirlo en zonas artificiales que no existen en la
   realidad del local.
2. **La forma miente.** Ninguna sala real es un rectángulo perfecto: hay salas en L, patios
   interiores, huecos de escalera, pilares en medio. Al dibujarlas siempre como un rectángulo lleno,
   el camarero pierde justo los puntos de referencia que le permiten reconocer su sala de un vistazo
   y encontrar la mesa 12 sin leer los nombres uno a uno.

Esta feature convierte el lienzo de un valor fijo del sistema en **un dato de cada zona**: sus
medidas, y qué parte de ese rectángulo es realmente suelo.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ajustar las medidas de la zona (Priority: P1)

El encargado entra en el modo de edición del plano de una zona y ajusta cuántas columnas y cuántas
filas tiene esa sala. El lienzo crece o se encoge inmediatamente en la previsualización, con las
mesas conservando su posición. Al pulsar el botón de guardar del plano, las medidas se persisten
junto con el resto del plano.

**Why this priority**: Es el cimiento. Sin medidas propias, recortar la forma solo permitiría
recortar dentro de un 8×6 ajeno al local. Además entrega valor por sí sola: una terraza pequeña deja
de dibujarse como un salón vacío.

**Independent Test**: Se puede probar entrando en modo edición de una zona, cambiando sus medidas,
guardando y recargando la Sala: el lienzo debe reaparecer con las medidas nuevas y las mesas en su
sitio.

**Acceptance Scenarios**:

1. **Given** una zona con las medidas por defecto, **When** el encargado aumenta el número de
   columnas, **Then** el lienzo se ensancha en la previsualización y las mesas existentes conservan
   su fila y columna.
2. **Given** una zona con medidas cambiadas pero sin guardar, **When** el encargado cancela la
   edición, **Then** el lienzo vuelve a sus medidas anteriores.
3. **Given** una zona con medidas cambiadas y guardadas, **When** se recarga la Sala, **Then** la
   zona conserva sus medidas nuevas, tanto en el editor como en la vista de servicio.
4. **Given** dos zonas de la misma sala, **When** el encargado cambia las medidas de una, **Then**
   la otra conserva las suyas: las medidas son de cada zona, no del local.

---

### User Story 2 - Recortar la planta para que deje de ser un rectángulo (Priority: P1)

El encargado marca celdas del lienzo como "fuera de la sala" y esas celdas dejan de dibujarse como
suelo: aparecen como vacío. Así puede representar una sala en L, un patio interior, el hueco de una
escalera o un pilar. Puede volver a marcarlas como suelo del mismo modo. El recorte se persiste con
el guardado explícito del plano.

**Why this priority**: Es la petición central del usuario ("no solo una sala rectangular, sino darle
una forma"). Depende de la P1 anterior únicamente en lo práctico (recortar tiene poco sentido sin
medidas propias), pero es la mitad del valor de la feature.

**Independent Test**: Se puede probar desactivando un bloque de celdas de una esquina, guardando y
recargando: la zona debe dibujarse con esa esquina vacía, tanto en el editor como en la vista de
servicio.

**Acceptance Scenarios**:

1. **Given** un lienzo rectangular vacío, **When** el encargado marca como fuera de la sala las
   celdas de una esquina, **Then** el plano se dibuja con forma de L y esas celdas no se ven como
   suelo.
2. **Given** una celda marcada como fuera de la sala, **When** el encargado intenta mover o agrandar
   una mesa sobre ella, **Then** la mesa no puede ocuparla, con la misma regla de bloqueo que se
   aplica hoy a una celda ocupada por otra mesa.
3. **Given** una celda marcada como fuera de la sala, **When** el encargado la vuelve a marcar como
   suelo, **Then** vuelve a admitir mesas.
4. **Given** un recorte hecho pero sin guardar, **When** el encargado cancela la edición, **Then**
   la planta vuelve a su forma anterior.

---

### User Story 3 - Nunca perder una mesa por cambiar el lienzo (Priority: P1)

Si el encargado intenta encoger la zona o recortar una parte de la planta donde hay mesas colocadas,
el sistema **no** mueve ni borra nada por su cuenta: bloquea la operación y le dice exactamente
cuántas mesas lo impiden, para que él decida moverlas primero.

**Why this priority**: Es indisociable de las dos anteriores. Una feature que reacomoda o borra
mesas en silencio al tocar un número convierte un ajuste cosmético en una pérdida de datos del
salón, y rompe el invariante que sostienen las features 039-041.

**Independent Test**: Colocando una mesa en la última columna e intentando reducir el número de
columnas; y marcando como fuera de la sala una celda ocupada por una mesa.

**Acceptance Scenarios**:

1. **Given** una mesa apoyada en la última columna de la zona, **When** el encargado intenta reducir
   el número de columnas por debajo de esa posición, **Then** la operación se rechaza con un aviso
   que indica cuántas mesas quedarían fuera, y el lienzo conserva sus medidas.
2. **Given** una celda ocupada por una mesa, **When** el encargado intenta marcarla como fuera de la
   sala, **Then** la celda no cambia y el sistema señala la mesa que lo impide.
3. **Given** el encargado mueve primero las mesas afectadas, **When** repite la reducción o el
   recorte, **Then** la operación se completa normalmente.

---

### User Story 4 - El camarero ve la misma sala que colocó el encargado (Priority: P2)

La vista de servicio del plano (feature 041) dibuja la zona con sus medidas y su forma reales, sin
que el camarero necesite permiso de configuración ni entre en modo edición.

**Why this priority**: Es lo que hace que la feature signifique algo en el día a día: la forma existe
para que quien atiende reconozca su sala. Va detrás porque las tres anteriores ya son verificables
en el editor.

**Independent Test**: Abriendo la Sala con un usuario **sin** permiso de configuración del plano y
comparando el contorno con el que ve el encargado en modo edición.

**Acceptance Scenarios**:

1. **Given** una zona con medidas propias y celdas recortadas, **When** un camarero sin permiso de
   configuración abre el plano en modo servicio, **Then** ve exactamente el mismo contorno, medidas
   y forma que el encargado.
2. **Given** una zona más ancha que la pantalla de la tablet, **When** el camarero abre el plano,
   **Then** puede alcanzar todas las mesas de la zona sin que ninguna quede inaccesible.

---

### Edge Cases

- **Zonas existentes**: toda zona creada antes de esta feature conserva exactamente el lienzo que
  tenía (8 columnas × 6 filas, planta completa), sin que nadie tenga que reconfigurarla.
- **Zona nueva**: nace con las mismas medidas por defecto y la planta entera como suelo; la feature
  no obliga a diseñar una sala antes de poder usarla.
- **Recortar toda la sala**: una zona no puede quedarse sin ninguna celda de suelo; debe conservar al
  menos una.
- **Agrandar la zona**: siempre es posible (no puede dejar mesas fuera); las celdas nuevas nacen como
  suelo.
- **Celdas recortadas que quedan fuera al encoger**: al reducir las medidas, el recorte de las celdas
  que dejan de existir se descarta; si se vuelve a agrandar, esas celdas vuelven como suelo, no como
  recorte "recordado".
- **Mesa nueva en una zona recortada**: al crear una mesa, el sistema la coloca en una celda de suelo
  libre; nunca aparece sobre una parte recortada de la planta.
- **Zona muy grande en tablet**: el lienzo debe seguir siendo alcanzable en una tablet, con
  desplazamiento si hace falta, sin que ninguna mesa quede inaccesible.
- **Otro dispositivo modificó el plano mientras se editaba**: el guardado se rechaza con el mismo
  aviso de conflicto que hoy, sin guardar nada a medias — las medidas y el recorte viajan en el mismo
  guardado que las mesas, no por separado.
- **Payload manipulado**: una petición de guardado con medidas fuera de los límites admitidos, con un
  recorte que deja mesas sobre celdas inactivas, sin ninguna celda de suelo, o con mesas fuera de la
  rejilla, se rechaza entera, sin aplicar ninguno de sus cambios.
- **Mesa ocupada** (con cuenta abierta): no impide cambiar el lienzo mientras su área siga siendo
  suelo válido; el lienzo es una propiedad del salón, no del servicio en curso.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Cada zona MUST tener sus propias medidas de rejilla (número de columnas y número de
  filas), independientes de las de cualquier otra zona.
- **FR-002**: El sistema MUST persistir, por zona, qué celdas de su rejilla están **fuera de la
  sala** (no son suelo), de modo que la planta pueda tener una forma no rectangular.
- **FR-003**: En modo edición, el encargado MUST poder cambiar el número de columnas y de filas de la
  zona, dentro de unos límites mínimos y máximos definidos por el sistema, y ver el efecto
  inmediatamente en la previsualización.
- **FR-004**: En modo edición, el encargado MUST poder marcar y desmarcar celdas como fuera de la
  sala, incluyendo el marcado continuo de varias celdas en un mismo gesto de arrastre.
- **FR-005**: Una celda marcada como fuera de la sala MUST dibujarse como vacío (no como suelo) en
  todas las vistas del plano, y MUST ser inequívocamente distinguible de una celda de suelo libre.
- **FR-006**: El sistema MUST impedir que el área de una mesa incluya cualquier celda que esté fuera
  de la sala, tanto al mover como al redimensionar, con la misma regla de bloqueo que ya se aplica a
  una celda ocupada por otra mesa (feature 040, FR-005/FR-007).
- **FR-007**: El sistema MUST rechazar la reducción de las medidas de una zona cuando alguna mesa
  quedaría total o parcialmente fuera de la rejilla resultante, **sin** mover, encoger ni borrar
  ninguna mesa.
- **FR-008**: El sistema MUST rechazar marcar como fuera de la sala una celda ocupada por una mesa,
  **sin** mover, encoger ni borrar esa mesa.
- **FR-009**: Cuando una operación se rechaza por FR-007, el sistema MUST indicar **cuántas mesas** lo
  impiden, no un mensaje genérico de error.
- **FR-010**: El feedback de rechazo al intentar recortar una celda ocupada MUST darse por un canal
  distinto del color del borde de la mesa (reservado para su estado) y MUST ser apto para un gesto
  continuo, es decir, no puede generar un aviso apilado por cada celda recorrida en el arrastre.
- **FR-011**: Toda zona MUST conservar al menos una celda de suelo; el sistema debe impedir recortar
  la planta entera.
- **FR-012**: Las medidas y la forma de la zona MUST calcularse y dibujarse desde el mismo origen
  compartido por el editor y la vista de servicio, de modo que ambas muestren un contorno idéntico, y
  ese origen MUST estar disponible para un usuario sin permiso de configuración del plano.
- **FR-013**: Las medidas y el recorte MUST persistirse con el mismo guardado explícito por botón, el
  mismo bloqueo optimista por versión de zona y la misma atomicidad que el resto del plano — nunca de
  forma automática al soltar el gesto, y nunca en una operación separada de la de las mesas.
- **FR-014**: El servidor MUST revalidar íntegramente la geometría recibida al guardar (medidas
  dentro de los límites, ausencia de mesas fuera de la rejilla, ausencia de mesas sobre celdas
  inactivas, al menos una celda de suelo, ausencia de solapamientos) y rechazar la petición completa
  si algo no cumple, sin aplicar cambios parciales.
- **FR-015**: Las zonas existentes MUST conservar su lienzo actual sin intervención del usuario:
  mismas medidas que hoy y planta completa como suelo, con todas sus mesas intactas.
- **FR-016**: La guía in-app de la Sala del POS MUST actualizarse para describir cómo se ajustan las
  medidas de una zona y cómo se recorta su planta.
- **FR-017**: La base de conocimiento del asistente IA MUST actualizarse con el nuevo concepto de
  lienzo por zona (medidas propias y forma recortable).
- **FR-018**: Ajustar las medidas y recortar la planta MUST exigir el mismo permiso que ya rige la
  edición del plano; un usuario sin ese permiso MUST poder ver la forma resultante pero no cambiarla.
- **FR-019**: Los controles de medidas y de recorte MUST ser utilizables con el dedo en tablet, sin
  exigir precisión de ratón, y el gesto de recortar no puede confundirse con el de mover una mesa.

- **FR-020**: Al crear una mesa nueva en una zona, el sistema MUST colocarla en una celda de suelo
  libre de esa zona, nunca sobre una celda que esté fuera de la sala.

### Key Entities

- **Zona**: contenedor del plano. Pasa a tener **lienzo propio**: unas medidas (columnas × filas) y
  un conjunto de celdas marcadas como fuera de la sala, que juntos definen la forma de la planta.
  Conserva su contador de versión para el bloqueo optimista y su suplemento.
- **Celda**: unidad de la rejilla de una zona, identificada por fila y columna. Pasa a tener dos
  estados posibles: **suelo** (admite mesas) o **fuera de la sala** (no se dibuja como suelo y no
  admite mesas). Es un dato de la zona, no un registro con vida propia.
- **Mesa del plano**: sin cambios estructurales. Sigue siendo un rectángulo de celdas anclado en una
  celda de origen; gana una restricción más (no puede pisar celdas fuera de la sala).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un encargado puede dejar el lienzo de una zona con las medidas de su sala real en menos
  de 30 segundos, sin salir del editor del plano.
- **SC-002**: Un encargado puede representar una sala en L (recortando un bloque de esquina) en menos
  de 1 minuto y sin ningún diálogo intermedio.
- **SC-003**: En cualquier plano guardado, el 100% de las mesas ocupa un área que cae íntegramente
  dentro de la rejilla de su zona, no se solapa con ninguna otra y no incluye ninguna celda fuera de
  la sala — invariante verificable sobre los datos, no solo sobre lo que se ve.
- **SC-004**: Ningún cambio de medidas o de forma provoca la pérdida, el borrado o el desplazamiento
  automático de una mesa: en el 100% de los intentos conflictivos la operación se rechaza y el plano
  queda exactamente como estaba.
- **SC-005**: Un intento de guardar un lienzo geométricamente inválido no modifica ningún dato.
- **SC-006**: El contorno (medidas y forma) que ve un camarero sin permiso de configuración coincide
  al 100% con el que ve el encargado en modo edición, para la misma zona.
- **SC-007**: Tras el despliegue, el 100% de las zonas existentes conserva su plano tal cual estaba,
  sin ninguna mesa desplazada ni desaparecida.

## Assumptions

- **Límites de medidas**: cada zona admite entre 4 y 24 columnas y entre 4 y 24 filas. El mínimo evita
  lienzos inservibles; el máximo evita planos imposibles de leer en una tablet y mantiene acotado el
  coste de validación. Es un valor del sistema, no configurable por tenant.
- **Valor por defecto**: una zona nueva nace con 8 columnas × 6 filas y la planta entera como suelo —
  exactamente el lienzo fijo de hoy —, de modo que quien no quiera diseñar nada no note el cambio.
- **El recorte es de celdas enteras**, alineadas a la rejilla. No hay diagonales, curvas, vértices
  libres ni rotación: la unidad mínima de forma sigue siendo la celda, igual que la unidad mínima de
  mesa (feature 040).
- **El tamaño en píxeles de la celda no cambia** con las medidas de la zona: un lienzo más grande es
  un lienzo más grande, con desplazamiento si no cabe, no un lienzo con celdas más pequeñas.
- **Ninguna medida se expresa en metros ni en unidades físicas.** La rejilla es una representación
  relativa del salón, no un plano a escala; convertirla en un plano acotado sería otra feature.
- **La rejilla de tarjetas de mesa de la Sala no cambia**: no representa el espacio físico y por
  tanto no le afectan ni las medidas ni la forma de la zona.
- Se reutilizan los permisos, el endpoint de guardado del plano y el bloqueo optimista por versión ya
  existentes; la feature no crea permisos nuevos, ni entradas de menú, ni un guardado aparte.
- No se añaden dependencias de terceros ni build step (Principio V): el lienzo ya se dimensiona por
  variables CSS, así que hacerlo variable no requiere un motor de dibujo nuevo.
- El rendimiento se mantiene con el mayor lienzo admitido (24×24 = 576 celdas) sin degradar la
  interacción del plano.

## Fuera de alcance

- **Polígonos libres, posicionamiento en píxeles y rotación de mesas.** Romperían el sistema de
  coordenadas por celdas del que dependen las features 039, 040 y 041 y convertirían el plano en un
  editor de geometría. Si algún día hicieran falta, serían una migración explícita, no una
  convivencia con la rejilla.
- **Elementos decorativos no-mesa** (pilar, barra, puerta, escalera, planta) como objetos colocables
  con nombre propio. Un pilar puede representarse hoy recortando su celda; darle identidad, aspecto y
  etiqueta es una feature posterior.
- **Plano a escala con medidas físicas** (metros, acotaciones, importación de un plano del
  arquitecto).
- **Copiar el lienzo de una zona a otra** o plantillas de sala predefinidas.
