/**
 * Vista de plano de la Sala en **modo servicio** (feature 041).
 *
 * Dibuja el mismo plano que colocó el encargado, pero para trabajar: se ve el estado de cada mesa
 * y se toca para ir a su cuenta. No hay arrastre, ni redimensionado, ni popover, ni una sola
 * petición de escritura — el plano solo se modifica desde el editor (`pos-sala-plano.init.js`),
 * que sigue requiriendo `ver-configuracion`.
 *
 * El dibujo NO se reimplementa aquí: viene de `pos-plano-dibujo.js`, el mismo módulo que usa el
 * editor. Es lo que garantiza que lo que ve el camarero coincida con lo que colocó el encargado.
 */
(function () {
	'use strict';

	var D = window.PosPlanoDibujo;

	var $wrap = document.getElementById('pos-plano-servicio');
	var $escala = document.getElementById('pos-plano-servicio-escala');
	var $canvas = document.getElementById('pos-plano-servicio-canvas');
	var $vacio = document.getElementById('pos-plano-servicio-vacio');
	var $sinSitio = document.getElementById('pos-plano-servicio-sin-sitio');
	var $sinSitioGrid = document.getElementById('pos-plano-servicio-sin-sitio-grid');

	if (!$wrap || !$canvas) { return; }

	function zonaActiva() {
		return window.posSalaZonaActiva ? window.posSalaZonaActiva() : '';
	}

	/**
	 * Lienzo de la zona activa (feature 042). Se lee del payload, igual que en el editor: es lo que
	 * garantiza que el camarero vea EXACTAMENTE la sala que coloco el encargado -- mismas medidas y
	 * mismos huecos-- y no un rectangulo generico.
	 */
	function geometriaActiva() {
		var datos = window.posSalaData || { zonas: [] };
		var zona = datos.zonas.filter(function (z) { return String(z.id) === String(zonaActiva()); })[0];

		return D.geometriaDeZona(zona);
	}

	function mesasDeZona() {
		var datos = window.posSalaData || { mesas: [] };
		var zona = zonaActiva();
		if (zona === '') { return []; }

		return datos.mesas.filter(function (m) { return String(m.zona_id) === String(zona); });
	}

	/**
	 * Encaje del lienzo en pantalla (D9, FR-021): si el ancho disponible es menor que el del
	 * lienzo, se escala proporcionalmente para que el ancho SIEMPRE quepa, dejando como mucho
	 * scroll vertical. Un plano que obliga a arrastrar en los dos ejes para encontrar una mesa es
	 * peor que la rejilla de tarjetas que viene a mejorar.
	 *
	 * Solo en la vista de servicio: en el editor el usuario está colocando mesas y necesita el
	 * tamaño real de la celda (FR-020).
	 */
	function encajar() {
		if (!$escala || $wrap.classList.contains('d-none')) { return; }

		var tamano = D.tamanoLienzo(geometriaActiva());
		var anchoLienzo = tamano.width;
		var altoLienzo = tamano.height;
		var disponible = $escala.parentNode.clientWidth;

		var factor = disponible > 0 && disponible < anchoLienzo ? disponible / anchoLienzo : 1;

		$canvas.style.transformOrigin = 'top left';
		$canvas.style.transform = factor === 1 ? '' : 'scale(' + factor + ')';
		// El contenedor deja de heredar la altura del lienzo en cuanto este se escala (`transform`
		// no afecta al flujo), así que se la fijamos nosotros para que la franja de abajo no se
		// solape con el plano.
		$escala.style.height = Math.round(altoLienzo * factor) + 'px';
	}

	function pintar() {
		var mesas = mesasDeZona();
		var geometria = geometriaActiva();

		var enRejilla = mesas.filter(function (m) { return m.fila !== null && m.columna !== null; });
		var sinSitio = mesas.filter(function (m) { return m.fila === null || m.columna === null; });

		// Las medidas del lienzo se escriben en ESTE lienzo, no en `documentElement` (D4): el
		// editor puede tener otra zona cargada y las dos vistas coexisten en la misma pagina.
		D.aplicarGeometria($canvas, geometria);

		// Capa de celdas SOLO con las inactivas (D5): al camarero le hace falta ver donde no hay
		// sala, no una rejilla de 576 divs de suelo que nadie va a tocar. Y aqui no hay ningun
		// control de edicion: esta vista no escribe nada.
		$canvas.innerHTML =
			'<div class="pos-plano-celdas">' + D.celdasHtml(geometria, { modo: 'servicio' }) + '</div>' +
			enRejilla.map(function (mesa) {
				return D.mesaHtml(mesa, { modo: 'servicio' });
			}).join('');

		// Mesas sin posición en la rejilla (FR-011): se dibujan con el MISMO componente de tarjeta
		// que la otra vista — cero markup y cero CSS nuevos — para que ninguna mesa activa quede
		// inalcanzable durante el servicio. El sistema no les inventa un sitio: dónde va una mesa
		// es decisión del encargado.
		if ($sinSitio && $sinSitioGrid) {
			$sinSitio.classList.toggle('d-none', sinSitio.length === 0);
			$sinSitioGrid.innerHTML = sinSitio.map(function (mesa) {
				var clase = D.claseEstado(mesa);
				var cuerpo = mesa.estado === 'libre'
					? '<span class="importe">Libre</span><span class="meta">Toca para abrir cuenta</span>'
					: '<span class="importe">' + D.formatoImporte(mesa.pendiente) + ' €</span>' +
					  '<span class="meta">Hace ' + mesa.abierta_hace_min + ' min</span>';

				return '<button type="button" class="pos-mesa ' + clase + '" data-mesa-id="' + mesa.id + '">' +
					'<span class="nombre">' + D.escapeHtml(mesa.nombre) + '</span>' +
					cuerpo +
					'</button>';
			}).join('');
		}

		// Un lienzo vacío nunca debe parecer un fallo (FR-012).
		if ($vacio) {
			$vacio.classList.toggle('d-none', mesas.length > 0);
		}

		encajar();
	}

	/**
	 * Toque por delegación (D7): un único listener en el contenedor, así no hay que volver a
	 * enganchar nada en cada repintado. El destino se resuelve **en el momento del toque** a partir
	 * del elemento tocado —nunca de un índice capturado antes (FR-017)— y con la MISMA decisión que
	 * usa la tarjeta (`window.posSalaDestinoMesa`), que es la forma de garantizar que las dos vistas
	 * lleven al mismo sitio en vez de repetir la regla.
	 */
	$wrap.addEventListener('click', function (e) {
		var el = e.target.closest('.plano-mesa, .pos-mesa');
		if (!el) { return; }

		var mesa = window.posSalaMesaPorId ? window.posSalaMesaPorId(el.getAttribute('data-mesa-id')) : null;
		var destino = window.posSalaDestinoMesa ? window.posSalaDestinoMesa(mesa) : null;

		if (destino) { window.location.href = destino; }
	});

	// El plano refleja el estado y la zona nuevos sin recargar la página ni volver a elegir la
	// vista (FR-016, FR-010).
	document.addEventListener('pos-sala:actualizado', pintar);
	document.addEventListener('pos-sala:zona-cambiada', pintar);
	document.addEventListener('pos-sala:vista-cambiada', pintar);

	window.addEventListener('resize', encajar);
})();
