# POS — módulo de hostelería (mesas y opciones de artículo)

Ampliación opcional del POS para bares y restaurantes. Se activa por tenant desde
**Configuración → POS** (interruptor maestro) y trae tres capacidades que también se activan por
separado: opciones de artículo, cobro por selección de líneas (cobro dividido de la cuenta) y
suplemento por zona. **Desactivado por defecto**: si el tenant no lo activó, nada de esto existe
en su POS.

## Sala y cuentas abiertas

La pantalla **Sala** (menú POS → Sala) muestra todas las mesas del local agrupadas por zona, con
su estado: libre (borde gris), ocupada (borde verde, con el importe pendiente y los minutos desde
que se abrió) u olvidada (borde ámbar, cuando lleva más tiempo del umbral configurado sin que
nadie le añada nada). Tocar una mesa libre abre el TPV con esa mesa; tocar una ocupada retoma su
cuenta.

Una **cuenta abierta** no es un ticket ni una factura en borrador: es su propia entidad, sin
número asignado, que no aparece en ningún listado de facturas hasta que se cobra. Se puede
guardar, recuperar y anular sin que eso consuma numeración de facturación.

### Plano de sala arrastrable

Quien tiene permiso de Configuración puede pulsar **Editar plano** en la Sala para reordenar las
mesas de la zona activa arrastrándolas sobre una rejilla fija (8 columnas × 6 filas por zona).
Tocar una mesa (sin arrastrarla) abre un selector para cambiar su **forma** (redonda, cuadrada,
rectangular, barra) y su **tamaño** (pequeña, mediana, grande) — propiedades puramente visuales del
mobiliario, sin efecto en el funcionamiento de la cuenta. Nada se persiste hasta pulsar **Guardar
plano**: cambiar de zona o salir sin guardar descarta los cambios pendientes.

Si se suelta una mesa sobre una celda ya ocupada, la mesa que estaba ahí se reubica sola en la
celda libre más cercana (nunca se pierde ni queda oculta); si la zona no tiene ninguna celda libre,
el movimiento se cancela con un aviso. Cada zona tiene su propio plano, independiente de las demás,
y cambiar de pestaña de zona sin guardar **no** descarta el arrastre pendiente de la zona anterior
(se conserva en memoria mientras dure el modo edición; se pierde solo al salir de "Editar plano"
sin guardar, o al recargar la página). El guardado usa el mismo mecanismo de bloqueo optimista que
las cuentas abiertas: si dos personas editan el plano de la misma zona a la vez, la segunda en
guardar recibe un aviso para recargar en vez de pisar los cambios de la primera.

En modo edición, un panel a la derecha del lienzo permite **crear, renombrar y eliminar zonas y
mesas** sin salir de la Sala. Es el único sitio donde se dan de alta o se editan: Configuración →
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
