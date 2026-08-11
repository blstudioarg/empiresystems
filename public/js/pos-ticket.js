/**
 * POS (TPV) — ticket: líneas, cantidades, totales y aviso de tope de la simplificada.
 *
 * Extraído de `pos-form.js` (feature 038, research D8). Es el dueño del array `PosApp.lineas`:
 * el resto de módulos lo lee, pero solo este lo dibuja.
 */
window.PosApp.registrar('ticket', function (PosApp) {
	'use strict';

	var lineas = PosApp.lineas;

	var $lineasScroll = document.getElementById('pos-lineas-scroll');
	var $vacio = document.getElementById('pos-vacio');
	var $ticketCount = document.getElementById('pos-ticket-count');
	var $footCount = document.getElementById('pos-foot-count');
	var $vaciar = document.getElementById('pos-vaciar');
	var $total = document.getElementById('pos-total');
	var $topeAlert = document.getElementById('pos-tope-alert');
	var $cobrar = document.getElementById('pos-cobrar');

	// Modal desglose del total.
	var $totalModal = document.getElementById('posTotalModal');
	var $modalSubtotal = document.getElementById('pos-modal-subtotal');
	var $modalImpuesto = document.getElementById('pos-modal-impuesto');
	var $modalTotal = document.getElementById('pos-modal-total');

	function render() {
		var count = lineas.reduce(function (acc, l) { return acc + l.cantidad; }, 0);

		if (!lineas.length) {
			$vacio.classList.remove('d-none');
			$lineasScroll.classList.add('d-none');
			$lineasScroll.innerHTML = '';
			$ticketCount.classList.add('d-none');
			if ($vaciar) { $vaciar.classList.add('d-none'); }
		} else {
			$vacio.classList.add('d-none');
			$lineasScroll.classList.remove('d-none');
			$ticketCount.classList.remove('d-none');
			$ticketCount.textContent = count;
			if ($vaciar) { $vaciar.classList.remove('d-none'); }

			$lineasScroll.innerHTML = '';
			lineas.forEach(function (l, i) {
				var row = document.createElement('div');
				row.className = 'pos-linea';
				// Opciones elegidas, en el "small" gris ya previsto en el CSS (FR-044): detalle
				// bajo el nombre, no una línea propia.
				var detalleOpciones = (l.opciones && l.opciones.length)
					? '<small class="pos-linea-opciones">' + PosApp.escapeHtml(l.opciones.map(function (o) { return o.nombre; }).join(', ')) + '</small>'
					: '';
				row.innerHTML =
					'<div class="linea-top">' +
						'<span class="concepto">' +
							'<span class="nombre-linea">' + PosApp.escapeHtml(l.concepto) + '</span>' +
							'<small>' + PosApp.format(l.precio) + ' € · ' + l.tipo + '%</small>' +
							detalleOpciones +
						'</span>' +
						'<span class="importe">' + PosApp.format(PosApp.brutoLinea(l)) + ' €</span>' +
					'</div>' +
					'<div class="linea-controls">' +
						'<span class="qty-group">' +
							'<button type="button" class="qty-btn" data-act="dec" data-i="' + i + '" aria-label="Restar">−</button>' +
							'<span class="qty">' + l.cantidad + '</span>' +
							'<button type="button" class="qty-btn" data-act="inc" data-i="' + i + '" aria-label="Sumar">+</button>' +
						'</span>' +
						'<button type="button" class="del" data-act="del" data-i="' + i + '" aria-label="Quitar">×</button>' +
					'</div>';
				$lineasScroll.appendChild(row);
			});
		}

		if ($footCount) {
			$footCount.textContent = count === 1 ? '1 artículo' : count + ' artículos';
		}

		var bruto = PosApp.totalBruto();
		$total.textContent = PosApp.format(bruto) + ' €';

		var excede = Math.round(bruto * 100) > Math.round(PosApp.state.tope * 100);
		$topeAlert.classList.toggle('show', excede);
		$cobrar.disabled = !lineas.length || excede;
	}

	/**
	 * @param {Element} btn
	 * @param {Array<{opcion_id:number,nombre:string,precio:number}>} [opciones] Selección hecha en
	 *   el modal de opciones (feature 038, US4). Sin opciones, es el mismo comportamiento de
	 *   siempre.
	 */
	function addArticulo(btn, opciones) {
		var id = btn.getAttribute('data-id');
		opciones = opciones || [];

		// Dos unidades del mismo artículo con selecciones DISTINTAS son líneas separadas
		// (FR-045): solo se fusiona con una línea existente si las opciones coinciden exactamente.
		var existente = opciones.length
			? null
			: lineas.find(function (l) { return l.articulo_id === id && (!l.opciones || !l.opciones.length); });

		if (existente) {
			existente.cantidad += 1;
		} else {
			var suplemento = opciones.reduce(function (acc, o) { return acc + (parseFloat(o.precio) || 0); }, 0);
			lineas.push({
				articulo_id: id,
				concepto: btn.getAttribute('data-nombre'),
				unidad: btn.getAttribute('data-unidad') || null,
				precio: (parseFloat(btn.getAttribute('data-precio')) || 0) + suplemento,
				tipo: parseFloat(btn.getAttribute('data-tipo-impositivo')) || 0,
				cantidad: 1,
				opciones: opciones,
			});
		}

		btn.classList.remove('just-added');
		// Forzar reflow para poder re-disparar la animación en clics consecutivos.
		void btn.offsetWidth;
		btn.classList.add('just-added');

		render();
	}

	/** Vacía el ticket sin preguntar. El botón "Vaciar" añade su propia guarda. */
	function limpiar() {
		lineas.length = 0;
	}

	// Al abrir el modal del total, refrescar el desglose con los importes actuales.
	function rellenarTotalModal() {
		var sub = PosApp.subtotalBase();
		var tot = PosApp.totalBruto();
		var imp = Math.round((tot - sub) * 100) / 100;
		if ($modalSubtotal) { $modalSubtotal.textContent = PosApp.format(sub) + ' €'; }
		if ($modalImpuesto) { $modalImpuesto.textContent = PosApp.format(imp) + ' €'; }
		if ($modalTotal) { $modalTotal.textContent = PosApp.format(tot) + ' €'; }
	}

	function init() {
		// Retomar una cuenta desde la Sala precarga sus líneas en el mismo array compartido que
		// usa el resto del POS (feature 038, US2).
		if (Array.isArray(PosApp.state.lineasPrecargadas)) {
			PosApp.state.lineasPrecargadas.forEach(function (l) { lineas.push(l); });
		}

		if ($lineasScroll) {
			$lineasScroll.addEventListener('click', function (e) {
				var el = e.target.closest('[data-act]');
				if (!el) { return; }
				var i = parseInt(el.getAttribute('data-i'), 10);
				var act = el.getAttribute('data-act');
				if (act === 'inc') { lineas[i].cantidad += 1; }
				else if (act === 'dec') { lineas[i].cantidad -= 1; if (lineas[i].cantidad <= 0) { lineas.splice(i, 1); } }
				else if (act === 'del') { lineas.splice(i, 1); }
				render();
			});
		}

		if ($vaciar) {
			$vaciar.addEventListener('click', function () {
				if (!lineas.length) { return; }
				limpiar();
				render();
			});
		}

		if ($totalModal) {
			$totalModal.addEventListener('show.bs.modal', rellenarTotalModal);
		}

		render();
	}

	return { init: init, render: render, addArticulo: addArticulo, limpiar: limpiar };
});
