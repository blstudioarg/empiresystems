# Feature Specification: Vista de plano en la Sala en modo servicio

**Feature Branch**: `041-plano-sala-servicio`

**Created**: 2026-08-20

**Status**: Draft

**Input**: User description: "Vista de plano en la Sala del POS en modo servicio (no edición). Hoy el lienzo del plano (rejilla 8x6 con las mesas dibujadas en su posición, forma y ocupación en celdas) solo se ve al entrar en 'Editar plano', y la Sala en uso normal muestra una rejilla de tarjetas `.pos-mesa`. Se quiere poder ver la Sala CON el plano sin entrar en modo edición: mismo lienzo y mismo dibujo de las mesas, pero funcionando como la vista normal — al tocar una mesa se va a crear/retomar el ticket de esa mesa (igual que hoy hacen las tarjetas), y cada mesa se colorea según su estado real (libre / ocupada / olvidada) mostrando la información de servicio que ya muestran las tarjetas. Sin arrastrar ni redimensionar nada: eso sigue siendo exclusivo del modo edición."

## Contexto y problema

El local ya puede dibujar su sala real: dónde está cada mesa, qué forma tiene y cuánto espacio
ocupa (features 039 y 040). Pero ese dibujo **solo existe dentro del editor**, una pantalla a la
que solo entra quien tiene permiso de configuración y a la que se entra para *cambiar* el plano,
no para trabajar.

Durante el servicio, el camarero ve una rejilla de tarjetas ordenadas por nombre. Esa rejilla no
se parece a la sala: para saber qué mesa es la "12" hay que leer, no mirar. El plano existe, es
fiel al local, y no se puede usar justo en el momento en que sería más útil.

La feature no crea un dibujo nuevo: **pone a trabajar el que ya existe**.

## Convenciones del proyecto que condicionan este spec

Leídas antes de redactarlo, según la regla de oro de `CLAUDE.md`:

- **`docs/04-front-guidelines.md` → "Tarjeta de mesa y sus tres estados"**: el **borde** comunica
  el estado (gris libre / verde ocupada / ámbar olvidada), y **la vista nunca hace aritmética de
  fechas** — `olvidada` lo decide el servidor con `ConfigPos::mesaOlvidadaMin`. El plano hereda las
  dos reglas: mismo código de color y mismo origen de la verdad.
- **`docs/04-front-guidelines.md` → "Feedback de bloqueo cuando el borde ya comunica estado"**
  (salida de la feature 040): refuerza que el color del borde está reservado al estado de la mesa.
- **`docs/04-front-guidelines.md` → filtros de zona**: las pestañas de zona reutilizan `.pos-filtro`;
  no se diseña un selector nuevo para lo mismo.
- **Constitución, Principio I**: la Sala ya sirve datos por tenant; ninguna vista nueva puede
  saltarse ese aislamiento.
- **Constitución, Principio V (simplicidad)**: el lienzo, el dibujo de mesas y el payload de estado
  ya existen. Esta feature reutiliza, no duplica.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver la sala como es y abrir una cuenta tocando la mesa (Priority: P1)

Durante el servicio, el camarero cambia la Sala a vista de plano y ve el local tal como está
distribuido: cada mesa en su sitio, con su forma y su tamaño. Toca la mesa que necesita y va
directo al ticket de esa mesa, exactamente como haría desde la rejilla de tarjetas.

**Why this priority**: es la feature entera. Sin esto no hay nada que entregar; el resto son
matices sobre esta base.

**Independent Test**: entrar en la Sala sin tocar "Editar plano", cambiar a vista de plano y tocar
una mesa libre; se abre el TPV con esa mesa preseleccionada. Repetir con una mesa ocupada; se
retoma su cuenta.

**Acceptance Scenarios**:

1. **Given** una zona con mesas colocadas en el plano, **When** el usuario cambia a vista de plano,
   **Then** ve el lienzo con cada mesa en su posición, forma y tamaño, sin ningún control de
   edición (ni asa de arrastre, ni asas de borde, ni botón de guardar).
2. **Given** la vista de plano activa, **When** el usuario toca una mesa **libre**, **Then** se
   abre el TPV con esa mesa preseleccionada, igual que al tocar su tarjeta.
3. **Given** la vista de plano activa, **When** el usuario toca una mesa **ocupada**, **Then** se
   abre la cuenta existente de esa mesa.
4. **Given** la vista de plano activa, **When** el usuario arrastra una mesa o su borde, **Then**
   no ocurre nada: la mesa no se mueve ni cambia de tamaño.

---

### User Story 2 - Leer el estado de la sala de un vistazo (Priority: P1)

El camarero mira el plano y distingue sin leer qué mesas están libres, cuáles ocupadas y cuáles
llevan demasiado tiempo sin que nadie las toque, además de cuánto tienen pendiente de cobro.

**Why this priority**: un plano que no dice el estado es un dibujo bonito e inútil para el
servicio. Es lo que convierte la vista en una herramienta de trabajo, y es inseparable de US1.

**Independent Test**: con una mesa libre, una con cuenta abierta reciente y una con cuenta abierta
por encima del umbral configurado, comprobar que las tres se distinguen en el plano sin leer texto.

**Acceptance Scenarios**:

1. **Given** una mesa sin cuenta abierta, **When** se ve en el plano, **Then** se muestra con el
   tratamiento visual de "libre", el mismo que en su tarjeta.
2. **Given** una mesa con cuenta abierta por debajo del umbral, **When** se ve en el plano,
   **Then** se muestra como "ocupada" e indica el importe **pendiente de cobro** (no lo consumido).
3. **Given** una mesa con cuenta abierta por encima del umbral configurado, **When** se ve en el
   plano, **Then** se muestra como "olvidada", con el mismo criterio que ya aplica la tarjeta.
4. **Given** el estado de la sala cambia (se abre o se cobra una cuenta), **When** la Sala se
   refresca, **Then** el plano refleja el estado nuevo sin que el usuario tenga que recargar la
   página ni volver a elegir la vista.

---

### User Story 3 - Elegir cómo ver la sala, y que lo recuerde (Priority: P2)

El usuario elige entre ver la Sala como tarjetas o como plano, y la próxima vez que entre la
encuentra como la dejó.

**Why this priority**: sin poder elegir, la feature le impone el plano a locales cuyo plano no
está dibujado. Y sin memoria, obliga a repetir el gesto en cada visita, decenas de veces por
turno. Es lo que hace la feature usable a diario, pero US1 y US2 ya entregan valor sin ella.

**Independent Test**: cambiar a vista de plano, navegar a otra pantalla, volver a la Sala y
comprobar que sigue en vista de plano.

**Acceptance Scenarios**:

1. **Given** la Sala en vista de tarjetas, **When** el usuario cambia a vista de plano y vuelve a
   entrar en la Sala más tarde, **Then** la encuentra en vista de plano.
2. **Given** la preferencia guardada en vista de plano, **When** otro usuario del mismo local abre
   la Sala, **Then** su propia preferencia no se ve afectada por la del primero.

---

### Edge Cases

- **Mesas sin posición en la rejilla**: una mesa creada cuando su zona ya estaba llena no tiene
  posición y hoy el plano la excluye. En modo edición eso es aceptable (hay un aviso); en servicio
  **no puede desaparecer una mesa que se puede usar**, porque el camarero dejaría de poder abrirle
  cuenta. Resolución en FR-011.
- **Zona sin ninguna mesa colocada**: el lienzo quedaría vacío y parecería un fallo.
- **Filtro "Todas" las zonas**: la vista de tarjetas permite ver el local entero, pero el plano es
  por zona (la rejilla de 8×6 es de una zona). Resolución en FR-010.
- **Local que nunca dibujó su plano**: todas las mesas están en las primeras celdas, en el orden en
  que se crearon; el plano es válido pero no representa la sala real. **No requiere comportamiento
  especial**: se dibuja tal cual y sigue siendo usable; corregirlo es dibujar el plano, que ya es
  posible desde el editor. Se recoge aquí para dejar claro que la vista no debe intentar detectar
  ni "arreglar" ese caso.
- **Mesa ocupada con importe largo** (cuentas grandes) dentro de una mesa de una sola celda: el
  texto no puede desbordar ni tapar el nombre.
- **Pantalla estrecha (móvil)**: el lienzo tiene un ancho mínimo mayor que la pantalla.
- **Cambio de estado mientras el dedo está sobre la mesa**: un refresco automático no debe provocar
  que el toque acabe abriendo una mesa distinta de la que se tocó.

## Requirements *(mandatory)*

### Functional Requirements

**Vista y navegación**

- **FR-001**: La Sala MUST ofrecer una vista de plano utilizable **sin entrar en modo edición**.
- **FR-002**: Los usuarios MUST poder alternar entre la vista de tarjetas y la vista de plano desde
  la propia Sala, con un control visible y de tamaño táctil.
- **FR-003**: El sistema MUST recordar la vista elegida por el usuario entre visitas a la Sala, de
  forma independiente para cada usuario.
- **FR-004**: La vista de plano MUST estar disponible para **cualquier usuario que pueda ver la
  Sala**, sin exigir permiso de configuración. Ver el plano y poder cambiarlo son cosas distintas:
  cambiarlo sigue exigiendo el permiso de configuración (FR-020). *(Aclarado 2026-08-20: si solo lo
  viera el encargado, la feature no serviría en el turno, que es su razón de ser.)*

**Contenido de cada mesa en el plano**

- **FR-005**: Cada mesa MUST dibujarse en el plano con la misma posición, forma y ocupación en
  celdas que tiene en el editor.
- **FR-006**: Cada mesa MUST comunicar su estado —libre, ocupada u olvidada— con el mismo código
  visual que la tarjeta equivalente, para que ambas vistas no se contradigan.
- **FR-007**: Cada mesa ocupada MUST mostrar el importe **pendiente de cobro** y el tiempo que
  lleva abierta, la misma información que muestra su tarjeta.
- **FR-008**: El sistema MUST determinar el estado "olvidada" **en el servidor**, con el umbral
  configurado del tenant; la vista NO puede calcularlo a partir de fechas.
- **FR-009**: La información de cada mesa MUST permanecer legible dentro del espacio de la mesa,
  incluso en una mesa de una sola celda y con importes largos.

**Alcance por zona y mesas sin sitio**

- **FR-010**: La vista de plano MUST mostrar **una zona cada vez**. El filtro "Todas" NO aplica en
  esta vista: si estaba activo al cambiar a plano, el sistema MUST seleccionar una zona concreta y
  reflejarlo en el filtro, de modo que la zona mostrada nunca sea ambigua. *(Aclarado 2026-08-20:
  el camarero está físicamente en una zona; apilar todos los planos solo añadiría scroll.)*
- **FR-011**: Ninguna mesa activa MUST quedar inaccesible en la vista de plano. Las mesas de la
  zona **sin posición en la rejilla** MUST mostrarse en una **franja identificada bajo el lienzo**,
  con el mismo estado y el mismo comportamiento al tocarlas que en la vista de tarjetas. El sistema
  MUST NOT asignarles una posición por su cuenta. *(Aclarado 2026-08-20: dónde va una mesa es una
  decisión del encargado, y colocarlas solas además fallaría justo cuando la zona está llena, que
  es el caso que crea el problema.)*
- **FR-012**: Cuando la zona activa no tenga ninguna mesa que dibujar, el sistema MUST mostrar un
  mensaje que explique la situación, en vez de un lienzo vacío sin explicación.

**Interacción**

- **FR-013**: Tocar una mesa **libre** en el plano MUST llevar al TPV con esa mesa preseleccionada,
  con el mismo resultado que tocar su tarjeta.
- **FR-014**: Tocar una mesa **ocupada** en el plano MUST abrir su cuenta existente, con el mismo
  resultado que tocar su tarjeta.
- **FR-015**: La vista de plano MUST ser de solo lectura: no permite mover, redimensionar ni
  cambiar la forma de ninguna mesa, ni ofrece guardar nada.
- **FR-016**: El sistema MUST reflejar los cambios de estado de la sala en el plano cuando la Sala
  se actualice, sin exigir recargar la página ni volver a elegir la vista.
- **FR-017**: Un refresco de estado MUST NOT provocar que un toque en curso termine actuando sobre
  una mesa distinta de la tocada.
- **FR-018**: Las métricas de cabecera (total, libres, ocupadas, olvidadas) MUST seguir mostrando
  las mismas cifras en ambas vistas.

**Compatibilidad**

- **FR-019**: La vista de tarjetas MUST seguir funcionando exactamente igual que hoy.
- **FR-020**: El modo edición del plano MUST seguir funcionando exactamente igual que hoy, y seguir
  siendo el único sitio donde se cambia el plano.
- **FR-021**: La vista de plano MUST ser usable en la pantalla donde se usa el POS (tablet), sin
  que el lienzo obligue a hacer scroll en dos direcciones para alcanzar una mesa.

### Key Entities

No se crean entidades nuevas ni se modifican las existentes. La feature **consume** lo que ya hay:

- **Mesa**: su posición (celda de origen), forma y ocupación en celdas ya existen desde las
  features 039 y 040; su estado (libre/ocupada/olvidada), importe pendiente y tiempo de apertura ya
  los deriva y sirve la Sala.
- **Zona**: agrupa mesas y define el ámbito de un plano.
- **Preferencia de vista**: qué vista eligió cada usuario para la Sala. Es el único dato nuevo, y
  es una preferencia de interfaz, no un dato de negocio.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Con la vista de plano ya activa y en la zona correcta, un camarero abre el ticket de
  una mesa concreta con **un solo toque** sobre ella, sin pasar por ninguna pantalla de
  configuración ni por el editor de plano.
- **SC-002**: Ante un plano con mesas en los tres estados, un usuario que no conoce la aplicación
  identifica correctamente cuáles están libres y cuáles ocupadas **sin leer el texto** de las
  mesas.
- **SC-003**: La vista elegida se conserva en el **100%** de las vueltas a la Sala dentro de la
  misma sesión del usuario.
- **SC-004**: **Ninguna** mesa activa del local queda inaccesible desde la vista de plano, incluidas
  las que no tienen posición en la rejilla.
- **SC-005**: Tras cobrar o abrir una cuenta, el plano refleja el estado nuevo en el siguiente
  refresco de la Sala, sin recargar la página.
- **SC-006**: Las cifras de las métricas de cabecera coinciden con lo que se ve dibujado, en las
  dos vistas.
- **SC-007**: En la vista de plano no existe **ninguna** manera de alterar el plano: ni moviendo,
  ni redimensionando, ni cambiando forma.

## Assumptions

- La vista de plano **reutiliza el lienzo y el dibujo de mesas que ya existen** en el editor
  (rejilla de 8×6 por zona, mesas con forma y ocupación en celdas). No se rediseña el dibujo.
- La información de estado que necesita el plano (estado, pendiente, minutos abierta, destino al
  tocar) **ya viaja** en el estado de la Sala que consume la vista de tarjetas; no hacen falta
  datos nuevos del servidor.
- El aislamiento por tenant y los permisos de la Sala **ya están resueltos** y esta feature no los
  altera; se apoya en ellos.
- La preferencia de vista es una preferencia de **interfaz por usuario y dispositivo**, no un
  ajuste de negocio configurable por el tenant. No necesita auditoría ni retención.
- El módulo de hostelería debe estar activo, igual que para el resto de la Sala.
- Fuera de alcance: reordenar o editar el plano desde esta vista (FR-015/FR-020), mostrar en el
  plano información que la tarjeta no muestra hoy, y cualquier cambio en cómo se calculan los
  importes o el estado de una mesa.
