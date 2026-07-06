(function ($) {
	var chartInstances = {};

	function destruirCharts() {
		Object.keys(chartInstances).forEach(function (key) {
			if (chartInstances[key]) {
				chartInstances[key].destroy();
				chartInstances[key] = null;
			}
		});
	}

	function renderizarCharts() {
		destruirCharts();

		var datos = window.dashboardData;
		if (!datos) {
			return;
		}

		var serieDiv = document.getElementById('morris-serie-facturacion');
		if (serieDiv && typeof window.Morris !== 'undefined') {
			Morris.Line({
				element: 'morris-serie-facturacion',
				data: datos.serie_facturacion,
				xkey: 'etiqueta',
				ykeys: ['facturado'],
				labels: ['Facturado'],
				parseTime: false,
				gridLineColor: 'transparent',
				lineColors: ['#1D69D6'],
				lineWidth: 2,
				pointSize: 4,
				smooth: false,
				hideHover: 'auto',
				resize: true,
			});
		}

		if (typeof window.Chart === 'undefined') {
			return;
		}

		var comparativoCanvas = document.getElementById('chart-comparativo');
		if (comparativoCanvas) {
			chartInstances.comparativo = new Chart(comparativoCanvas, {
				type: 'bar',
				data: {
					labels: datos.comparativo.map(function (p) { return p.etiqueta; }),
					datasets: [
						{
							label: 'Facturado',
							data: datos.comparativo.map(function (p) { return p.facturado; }),
							backgroundColor: '#1D69D6',
						},
						{
							label: 'Cobrado',
							data: datos.comparativo.map(function (p) { return p.cobrado; }),
							backgroundColor: '#1F2025',
						},
					],
				},
				options: {
					responsive: true,
					scales: { y: { beginAtZero: true } },
				},
			});
		}

		var distribucionCanvas = document.getElementById('chart-distribucion-estados');
		if (distribucionCanvas) {
			chartInstances.distribucion = new Chart(distribucionCanvas, {
				type: 'polarArea',
				data: {
					labels: datos.distribucion_estados.map(function (p) { return p.estado; }),
					datasets: [{
						data: datos.distribucion_estados.map(function (p) { return p.cantidad; }),
						backgroundColor: ['#1D69D6', '#22C55E', '#F59E0B', '#EF4444', '#6B7280', '#8B5CF6'],
					}],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
				},
			});
		}
	}

	function bindFilasFacturasRecientes() {
		document.querySelectorAll('.factura-reciente-fila').forEach(function (fila) {
			fila.addEventListener('click', function () {
				window.location.href = fila.dataset.href;
			});
		});
	}

	renderizarCharts();
	bindFilasFacturasRecientes();

	// --- Filtro de rango: recarga por AJAX (sin recargar la página) ---

	var $form = $('#dashboard-filtro-form');
	if (!$form.length) {
		return;
	}

	var $contenido = $('#dashboard-contenido');
	var $mostrando = $('#dashboard-rango-mostrando');

	// Deja el estado inicial (carga normal, sin AJAX todavía) navegable con atrás/adelante:
	// asocia los query params actuales al primer entry del historial.
	window.history.replaceState(
		{ dashboardParams: Object.fromEntries(new URLSearchParams(window.location.search)) },
		'',
		window.location.href
	);

	function formatearFecha(fechaIso) {
		var partes = fechaIso.split('-');
		return partes[2] + '/' + partes[1] + '/' + partes[0];
	}

	function actualizarBarraFiltro(rango) {
		$form.find('input[name="preset"]').prop('checked', false);
		$('#preset-' + rango.preset).prop('checked', true);

		if (rango.preset === 'personalizado') {
			$('#dashboard-rango-personalizado').removeClass('d-none');
			$('#dashboard-rango-input').val(formatearFecha(rango.desde) + ' - ' + formatearFecha(rango.hasta));
			$('#dashboard-rango-desde').val(rango.desde);
			$('#dashboard-rango-hasta').val(rango.hasta);
		} else {
			$('#dashboard-rango-personalizado').addClass('d-none');
		}

		$mostrando.html(
			'<i class="fas fa-calendar-alt me-1"></i>Mostrando: '
			+ formatearFecha(rango.desde) + ' – ' + formatearFecha(rango.hasta)
		);
	}

	function aplicarFiltro(params, empujarHistorial) {
		$contenido.addClass('dashboard-cargando');

		$.ajax({
			url: window.location.pathname,
			method: 'GET',
			data: params,
			headers: { Accept: 'application/json' },
			dataType: 'json',
		}).done(function (respuesta) {
			destruirCharts();
			$contenido.html(respuesta.html);
			window.dashboardData = respuesta.graficos;
			renderizarCharts();
			bindFilasFacturasRecientes();
			actualizarBarraFiltro(respuesta.rango);

			if (respuesta.aviso && typeof window.showToast === 'function') {
				window.showToast('warning', respuesta.aviso);
			}

			if (empujarHistorial !== false) {
				var query = $.param(params);
				var url = window.location.pathname + (query ? '?' + query : '');
				window.history.pushState({ dashboardParams: params }, '', url);
			}
		}).fail(function () {
			// Ante un fallo de red/servidor, degradar a navegación normal (comportamiento
			// previo) en vez de dejar el filtro sin respuesta.
			var query = $.param(params);
			window.location.href = window.location.pathname + (query ? '?' + query : '');
		}).always(function () {
			$contenido.removeClass('dashboard-cargando');
		});
	}

	$form.find('input[name="preset"]').on('change', function () {
		var preset = $(this).val();

		if (preset === 'personalizado') {
			$('#dashboard-rango-personalizado').removeClass('d-none');
			$('#dashboard-rango-input').trigger('focus');
			return;
		}

		$('#dashboard-rango-personalizado').addClass('d-none');
		aplicarFiltro({ preset: preset });
	});

	if (typeof $.fn.daterangepicker === 'function') {
		$('#dashboard-rango-input').daterangepicker({
			autoUpdateInput: true,
			opens: 'left',
			locale: {
				format: 'DD/MM/YYYY',
				applyLabel: 'Aplicar',
				cancelLabel: 'Cancelar',
				daysOfWeek: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'],
				monthNames: [
					'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
					'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
				],
				firstDay: 1,
			},
		}, function (desde, hasta) {
			aplicarFiltro({
				preset: 'personalizado',
				desde: desde.format('YYYY-MM-DD'),
				hasta: hasta.format('YYYY-MM-DD'),
			});
		});
	}

	window.addEventListener('popstate', function (event) {
		var params = (event.state && event.state.dashboardParams) || {};
		aplicarFiltro(params, false);
	});
})(jQuery);
