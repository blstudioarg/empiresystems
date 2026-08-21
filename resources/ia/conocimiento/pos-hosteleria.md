# POS — módulo de hostelería (mesas y opciones de artículo)

Ampliación opcional del POS para bares y restaurantes. Se activa por tenant desde
**Configuración → POS** (interruptor maestro) y trae tres capacidades que también se activan por
separado: opciones de artículo, cobro por selección de líneas (cobro dividido de la cuenta) y
suplemento por zona. **Desactivado por defecto**: si el tenant no lo activó, nada de esto existe
en su POS.

Si alguien entra por URL directa a una pantalla del módulo (por ejemplo `/pos/opciones`) con el
módulo o esa capacidad apagados, la app no da un error de "página no encontrada": muestra un
cartel explicando que el módulo de hostelería está desactivado, con un enlace a Configuración →
POS para activarlo (solo si el usuario tiene permiso de configuración).

## Sala y cuentas abiertas

La pantalla **Sala** (menú POS → Sala) muestra todas las mesas del local agrupadas por zona, con
su estado: libre (borde gris), ocupada (borde verde, con el importe pendiente y los minutos desde
que se abrió) u olvidada (borde ámbar, cuando lleva más tiempo del umbral configurado sin que
nadie le añada nada). Tocar una mesa libre abre el TPV con esa mesa; tocar una ocupada retoma su
cuenta.

La Sala se puede ver de **dos formas**, con un selector en la cabecera visible para cualquiera que
pueda entrar a la Sala (no hace falta permiso de Configuración): **Tarjetas** (la rejilla de
siempre, la vista por defecto) y **Plano** (las mesas dibujadas en la posición y el tamaño que el
encargado les dio). Las dos muestran exactamente los mismos datos y llevan al mismo sitio al tocar
una mesa; solo cambia el envase. La vista elegida se recuerda por usuario y por dispositivo (se
guarda en el navegador, no en el servidor), así que la tablet de sala puede quedarse en plano y el
PC de caja en tarjetas.

En la vista de plano, el filtro **«Todas» no aplica** —la rejilla es de una sola zona— y aparece
deshabilitado: se muestra la primera zona disponible, reflejada en el filtro. Las mesas de la zona
que **no tienen posición** en la rejilla (creadas cuando ya estaba llena) no desaparecen: se
dibujan como tarjetas en una franja «Sin sitio en el plano» bajo el lienzo, con su mismo estado y
comportamiento, para que ninguna mesa activa quede sin poder cobrarse. El lienzo se escala para que
el ancho quepa siempre en pantalla, sin obligar a arrastrar en horizontal.

La vista de plano es de **solo lectura**: no se arrastra, no se redimensiona y no guarda nada. El
plano solo se modifica desde **Editar plano**, que sigue requiriendo permiso de Configuración.

Una **cuenta abierta** no es un ticket ni una factura en borrador: es su propia entidad, sin
número asignado, que no aparece en ningún listado de facturas hasta que se cobra. Se puede
guardar, recuperar y anular sin que eso consuma numeración de facturación.

### Resumen de la sala

Encima de la sala hay cuatro métricas —total de mesas, libres, ocupadas y olvidadas— que vienen
**plegadas por defecto**: en la tablet de sala lo que importa es ver las mesas, y las tarjetas
empujaban el plano fuera de la primera pantalla. Se despliegan con el botón **Resumen** y la
elección se recuerda por usuario y dispositivo, igual que la de vista (tarjetas o plano), porque
varias personas comparten la misma tablet.

### Plano de sala arrastrable

Quien tiene permiso de Configuración puede pulsar **Editar plano** en la Sala para reordenar las
mesas de la zona activa arrastrándolas sobre el **lienzo de esa zona**, una rejilla de celdas cuyo
tamaño decide el propio encargado (ver «Forma y medidas de la zona» más abajo). Una zona nueva
nace con 8 columnas × 6 filas, que es lo que tenían todas las zonas antes de que el lienzo fuera
configurable.

Cada mesa **ocupa un rectángulo de celdas** de esa rejilla: su celda de origen más el ancho y el
alto en celdas que se le hayan dado. Se redimensiona **arrastrando su borde o su esquina**, y el
tamaño encaja siempre en celdas enteras. La **forma** (redonda, cuadrada, barra) se cambia
tocando la mesa y ya solo decide su aspecto y cómo se reparten las sillas dibujadas alrededor, no
el espacio que reserva: una barra puede ser de una sola celda y una mesa cuadrada de 3×2. **No
existe una forma «rectangular»**: una mesa rectangular es una cuadrada estirada, y su borde se
redondea menos en cuanto deja de ser un cuadrado. La `barra` sí se conserva como forma propia
porque sus sillas van solo en el lado largo, y eso no se puede deducir del tamaño. Ya **no existe** el antiguo atributo de tamaño «pequeña / mediana / grande». El número de
sillas dibujadas crece con la mesa, como señal visual; no es la capacidad de comensales como dato
de negocio. Nada se persiste hasta pulsar **Guardar plano**: cambiar de zona o salir sin guardar
descarta los cambios pendientes.

**Dos mesas nunca se solapan.** Al agrandar, el borde se detiene en el último tamaño válido cuando
choca con otra mesa o con el límite de la rejilla (con un resalte momentáneo), y puede seguir
creciendo por el eje que sí tenga hueco; no se aparta a nadie. Las formas alargadas tampoco
desbordan ya sobre celdas ajenas, como hacían antes de forma decorativa. Mover sí es distinto: si
se suelta una mesa encima de otra, la desplazada se reubica sola en el hueco libre más cercano
donde quepa entera (nunca se pierde ni queda oculta); si no hay espacio suficiente para ella, el
movimiento se cancela con un aviso. El servidor revalida toda la geometría en cada guardado y
rechaza el plano entero —sin cambios parciales— si alguna mesa se sale de la rejilla o se solapa
con otra. Cada zona tiene su propio plano, independiente de las demás,
y cambiar de pestaña de zona sin guardar **no** descarta el arrastre pendiente de la zona anterior
(se conserva en memoria mientras dure el modo edición; se pierde solo al salir de "Editar plano"
sin guardar, o al recargar la página). El guardado usa el mismo mecanismo de bloqueo optimista que
las cuentas abiertas: si dos personas editan el plano de la misma zona a la vez, la segunda en
guardar recibe un aviso para recargar en vez de pisar los cambios de la primera.

### Forma y medidas de la zona

Cada zona tiene **su propio lienzo**: no hay una rejilla única para todo el local. En la barra del
modo edición se ajustan el **ancho** y el **alto** de la zona en celdas, entre 4 y 24 cada uno. Una
terraza pequeña deja así de dibujarse con el mismo tamaño que el salón. El cambio se ve al momento
pero no se guarda hasta pulsar **Guardar plano**, igual que las posiciones de las mesas.

La sala tampoco tiene por qué ser un rectángulo. El botón **Recortar sala** activa un modo aparte en
el que arrastrar el dedo por el plano marca qué celdas **no son sala**: el hueco de una planta en L,
un patio, un pilar, la barra. Esas celdas se dibujan como un hueco con trama gris y **ninguna mesa
puede colocarse encima** —ni moviéndola, ni agrandándola—. Volver a arrastrar sobre una celda
recortada la devuelve a sala. Mientras el modo recorte está activo las mesas no se mueven ni se
redimensionan: es deliberado, para que el mismo gesto de arrastre no signifique dos cosas distintas.

**Ningún ajuste del lienzo mueve, encoge ni borra una mesa.** Si al reducir el ancho o el alto
alguna mesa quedaría fuera, el cambio no se aplica y se avisa de **cuántas** mesas lo impiden; hay
que moverlas primero. Si se intenta recortar una celda con una mesa encima, la celda no cambia, la
mesa que estorba parpadea, y al soltar el gesto se avisa una sola vez con el total de celdas
rechazadas. Una zona conserva siempre al menos una celda de sala: no se puede recortar entera. Y si
la zona se encoge, el recorte de las celdas que dejan de existir se descarta —si más tarde vuelve a
crecer, esas celdas vuelven como suelo, no como recorte recordado—.

El servidor revalida todo esto en cada guardado y rechaza el plano entero si alguna mesa se sale del
lienzo o queda sobre una celda recortada: medidas, recorte y mesas se guardan juntos o no se guarda
nada.

Ajustar el lienzo requiere permiso de Configuración, pero **verlo no**: la vista de plano en modo
servicio dibuja las medidas y la forma reales de la zona, con sus huecos, para cualquier usuario con
acceso a la Sala. El camarero ve exactamente la sala que colocó el encargado.

En modo edición, un panel a la derecha del lienzo permite **crear, renombrar y eliminar zonas y
mesas** sin salir de la Sala. El alta se confirma de forma explícita: se pulsa «+», se escribe
el nombre y se confirma con el botón de **check** (o con Enter); la X o Escape la descartan, y
salir del campo sin confirmar **no** crea nada. Renombrar, en cambio, sí se guarda al salir del
campo. Es el único sitio donde se dan de alta o se editan: Configuración →
POS ya solo contiene los ajustes del módulo (interruptor maestro, suplemento por zona, etc.), no el
listado de zonas y mesas. Restricciones: una zona con mesas o una mesa con cuenta abierta no se
pueden eliminar.

## Opciones de artículo (modificadores)

Desde POS → Opciones de artículo se definen **grupos** (p. ej. "Punto de cocción") con reglas de
selección (obligatorio u opcional, mínimo y máximo de opciones) y las **opciones** de cada grupo
(p. ej. "Al punto", "Muy hecho"), con un precio por defecto. Luego se asignan a los artículos desde
el catálogo, y ahí se fija el **precio real para ese artículo** — el mismo extra puede costar
distinto según el plato.

Al vender, un artículo con opciones abre un modal de selección; uno sin opciones se añade directo,
en un toque, igual que cualquier producto normal. Las opciones elegidas aparecen bajo el nombre
del plato en el ticket, sin ser una línea aparte del documento.

Una opción puede llevar un **artículo vinculado** del catálogo (p. ej. "Refresco incluido" vinculado
a un refresco concreto): al venderla, descuenta stock de ese artículo aunque no aparezca como línea
propia en el ticket.

## Cobro por partes (cobro dividido de la cuenta)

Con esta capacidad activa, al cobrar una cuenta se puede elegir **qué líneas cobrar** en vez de la
cuenta entera — útil cuando varios comensales pagan por separado. Cada cobro genera su propio
ticket (factura simplificada), numerado correlativamente como cualquier otro. La mesa sigue
ocupada mostrando lo que queda pendiente hasta que se salda todo, momento en el que se libera sola.

## Transferir y unir mesas

Una cuenta abierta se puede mover a otra mesa (transferir) o combinar con la cuenta de otra mesa
(unir), sin perder nada de lo ya cargado. Si se intenta transferir a una mesa que ya tiene una
cuenta abierta, se ofrece unirlas en vez de fallar.

## Suplemento por zona

Una zona (p. ej. "Terraza") puede llevar un recargo porcentual que se aplica al precio de cada
línea al cobrar, sobre lo que esté pendiente en ese momento — no al añadir la línea. Se ve tanto
en la mesa/cuenta como en el modal de cobro, para que nunca sea un aumento silencioso del importe.
