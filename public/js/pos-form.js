(function () {
	'use strict';

	var state = window.posState || {};
	var lineas = []; // { articulo_id, concepto, unidad, precio, tipo, cantidad }
	var filtroActivo = 'todos';

	var $grid = document.getElementById('pos-grid');
	var $emptyCatalogo = document.getElementById('pos-empty-catalogo');
	var $filtros = document.getElementById('pos-filtros');
	var $search = document.getElementById('pos-search');
	var $lineasScroll = document.getElementById('pos-lineas-scroll');
	var $vacio = document.getElementById('pos-vacio');
	var $ticketCount = document.getElementById('pos-ticket-count');
	var $total = document.getElementById('pos-total');
	var $topeAlert = document.getElementById('pos-tope-alert');
	var $emitir = document.getElementById('pos-emitir');
	var $cualificadaToggle = document.getElementById('pos-cualificada-toggle');
	var $receptor = document.getElementById('pos-receptor');
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
		} else {
			$vacio.classList.add('d-none');
			$lineasScroll.classList.remove('d-none');
			$ticketCount.classList.remove('d-none');
			$ticketCount.textContent = count;

			$lineasScroll.innerHTML = '';
			lineas.forEach(function (l, i) {
				var row = document.createElement('div');
				row.className = 'pos-linea';
				row.innerHTML =
					'<span class="concepto">' +
						'<span class="nombre-linea">' + escapeHtml(l.concepto) + '</span>' +
						'<small>' + format(l.precio) + ' € · ' + l.tipo + '%</small>' +
					'</span>' +
					'<span class="qty-group">' +
						'<button type="button" class="qty-btn" data-act="dec" data-i="' + i + '">−</button>' +
						'<span class="qty">' + l.cantidad + '</span>' +
						'<button type="button" class="qty-btn" data-act="inc" data-i="' + i + '">+</button>' +
					'</span>' +
					'<span class="importe">' + format(brutoLinea(l)) + ' €</span>' +
					'<button type="button" class="del" data-act="del" data-i="' + i + '" aria-label="Quitar">×</button>';
				$lineasScroll.appendChild(row);
			});
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

	function aplicarFiltros() {
		var q = ($search ? $search.value.trim().toLowerCase() : '');
		var visibles = 0;

		$grid.querySelectorAll('.pos-articulo').forEach(function (btn) {
			var nombre = (btn.getAttribute('data-nombre') || '').toLowerCase();
			var tipoArticulo = btn.getAttribute('data-tipo-articulo');

			var coincideTexto = nombre.indexOf(q) !== -1;
			var coincideTipo = filtroActivo === 'todos' || tipoArticulo === filtroActivo;
			var visible = coincideTexto && coincideTipo;

			btn.style.display = visible ? '' : 'none';
			if (visible) { visibles += 1; }
		});

		if ($emptyCatalogo) {
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

			filtroActivo = btn.getAttribute('data-filtro');
			$filtros.querySelectorAll('.pos-filtro').forEach(function (b) { b.classList.remove('active'); });
			btn.classList.add('active');
			aplicarFiltros();
		});
	}

	if ($cualificadaToggle) {
		$cualificadaToggle.addEventListener('change', function () {
			$receptor.style.display = this.checked ? 'block' : 'none';
		});
	}

	if ($cliente) {
		$cliente.addEventListener('change', function () {
			var opt = this.options[this.selectedIndex];
			$nif.value = opt.getAttribute('data-nif') || '';
			$nombre.value = opt.getAttribute('data-nombre') || '';
			$direccion.value = opt.getAttribute('data-direccion') || '';
		});
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

		if ($cualificadaToggle && $cualificadaToggle.checked && $nif.value.trim()) {
			data.receptor = {
				cliente_id: $cliente.value || null,
				cliente_nif: $nif.value.trim(),
				cliente_nombre: $nombre.value.trim() || null,
				cliente_razon_social: $nombre.value.trim() || null,
				cliente_direccion: $direccion.value.trim() || null,
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
						window.showToast('success', res.data.message);
						window.location.href = state.indexUrl;
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
