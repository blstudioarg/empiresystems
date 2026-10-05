# POS (punto de venta / TPV)

El POS es la pantalla de venta rápida de mostrador, pensada para tablet táctil. Emite **tickets**,
que son **facturas simplificadas** (el "ticket de caja" español): se emiten en el acto, sin cargar
todos los datos del cliente, y como toda factura emitida no se editan después. Viven en su propio
módulo, separadas de las facturas ordinarias.

- **Crear ticket**: se tocan productos del catálogo (solo productos, no servicios) y se van sumando
  al ticket. Se ajustan cantidades y se cobra. El PDF del ticket se imprime en rollo de 80 mm o A4.
- **Receptor opcional**: por defecto el ticket va a "consumidor final". Si el cliente pide una
  simplificada **cualificada** (con derecho a deducir IVA), se le informa NIF y domicilio.
- **Tope de importe**: una simplificada no puede superar el tope legal (400 € en general, 3.000 €
  en sectores con tope ampliado, según configuración del tenant). Por encima hay que emitir una
  factura ordinaria; el POS lo bloquea.

- **Caja**: para cobrar, la caja del día tiene que estar abierta (POS → Caja). Si está cerrada, el
  TPV deja abrirla al tocar Cobrar. Ver `pos-caja.md` para apertura, arqueo y cierre.

## Anular un ticket

Un ticket emitido **no se puede borrar** (es una factura: numeración sin huecos, Verifactu). Si se
emitió por error, se **anula** desde el listado de tickets (POS → Facturas simplificadas →
Acciones → Anular), escribiendo el motivo. Requiere el permiso **Anular tickets** (por defecto solo
el rol Administrador; se asigna en Roles). Efectos: el ticket queda «Anulado» con su número, deja de
contar en los totales del listado y en la caja abierta (lo vendido y el efectivo esperado), las
unidades vendidas vuelven al stock y, con Verifactu activo, se genera el registro de anulación. Un
cierre de caja ya hecho no cambia. No se puede anular un ticket ya anulado, con cobros registrados
aparte o que ya tenga una rectificativa. **Anular no es devolver**: una devolución de algo que el
cliente se llevó se hace con una rectificativa.

## Idioma del POS (chino)

El POS puede usarse en **chino simplificado** (Configuración → POS → Idioma del POS, para todo el
tenant). Los textos de la aplicación se traducen automáticamente una sola vez y se guardan (los
términos del oficio —precuenta, mesa, caja, arqueo…— tienen traducción fijada); un texto nuevo
puede verse en español la primera vez hasta que se traduce. Lo que **no** cambia con el idioma:
los datos del negocio (nombres de platos, mesas, zonas, clientes, tal como se cargaron), los
importes, la numeración, los impuestos, el QR y Verifactu. Con el POS en chino, el **ticket** (80
mm y A4) y la **precuenta** se imprimen **bilingües español/chino** (el castellano siempre está en
el documento, por normativa), y un PDF con caracteres chinos en los datos los muestra bien aunque el
POS esté en español. El informe de cierre de caja sale entero en el idioma del POS. Una traducción
que quedó mal se corrige en Configuración → POS → Traducciones del POS. El asistente IA no se
traduce.

## Pago dividido (métodos de cobro)

Al cobrar, se elige con qué método se pagó: **efectivo, tarjeta, transferencia o domiciliación**.
El importe puede **dividirse entre varios métodos** (por ejemplo, parte en efectivo y parte con
tarjeta): se elige un método, se teclea su importe y se añade; el "Restante" baja hasta cero y
recién ahí se puede emitir. El reparto siempre cuadra al céntimo con el total del ticket.

Este desglose es **interno** (control de caja): alimenta el cierre de caja y se consulta en el listado de tickets —con un chip
"Dividido" cuando hay más de un método— pero **no aparece en el PDF** del ticket que recibe el
cliente, y no interviene en el módulo de cobros de facturas ni en los KPIs del dashboard. Si no se
especifica reparto, el ticket se registra como cobrado íntegro en efectivo.

En efectivo se puede teclear lo "entregado" y la pantalla calcula el "devolver" (cambio): es solo
una ayuda de caja, no cambia el importe cobrado ni figura en el ticket. El campo "Entregado" no se
escribe con el teclado del sistema: al tocarlo se abre un teclado numérico propio dentro del modal
de cobro (el mismo tipo de teclado con el que se teclea el importe), que muestra el vuelto en vivo
y se confirma con "Listo" o se descarta con "Cancelar". Está pensado así para tablet, donde el
teclado del sistema tapa media pantalla y deja el importe fuera de vista.

## Módulo de hostelería (mesas, opciones de artículo)

El POS tiene un módulo opcional para bares y restaurantes —mesas, cuentas abiertas, opciones de
plato, cobro por partes— que cada tenant activa (o no) desde Configuración → POS. Está
**desactivado por defecto**: si el tenant no lo activó, todo lo de esta sección no existe para él y
el POS funciona exactamente como se describe arriba. Ver `pos-hosteleria.md` para el detalle.
