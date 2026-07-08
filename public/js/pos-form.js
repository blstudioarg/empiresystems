(function () {
	'use strict';

	var state = window.posState || {};
	var lineas = []; // { articulo_id, concepto, unidad, precio, tipo, cantidad }

	var $grid = document.getElementById('pos-grid');
	var $emptyCatalogo = document.getElementById('pos-empty-catalogo');
	var $search = document.getElementById('pos-search');
	var $filtros = document.getElementById('pos-filtros');
	var categoriaActiva = ''; // '' = todas
	var $lineasScroll = document.getElementById('pos-lineas-scroll');
	var $vacio = document.getElementById('pos-vacio');
	var $ticketCount = document.getElementById('pos-ticket-count');
	var $footCount = document.getElementById('pos-foot-count');
	var $vaciar = document.getElementById('pos-vaciar');
	var $total = document.getElementById('pos-total');
	var $topeAlert = document.getElementById('pos-tope-alert');
	var $emitir = document.getElementById('pos-emitir');

	// Modal de éxito al emitir (OK + mensaje + acciones; sin PDF embebido).
	var $exitoModalEl = document.getElementById('posExitoModal');
	var exitoModal = $exitoModalEl ? bootstrap.Modal.getOrCreateInstance($exitoModalEl) : null;
	var $exitoNumero = document.getElementById('pos-exito-numero');
	var $exitoVer = document.getElementById('pos-exito-ver');
	var $exitoImprimir = document.getElementById('pos-exito-imprimir');
	var $exitoSeguir = document.getElementById('pos-exito-seguir');
	var ultimoTicketPdfUrl = null;

	// Ver ticket (modal aparte con el PDF) + iframe oculto para imprimir.
	var $verModalEl = document.getElementById('posVerTicketModal');
	var verModal = $verModalEl ? bootstrap.Modal.getOrCreateInstance($verModalEl) : null;
	var $verFrame = document.getElementById('pos-ver-frame');
	var $printFrame = document.getElementById('pos-print-frame');

	// Modal desglose del total.
	var $totalModal = document.getElementById('posTotalModal');
	var $modalSubtotal = document.getElementById('pos-modal-subtotal');
	var $modalImpuesto = document.getElementById('pos-modal-impuesto');
	var $modalTotal = document.getElementById('pos-modal-total');

	// Receptor (modal): botón de la botonera + campos.
	var $clienteBtn = document.getElementById('pos-cliente-btn');
	var $clienteBtnLabel = document.getElementById('pos-cliente-btn-label');
	var $receptorModal = document.getElementById('posReceptorModal');
	var $receptorQuitar = document.getElementById('pos-receptor-quitar');
	var $cliente = document.getElementById('pos-cliente');
	var $nif = document.getElementById('pos-nif');
	var $nombre = document.getElementById('pos-nombre');
	var $direccion = document.getElementById('pos-direccion');

	function format(n) {
		return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function baseLinea(l) {
		return Math.round(l.precio * l.cantidad * 100) / 100;
	}

	function brutoLinea(l) {
		var base = baseLinea(l);
		return base + Math.round(base * l.tipo / 100 * 100) / 100;
	}

	function totalBruto() {
		return lineas.reduce(function (acc, l) { return acc + brutoLinea(l); }, 0);
	}

	function subtotalBase() {
		return lineas.reduce(function (acc, l) { return acc + baseLinea(l); }, 0);
	}

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

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
				row.innerHTML =
					'<div class="linea-top">' +
						'<span class="concepto">' +
							'<span class="nombre-linea">' + escapeHtml(l.concepto) + '</span>' +
							'<small>' + format(l.precio) + ' € · ' + l.tipo + '%</small>' +
						'</span>' +
						'<span class="importe">' + format(brutoLinea(l)) + ' €</span>' +
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

		var bruto = totalBruto();
		$total.textContent = format(bruto) + ' €';

		var excede = Math.round(bruto * 100) > Math.round(state.tope * 100);
		$topeAlert.classList.toggle('show', excede);
		$emitir.disabled = !lineas.length || excede;
	}

	function addArticulo(btn) {
		var id = btn.getAttribute('data-id');
		var existente = lineas.find(function (l) { return l.articulo_id === id; });
		if (existente) {
			existente.cantidad += 1;
		} else {
			lineas.push({
				articulo_id: id,
				concepto: btn.getAttribute('data-nombre'),
				unidad: btn.getAttribute('data-unidad') || null,
				precio: parseFloat(btn.getAttribute('data-precio')) || 0,
				tipo: parseFloat(btn.getAttribute('data-tipo-impositivo')) || 0,
				cantidad: 1,
			});
		}

		btn.classList.remove('just-added');
		// Forzar reflow para poder re-disparar la animación en clics consecutivos.
		void btn.offsetWidth;
		btn.classList.add('just-added');

		render();
	}

	if ($grid) {
		$grid.addEventListener('click', function (e) {
			var btn = e.target.closest('.pos-articulo');
			if (btn) { addArticulo(btn); }
		});
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
			lineas = [];
			render();
		});
	}

	// Al abrir el modal del total, refrescar el desglose con los importes actuales.
	function rellenarTotalModal() {
		var sub = subtotalBase();
		var tot = totalBruto();
		var imp = Math.round((tot - sub) * 100) / 100;
		if ($modalSubtotal) { $modalSubtotal.textContent = format(sub) + ' €'; }
		if ($modalImpuesto) { $modalImpuesto.textContent = format(imp) + ' €'; }
		if ($modalTotal) { $modalTotal.textContent = format(tot) + ' €'; }
	}

	if ($totalModal) {
		$totalModal.addEventListener('show.bs.modal', rellenarTotalModal);
	}

	// ── Éxito al emitir: OK + PDF (formato ticket, igual que "Ver ticket" de la tabla) ──
	function pdfUrlPara(id) {
		return (state.pdfUrlTemplate || '').replace('__ID__', id);
	}

	function mostrarExito(data) {
		ultimoTicketPdfUrl = pdfUrlPara(data.id);
		// Precargar el PDF en el iframe oculto para que "Imprimir" responda al instante.
		if ($printFrame) { $printFrame.setAttribute('src', ultimoTicketPdfUrl); }
		if ($exitoNumero) { $exitoNumero.textContent = data.numero_completo ? ('Nº ' + data.numero_completo) : ''; }
		if (exitoModal) { exitoModal.show(); }
	}

	function nuevoTicket() {
		lineas = [];
		if ($cliente) { $cliente.value = ''; }
		if ($nif) { $nif.value = ''; }
		if ($nombre) { $nombre.value = ''; }
		if ($direccion) { $direccion.value = ''; }
		actualizarBotonCliente();
		render();
	}

	// "Ver ticket": recién acá se carga y muestra el PDF, en su propio modal.
	if ($exitoVer) {
		$exitoVer.addEventListener('click', function () {
			if (!ultimoTicketPdfUrl) { return; }
			if ($verFrame) { $verFrame.setAttribute('src', ultimoTicketPdfUrl); }
			if (verModal) { verModal.show(); }
		});
	}

	if ($exitoImprimir) {
		$exitoImprimir.addEventListener('click', function () {
			if ($printFrame && $printFrame.contentWindow) {
				$printFrame.contentWindow.focus();
				$printFrame.contentWindow.print();
			}
		});
	}

	if ($exitoSeguir) {
		$exitoSeguir.addEventListener('click', function () {
			if (exitoModal) { exitoModal.hide(); }
		});
	}

	// Cualquier cierre del modal de éxito (Seguir creando, X, Esc) vacía todo para un ticket nuevo.
	if ($exitoModalEl) {
		$exitoModalEl.addEventListener('hidden.bs.modal', function () {
			nuevoTicket();
			if ($printFrame) { $printFrame.setAttribute('src', ''); }
		});
	}

	// Al cerrar el modal del PDF, liberar el iframe.
	if ($verModalEl) {
		$verModalEl.addEventListener('hidden.bs.modal', function () {
			if ($verFrame) { $verFrame.setAttribute('src', ''); }
		});
	}

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

	// ── Carrusel de categorías: flechas visibles solo cuando hay desborde ──
	var $filtrosPrev = document.getElementById('pos-filtros-prev');
	var $filtrosNext = document.getElementById('pos-filtros-next');

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

	if ($filtros && $filtrosPrev && $filtrosNext) {
		$filtrosPrev.addEventListener('click', function () { scrollFiltros(-1); });
		$filtrosNext.addEventListener('click', function () { scrollFiltros(1); });
		$filtros.addEventListener('scroll', actualizarFlechas, { passive: true });
		window.addEventListener('resize', actualizarFlechas);
		actualizarFlechas();
	}

	// ── Receptor (factura simplificada cualificada, opcional) ──
	function receptorTieneDatos() {
		return $nif && $nif.value.trim().length > 0;
	}

	function actualizarBotonCliente() {
		if (!$clienteBtn) { return; }
		var tiene = receptorTieneDatos();
		$clienteBtn.classList.toggle('active', tiene);
		if ($clienteBtnLabel) {
			$clienteBtnLabel.textContent = tiene
				? (($nombre && $nombre.value.trim()) || $nif.value.trim())
				: 'Cliente';
		}
	}

	if ($cliente) {
		$cliente.addEventListener('change', function () {
			var opt = this.options[this.selectedIndex];
			$nif.value = opt.getAttribute('data-nif') || '';
			$nombre.value = opt.getAttribute('data-nombre') || '';
			$direccion.value = opt.getAttribute('data-direccion') || '';
		});
	}

	if ($receptorQuitar) {
		$receptorQuitar.addEventListener('click', function () {
			if ($cliente) { $cliente.value = ''; }
			$nif.value = '';
			$nombre.value = '';
			$direccion.value = '';
			actualizarBotonCliente();
		});
	}

	// Al cerrar el modal, reflejar en el botón si quedó receptor cargado o no.
	if ($receptorModal) {
		$receptorModal.addEventListener('hidden.bs.modal', actualizarBotonCliente);
	}

	function payload() {
		var data = {
			lineas: lineas.map(function (l) {
				return {
					articulo_id: l.articulo_id,
					concepto: l.concepto,
					unidad: l.unidad,
					cantidad: l.cantidad,
					precio_unitario: l.precio,
					tipo_impositivo: l.tipo,
				};
			}),
		};

		if (receptorTieneDatos()) {
			data.receptor = {
				cliente_id: ($cliente && $cliente.value) || null,
				cliente_nif: $nif.value.trim(),
				cliente_nombre: ($nombre && $nombre.value.trim()) || null,
				cliente_razon_social: ($nombre && $nombre.value.trim()) || null,
				cliente_direccion: ($direccion && $direccion.value.trim()) || null,
			};
		}

		return data;
	}

	if ($emitir) {
		$emitir.addEventListener('click', function () {
			// render() recalcula el disabled real (cart vacío / tope excedido) DESPUÉS de que
			// withButtonLoading restaure el botón — si no, la restauración automática
			// (disabled=false) pisaría la lógica de negocio de render().
			window.withButtonLoading($emitir, function () {
				return fetch(state.storeUrl, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify(payload()),
				})
					.then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
					.then(function (res) {
						if (!res.ok) {
							window.showToast('error', res.data.message || 'No se pudo emitir el ticket.');
							return;
						}
						mostrarExito(res.data);
					})
					.catch(function () {
						window.showToast('error', 'No se pudo emitir el ticket.');
					});
			}).always(function () {
				render();
			});
		});
	}

	render();
})();
