# Feature Specification: Mesas redimensionables por celdas en el plano del POS

**Feature Branch**: `040-pos-mesas-redimensionables`

**Created**: 2026-08-19

**Status**: Draft

**Input**: User description: "Al hacer clic y arrastrar del borde de una mesa en el editor de planos, poder agrandarla un cuadrado más o uno menos. Implica cambiar la estructura de las mesas para que puedan ocupar más cuadrados."

## Documentación consultada (regla de oro del proyecto)

- **`docs/04-front-guidelines.md`, "Tarjeta de mesa y sus tres estados (Sala del POS, feature 038)"**
  (líneas ~1260-1272): el **borde** de la mesa es el canal que comunica el estado (gris = libre,
  verde = ocupada, ámbar = olvidada) y la vista **nunca hace aritmética** que le corresponda al
  servidor. Esta feature convierte ese mismo borde en la superficie de arrastre del
  redimensionado, así que el feedback de "no puedo crecer más" **no puede** apoyarse en cambiar el
  color del borde (colisionaría con el significado de estado ya establecido): tiene que ser un
  canal distinto (ver FR-009).
- **`docs/04-front-guidelines.md`, "Estado de carga en botones (AJAX/fetch)"** (líneas ~395-459):
  el guardado del plano ya usa `withButtonLoading`; el redimensionado **no añade peticiones
  nuevas** (viaja en el mismo guardado explícito por botón), así que no introduce estados de carga
  adicionales.
- **`docs/04-front-guidelines.md`, "Acción frecuente y consecuente pero no destructiva: toast con
  Deshacer, no modal"** (líneas ~353-394): redimensionar es una acción frecuente y de bajo riesgo
  dentro de un modo edición que ya tiene guardado explícito y botón de cancelar; **no** lleva
  modal de confirmación ni toast de deshacer propio — el "deshacer" real es no guardar.
- **`docs/04-front-guidelines.md`, "Ayuda contextual"** (líneas ~981-1016): la Sala del POS tiene
  guía in-app; al cambiar cómo se da tamaño a una mesa (desaparece el selector de tamaños), la
  guía correspondiente entra en el mismo cambio (FR-016).
- **`docs/03-modelo-datos.md`, módulo POS (feature 038/039)**: `pos_mesas` vive bajo `tenant_id`
  con prefijo `pos_`, y el estado ocupada/olvidada **no se almacena** (se deriva de `pos_cuentas`).
  Esta feature añade atributos geométricos a `pos_mesas` y no toca ese cálculo derivado.
- **`.specify/memory/constitution.md`**: Principio I (todo query bajo el scope de tenant),
  Principio III (el servidor revalida siempre la geometría recibida, el cliente nunca es la única
  barrera), Principio IV (test-first en la lógica de solapamiento), Principio V (sin build step ni
  dependencias nuevas: jQuery UI ya está vendorizado y cargado en la vista).

## Contexto: qué está roto hoy

En el plano actual **una mesa ocupa exactamente una celda** de la rejilla de la zona, y el tamaño
es un atributo de tres valores (pequeña / mediana / grande) que **solo escala los píxeles dentro de
esa celda**. Como consecuencia, las mesas alargadas (rectangular y barra) se dibujan invadiendo
visualmente las celdas vecinas **sin reservarlas**: el plano muestra dos mesas superpuestas y las
da por válidas, porque a efectos de colisión cada una sigue siendo un punto. El plano miente sobre
el espacio que ocupa cada mesa, que es justo lo que un plano de sala tiene que representar bien.

Esta feature elimina esa mentira: el espacio que se ve ocupado pasa a ser el espacio que
realmente se reserva.

## Clarifications

### Session 2026-08-19

- P: ¿Qué pasa con el atributo de tamaño (pequeña/mediana/grande) existente?
  → R: **Se sustituye** por la ocupación en celdas. El redimensionado por arrastre pasa a ser la
  única forma de dar tamaño a una mesa y el selector de tamaños desaparece de la interfaz.
- P: ¿Qué ocurre si al crecer la mesa invade a otra?
  → R: **Se bloquea el crecimiento** (el borde no avanza más allá del último tamaño válido). No se
  reacomodan otras mesas en cadena, a diferencia de lo que hace hoy el arrastre de posición.
- P: ¿Cómo se convierten las mesas ya existentes?
  → R: Todas pasan a ocupar una sola celda, salvo las que hoy ya se dibujan alargadas: las
  rectangulares pasan a 2 celdas de ancho por 1 de alto, y las barras a 3 por 1 — es decir, se
  formaliza como ocupación real lo que ya aparentaban ocupar.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Agrandar y encoger una mesa arrastrando su borde (Priority: P1)

El encargado entra en el modo de edición del plano de una zona, sitúa el puntero (o el dedo) sobre
el borde de una mesa y arrastra hacia fuera: la mesa crece de golpe una celda entera, encajada en
la rejilla, y sigue creciendo celda a celda mientras arrastra. Arrastrando hacia dentro, encoge del
mismo modo hasta el mínimo de una celda. Al soltar, la mesa queda con su nuevo tamaño en la
previsualización; el cambio se persiste al pulsar el botón de guardar del plano, igual que los
movimientos de posición.

**Why this priority**: Es la petición completa del usuario. Sin esto no hay feature; todo lo demás
es consecuencia de soportarlo correctamente.

**Independent Test**: Se puede probar entrando en modo edición, arrastrando el borde de una mesa
suelta en una zona con espacio libre, guardando y recargando la pantalla: la mesa debe reaparecer
con el tamaño nuevo.

**Acceptance Scenarios**:

1. **Given** una mesa de una celda con espacio libre a su derecha, **When** el usuario arrastra su
   borde derecho hacia fuera lo suficiente para cubrir la celda vecina, **Then** la mesa pasa a
   ocupar dos celdas de ancho, encajada exactamente en la rejilla (sin quedar a medio camino entre
   dos celdas).
2. **Given** una mesa de tres celdas de ancho, **When** el usuario arrastra su borde derecho hacia
   dentro, **Then** la mesa encoge de celda en celda y **nunca** por debajo de una celda de ancho
   por una de alto.
3. **Given** una mesa redimensionada pero con el plano aún sin guardar, **When** el usuario sale
   del modo edición descartando los cambios, **Then** la mesa vuelve a su tamaño anterior.
4. **Given** una mesa redimensionada y guardada, **When** se recarga la Sala y se vuelve a entrar
   en modo edición, **Then** la mesa conserva el tamaño nuevo.

---

### User Story 2 - El plano deja de permitir solapamientos (Priority: P1)

Al crecer, una mesa nunca puede invadir el espacio de otra ni salirse de la rejilla de la zona. El
borde simplemente deja de avanzar en la dirección bloqueada, con una señal visual clara de que se
alcanzó el límite. La mesa puede seguir creciendo en otras direcciones si ahí sí hay hueco. Del
mismo modo, mover una mesa (funcionalidad ya existente) pasa a tener en cuenta el rectángulo
completo, no solo su celda de origen.

**Why this priority**: Es indisociable de la P1 anterior — permitir redimensionar sin control de
solapamiento produciría planos incoherentes, que es precisamente el defecto que la feature viene a
corregir.

**Independent Test**: Colocando dos mesas en celdas contiguas e intentando agrandar una contra la
otra; y agrandando una mesa situada en el borde de la rejilla hacia fuera.

**Acceptance Scenarios**:

1. **Given** dos mesas en celdas contiguas, **When** el usuario arrastra el borde de una hacia la
   otra, **Then** el borde no avanza sobre la celda ocupada y la mesa conserva su último tamaño
   válido.
2. **Given** una mesa apoyada en el límite derecho de la rejilla, **When** el usuario arrastra su
   borde derecho hacia fuera, **Then** el borde no avanza y la mesa no se sale de la zona.
3. **Given** una mesa bloqueada a la derecha pero con hueco arriba, **When** el usuario arrastra su
   borde superior, **Then** la mesa sí crece hacia arriba.
4. **Given** una mesa de dos celdas de ancho, **When** el usuario la mueve a una posición donde su
   segunda celda caería sobre otra mesa, **Then** el sistema aplica la misma regla de colisión que
   para cualquier celda ocupada (no se permite el solapamiento silencioso).

---

### User Story 3 - Las sillas reflejan el tamaño real de la mesa (Priority: P2)

Las marcas de sillas dibujadas alrededor de una mesa dejan de ser un dibujo fijo por forma y pasan
a derivarse del contorno real: una mesa más larga muestra más sillas a lo largo de su lado largo,
de modo que el plano transmita de un vistazo la capacidad aproximada de cada mesa.

**Why this priority**: Es lo que hace que el redimensionado *signifique* algo para quien lee el
plano. Sin ello, una barra de cuatro celdas y otra de dos mostrarían las mismas sillas y el tamaño
sería puramente decorativo. Aun así, la feature es utilizable sin esto, por lo que va detrás.

**Independent Test**: Comparando visualmente dos mesas de la misma forma y distinto tamaño en el
plano: la más grande debe mostrar más sillas.

**Acceptance Scenarios**:

1. **Given** una mesa alargada a lo largo de varias celdas, **When** se dibuja en el plano,
   **Then** muestra más marcas de silla en su lado largo que una mesa de una sola celda.
2. **Given** una mesa que crece de una celda a dos, **When** el usuario suelta el borde, **Then**
   las sillas se recalculan inmediatamente en la previsualización, sin necesidad de guardar.

---

### Edge Cases

- **Un plano guardado antes de esta feature**: todas sus mesas siguen siendo válidas y visibles
  tras la conversión, sin solapamientos nuevos ni mesas fuera de la rejilla.
- **Una mesa alargada convertida que ya no cabe**: si al formalizar la ocupación (por ejemplo una
  barra en la última columna, que pasaría a desbordar la rejilla) el rectángulo no cabe, la
  conversión debe reducir su ocupación hasta que quepa, nunca dejar datos inválidos.
- **Una mesa alargada convertida que pisa a otra mesa**: si dos mesas quedaran solapadas tras la
  conversión, la conversión debe reducir la ocupación de la que colisiona hasta que no haya
  solapamiento — el plano nunca puede quedar en un estado que la propia validación rechazaría.
- **Mesa ocupada** (con una cuenta abierta): se puede redimensionar igual que se puede mover hoy;
  el tamaño es una propiedad del plano, no del servicio en curso.
- **Redimensionar en pantalla táctil**: la zona de agarre del borde debe ser suficientemente grande
  para el dedo, dado que la Sala se usa en tablet.
- **Otro dispositivo modificó el plano mientras se editaba**: el guardado sigue rechazándose con el
  mismo aviso de conflicto que hoy, sin guardar nada a medias.
- **Payload manipulado**: una petición de guardado con un rectángulo que se sale de la rejilla, que
  solapa a otra mesa, o con una ocupación menor que una celda, se rechaza entera, sin aplicar
  ninguno de sus cambios.
- **Zona sin espacio**: una mesa rodeada por todos los lados no puede crecer en ninguna dirección;
  debe poder encoger igualmente.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Una mesa del plano MUST ocupar un área rectangular de un número entero de celdas de
  ancho por un número entero de celdas de alto, siendo el mínimo una celda por una celda.
- **FR-002**: El sistema MUST persistir la ocupación en celdas de cada mesa como parte de su
  configuración de plano, junto a su posición y forma.
- **FR-003**: En modo edición, el usuario MUST poder cambiar la ocupación de una mesa arrastrando
  desde sus bordes, en incrementos y decrementos de **una celda completa**, con encaje en la
  rejilla (nunca queda a medio camino entre dos celdas).
- **FR-004**: El redimensionado MUST poder aplicarse de forma independiente en cada dirección
  (ancho y alto), de modo que bloquear una dirección no impida crecer en otra.
- **FR-005**: El sistema MUST impedir que el área de una mesa se solape con el área de otra mesa de
  la misma zona, tanto al redimensionar como al mover.
- **FR-006**: El sistema MUST impedir que el área de una mesa sobresalga de los límites de la
  rejilla de la zona.
- **FR-007**: Cuando el crecimiento se bloquea por colisión o por límite de la rejilla, el sistema
  MUST detener el borde en el último tamaño válido y **no** desplazar ni reacomodar otras mesas.
- **FR-008**: El sistema MUST conservar el comportamiento actual de reacomodo por colisión al
  **mover** una mesa (la mesa desplazada busca hueco), aplicado ahora al rectángulo completo: si no
  existe ningún hueco del tamaño necesario, el movimiento se cancela y se avisa al usuario.
- **FR-009**: El sistema MUST dar feedback visual inmediato cuando el redimensionado se bloquea,
  por un canal distinto del color del borde, que ya está reservado para comunicar el estado de la
  mesa (libre / ocupada / olvidada).
- **FR-010**: El atributo de tamaño de tres valores (pequeña / mediana / grande) MUST retirarse del
  modelo y de la interfaz; la ocupación en celdas pasa a ser la única noción de tamaño de una mesa.
- **FR-011**: Los planos existentes MUST convertirse automáticamente sin intervención del usuario:
  toda mesa pasa a ocupar una celda, salvo las de forma rectangular (dos celdas de ancho por una de
  alto) y las de forma barra (tres por una).
- **FR-012**: La conversión MUST garantizar que ningún plano resultante quede con mesas solapadas o
  fuera de la rejilla, reduciendo la ocupación de la mesa afectada cuando sea necesario.
- **FR-013**: El servidor MUST revalidar íntegramente la geometría recibida al guardar (límites de
  la rejilla, ausencia de solapamientos, ocupación mínima) y rechazar la petición completa si algo
  no cumple, sin aplicar cambios parciales.
- **FR-014**: Las marcas de silla dibujadas alrededor de una mesa MUST derivarse de su contorno
  real (forma y ocupación en celdas), de modo que una mesa mayor muestre más sillas.
- **FR-015**: La ocupación en celdas de cada mesa MUST estar disponible para toda vista que dibuje
  el plano. La Sala en modo servicio (rejilla de tarjetas de mesa) **no dibuja el plano** y por tanto
  queda sin cambios: llevar el plano a la vista de servicio es una feature distinta y está fuera de
  alcance.
- **FR-016**: La guía in-app de la Sala del POS MUST actualizarse para describir el redimensionado
  por arrastre y dejar de mencionar el selector de tamaños retirado.
- **FR-017**: La base de conocimiento del asistente IA MUST actualizarse con la nueva forma de dar
  tamaño a una mesa.
- **FR-018**: El redimensionado MUST guardarse con el mismo guardado explícito por botón que el
  resto del plano — nunca automáticamente al soltar el borde — y descartarse si el usuario cancela
  la edición.
- **FR-019**: La superficie de agarre para redimensionar MUST ser utilizable con el dedo en tablet,
  sin exigir precisión de ratón.

### Key Entities

- **Mesa del plano**: una mesa de una zona, situada en una celda de origen de la rejilla y que
  ocupa desde ahí un rectángulo de N celdas de ancho por M de alto (mínimo 1×1). Conserva su forma
  (redonda, cuadrada, rectangular, barra), que ahora determina solo su aspecto y el reparto de
  sillas, no el espacio que reserva. Pierde el atributo de tamaño de tres valores.
- **Zona**: contenedor del plano, con una rejilla de dimensiones fijas y un contador de versión que
  detecta ediciones concurrentes. Sin cambios estructurales en esta feature.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un encargado puede dar a una mesa el tamaño que quiere (hasta 3 celdas de ancho) en
  menos de 10 segundos y sin abrir ningún menú ni diálogo intermedio.
- **SC-002**: En cualquier plano guardado, el 100% de las mesas ocupa un área que no se solapa con
  ninguna otra y que cae íntegramente dentro de la rejilla de su zona — invariante verificable
  sobre los datos, no solo sobre lo que se ve.
- **SC-003**: Tras la conversión de los planos existentes, el 100% de las mesas sigue presente y
  visible, sin ninguna pérdida ni desaparición.
- **SC-004**: Un intento de guardar un plano geométricamente inválido no modifica ningún dato: el
  plano queda exactamente como estaba antes del intento.
- **SC-005**: En una tablet, un usuario acierta a agarrar el borde de una mesa para redimensionarla
  en el primer intento, sin activar por error el arrastre de posición.
- **SC-006**: El número de sillas dibujadas en una mesa se corresponde con su tamaño: dos mesas de
  la misma forma y distinta ocupación nunca muestran el mismo número de sillas.

## Assumptions

- La rejilla de cada zona conserva sus dimensiones actuales (8 columnas × 6 filas); esta feature no
  la hace configurable ni la amplía.
- El límite práctico de ocupación de una mesa es el tamaño de la rejilla; no se define un máximo
  arbitrario adicional más allá de que debe caber en su zona.
- Redimensionar cambia el aspecto de la mesa en el plano, pero **no** modifica el número de
  comensales ni ningún dato de la cuenta abierta sobre ella: es una propiedad de la representación
  del salón, no del servicio.
- El redimensionado ancla la mesa por su celda de origen (arriba-izquierda); crecer por el borde
  superior o izquierdo mueve esa celda de origen, como es habitual en cualquier editor.
- Se mantiene el guardado explícito por botón y el bloqueo optimista por versión ya existentes; la
  feature no introduce ningún guardado automático ni endpoint nuevo.
- No se añaden dependencias de terceros: el mecanismo de arrastre ya disponible en la aplicación
  cubre también el redimensionado.
- Los permisos de acceso al editor de plano son los ya existentes; esta feature no crea permisos
  nuevos ni entradas de menú.
- **El plano solo se dibuja en modo edición.** Fuera de él, la Sala sigue mostrando su rejilla de
  tarjetas de mesa, que esta feature no toca. Sustituir esa rejilla por el plano sería un cambio de
  la pantalla operativa del camarero, no del editor, y es una feature aparte.
