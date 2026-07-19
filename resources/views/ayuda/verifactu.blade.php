<p><strong>Verifactu</strong> es el sistema anti-fraude de la Agencia Tributaria: cada factura emitida
queda sellada con una huella encadenada a la anterior y, si está activado, se envía en tiempo real a
la AEAT. Se activa en <strong>Configuración → Verifactu</strong>; hasta entonces, emitir funciona
exactamente igual que hoy, sin ningún dato Verifactu.</p>

<ol>
	<li><strong>Activarlo</strong>: en Configuración → Verifactu, encendé el interruptor y elegí el
		entorno (<strong>Pruebas</strong> para probar sin efectos fiscales, <strong>Producción</strong>
		para el envío real). El entorno no se puede cambiar una vez emitida la primera factura con el
		flag activo.</li>
	<li><strong>Estado en la factura</strong>: cada factura registrada muestra una etiqueta bajo su
		estado — <strong>Registrada</strong> (sellada, envío pendiente), <strong>Enviada</strong>
		(aceptada por la AEAT) o <strong>Error</strong> (el envío falló; el registro local ya quedó
		sellado igualmente, así que la factura es válida).</li>
	<li><strong>Reintentar envío</strong> (menú Acciones, solo si está en error): reenvía el mismo
		registro sin cambiar su huella. También hay un reintento automático programado.</li>
	<li><strong>QR y "VERI*FACTU"</strong>: aparecen en el PDF y en el ticket de cualquier factura
		registrada, incluso si el envío está en error — el QR no depende de que la AEAT haya
		respondido.</li>
	<li><strong>Anular</strong> (menú Acciones): solo para un registro erróneo que todavía no tuvo
		cobros. Para corregir una factura ya en circulación, se usa una <strong>rectificativa</strong>.</li>
</ol>

<p class="ayuda-nota">Un fallo de la AEAT nunca bloquea la emisión: la factura siempre queda emitida
y con su huella sellada, aunque el envío quede en error hasta el próximo reintento.</p>
