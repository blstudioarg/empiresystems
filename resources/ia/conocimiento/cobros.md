# Cobros

Pantalla `/cobros` (menú Facturas → Cobros, permiso `ver-cobros`) que centraliza la gestión de
cobro de facturas emitidas: cuánto se debe, quién lo debe y qué se cobró. No introduce reglas de
negocio nuevas — reutiliza el registro/anulación de pagos y el cálculo de saldo y estado de cobro
que ya existen en el módulo de Facturas.

## Resumen (cards)

Cuatro cifras, cada una con un criterio de fecha explícito:

- **Pendiente de cobro** y **Vencido**: instantáneas a hoy, no dependen del rango de fechas
  elegido. "Vencido" suma el saldo de las facturas con fecha de vencimiento pasada.
- **Cobrado en el periodo**: depende del rango seleccionado (mes/trimestre/año/personalizado) y se
  calcula por la **fecha del cobro**, no por la fecha de la factura.
- **Facturas pendientes**: número de facturas con saldo mayor que cero, también instantánea.

## Listado

Una factura por operación de cobro: los borradores no aparecen, y una factura rectificativa nunca
aparece con fila propia — el cobro se gestiona siempre desde la **factura original ya
rectificada**, con su importe efectivo (no su total bruto). Se puede filtrar por estado de cobro
(pendiente/parcial/cobrada), cliente, serie, rango de fechas y "solo vencidas" (combinables entre
sí), buscar por número de factura o nombre de cliente, y ordenar por fecha, saldo pendiente o días
de retraso.

## Registrar y anular un cobro

Desde el menú "Acciones" de cada fila: "Registrar cobro" abre un formulario con la fecha de hoy y
el saldo pendiente prerrellenados (importe, fecha, forma de pago, referencia opcional); un importe
mayor al saldo, cero o negativo se rechaza sin registrar nada. "Cobros" muestra el historial
completo de esa factura (vigentes y anulados) y permite anular un cobro vigente con confirmación
explícita — un cobro anulado sigue visible marcado como tal y no puede volver a anularse. Tras
registrar o anular, la fila y las cuatro cards de arriba se actualizan solas.

## Ver la factura

Desde "Ver factura" se abre el PDF en una ventana superpuesta, sin perder los filtros, el orden ni
la página del listado.

## Permisos

`ver-cobros` es un permiso propio, independiente de `ver-facturas`. Un usuario con `ver-facturas`
pero sin `ver-cobros` no ve las acciones de cobro en el módulo de Facturas ni puede acceder a
`/cobros`. Un usuario con `ver-cobros` pero sin `ver-facturas` puede registrar y anular cobros
pero no ve la opción "Ver factura" (esa acción exige `ver-facturas`).
