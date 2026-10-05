# Feature Specification: Precuenta en el POS de hostelería

**Feature Branch**: `049-precuenta-pos`

**Created**: 2026-10-05

**Status**: Draft

**Input**: User description: "Precuenta en el POS de hostelería: imprimir/ver la cuenta abierta de una mesa como documento NO fiscal (sin número, sin Verifactu, sin QR, con leyenda "no válido como factura") para que el cliente revise lo consumido antes de cobrar; luego se cobra y se emite el ticket (factura simplificada) como hoy. Incluye estado visual de mesa "precuenta pedida" en la Sala, aviso si se modifican líneas tras imprimir la precuenta, reimpresión, y precuenta parcial opcional cuando el cobro dividido está activo."

## Contexto y encuadre

El cliente (un local de hostelería) pidió poder "dar la precuenta, para que revises lo que has
consumido, y luego cobrar con el ticket al final". Es el flujo estándar de cualquier bar o
restaurante en España, y tiene **dos momentos separados**:

1. **La mesa pide la cuenta → se entrega la precuenta**: un papel con lo consumido hasta ese
   momento, para que el cliente lo revise antes de pagar. **No es una venta ni un documento fiscal.**
2. **El cliente da el visto bueno y paga → se cobra y se emite el ticket** (factura simplificada),
   exactamente como el POS lo hace hoy.

Hoy el módulo de hostelería (features 038–042) ya tiene la pieza central: la **cuenta abierta** de
mesa, una entidad propia sin número que no es una factura en borrador. Lo que falta es el paso
intermedio: hoy una cuenta solo puede **guardarse** (no sale nada en papel) o **cobrarse** (sale el
ticket fiscal). No hay forma de enseñarle al cliente lo que lleva sin cobrárselo.

Lo que esta feature **no** toca: la emisión del ticket, su numeración, su cálculo de impuestos, su
registro Verifactu ni el flujo de cobro (total o por partes). La precuenta **lee** la cuenta abierta;
no crea ni altera ningún documento fiscal.

### Encuadre normativo

La precuenta es un documento **informativo**, equivalente a una proforma: no es una factura, no
genera registro de facturación Verifactu y no consume numeración de ninguna serie
(`docs/02-facturacion-espana.md` §1 y §7: Verifactu y la numeración correlativa aplican a las
facturas expedidas; las proformas no generan registro). El riesgo normativo real es otro: que un
documento entregado al cliente **pueda confundirse con una factura** y sirva para cobrar ventas que
luego nunca se facturan. Por eso la spec exige que la precuenta se distinga sin ambigüedad del ticket
(título, leyenda, ausencia de número y de QR) y que **cada precuenta emitida quede registrada** de
forma append-only, de modo que una cuenta con precuenta que acaba anulada sin cobrarse sea
detectable a posteriori.

### Convenciones de front que condicionan esta spec

Leídas antes de redactar, según la REGLA DE ORO de `CLAUDE.md`:

- `docs/04-front-guidelines.md` § **"«Ver» un documento (factura, presupuesto, albarán, ticket):
  SIEMPRE en modal, nunca otra pestaña"** — la vista previa de la precuenta se abre en un modal con
  el documento dentro, centrado, `modal-xl`, igual que el ticket tras cobrar; nunca en otra pestaña
  ni como descarga directa.
- `docs/04-front-guidelines.md` § **"Tarjeta de mesa y sus tres estados (Sala del POS)"** — el
  borde de la mesa comunica el estado de un vistazo y **el servidor decide el estado**; la vista no
  calcula nada. "Precuenta pedida" se añade como cuarto estado con su propio color de borde, decidido
  en servidor.
- `docs/04-front-guidelines.md` § **"Dos vistas de los mismos datos: el estado y el destino se
  comparten, no se repiten"** — el estado nuevo se calcula en un único sitio y lo consumen por igual
  la rejilla de tarjetas, el plano de servicio y las métricas de la cabecera.
- `docs/04-front-guidelines.md` § **"Feedback de bloqueo cuando el borde ya comunica estado"** — el
  color nuevo no puede chocar con los ya reservados (gris libre, verde ocupada, ámbar olvidada) ni
  con el feedback de rechazo.
- `docs/04-front-guidelines.md` § **"Franja central de la botonera del POS: mini-grid con el
  módulo de hostelería"** — la botonera no cambia de estructura; el acceso a la precuenta vive en el
  contexto de cuenta (cabecera del ticket), no en una botonera más grande.
- `docs/04-front-guidelines.md` § **"Suplemento de zona y contexto de cuenta: nunca un aumento
  silencioso"** — la precuenta muestra el suplemento de zona como concepto visible, igual que el
  ticket y el modal de cobro.
- `docs/04-front-guidelines.md` § **"Estado de carga en botones"**, § **"Notificaciones"**
  (toastr), § **"Modales: siempre centrados verticalmente"**, § **"Bloqueo por estado del servidor
  con resolución inline"** (dos modales nunca a la vez).
- `docs/04-front-guidelines.md` § **"Nueva entrada de menú ⇒ nuevo permiso"** — **no aplica**:
  la precuenta no es una entrada de menú, es una acción dentro de la cuenta abierta y queda cubierta
  por el permiso que ya protege las cuentas (`ver-pos-sala`).
- `docs/04-front-guidelines.md` § **"Ayuda contextual"** — las guías de Crear ticket y de Sala
  cambian y se actualizan en el mismo cambio.
- `.specify/memory/constitution.md` — Principio I (aislamiento por tenant y tests de fuga),
  Principio II (la precuenta no puede confundirse con una factura ni tocar numeración/Verifactu),
  Principio III (todo importe de la precuenta se calcula en servidor), Principio IV (test-first en
  importes y aislamiento), Principio V (sin dependencias nuevas; se reutiliza el motor de PDF del
  ticket).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Dar la precuenta a una mesa (Priority: P1)

La mesa 4 pide la cuenta. El camarero la toca en la Sala, se abre su cuenta en el TPV y pulsa
**Precuenta**. Aparece en pantalla la vista previa de un ticket de 80 mm titulado "PRECUENTA", con
la mesa, la hora, cada consumo con sus opciones, el suplemento de terraza y el total, y la leyenda
"Documento no válido como factura". Pulsa Imprimir, se la lleva a la mesa y el cliente la revisa.
Nada se ha cobrado y no se ha emitido ningún ticket.

**Why this priority**: es exactamente lo que pidió el cliente. Entregada sola, ya cubre el flujo
completo "precuenta → cobro", porque el cobro ya existe.

**Independent Test**: con una cuenta abierta con varias líneas, generar la precuenta y comprobar que
su total coincide con el pendiente de la cuenta, que no aparece ningún documento nuevo en el listado
de tickets, que la numeración de la serie no avanzó y que la cuenta sigue abierta.

**Acceptance Scenarios**:

1. **Given** una mesa con cuenta abierta y consumos guardados, **When** el camarero pulsa
   Precuenta, **Then** se muestra la vista previa de la precuenta en un modal, con opción de
   imprimirla, y la cuenta sigue abierta con todo su contenido.
2. **Given** una precuenta generada, **When** se revisa el documento, **Then** lleva el título
   "PRECUENTA", la leyenda "Documento no válido como factura", el nombre del local, mesa y zona,
   fecha y hora, comensales si constan, cada línea pendiente con cantidad, opciones elegidas e
   importe, el suplemento de zona si aplica, el total e "IVA incluido"; y **no** lleva número ni
   serie de factura, ni código QR, ni la mención VERI*FACTU.
3. **Given** una precuenta generada, **When** se consulta el listado de tickets emitidos, **Then**
   no aparece ningún documento nuevo, y el siguiente ticket que se cobre recibe el número que le
   habría tocado igualmente.
4. **Given** el TPV con cambios en la cuenta que todavía no se guardaron, **When** el camarero pulsa
   Precuenta, **Then** los cambios se guardan primero y la precuenta refleja la cuenta ya guardada,
   nunca un estado que solo existe en la pantalla.
5. **Given** una cuenta con líneas ya cobradas por partes, **When** se genera la precuenta, **Then**
   solo incluye lo **pendiente** de cobro, y su total coincide con el pendiente que muestra la mesa.
6. **Given** la precuenta generada, **When** el camarero cierra la vista previa, **Then** el TPV
   queda en cero, igual que tras Guardar (la cuenta sigue abierta en su mesa).
7. **Given** una cuenta abierta con precuenta, **When** después se cobra, **Then** el ticket se
   emite exactamente igual que hoy, con su numeración, su registro Verifactu y su QR cuando
   corresponda.

---

### User Story 2 - Ver en la Sala qué mesas esperan para pagar (Priority: P1)

En pleno turno, el encargado mira la Sala y distingue al instante las mesas a las que ya se les dio
la precuenta: tienen un borde de color propio y la indicación "Precuenta". Sabe que esas mesas están
esperando para pagar y manda a alguien a cobrarlas.

**Why this priority**: en un turno lleno, saber qué mesas "ya pidieron la cuenta" es lo que evita
que un cliente espere diez minutos para pagar. Es la mitad del valor operativo de la precuenta.

**Independent Test**: generar la precuenta de una mesa y comprobar que en la rejilla de tarjetas,
en el plano y en las métricas de la cabecera esa mesa aparece como "precuenta pedida"; cobrarla del
todo y comprobar que vuelve a libre.

**Acceptance Scenarios**:

1. **Given** una mesa ocupada, **When** se genera su precuenta, **Then** en la Sala pasa a mostrarse
   como "precuenta pedida", con un color de borde propio distinto de libre, ocupada y olvidada, y con
   el pendiente actual y el tiempo transcurrido desde que se dio.
2. **Given** una mesa con precuenta, **When** se mira en la vista de tarjetas y en la de plano,
   **Then** ambas la muestran con el mismo estado, y la métrica de la cabecera la cuenta en su propia
   categoría "Precuenta".
3. **Given** una mesa con precuenta que además superaría el umbral de "olvidada", **When** se mira la
   Sala, **Then** se muestra como "precuenta pedida" (es la información más accionable: la mesa no
   está olvidada, está esperando para pagar).
4. **Given** una mesa con precuenta, **When** se cobra entera, **Then** la mesa queda libre y el
   estado de precuenta desaparece con la cuenta.
5. **Given** una mesa con precuenta, **When** se cobra solo una parte, **Then** la mesa sigue en
   "precuenta pedida" mostrando el pendiente actualizado.
6. **Given** una mesa con precuenta, **When** su cuenta se transfiere a otra mesa, **Then** el estado
   de precuenta viaja con la cuenta a la mesa nueva.
7. **Given** una mesa con precuenta, **When** se toca, **Then** abre su cuenta en el TPV igual que
   cualquier mesa ocupada, de un solo toque, sin menús nuevos.

---

### User Story 3 - Aviso cuando la cuenta cambia después de dar la precuenta (Priority: P2)

El cliente de la mesa 4 ya tiene la precuenta en la mano, pero pide un café más. El camarero lo
añade y guarda. El TPV le avisa: "La precuenta impresa ya no coincide con la cuenta. Reimprímela
antes de cobrar". En la Sala la mesa deja de verse como "precuenta pedida", porque ya no lo está. Si
alguien intenta cobrar sin reimprimir, el modal de cobro le recuerda que el total que va a cobrar no
es el de la precuenta que tiene el cliente.

**Why this priority**: es el error que más discusiones genera en una mesa: el cliente revisó un
papel y le cobran otra cifra. No bloquea el flujo principal, pero sin él la precuenta pierde su
razón de ser (que lo cobrado coincida con lo revisado).

**Independent Test**: generar la precuenta, añadir una línea y guardar; comprobar que aparece el
aviso, que la mesa vuelve a "ocupada" y que el modal de cobro muestra la diferencia entre el total
de la precuenta y el actual.

**Acceptance Scenarios**:

1. **Given** una cuenta con precuenta, **When** se añade, quita o cambia la cantidad, el precio o
   las opciones de una línea y se guarda, **Then** la precuenta pasa a "desactualizada", el TPV avisa
   con una notificación y el contexto de cuenta muestra una marca "Precuenta desactualizada" con
   acceso a reimprimirla.
2. **Given** una precuenta desactualizada, **When** se mira la Sala, **Then** la mesa se muestra
   como ocupada (u olvidada, si corresponde), no como "precuenta pedida".
3. **Given** una precuenta desactualizada, **When** se abre el modal de cobro, **Then** se muestra un
   aviso con el total de la última precuenta y el total actual, sin impedir cobrar.
4. **Given** una precuenta desactualizada, **When** se genera una nueva, **Then** la mesa vuelve a
   "precuenta pedida" y el aviso desaparece.
5. **Given** una cuenta con precuenta, **When** se cobra una parte (cobro dividido), **Then** la
   precuenta **no** pasa a desactualizada: cobrar no es cambiar el consumo.
6. **Given** una cuenta con precuenta, **When** se le une la cuenta de otra mesa, **Then** la
   precuenta de la cuenta resultante pasa a desactualizada (el consumo cambió).
7. **Given** una cuenta con precuenta, **When** solo se cambian datos que no afectan al importe
   (notas, comensales, datos del cliente para factura), **Then** la precuenta sigue vigente.

---

### User Story 4 - Reimprimir la precuenta (Priority: P2)

El cliente perdió la precuenta, o se rompió el papel, o hay que reimprimirla tras añadir el café. El
camarero vuelve a pulsar Precuenta y obtiene una nueva, con la hora actual. Si la cuenta no cambió
desde la anterior, el documento indica que es una reimpresión.

**Why this priority**: es imprescindible en el día a día, pero es una consecuencia directa de la
historia 1 (pulsar el mismo botón otra vez), con poco trabajo propio.

**Independent Test**: generar dos precuentas seguidas sin cambiar la cuenta; la segunda indica
"Reimpresión" y ambas quedan registradas.

**Acceptance Scenarios**:

1. **Given** una cuenta con precuenta vigente y sin cobros desde entonces, **When** se vuelve a pulsar Precuenta, **Then** se
   genera una nueva con la hora actual, marcada como "Reimpresión", y queda registrada como otra
   emisión.
2. **Given** una cuenta con precuenta desactualizada, **When** se pulsa Precuenta, **Then** la nueva
   **no** se marca como reimpresión: es una precuenta nueva, con el contenido actualizado.

---

### Edge Cases

- **Cuenta sin nada pendiente**: si el ticket en pantalla está vacío, el botón de precuenta aparece
  deshabilitado; y si la cuenta guardada no tiene nada pendiente (todo cobrado), el servidor rechaza
  la precuenta con un aviso.
- **Cuenta cerrada o anulada**: no admite precuenta; el servidor la rechaza.
- **Venta directa en barra (sin cuenta abierta)**: no hay precuenta. La precuenta existe solo sobre
  una cuenta abierta del módulo de hostelería; el TPV sin módulo de hostelería no cambia nada.
- **Cuenta aparcada sin mesa**: admite precuenta igual que una de mesa; en el documento, en lugar de
  mesa y zona, figura "Sin mesa". No tiene estado en la Sala porque no está en ninguna mesa.
- **Concurrencia**: si otro dispositivo modificó la cuenta mientras tanto, la generación respeta el
  mismo control de versión que el guardado: avisa del conflicto, recarga la cuenta y no genera nada.
- **Cuenta anulada después de dar precuenta**: se permite anular (la anulación ya pide confirmación),
  pero las precuentas emitidas **siguen registradas** y no se borran nunca.
- **Precuenta con suplemento de zona y transferencia a otra zona**: al transferir, el suplemento
  aplicable cambia y por tanto el total; la precuenta pasa a desactualizada.
- **Desactivar el módulo de hostelería** con precuentas registradas: el registro se conserva; al no
  haber cuentas abiertas (el módulo no se desactiva con cuentas abiertas), no queda ningún estado
  visible pendiente.
- **Importe del documento**: la precuenta muestra el mismo importe que se cobraría en ese momento;
  si el tope de la factura simplificada se superaría, la precuenta se genera igual (es informativa),
  y el aviso del tope sigue apareciendo donde ya aparece hoy, al cobrar.

## Requirements *(mandatory)*

### Functional Requirements

**Generación y contenido**

- **FR-001**: El sistema DEBE permitir generar una precuenta de cualquier cuenta abierta con importe
  pendiente mayor que cero, desde el TPV con esa cuenta cargada.
- **FR-002**: La precuenta DEBE calcularse en servidor a partir del estado guardado de la cuenta;
  ningún importe que la compone se toma del cliente.
- **FR-003**: Si el TPV tiene cambios sin guardar, DEBE guardarlos antes de generar la precuenta,
  con el mismo control de versión del guardado; si hay conflicto, no se genera.
- **FR-004**: La precuenta DEBE incluir solo las unidades **pendientes** de cobro de cada línea, con
  su concepto, cantidad, opciones elegidas (bajo el nombre del plato, como en el ticket), precio e
  importe; el suplemento de zona aplicable como concepto visible; el total; y la mención "IVA
  incluido".
- **FR-005**: El total de una precuenta completa DEBE coincidir al céntimo con el importe que se
  cobraría si en ese instante se cobrase la cuenta entera.
- **FR-006**: La precuenta DEBE identificar el local (nombre comercial), la mesa y su zona (o "Sin
  mesa"), la fecha y hora de emisión, los comensales si constan y quién la emitió.
- **FR-007**: La precuenta DEBE llevar el título "PRECUENTA" bien visible y la leyenda "Documento no
  válido como factura" al principio y al final del documento.
- **FR-008**: La precuenta NO DEBE llevar número ni serie de factura, código QR, la mención
  VERI*FACTU ni ningún otro elemento propio de una factura simplificada que pueda inducir a tomarla
  por tal.
- **FR-009**: Generar una precuenta NO DEBE emitir ninguna factura, crear ningún registro de
  facturación Verifactu, consumir numeración de ninguna serie, mover stock ni registrar cobro alguno.
- **FR-010**: La precuenta DEBE mostrarse en una vista previa dentro de un modal, con acción de
  imprimir, en formato de rollo de 80 mm como el ticket.
- **FR-011**: Al cerrar la vista previa, el TPV DEBE quedar en cero igual que tras Guardar; la
  cuenta sigue abierta en su mesa.

**Registro de precuentas**

- **FR-012**: Cada precuenta generada DEBE quedar registrada con: la cuenta, la mesa en ese momento,
  el usuario que la emitió, la fecha y hora, el total, si es reimpresión, la versión de la cuenta a la
  que corresponde y una foto de las líneas impresas, de modo que volver a abrir esa precuenta muestre
  exactamente lo que se entregó al cliente.
- **FR-013**: El registro de precuentas DEBE ser append-only: nunca se edita ni se borra una
  precuenta registrada, tampoco cuando la cuenta se anula, se cobra o se transfiere.
- **FR-014**: El registro DEBE estar aislado por tenant como cualquier tabla de negocio.

**Estado en la Sala**

- **FR-015**: Una mesa cuya cuenta tiene una precuenta **vigente** DEBE mostrarse en la Sala con el
  estado "precuenta pedida", decidido en servidor, con un color de borde propio distinto de los de
  libre, ocupada y olvidada.
- **FR-016**: Una precuenta es **vigente** mientras el consumo de la cuenta no haya cambiado desde
  que se emitió; deja de serlo (pasa a **desactualizada**) cuando se añade, quita o modifica una
  línea en lo que afecta al importe (cantidad, precio, opciones), cuando se une otra cuenta a ella o
  cuando cambia el suplemento de zona aplicable (por una transferencia a otra zona o por un cambio en
  la configuración del suplemento).
- **FR-017**: Cobrar parte de la cuenta, cambiar notas, comensales o datos del receptor, o
  transferirla a una mesa de la misma zona (o de otra con igual suplemento) NO DEBE dejar la
  precuenta desactualizada.
- **FR-018**: "Precuenta pedida" DEBE prevalecer sobre "olvidada" cuando ambas condiciones se dan.
- **FR-019**: La tarjeta y la mesa del plano en estado "precuenta pedida" DEBEN mostrar el pendiente
  actual y el tiempo transcurrido desde la última precuenta vigente.
- **FR-020**: Las dos vistas de la Sala (tarjetas y plano) y la tira de métricas DEBEN mostrar el
  mismo estado; las métricas DEBEN incluir el recuento de mesas en "precuenta pedida".
- **FR-021**: Tocar una mesa en "precuenta pedida" DEBE llevar a su cuenta en el TPV igual que una
  mesa ocupada, sin pasos adicionales.

**Avisos de precuenta desactualizada**

- **FR-022**: Al guardar una cuenta cuya precuenta acaba de quedar desactualizada, el TPV DEBE
  avisar con una notificación de que la precuenta impresa ya no coincide.
- **FR-023**: El contexto de cuenta del TPV DEBE indicar si la cuenta tiene precuenta vigente o
  desactualizada, con acceso directo a generarla de nuevo.
- **FR-024**: El modal de cobro DEBE mostrar, cuando la última precuenta está desactualizada, el
  total de esa precuenta junto al total actual, sin impedir el cobro.

**Reimpresión**

- **FR-025**: Generar una precuenta cuando la cuenta no cambió desde la última (ni su consumo ni lo
  cobrado) DEBE producir un documento marcado como "Reimpresión", registrado como una emisión más.

- **FR-026**: La precuenta es siempre de **toda la cuenta pendiente**. La precuenta parcial queda
  fuera de alcance (ver Assumptions).

**Alcance y permisos**

- **FR-027**: La precuenta DEBE estar disponible solo con el módulo de hostelería activo y para
  quien ya puede operar las cuentas abiertas (mismo permiso que la Sala); sin entrada de menú nueva
  ni permiso nuevo.
- **FR-028**: Con el módulo de hostelería desactivado, el POS DEBE comportarse exactamente igual que
  antes de esta feature.

**Documentación**

- **FR-029**: Las guías in-app de Crear ticket y de Sala y la base de conocimiento del asistente
  sobre el módulo de hostelería DEBEN actualizarse en el mismo cambio para describir la precuenta,
  el estado nuevo de mesa y el aviso de desactualizada.

### Key Entities

- **Precuenta emitida**: registro inmutable de cada precuenta generada. Pertenece a un tenant y a una
  cuenta abierta; guarda la mesa en ese momento, quién la emitió y cuándo, el total, la versión de la
  cuenta a la que corresponde, si fue reimpresión y una **foto de las líneas** tal como se
  imprimieron. No es un documento fiscal: no tiene número ni serie. El documento imprimible se
  regenera siempre a partir de esa foto, nunca de la cuenta viva (que puede haber cambiado).
- **Cuenta abierta** (existente, feature 038): pasa a poder tener precuentas emitidas. Su estado de
  precuenta (sin precuenta / vigente / desactualizada) se deriva de su última precuenta y de si su
  consumo cambió desde entonces.
- **Mesa** (existente): gana el estado visible "precuenta pedida", derivado de su cuenta abierta.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un camarero genera e imprime la precuenta de una mesa en menos de 10 segundos y 3
  toques desde que tiene la cuenta en pantalla.
- **SC-002**: En el 100 % de los casos, el total de una precuenta completa vigente coincide al
  céntimo con el total del ticket emitido al cobrar esa cuenta entera.
- **SC-003**: Generar precuentas no altera la numeración: tras emitir cualquier cantidad de
  precuentas, la serie de tickets sigue sin huecos ni saltos, y el número de registros Verifactu y de
  facturas no varía.
- **SC-004**: Ninguna persona que reciba la precuenta puede confundirla con un ticket: carece de
  número, serie y QR, y el título y la leyenda la identifican como no válida como factura.
- **SC-005**: En la Sala, el encargado identifica en menos de 3 segundos qué mesas tienen la
  precuenta dada, en cualquiera de las dos vistas.
- **SC-006**: Toda modificación del consumo posterior a una precuenta produce aviso en el TPV y en el
  cobro; 0 casos en los que se cobra una cifra distinta de la precuenta sin aviso previo.
- **SC-007**: Cada precuenta emitida queda registrada; es posible saber, para cualquier cuenta
  anulada, si llegó a tener precuenta.
- **SC-008**: Un tenant sin el módulo de hostelería no ve ningún cambio en su POS.

## Assumptions

- **La precuenta no es un documento fiscal.** Se trata como una proforma informativa: no genera
  registro Verifactu ni consume numeración (en línea con que las proformas no generan registro;
  ver [Holded — tipos de facturas y Verifactu](https://www.holded.com/es/blog/tipos-facturas-verifactu)).
  Se reconfirma contra las FAQ de la AEAT al planificar y se cita en `docs/02-facturacion-espana.md`.
- **El documento no se archiva como PDF**: se regenera a partir del registro, que guarda la foto de
  las líneas impresas (quién, cuándo, cuánto, qué y sobre qué versión). Suficiente para el control
  interno y para reimprimir exactamente lo entregado.
- **Precuenta parcial fuera de alcance.** Se había propuesto como opcional para el cobro dividido,
  pero al revisar el código el modal de cobro **no envía selección de líneas**: el cobro por partes
  existe en servidor (feature 038, `CobradorCuenta` acepta selección) pero no tiene interfaz para
  elegir líneas, aunque la guía in-app de Crear ticket la describe. Construir un selector de líneas
  solo para la precuenta, antes que para el cobro, sería empezar por el lado equivocado. Se retoma
  cuando exista el selector del cobro por partes (hueco a tratar aparte).
- **Sin interruptor de configuración propio**: la precuenta forma parte del módulo de hostelería y
  está disponible siempre que el módulo lo esté. Un botón que no se usa no molesta, y añadir un
  interruptor más complica la configuración sin ganar nada (Principio V).
- **No hay acción de precuenta en la Sala**: tocar una mesa sigue llevando directo a su cuenta, de un
  toque (decisión de la feature 041); la precuenta se genera desde el TPV.
- **Impresión**: se imprime con el mismo mecanismo que el ticket tras cobrar (vista previa en modal +
  impresión del navegador sobre el rollo de 80 mm). No se integra con impresoras de tickets por
  protocolo propio.
- **Desglose de impuestos**: la precuenta muestra "IVA incluido" (o la mención del régimen del
  tenant: IGIC/IPSI) sin desglose por tipo; el desglose es propio del ticket fiscal.
- **Datos personales**: el registro guarda el usuario que emitió la precuenta, igual que la cuenta
  guarda quién la abrió; no añade categorías nuevas de datos personales ni IP, por lo que sigue el
  mismo ciclo de vida que las cuentas y no requiere una purga propia.
- **Fuera de alcance**: informes de precuentas (p. ej. "cuentas anuladas con precuenta") en el
  cierre de caja; enviar la precuenta por email o QR al móvil del cliente; precuenta en la venta
  directa sin cuenta.
