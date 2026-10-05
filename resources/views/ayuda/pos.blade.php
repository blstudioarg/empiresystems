<p>{!! __('Acá ves los <strong>tickets</strong> ya emitidos: las facturas simplificadas del punto de venta (TPV), pensadas para ventas rápidas de mostrador sin cargar todos los datos del cliente.') !!}</p>

<ol>
	<li>{!! __('<strong>Nuevo ticket</strong>: abre la pantalla de venta rápida (grid de productos) para cobrar en el momento.') !!}</li>
	<li>{!! __('<strong>Ver / imprimir</strong>: desde Acciones abrís la vista previa del ticket para reimprimirlo o descargarlo.') !!}</li>
	<li>{!! __('<strong>Anular</strong>: si un ticket se emitió por error, en Acciones elegí <em>Anular</em> y escribí el motivo. Queda en el listado marcado como <em>Anulado</em> con su número, deja de contar en las ventas y en la caja abierta, y lo vendido vuelve al stock. Hace falta el permiso <em>Anular tickets</em>.') !!}</li>
	<li>{!! __('<strong>Pago</strong>: la columna muestra cómo se cobró cada ticket. Si se dividió en varios métodos, verás el chip <em>Dividido</em> con el detalle de cada método y su importe.') !!}</li>
</ol>

<p class="ayuda-nota">{!! __('El total del día, cómo te pagaron y el arqueo del efectivo están en <strong>POS → Caja</strong>, que también guarda el historial de cierres.') !!}</p>

<p class="ayuda-nota">{!! __('<strong>Anular no es devolver</strong>: se anula un ticket que no debió emitirse (duplicado, mesa equivocada). Si el cliente devuelve algo que ya se llevó, se hace una rectificativa. Un cierre de caja ya hecho no cambia al anular un ticket de ese día.') !!}</p>

<p class="ayuda-nota">{!! __('Un ticket es una factura simplificada: se emite en el acto y, como toda factura emitida, no se edita después. Para importes altos o clientes que necesitan factura completa con sus datos, usá <strong>Facturas</strong>.') !!}</p>
