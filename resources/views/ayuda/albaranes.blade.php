<p>El <strong>albarán</strong> registra una entrega física antes de facturarla. No es un documento
fiscal: sirve para mover el stock en el momento real de la entrega y para agrupar varias entregas
de un mismo cliente en una única factura.</p>

<ol>
	<li><strong>Crear</strong>: desde un presupuesto aceptado (heredando sus líneas, con la cantidad
		acotada a lo pendiente de entrega) o directo a un cliente, sin presupuesto previo.</li>
	<li><strong>Ciclo de vida</strong>: <code>borrador → entregado → facturado</code>, con
		<code>anulado</code> como salida desde "entregado" si no se llegó a facturar. Al confirmar
		como entregado, el stock de los artículos con gestión de stock baja en ese momento.</li>
	<li><strong>Facturar varios a la vez</strong>: desde el listado, marcá los albaranes entregados
		de un mismo cliente y usá "Convertir a factura" — se crea una factura borrador con todas las
		líneas, sin volver a mover stock (ya se movió al entregar cada uno).</li>
</ol>

<p class="ayuda-nota">Un mismo presupuesto se puede entregar en varios albaranes parciales, pero la
suma nunca puede superar la cantidad de cada línea. Un albarán ya facturado queda de solo lectura y
no se puede anular.</p>
