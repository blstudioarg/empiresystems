/**
 * POS (TPV) — catálogo: búsqueda, filtros de categoría y carrusel de filtros.
 *
 * Extraído de `pos-form.js` (feature 038, research D8). Solo decide QUÉ se ve en la grilla y
 * delega en el módulo `ticket` cuando se toca un artículo.
 */
window.PosApp.registrar('catalogo', function (PosApp) {
	'use strict';

	var $grid = document.getElementById('pos-grid');
	var $emptyCatalogo = document.getElementById('pos-empty-catalogo');
	var $search = document.getElementById('pos-search');
	var $filtros = document.getElementById('pos-filtros');
	var $filtrosPrev = document.getElementById('pos-filtros-prev');
	var $filtrosNext = document.getElementById('pos-filtros-next');
	var categoriaActiva = ''; // '' = todas

	// Filtrado del catálogo: combina búsqueda por texto + filtro de categoría (botones grandes).
	// Animado estilo "galería con filtros" (FLIP): los artículos que quedan reubican su posición
	// con una transición de transform en vez de saltar de golpe; los que se ocultan se encogen y
	// desvanecen en su sitio antes de salir del grid; los que aparecen entran con fade + scale.
	var EASE_OUT = 'cubic-bezier(0.23, 1, 0.32, 1)';

	function flip(elementos, rectsPrevios) {
		elementos.forEach(function (el) {
			var prev = rectsPrevios.get(el);
			if (!prev) { return; }
			var next = el.getBoundingClientRect();
			var dx = prev.left - next.left;
			var dy = prev.top - next.top;
			if (!dx && !dy) { return; }
			el.style.transition = 'none';
			el.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
			// Fuerza reflow para que el navegador registre la posición de partida antes de animar.
			el.getBoundingClientRect();
			requestAnimationFrame(function () {
				el.style.transition = 'transform 260ms ' + EASE_OUT;
				el.style.transform = '';
				el.addEventListener('transitionend', function limpiar() {
					el.style.transition = '';
					el.removeEventListener('transitionend', limpiar);
				});
			});
		});
	}

	function aplicarFiltros() {
		var q = ($search ? $search.value.trim().toLowerCase() : '');

		function coincide(btn) {
			var nombre = (btn.getAttribute('data-nombre') || '').toLowerCase();
			var cat = btn.getAttribute('data-categoria') || '';
			return nombre.indexOf(q) !== -1 && (categoriaActiva === '' || cat === categoriaActiva);
		}

		var todos = Array.prototype.slice.call($grid.querySelectorAll('.pos-articulo'));
		var staying = [], exiting = [], entering = [];

		todos.forEach(function (btn) {
			var estabaOculto = btn.classList.contains('filtrado-oculto');
			var seraVisible = coincide(btn);
			if (!estabaOculto && seraVisible) { staying.push(btn); }
			else if (!estabaOculto && !seraVisible) { exiting.push(btn); }
			else if (estabaOculto && seraVisible) { entering.push(btn); }
		});

		// FIRST: posición de los que se quedan, antes de que entren los nuevos.
		var firstRects = new Map();
		staying.forEach(function (btn) { firstRects.set(btn, btn.getBoundingClientRect()); });

		// Los que entran: se revelan ya (ocupan su celda en el grid) pero arrancan invisibles/pequeños.
		entering.forEach(function (btn) {
			btn.classList.remove('filtrado-oculto');
			btn.style.transition = 'none';
			btn.style.opacity = '0';
			btn.style.transform = 'scale(.9)';
		});
		entering.forEach(function (btn) { btn.getBoundingClientRect(); }); // reflow

		// LAST + PLAY: los que se quedaban se reubican suavemente por la entrada de los nuevos.
		flip(staying, firstRects);

		// Entrada con fade + scale, con un pequeño stagger entre artículos.
		requestAnimationFrame(function () {
			entering.forEach(function (btn, i) {
				btn.style.transition = 'opacity 220ms ' + EASE_OUT + ', transform 220ms ' + EASE_OUT;
				btn.style.transitionDelay = Math.min(i, 6) * 25 + 'ms';
				btn.style.opacity = '';
				btn.style.transform = '';
				btn.addEventListener('transitionend', function limpiar() {
					btn.style.transition = '';
					btn.style.transitionDelay = '';
					btn.removeEventListener('transitionend', limpiar);
				});
			});
		});

		// Los que salen: se encogen/desvanecen en su celda actual y solo entonces se retiran del
		// grid, momento en el que el resto de artículos vuelve a reubicarse (segundo FLIP).
		if (exiting.length) {
			exiting.forEach(function (btn) {
				btn.style.transition = 'none';
				btn.style.pointerEvents = 'none';
				requestAnimationFrame(function () {
					btn.style.transition = 'opacity 160ms ease, transform 160ms ease';
					btn.style.opacity = '0';
					btn.style.transform = 'scale(.85)';
				});
			});

			setTimeout(function () {
				var restantes = todos.filter(function (btn) { return coincide(btn); });
				var preRemocion = new Map();
				restantes.forEach(function (btn) { preRemocion.set(btn, btn.getBoundingClientRect()); });

				exiting.forEach(function (btn) {
					btn.classList.add('filtrado-oculto');
					btn.style.transition = '';
					btn.style.opacity = '';
					btn.style.transform = '';
					btn.style.pointerEvents = '';
				});

				flip(restantes, preRemocion);
			}, 170);
		}

		if ($emptyCatalogo) {
			var visibles = todos.filter(coincide).length;
			$emptyCatalogo.classList.toggle('d-none', visibles > 0);
		}
	}

	// ── Carrusel de categorías: flechas visibles solo cuando hay desborde ──
	function actualizarFlechas() {
		if (!$filtros || !$filtrosPrev || !$filtrosNext) { return; }
		var max = $filtros.scrollWidth - $filtros.clientWidth;
		var hayDesborde = max > 1;
		var x = $filtros.scrollLeft;
		$filtrosPrev.classList.toggle('visible', hayDesborde && x > 1);
		$filtrosNext.classList.toggle('visible', hayDesborde && x < max - 1);
	}

	function scrollFiltros(dir) {
		if (!$filtros) { return; }
		// Avanza ~80% del ancho visible por clic (sensación de "página" del carrusel).
		$filtros.scrollBy({ left: dir * $filtros.clientWidth * 0.8, behavior: 'smooth' });
	}

	function init() {
		if ($grid) {
			$grid.addEventListener('click', function (e) {
				var btn = e.target.closest('.pos-articulo');
				if (!btn) { return; }

				// SC-004: un artículo SIN opciones se añade en un solo toque, igual que hoy. Uno
				// CON opciones abre el modal de selección (pos-opciones.js) en vez de añadirse
				// directo (FR-042/FR-043).
				var tieneOpciones = btn.getAttribute('data-tiene-opciones') === '1';
				if (tieneOpciones && PosApp.modulos.opciones) {
					PosApp.modulos.opciones.abrirParaArticulo(btn);
					return;
				}

				PosApp.modulos.ticket.addArticulo(btn);
			});
		}

		if ($search) {
			$search.addEventListener('input', aplicarFiltros);
		}

		if ($filtros) {
			$filtros.addEventListener('click', function (e) {
				var btn = e.target.closest('.pos-filtro');
				if (!btn) { return; }
				categoriaActiva = btn.getAttribute('data-categoria') || '';
				$filtros.querySelectorAll('.pos-filtro').forEach(function (b) {
					var activo = b === btn;
					b.classList.toggle('active', activo);
					b.setAttribute('aria-pressed', activo ? 'true' : 'false');
				});
				aplicarFiltros();
			});
		}

		if ($filtros && $filtrosPrev && $filtrosNext) {
			$filtrosPrev.addEventListener('click', function () { scrollFiltros(-1); });
			$filtrosNext.addEventListener('click', function () { scrollFiltros(1); });
			$filtros.addEventListener('scroll', actualizarFlechas, { passive: true });
			window.addEventListener('resize', actualizarFlechas);
			actualizarFlechas();
		}
	}

	return { init: init, aplicarFiltros: aplicarFiltros };
});
