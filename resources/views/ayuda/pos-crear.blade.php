<p>Esta es la pantalla de venta rápida (TPV), pensada para cobrar de mostrador con el dedo, en
tablet o pantalla táctil. Tocás productos y se van sumando al ticket de la derecha.</p>

<ol>
	<li><strong>Buscar y filtrar</strong>: usá el buscador o los botones de categoría de arriba
		para encontrar el producto rápido.</li>
	<li><strong>Armar el ticket</strong>: cada toque en un producto lo agrega; ajustá las
		cantidades en el detalle de la derecha.</li>
	<li><strong>Cobrar</strong>: tocás <em>Cobrar</em> y elegís el método de pago (efectivo,
		tarjeta, transferencia o domiciliación). Podés <strong>dividir el pago</strong> en varios
		métodos: elegís uno, tecleás su importe y lo añadís; el «Restante» te va indicando cuánto
		falta hasta llegar a cero. Al completar el total se habilita <em>Emitir ticket</em> y se
		emite como factura simplificada al instante.</li>
</ol>

<p class="ayuda-nota">El reparto por métodos es <strong>interno</strong>: sirve para tu control de
caja y se consulta en el listado de tickets. No aparece en el PDF del ticket que recibe el cliente.</p>

<p class="ayuda-nota">Un producto en rojo (“Sin stock”) o en ámbar (“Quedan pocos”) igual se puede
vender: el badge es solo un aviso, no un bloqueo.</p>

<p class="ayuda-nota">En efectivo, podés teclear lo que te <strong>entregó</strong> el cliente y la
pantalla calcula cuánto <strong>devolver</strong>. Es solo una ayuda de caja: no cambia el importe
cobrado ni aparece en el ticket impreso.</p>

@if ($hosteleriaActiva ?? false)
	<hr>
	<p><strong>Con el módulo de hostelería activo</strong>, esta pantalla se usa también para
	atender mesas, no solo ventas de mostrador.</p>

	<ol>
		<li><strong>Contexto de mesa</strong>: si venís de la Sala (o tocaste una mesa libre), la
			cabecera del ticket muestra un chip con el nombre de la mesa y el importe pendiente.
			Desde ahí podés <em>anular</em> la cuenta (⨯) o <em>transferirla/unirla</em> con otra
			mesa (⇄).</li>
		<li><strong>Guardar y aparcar</strong>: en vez de cobrar de inmediato, podés tocar
			<em>Guardar</em> para dejar la cuenta abierta con lo cargado hasta ahora, y
			<em>Aparcadas</em> para volver a la Sala y atender otra mesa mientras tanto.</li>
		<li><strong>Artículos con opciones</strong>: un plato con modificadores (punto de cocción,
			extras…) abre un modal de selección al tocarlo; uno sin opciones se añade directo, en
			un toque, igual que siempre.</li>
		<li><strong>Cobro por partes</strong> (si está activado): en el modal de cobro podés elegir
			qué líneas cobrar en vez de la cuenta entera. La mesa sigue ocupada mostrando lo que
			falta, hasta que se salda todo.</li>
	</ol>

	<p class="ayuda-nota">Si dos camareros abren la misma cuenta en dispositivos distintos y ambos
	guardan cambios, el segundo en guardar recibe un aviso y la pantalla se recarga con los datos
	actuales — nunca se pisa en silencio el trabajo del primero.</p>

	<p class="ayuda-nota">Transferir a una mesa que ya tiene una cuenta abierta te ofrece
	<strong>unirlas</strong> en vez de fallar: nunca se pierde consumo ya cargado.</p>
@endif
