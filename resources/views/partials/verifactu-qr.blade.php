{{--
	QR de cotejo AEAT + texto "VERI*FACTU" (FR-009, docs/02-facturacion-espana.md §1.2). Compartido
	por facturas/pdf.blade.php (A4) y facturas/ticket-80mm.blade.php. Solo se muestra si la factura
	tiene registro Verifactu sellado (huella no nula); las emitidas con el flag apagado no muestran
	nada. Reutiliza `qr_contenido` ya persistido (no recompone la URL en cada render).
--}}
@if ($factura->tieneRegistroVerifactu())
	<div class="verifactu-qr">
		<div class="verifactu-qr__etiqueta">QR tributario</div>
		<img class="verifactu-qr__imagen" src="{{ \App\Support\QrVerifactu::imagen($factura->qr_contenido) }}" alt="QR tributario Verifactu">
		<div class="verifactu-qr__leyenda">VERI*FACTU</div>
	</div>
@endif
