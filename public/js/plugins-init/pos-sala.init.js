/**
 * Sala del POS (feature 038): carga el estado de todas las mesas de una vez y filtra por zona en
 * cliente.
 *
 * El filtro es client-side a propósito: el JSON completo de la sala son unas decenas de mesas, y
 * volver al servidor por cada toque de pestaña añadiría latencia visible en tablet sin ahorrar
 * nada. Lo que NO se calcula aquí es el estado "olvidada": lo decide el servidor (si dependiera
 * del reloj de la tablet, dos dispositivos mostrarían cosas distintas).
 */
(function () {
	'use strict';

	var state = window.posSalaState || {};

	var $zonas = document.getElementById('pos-sala-zonas');
	var $mesas = document.getElementById('pos-sala-mesas');
	var $vacia = document.getElementById('pos-sala-vacia');
	var $refrescar = document.getElementById('pos-sala-refrescar');

	if (!$zonas || !$mesas) { return; }

	var datos = { zonas: [], mesas: [] };
	var zonaActiva = ''; // '' = todas

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

	function formatoImporte(valor) {
		return parseFloat(valor || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function mesasVisibles() {
		return datos.mesas.filter(function (m) {
			return zonaActiva === '' || String(m.zona_id) === String(zonaActiva);
		});
	}

	function pintarZonas() {
		var total = datos.mesas.length;

		var html = '<button type="button" class="pos-filtro' + (zonaActiva === '' ? ' active' : '') + '"' +
			' data-zona="" aria-pressed="' + (zonaActiva === '' ? 'true' : 'false') + '">' +
			'Todas <span class="badge-count">' + total + '</span></button>';

		datos.zonas.forEach(function (zona) {
			var activa = String(zonaActiva) === String(zona.id);
			var suplemento = parseFloat(zona.suplemento || 0);

			html += '<button type="button" class="pos-filtro' + (activa ? ' active' : '') + '"' +
				' data-zona="' + zona.id + '" aria-pressed="' + (activa ? 'true' : 'false') + '">' +
				escapeHtml(zona.nombre) +
				(suplemento > 0 ? ' <span class="badge-count">+' + formatoImporte(suplemento) + '%</span>' : '') +
				' <span class="badge-count">' + zona.total_mesas + '</span></button>';
		});

		$zonas.innerHTML = html;
	}

	function pintarMesas() {
		var visibles = mesasVisibles();

		$vacia.classList.toggle('d-none', visibles.length > 0);

		$mesas.innerHTML = visibles.map(function (mesa) {
			var clase = mesa.estado === 'libre' ? 'libre' : (mesa.olvidada ? 'olvidada' : 'ocupada');

			var badge = '';
			if (mesa.olvidada) {
				badge = '<span class="estado-badge">Sin tocar</span>';
			} else if (mesa.estado === 'ocupada') {
				badge = '<span class="estado-badge">Abierta</span>';
			}

			var cuerpo = mesa.estado === 'libre'
				? '<span class="importe">Libre</span><span class="meta">Toca para abrir cuenta</span>'
				: '<span class="importe">' + formatoImporte(mesa.pendiente) + ' €</span>' +
				  '<span class="meta">Hace ' + mesa.abierta_hace_min + ' min</span>';

			return '<button type="button" class="pos-mesa ' + clase + '" data-url="' + escapeHtml(mesa.abrir_url) + '"' +
				' data-mesa-id="' + mesa.id + '">' +
				badge +
				'<span class="nombre">' + escapeHtml(mesa.nombre) + '</span>' +
				cuerpo +
				'</button>';
		}).join('');
	}

	function cargar() {
		return fetch(state.estadoUrl, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (r) { return r.json(); })
			.then(function (json) {
				datos = json;
				pintarZonas();
				pintarMesas();
			})
			.catch(function () {
				window.showToast('error', 'No se pudo cargar el estado de la sala.');
			});
	}

	$zonas.addEventListener('click', function (e) {
		var btn = e.target.closest('.pos-filtro');
		if (!btn) { return; }
		zonaActiva = btn.getAttribute('data-zona') || '';
		pintarZonas();
		pintarMesas();
	});

	$mesas.addEventListener('click', function (e) {
		var btn = e.target.closest('.pos-mesa');
		if (!btn) { return; }

		var url = btn.getAttribute('data-url');
		var mesaId = btn.getAttribute('data-mesa-id');
		var mesa = datos.mesas.filter(function (m) { return String(m.id) === String(mesaId); })[0];

		// Mesa libre: se abre el TPV con la mesa preseleccionada; el POS crea la cuenta al
		// guardar la primera línea, para no dejar cuentas vacías por cada toque en la sala.
		window.location.href = (mesa && mesa.estado === 'libre')
			? url + (url.indexOf('?') === -1 ? '?' : '&') + 'mesa=' + mesaId
			: url;
	});

	if ($refrescar) {
		$refrescar.addEventListener('click', function () {
			window.withButtonLoading($refrescar, cargar);
		});
	}

	cargar();
})();
