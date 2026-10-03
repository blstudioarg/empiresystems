# Feature Specification: Adjuntar material al asistente pegando o arrastrando

**Feature Branch**: `047-adjuntar-pegar-arrastrar`
**Created**: 2026-09-08
**Status**: Draft
**Input**: Permitir adjuntar material a la conversación del asistente IA pegando desde el portapapeles (Ctrl+V) y arrastrando y soltando ficheros sobre el panel del chat, además del clip que ya existe.

## User Scenarios & Testing *(mandatory)*

La feature 046 dejó al asistente capaz de recibir material (hojas de cálculo, PDF, imágenes y
texto) y de interpretarlo para proponer una importación. Pero el único gesto para entregarle un
fichero es el clip, que abre el selector de archivos del sistema. Eso obliga a que **todo material
exista antes como fichero guardado en disco**.

El caso que hoy no tiene salida es el más frecuente de todos: la persona tiene el listado a la
vista —en un correo, en un ERP viejo, en un PDF abierto— hace una captura de pantalla, y no tiene
dónde ponerla. Tiene que guardarla a disco, recordar en qué carpeta quedó, abrir el clip y
buscarla. Tres pasos manuales para algo que en cualquier chat moderno es un Ctrl+V.

### User Story 1 - Pegar una captura de pantalla en el chat (Priority: P1)

Una persona está mirando un listado de clientes en otro sistema. Hace una captura de pantalla,
abre el asistente, y la pega con Ctrl+V directamente en el campo de escribir. La captura queda
adjuntada a la conversación y el asistente puede analizarla.

**Why this priority**: Es el motivo entero de la feature y el flujo con más fricción hoy. Sin
esto, la capacidad de leer imágenes que ya trae el sistema queda desaprovechada porque el camino
para entregarle una imagen es incómodo. Entrega valor por sí sola, sin depender de las demás.

**Independent Test**: Copiar una imagen al portapapeles, pegarla en el campo del chat y comprobar
que aparece adjuntada y que el asistente la analiza igual que un fichero elegido con el clip.

**Acceptance Scenarios**:

1. **Given** una imagen en el portapapeles, **When** la persona pega en el campo de escribir del
   chat, **Then** la imagen queda adjuntada y se muestra en el indicador de adjunto.
2. **Given** una imagen pegada, **When** el asistente la analiza, **Then** produce el mismo
   resultado que si se hubiera adjuntado con el clip.
3. **Given** texto normal en el portapapeles, **When** la persona pega en el campo de escribir,
   **Then** el texto se inserta en el campo como siempre y no se adjunta nada.
4. **Given** una imagen en el portapapeles, **When** la persona pega estando fuera del panel del
   chat, **Then** no ocurre nada relacionado con el asistente.
5. **Given** una captura pegada, **When** aparece en la conversación, **Then** tiene un nombre
   legible que permite referirse a ella en la charla.

### User Story 2 - Arrastrar un fichero sobre el panel del chat (Priority: P2)

Una persona tiene el fichero en el escritorio o en una carpeta abierta. En vez de usar el clip y
navegar hasta él, lo arrastra y lo suelta sobre el panel del chat.

**Why this priority**: Ahorra pasos de forma clara, pero a diferencia de pegar, para este caso ya
existe un camino que funciona (el clip). Es mejora de comodidad, no habilitación de algo imposible.

**Independent Test**: Arrastrar un fichero admitido desde el explorador hasta el panel del chat,
soltarlo, y comprobar que queda adjuntado.

**Acceptance Scenarios**:

1. **Given** un fichero admitido arrastrado sobre el panel, **When** la persona lo suelta,
   **Then** el fichero queda adjuntado.
2. **Given** un fichero arrastrado sobre el panel, **When** está sobrevolando la zona, **Then** el
   panel indica visualmente que ahí se puede soltar.
3. **Given** un fichero arrastrado, **When** la persona lo suelta fuera de la zona válida o
   cancela el arrastre, **Then** no se adjunta nada y el indicador visual desaparece.
4. **Given** un fichero soltado sobre el panel, **When** el navegador procesa el gesto, **Then**
   la aplicación no se sustituye por el fichero ni se abre en otra pestaña.

### User Story 3 - Rechazos comprensibles con los gestos nuevos (Priority: P3)

Una persona pega o arrastra algo que el asistente no puede procesar —un fichero de un tipo no
admitido, o uno que supera el tamaño máximo—. Recibe la misma explicación en español que recibiría
usando el clip.

**Why this priority**: No añade capacidad nueva; asegura que los caminos nuevos no degraden la
calidad de los mensajes de error que ya existen. Depende de que al menos uno de los gestos
anteriores esté implementado.

**Independent Test**: Arrastrar un fichero de tipo no admitido y comprobar que aparece el mismo
mensaje explicativo que al elegirlo con el clip.

**Acceptance Scenarios**:

1. **Given** un fichero de tipo no admitido, **When** se pega o se arrastra, **Then** se muestra
   la misma explicación que da el clip y no se adjunta nada.
2. **Given** un fichero que supera el tamaño máximo, **When** se pega o se arrastra, **Then** se
   avisa del límite y no se adjunta nada.
3. **Given** un rechazo por cualquier motivo, **When** se muestra al usuario, **Then** aparece
   como notificación del sistema, con el mismo aspecto que el resto de avisos de la aplicación.

### Edge Cases

- **Pegar contenido mixto** (una imagen y texto a la vez, como al copiar de un documento): se
  adjunta la imagen y el texto acompañante se inserta en el campo de escribir. Ninguno de los dos
  se pierde en silencio.
- **Pegar o arrastrar varios ficheros de una vez**: se adjunta el primero y se avisa de que el
  resto no se tomó, invitando a añadirlos de a uno. Ver Assumptions.
- **Arrastrar algo que no es un fichero** (texto seleccionado de otra página, un enlace): no se
  adjunta nada y el indicador visual desaparece sin dejar rastro.
- **Pegar cuando ya hay material adjunto**: el material nuevo se suma a la importación en curso,
  igual que hoy hace el clip.
- **Pegar o arrastrar sin permiso para importar ese módulo**: se recibe el mismo aviso de permiso
  que da el clip; el gesto no es una vía alternativa para saltarse un permiso.
- **Arrastrar mientras hay un turno en vuelo**: el material queda adjuntado y disponible para el
  turno siguiente; no interrumpe la respuesta en curso.
- **Portapapeles vacío o con contenido no soportado**: el gesto no produce error visible ni
  bloquea el campo de escribir.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Las personas MUST poder adjuntar material al asistente pegándolo desde el
  portapapeles mientras están escribiendo en el chat.
- **FR-002**: Las personas MUST poder adjuntar material arrastrándolo y soltándolo sobre el panel
  del chat.
- **FR-003**: El sistema MUST seguir permitiendo pegar texto con normalidad: el gesto de pegar
  solo adjunta cuando lo que viene en el portapapeles es un fichero.
- **FR-004**: El gesto de pegar MUST estar acotado al panel del asistente y no afectar al resto de
  la aplicación.
- **FR-005**: El sistema MUST dar al material pegado sin nombre propio (una captura de pantalla)
  un nombre legible y distinguible, de modo que la persona y el asistente puedan referirse a él
  dentro de la conversación.
- **FR-006**: El sistema MUST indicar visualmente, mientras se arrastra un fichero sobre el panel,
  que esa es una zona donde se puede soltar.
- **FR-007**: El indicador visual de arrastre MUST desaparecer cuando el fichero se suelta, sale
  de la zona o el arrastre se cancela.
- **FR-008**: El sistema MUST impedir que soltar un fichero sobre la aplicación provoque que el
  navegador lo abra y descarte la página.
- **FR-009**: Los gestos nuevos MUST aplicar exactamente las mismas reglas de tipo admitido,
  tamaño máximo y permisos que el gesto del clip, sin excepciones ni atajos.
- **FR-010**: Los rechazos de los gestos nuevos MUST explicarse con el mismo texto en español y
  por el mismo canal de notificación que los del clip.
- **FR-011**: Cuando un gesto aporta más de un fichero a la vez, el sistema MUST tomar uno y
  avisar de forma explícita de que el resto no se adjuntó.
- **FR-012**: Los tres gestos —clip, pegar y arrastrar— MUST converger en el mismo
  comportamiento posterior: mismo indicador de adjunto, misma forma de descartarlo y misma
  acumulación sobre una importación en curso.
- **FR-013**: El material adjuntado con los gestos nuevos MUST quedar asociado únicamente a la
  conversación y al tenant de quien lo aporta.

### Key Entities

Esta feature no introduce entidades nuevas. Opera sobre el **material de importación** que ya
define la feature 046: un fichero aportado a una conversación, con su tipo, su tamaño y su
pertenencia a un tenant y a una conversación. Lo único que cambia es **por qué vía llega** ese
fichero.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Adjuntar una captura de pantalla al asistente pasa de requerir tres acciones
  manuales (guardar a disco, abrir el selector, localizar el fichero) a una sola.
- **SC-002**: Una persona que nunca usó la función consigue adjuntar una captura pegándola, sin
  instrucciones previas ni consultar la ayuda.
- **SC-003**: El 100% de los rechazos producidos por los gestos nuevos muestran una explicación en
  español que nombra el motivo concreto; ninguno acaba en un error genérico ni en silencio.
- **SC-004**: Pegar texto en el campo de escribir sigue funcionando exactamente igual que antes en
  el 100% de los casos.
- **SC-005**: Soltar un fichero sobre la aplicación nunca provoca la pérdida de la página ni de lo
  que la persona tenía escrito sin enviar.
- **SC-006**: El material adjuntado por los gestos nuevos produce resultados idénticos a los del
  mismo fichero adjuntado con el clip.

## Assumptions

- **Un fichero por gesto**: se mantiene el comportamiento actual de tratar un fichero por vez.
  Cuando el gesto aporta varios, se toma el primero y se avisa del resto. Motivo: es el
  comportamiento que ya tiene el clip, y la acumulación de material sobre una misma importación ya
  está resuelta añadiéndolos uno a uno. Permitir varios de golpe multiplicaría los estados
  intermedios (uno acepta, otro se rechaza por tamaño) sin resolver ningún caso que hoy no se
  pueda resolver.
- **Nombre del material pegado**: se compone con la fecha y hora del momento en que se pega, para
  que dos capturas seguidas sean distinguibles entre sí en la conversación.
- **Tipos admitidos y tamaño máximo**: se heredan sin cambios de lo ya definido en la feature 046.
  Esta feature no amplía ni restringe qué se puede adjuntar; solo añade maneras de entregarlo.
- **Alcance de interfaz**: la feature se limita a los gestos de entrega de material. No cambia
  cómo el asistente interpreta el material, ni el flujo de propuesta y confirmación posterior.
- **Sin cambios en el servidor**: la recepción, validación y almacenamiento del material ya
  existen y se reutilizan tal cual.
- **Disponibilidad de los gestos**: los gestos se ofrecen donde el navegador los soporta. En
  contextos donde no estén disponibles (por ejemplo, táctiles sin teclado ni arrastre de
  ficheros), el clip sigue siendo el camino y no se degrada.

## Dependencies

- **Feature 046 (importación conversacional)**: aporta la recepción de material, la validación de
  tipo y tamaño, los mensajes de rechazo y el indicador de adjunto. Esta feature es una capa de
  entrada sobre eso; sin la 046 desplegada, no tiene sentido.
- **Feature 030 (asistente IA)**: aporta el panel del chat y su campo de escribir, que son la
  superficie donde ocurren los gestos.

## Out of Scope

- Ampliar los tipos de fichero admitidos o el tamaño máximo.
- Adjuntar material desde una URL o desde el gestor documental de la propia aplicación.
- Previsualizar el contenido del material dentro del chat antes de que el asistente lo analice.
- Adjuntar varios ficheros en un mismo gesto (ver Assumptions).
- Cambios en cómo el asistente interpreta el material o propone la importación.
