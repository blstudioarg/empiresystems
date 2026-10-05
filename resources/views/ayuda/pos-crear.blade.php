<p>{!! __('Esta es la pantalla de venta rápida (TPV), pensada para cobrar de mostrador con el dedo, en tablet o pantalla táctil. Tocás productos y se van sumando al ticket de la derecha.') !!}</p>

<ol>
	<li>{!! __('<strong>Buscar y filtrar</strong>: usá el buscador o los botones de categoría de arriba para encontrar el producto rápido.') !!}</li>
	<li>{!! __('<strong>Armar el ticket</strong>: cada toque en un producto lo agrega; ajustá las cantidades en el detalle de la derecha.') !!}</li>
	<li>{!! __('<strong>Cobrar</strong>: tocás <em>Cobrar</em> y elegís el método de pago (efectivo, tarjeta, transferencia o domiciliación). Podés <strong>dividir el pago</strong> en varios métodos: elegís uno, tecleás su importe y lo añadís; el «Restante» te va indicando cuánto falta hasta llegar a cero. Al completar el total se habilita <em>Emitir ticket</em> y se emite como factura simplificada al instante.') !!}</li>
</ol>

<p class="ayuda-nota">{!! __('Para cobrar, la <strong>caja tiene que estar abierta</strong> (lo ves en el chip junto a «Ticket»). Si está cerrada, al tocar <em>Cobrar</em> te la deja abrir ahí mismo con el fondo de cambio y seguís cobrando; el ticket que armaste no se pierde.') !!}</p>

<p class="ayuda-nota">{!! __('El reparto por métodos es <strong>interno</strong>: sirve para tu control de caja y se consulta en el listado de tickets. No aparece en el PDF del ticket que recibe el cliente.') !!}</p>

<p class="ayuda-nota">{!! __('Un producto en rojo (“Sin stock”) o en ámbar (“Quedan pocos”) igual se puede vender: el badge es solo un aviso, no un bloqueo.') !!}</p>

<p class="ayuda-nota">{!! __('En efectivo, tocá el campo <strong>Entregado</strong> y se abre un teclado dentro de la propia pantalla —nunca el teclado del sistema, que en tablet taparía el importe— para escribir lo que te dio el cliente; mientras tecleás ya se ve cuánto hay que <strong>devolver</strong>, y con <em>Listo</em> queda guardado. Es solo una ayuda de caja: no cambia el importe cobrado ni aparece en el ticket impreso.') !!}</p>

@if ($hosteleriaActiva ?? false)
	<hr>
	<p>{!! __('<strong>Con el módulo de hostelería activo</strong>, esta pantalla se usa también para atender mesas, no solo ventas de mostrador.') !!}</p>

	<ol>
		<li>{!! __('<strong>Contexto de mesa</strong>: si venís de la Sala (o tocaste una mesa libre), la cabecera del ticket muestra un chip con el nombre de la mesa y el importe pendiente. Desde ahí podés <em>anular</em> la cuenta (⨯) o <em>transferirla/unirla</em> con otra mesa (⇄).') !!}</li>
		<li>{!! __('<strong>Guardar y aparcar</strong>: en vez de cobrar de inmediato, podés tocar <em>Guardar</em> para dejar la cuenta abierta con lo cargado hasta ahora, y <em>Aparcadas</em> para volver a la Sala y atender otra mesa mientras tanto. Al guardar, <strong>la pantalla se vacía</strong> y queda lista para el ticket siguiente: la cuenta no se cierra ni se pierde nada, sigue abierta en su mesa con todo lo guardado, y se retoma tocando esa mesa en la Sala. Es lo mismo que pasa al cobrar, para que no te quedes escribiendo sin querer sobre la cuenta anterior.') !!}</li>
		<li>{!! __('<strong>Artículos con opciones</strong>: un plato con modificadores (punto de cocción, extras…) abre un modal de selección al tocarlo; uno sin opciones se añade directo, en un toque, igual que siempre.') !!}</li>
		<li>{!! __('<strong>Precuenta</strong>: cuando la mesa pide la cuenta, tocá <em>Precuenta</em> en el chip de la mesa. Se guarda lo que tengas en pantalla y aparece la vista previa de un papel titulado «PRECUENTA» con lo consumido pendiente de cobro y el total; desde ahí la <em>imprimís</em> y se la llevás a la mesa. <strong>No es un ticket ni cobra nada</strong>: no tiene número, no cuenta como venta y lleva la leyenda «Documento no válido como factura». Al cerrar la vista previa la pantalla queda en cero, igual que al guardar, y la cuenta sigue abierta en su mesa. Cuando el cliente paga, cobrás como siempre y entonces sí se emite el ticket. Mientras la precuenta siga valiendo, el chip de la mesa muestra un check (✓); si la volvés a sacar sin cambios, sale marcada como «Reimpresión».') !!}</li>
		<li>{!! __('<strong>Cobro por partes</strong>: <em>disponible próximamente</em>. Por ahora, desde el TPV la cuenta se cobra entera.') !!}</li>
	</ol>

	<p class="ayuda-nota">{!! __('Si después de dar la precuenta añadís o quitás algo y guardás, te avisa de que <strong>la precuenta impresa ya no coincide</strong> y el chip de la mesa muestra «Desactualizada» en ámbar. Reimprimila antes de cobrar: al abrir el cobro también vas a ver la cifra de la precuenta junto al total actual, para que nunca se cobre algo distinto de lo que el cliente revisó sin darte cuenta. Cambiar las notas, los comensales o los datos del cliente no la desactualiza.') !!}</p>

	<p class="ayuda-nota">{!! __('Si dos camareros abren la misma cuenta en dispositivos distintos y ambos guardan cambios, el segundo en guardar recibe un aviso y la pantalla se recarga con los datos actuales — nunca se pisa en silencio el trabajo del primero.') !!}</p>

	<p class="ayuda-nota">{!! __('Transferir a una mesa que ya tiene una cuenta abierta te ofrece <strong>unirlas</strong> en vez de fallar: nunca se pierde consumo ya cargado.') !!}</p>
@endif
