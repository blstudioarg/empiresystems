<p>La sala muestra todas las mesas del local y en qué estado está cada una. Es la pantalla desde
la que se abren, se retoman y se cobran las cuentas.</p>

<ol>
	<li><strong>Filtrar por zona</strong>: las pestañas de arriba filtran las mesas. «Todas» muestra
		el local entero.</li>
	<li><strong>Abrir una cuenta</strong>: tocá una mesa <em>libre</em> (borde gris) y se abre el
		TPV con esa mesa ya seleccionada. La cuenta se crea al guardar la primera línea, así que
		tocar una mesa por error no deja nada abierto.</li>
	<li><strong>Retomar una cuenta</strong>: tocá una mesa <em>ocupada</em> (borde verde) para
		volver a su cuenta y seguir añadiendo consumo.</li>
	<li><strong>Cobrar</strong>: desde la cuenta, con el botón <em>Cobrar</em> de siempre. Al
		saldarse todo, la mesa vuelve a quedar libre sola.</li>
</ol>

<p class="ayuda-nota">El importe que muestra cada mesa es lo que <strong>falta por cobrar</strong>,
no lo consumido. Si ya se cobró parte de la cuenta, la mesa sigue ocupada mostrando solo el resto.</p>

<p class="ayuda-nota">Una mesa con borde <strong>ámbar</strong> lleva mucho tiempo sin que nadie le
añada nada (el umbral se configura en <em>Configuración → POS</em>). Es un aviso para que no se
quede una cuenta olvidada sin cobrar, no un error.</p>

<p class="ayuda-nota">Mover una cuenta a otra mesa o juntar dos cuentas se hace desde la propia
cuenta, en el chip de mesa que aparece en la cabecera del ticket.</p>

<p><strong>Editar el plano de la sala</strong> (solo con permiso de Configuración): el botón
<em>Editar plano</em> activa el lienzo de la zona seleccionada, donde cada mesa se arrastra por su
asa (el círculo con la flecha) a una celda libre distinta. Tocar una mesa (sin arrastrarla) abre un
panel para cambiar su forma (redonda, cuadrada, rectangular, barra) y su tamaño. Nada se guarda
hasta pulsar <em>Guardar plano</em>: recargar la página o cambiar de zona sin guardar descarta los
cambios pendientes.</p>

<p class="ayuda-nota">Si sueltas una mesa exactamente sobre otra, la mesa que ya estaba ahí se
reubica sola en la celda libre más cercana — nunca se pierde ni queda oculta. Si la zona no tiene
ninguna celda libre, el movimiento se cancela con un aviso.</p>

<p>A la derecha del lienzo, en modo edición, hay un panel para <strong>gestionar zonas y
mesas</strong> sin salir de la Sala: crear (botón «+»), renombrar (tocar el nombre y escribir) y
eliminar (papelera, con confirmación). Es el mismo CRUD de <em>Configuración → POS</em>, solo que
más rápido de usar desde una tablet mientras se arma el plano.</p>

<p class="ayuda-nota">Cambiar de pestaña de zona ya no descarta el arrastre pendiente de la zona
anterior: cada zona conserva su propio plano sin guardar mientras dure el modo edición. Solo se
pierde si se sale de «Editar plano» sin guardar, o se recarga la página.</p>
