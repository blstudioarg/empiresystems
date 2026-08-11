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

## Pago dividido (métodos de cobro)

Al cobrar, se elige con qué método se pagó: **efectivo, tarjeta, transferencia o domiciliación**.
El importe puede **dividirse entre varios métodos** (por ejemplo, parte en efectivo y parte con
tarjeta): se elige un método, se teclea su importe y se añade; el "Restante" baja hasta cero y
recién ahí se puede emitir. El reparto siempre cuadra al céntimo con el total del ticket.

Este desglose es **interno** (control de caja): se consulta en el listado de tickets —con un chip
"Dividido" cuando hay más de un método— pero **no aparece en el PDF** del ticket que recibe el
cliente, y no interviene en el módulo de cobros de facturas ni en los KPIs del dashboard. Si no se
especifica reparto, el ticket se registra como cobrado íntegro en efectivo.

En efectivo se puede teclear lo "entregado" y la pantalla calcula el "devolver" (cambio): es solo
una ayuda de caja, no cambia el importe cobrado ni figura en el ticket.

## Módulo de hostelería (mesas, opciones de artículo)

El POS tiene un módulo opcional para bares y restaurantes —mesas, cuentas abiertas, opciones de
plato, cobro por partes— que cada tenant activa (o no) desde Configuración → POS. Está
**desactivado por defecto**: si el tenant no lo activó, todo lo de esta sección no existe para él y
el POS funciona exactamente como se describe arriba. Ver `pos-hosteleria.md` para el detalle.
