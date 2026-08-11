/**
 * Plano de sala arrastrable (feature 039). Se apoya en `window.posSalaData` (cargado por
 * `pos-sala.init.js`) para las mesas/zonas y añade un "modo edición" propio: rejilla de 8×6
 * celdas por zona, arrastre con jQuery UI `draggable` (asa obligatoria, sin dependencia nueva —
 * docs/04-front-guidelines.md), reacomodo por colisión en cliente (previsualización, D3 de
 * research.md) y guardado explícito por botón (D4), nunca por evento de arrastre.
 *
 * El arrastre solo toca el estado en memoria (`pendiente`); nada se persiste hasta "Guardar
 * plano". Cambiar de zona o recargar sin guardar descarta los cambios (FR-009).
 */
(function () {
	'use strict';

	var CELL = 96;
	var GAP = 12;
	var COLS = 8;
	var ROWS = 6;
	var STEP = CELL + GAP;

	var FORMAS = [
		{ valor: 'redonda', etiqueta: 'Redonda' },
		{ valor: 'cuadrada', etiqueta: 'Cuadrada' },
		{ valor: 'rectangular', etiqueta: 'Rectangular' },
		{ valor: 'barra', etiqueta: 'Barra' },
	];
	var TAMANOS = [
		{ valor: 'pequena', etiqueta: 'Pequeña' },
		{ valor: 'mediana', etiqueta: 'Mediana' },
		{ valor: 'grande', etiqueta: 'Grande' },
	];

	var state = window.posPlanoState || {};
	if (!state.puedeEditar) { return; }

	var $toggle = document.getElementById('pos-plano-toggle');
	var $guardar = document.getElementById('pos-plano-guardar');
	var $wrap = document.getElementById('pos-plano-wrap');
	var $canvas = document.getElementById('pos-plano-canvas');
	var $fueraRejilla = document.getElementById('pos-plano-fuera-rejilla');
	var $popover = document.getElementById('plano-popover');
	var $popoverFormas = document.getElementById('plano-popover-formas');
	var $popoverTamanos = document.getElementById('plano-popover-tamanos');
	var $mesasLectura = document.getElementById('pos-sala-mesas');
	var $zonasTabs = document.getElementById('pos-sala-zonas');

	if (!$toggle || !$wrap || !$canvas) { return; }

	document.documentElement.style.setProperty('--plano-cols', COLS);
	document.documentElement.style.setProperty('--plano-rows', ROWS);
	document.documentElement.style.setProperty('--plano-cell', CELL + 'px');
	document.documentElement.style.setProperty('--plano-gap', GAP + 'px');

	var editando = false;
	var zonaId = null;
	var versionZona = 1;
	var pendiente = []; // [{id, fila, columna, forma, tamano, estado, olvidada}]
	var mesaSeleccionada = null; // id de la mesa con el popover abierto

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

	function mesaPorId(id) {
		return pendiente.filter(function (m) { return String(m.id) === String(id); })[0] || null;
	}

	function celdaLibre(fila, columna, ignorarId) {
		return !pendiente.some(function (m) {
			return String(m.id) !== String(ignorarId) && m.fila === fila && m.columna === columna;
		});
	}

	/** Celda libre de menor distancia euclídea a (filaOrigen, columnaOrigen). `null` si no hay ninguna (D3). */
	function celdaLibreMasCercana(filaOrigen, columnaOrigen, ignorarId) {
		var mejor = null;
		var mejorDist = Infinity;

		for (var f = 0; f < ROWS; f++) {
			for (var c = 0; c < COLS; c++) {
				if (!celdaLibre(f, c, ignorarId)) { continue; }
				var dist = Math.pow(f - filaOrigen, 2) + Math.pow(c - columnaOrigen, 2);
				if (dist < mejorDist) {
					mejorDist = dist;
					mejor = { fila: f, columna: c };
				}
			}
		}

		return mejor;
	}

	function tamanoEscala(tamano) {
		return tamano === 'pequena' ? 0.68 : (tamano === 'grande' ? 1 : 0.85);
	}

	function sillasParaForma(forma) {
		// Puntos alrededor del perímetro, en porcentaje del tamaño del elemento.
		if (forma === 'barra') {
			return [[10, -6], [30, -6], [50, -6], [70, -6], [90, -6]];
		}
		if (forma === 'rectangular') {
			return [[20, -6], [50, -6], [80, -6], [20, 106], [50, 106], [80, 106]];
		}
		// redonda y cuadrada: 4 puntos cardinales.
		return [[50, -6], [106, 50], [50, 106], [-6, 50]];
	}

	function posicionPx(fila, columna) {
		return { left: columna * STEP, top: fila * STEP };
	}

	function renderMesaHtml(mesa) {
		var pos = posicionPx(mesa.fila, mesa.columna);
		var escala = tamanoEscala(mesa.tamano);
		var size = Math.round(CELL * escala);
		var offset = Math.round((CELL - size) / 2);
		var clase = mesa.estado === 'libre' ? 'libre' : (mesa.olvidada ? 'olvidada' : 'ocupada');

		var sillasHtml = sillasParaForma(mesa.forma).map(function (p) {
			return '<span class="silla" style="left:' + p[0] + '%;top:' + p[1] + '%;transform:translate(-50%,-50%);"></span>';
		}).join('');

		return '<div class="plano-mesa ' + clase + ' forma-' + mesa.forma + '" data-mesa-id="' + mesa.id + '"' +
			' style="left:' + (pos.left + offset) + 'px;top:' + (pos.top + offset) + 'px;width:' + size + 'px;height:' + size + 'px;">' +
			sillasHtml +
			'<span class="plano-mesa-nombre">' + escapeHtml(mesa.nombre) + '</span>' +
			'<span class="plano-mesa-handle" title="Arrastrar"><i class="fas fa-arrows-up-down-left-right"></i></span>' +
			'</div>';
	}

	function pintarCanvas() {
		$canvas.innerHTML = pendiente.map(renderMesaHtml).join('');

		$canvas.querySelectorAll('.plano-mesa').forEach(function (el) {
			$(el).draggable({
				handle: '.plano-mesa-handle',
				containment: 'parent',
				grid: [STEP, STEP],
				distance: 6,
				stack: '.plano-mesa',
				stop: function () { onDragStop(el); },
			});

			el.addEventListener('click', function (e) {
				if (e.target.closest('.plano-mesa-handle')) { return; }
				abrirPopover(el.getAttribute('data-mesa-id'));
			});
		});
	}

	function onDragStop(el) {
		var id = el.getAttribute('data-mesa-id');
		var mesa = mesaPorId(id);
		if (!mesa) { return; }

		var left = parseInt(el.style.left, 10) || 0;
		var top = parseInt(el.style.top, 10) || 0;
		var offset = Math.round((CELL - Math.round(CELL * tamanoEscala(mesa.tamano))) / 2);
		var columna = Math.round((left - offset) / STEP);
		var fila = Math.round((top - offset) / STEP);
		columna = Math.max(0, Math.min(COLS - 1, columna));
		fila = Math.max(0, Math.min(ROWS - 1, fila));

		var filaOriginal = mesa.fila;
		var columnaOriginal = mesa.columna;

		if (celdaLibre(fila, columna, id)) {
			mesa.fila = fila;
			mesa.columna = columna;
			pintarCanvas();
			return;
		}

		// Celda ocupada: la mesa que estaba ahí se reubica a la celda libre más cercana a SU
		// posición original (D3). Si no hay ninguna, se cancela el movimiento completo.
		var ocupante = pendiente.filter(function (m) {
			return String(m.id) !== String(id) && m.fila === fila && m.columna === columna;
		})[0];

		var destinoOcupante = ocupante ? celdaLibreMasCercana(ocupante.fila, ocupante.columna, ocupante.id) : null;

		if (!ocupante || !destinoOcupante) {
			mesa.fila = filaOriginal;
			mesa.columna = columnaOriginal;
			pintarCanvas();
			window.showToast('warning', 'No hay ninguna celda libre para reacomodar la mesa desplazada. Movimiento cancelado.');
			return;
		}

		ocupante.fila = destinoOcupante.fila;
		ocupante.columna = destinoOcupante.columna;
		mesa.fila = fila;
		mesa.columna = columna;
		pintarCanvas();
	}

	function cerrarPopover() {
		mesaSeleccionada = null;
		$popover.classList.remove('abierto');
	}

	function abrirPopover(id) {
		var mesa = mesaPorId(id);
		if (!mesa) { return; }

		mesaSeleccionada = id;

		$popoverFormas.innerHTML = FORMAS.map(function (f) {
			var activo = f.valor === mesa.forma;
			return '<button type="button" class="btn btn-sm ' + (activo ? 'btn-primary' : 'btn-outline-secondary') + '" data-forma="' + f.valor + '">' + f.etiqueta + '</button>';
		}).join('');

		$popoverTamanos.innerHTML = TAMANOS.map(function (t) {
			var activo = t.valor === mesa.tamano;
			return '<button type="button" class="btn btn-sm ' + (activo ? 'btn-primary' : 'btn-outline-secondary') + '" data-tamano="' + t.valor + '">' + t.etiqueta + '</button>';
		}).join('');

		var pos = posicionPx(mesa.fila, mesa.columna);
		var left = Math.min(pos.left, $canvas.clientWidth - 15 * 16);
		$popover.style.left = Math.max(0, left) + 'px';
		$popover.style.top = (pos.top + CELL + 8) + 'px';
		$popover.classList.add('abierto');
	}

	$popover.addEventListener('click', function (e) {
		var btnForma = e.target.closest('[data-forma]');
		var btnTamano = e.target.closest('[data-tamano]');
		if (!btnForma && !btnTamano) { return; }

		var mesa = mesaPorId(mesaSeleccionada);
		if (!mesa) { return; }

		if (btnForma) { mesa.forma = btnForma.getAttribute('data-forma'); }
		if (btnTamano) { mesa.tamano = btnTamano.getAttribute('data-tamano'); }

		pintarCanvas();
		abrirPopover(mesaSeleccionada);
	});

	document.addEventListener('click', function (e) {
		if (!$popover.classList.contains('abierto')) { return; }
		if (e.target.closest('#plano-popover') || e.target.closest('.plano-mesa')) { return; }
		cerrarPopover();
	});

	function cargarZona(id) {
		cerrarPopover();
		zonaId = id;

		var datos = window.posSalaData || { zonas: [], mesas: [] };
		var zona = datos.zonas.filter(function (z) { return String(z.id) === String(id); })[0];
		versionZona = zona ? zona.version : 1;

		var mesasZona = datos.mesas.filter(function (m) { return String(m.zona_id) === String(id); });

		pendiente = mesasZona
			.filter(function (m) { return m.fila !== null && m.columna !== null; })
			.map(function (m) {
				return { id: m.id, nombre: m.nombre, fila: m.fila, columna: m.columna, forma: m.forma, tamano: m.tamano, estado: m.estado, olvidada: m.olvidada };
			});

		var fuera = mesasZona.length - pendiente.length;
		$fueraRejilla.textContent = fuera > 0
			? fuera + ' mesa(s) de esta zona no tienen posición en la rejilla (creadas cuando ya estaba llena) y no aparecen en el plano.'
			: '';

		pintarCanvas();
	}

	function activarEdicion() {
		if (editando) { return; }

		var zid = window.posSalaZonaActiva ? window.posSalaZonaActiva() : '';
		if (!zid) {
			var datos = window.posSalaData || { zonas: [] };
			if (datos.zonas.length === 0) {
				window.showToast('warning', 'No hay zonas configuradas todavía.');
				return;
			}
			zid = datos.zonas[0].id;
			// Selecciona la pestaña de esa zona para que quede consistente con la vista de lectura.
			var btn = $zonasTabs.querySelector('.pos-filtro[data-zona="' + zid + '"]');
			if (btn) { btn.click(); }
		}

		editando = true;
		$toggle.classList.add('d-none');
		$guardar.classList.remove('d-none');
		$mesasLectura.classList.add('d-none');
		$wrap.classList.add('activo');
		cargarZona(zid);
	}

	function desactivarEdicion() {
		editando = false;
		cerrarPopover();
		$toggle.classList.remove('d-none');
		$guardar.classList.add('d-none');
		$mesasLectura.classList.remove('d-none-plano', 'd-none');
		$wrap.classList.remove('activo');
	}

	$toggle.addEventListener('click', activarEdicion);

	document.addEventListener('pos-sala:zona-cambiada', function (e) {
		// FR-009: los cambios pendientes sin guardar se descartan al salir del modo edición de
		// una zona — cambiar de pestaña siempre recarga desde el último estado guardado.
		if (editando) { cargarZona(e.detail.zonaId); }
	});

	document.addEventListener('pos-sala:actualizado', function () {
		// Un refresco manual (botón "Actualizar") no debe pisar ediciones en curso: si el usuario
		// está en modo edición, se limita a refrescar el `version` disponible por si cambió.
		if (!editando) { return; }
	});

	$guardar.addEventListener('click', function () {
		if (!zonaId) { return; }

		var payload = {
			version: versionZona,
			mesas: pendiente.map(function (m) {
				return { id: m.id, fila: m.fila, columna: m.columna, forma: m.forma, tamano: m.tamano };
			}),
		};

		var url = state.guardarUrlTemplate.replace('__ZONA__', zonaId);

		window.withButtonLoading($guardar, function () {
			return $.ajax({
				url: url,
				method: 'PUT',
				dataType: 'json',
				contentType: 'application/json',
				data: JSON.stringify(payload),
				headers: {
					Accept: 'application/json',
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				},
			});
		})
			.done(function (respuesta) {
				window.showToast('success', respuesta.message || 'Plano guardado.');
				versionZona = respuesta.version;
				desactivarEdicion();
				// Recarga el estado de la sala para que la vista de lectura refleje el plano nuevo.
				document.getElementById('pos-sala-refrescar').click();
			})
			.fail(function (xhr) {
				if (xhr.status === 409) {
					window.showToast('warning', (xhr.responseJSON && xhr.responseJSON.message) ||
						'El plano se modificó desde otro dispositivo. Recárgalo antes de guardar.');
					// Recarga el plano vigente de esta zona en vez de dejar al usuario reintentar
					// a ciegas contra un `version` que ya sabemos desactualizado.
					document.getElementById('pos-sala-refrescar').click();
					setTimeout(function () { if (editando) { cargarZona(zonaId); } }, 400);
					return;
				}

				var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo guardar el plano.';
				window.showToast('danger', msg);
			});
	});
})();
