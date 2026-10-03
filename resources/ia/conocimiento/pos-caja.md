# Caja del POS (apertura, arqueo y cierre)

La **caja** es el control del efectivo del día en el POS. Vive en **POS → Caja** y necesita el
permiso "Caja" (`ver-pos-caja`). Hay **una sola caja por negocio**, compartida por todas las
tablets: todo lo que se cobra en cualquier dispositivo mientras está abierta cuenta en ella.

## Abrir

Se abre indicando el **fondo de cambio** (el efectivo con el que arranca el cajón), como importe o
contando billete a billete. **Sin caja abierta no se puede cobrar** en el POS (ni tickets ni cuentas
de mesa): el TPV muestra un chip "Caja cerrada" y, al tocar Cobrar, deja abrirla ahí mismo y sigue
al cobro sin perder el ticket armado. Puede abrir la caja quien tiene el permiso de caja o el de
crear tickets. Armar tickets y guardar cuentas de mesa sí se puede sin caja.

## Durante el turno

La pantalla de caja muestra en vivo (informe X) lo vendido, el número de tickets, el ticket medio y
cuánto se cobró por cada método (efectivo, tarjeta, transferencia, domiciliación), más el fondo y
los movimientos. Los **movimientos** son entradas o salidas manuales de efectivo que no son ventas:
pago a un proveedor, retirada a la caja fuerte, cambio. Se registran con importe y motivo. Un
movimiento equivocado **no se edita ni se borra**: se registra otro del tipo contrario.

## Cerrar (arqueo)

Al cerrar se cuenta el efectivo del cajón por billetes y monedas (o como importe total). El conteo
es **a ciegas**: no se muestra lo esperado hasta confirmar, para que el arqueo sea honesto. El
sistema calcula entonces:

- **Esperado** = fondo + ventas en efectivo + entradas − salidas.
- **Diferencia** = contado − esperado: *cuadra*, *sobrante* o *faltante*.

Si la diferencia supera un umbral (5,00 € por defecto, configurable en Configuración → POS →
"Diferencia máxima sin explicación"), hay que escribir qué pasó para poder cerrar. Solo el efectivo
se arquea; tarjeta y demás métodos se informan como total cobrado.

## Informe Z e historial

El cierre genera el **informe Z**: tickets y total facturado, desglose por método de pago y por
tipo de impuesto (IVA, IGIC o IPSI según el negocio), primer y último ticket, anulados, movimientos,
arqueo y la observación si la hubo. Se imprime en rollo de 80 mm o en A4. Todos los cierres quedan
en el **historial** (POS → Caja → Historial). Es un documento de control interno, no fiscal.

Un cierre **no se puede modificar ni borrar**. Si después se anula un ticket de ese día, el cierre
sigue mostrando lo que había en el cajón al cerrar. Cubre solo lo vendido en el POS: las facturas
ordinarias y sus cobros tienen sus propios módulos.
