<p>Acá gestionás <strong>presupuestos</strong>: ofertas comerciales para un cliente o un lead, sin
valor fiscal. Reutilizan el mismo motor de cálculo que las facturas, así que el total coincide al
céntimo con una factura equivalente.</p>

<ol>
	<li><strong>Crear</strong>: elegí un cliente o un lead como receptor, agregá líneas (con su
		cantidad, precio e impuesto) y guardá. Los totales los calcula siempre el servidor.</li>
	<li><strong>Ciclo de vida</strong>: <code>borrador → enviado → aceptado/rechazado/caducado</code>.
		Solo en borrador se puede editar.</li>
	<li><strong>Convertir en factura</strong>: disponible solo cuando el presupuesto está
		“Aceptado”. Crea una factura en borrador con las mismas líneas e importes — todavía no
		consume numeración ni Verifactu, eso pasa recién al emitir la factura.</li>
</ol>

<p class="ayuda-nota">Un presupuesto ya convertido en factura queda de solo lectura — no se puede
	editar ni volver a convertir.</p>
