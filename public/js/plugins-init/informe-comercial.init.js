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

	var COLORES = ['#1D69D6', '#22C55E', '#F59E0B', '#EF4444', '#8B5CF6', '#6B7280'];

	function renderizarCharts() {
		destruirCharts();

		var datos = window.informeComercialData;
		if (!datos) {
			return;
		}

		var evolucionDiv = document.getElementById('morris-evolucion-comercial');
		if (evolucionDiv && typeof window.Morris !== 'undefined') {
			Morris.Line({
				element: 'morris-evolucion-comercial',
				data: datos.evolucion,
				xkey: 'etiqueta',
				ykeys: ['leads', 'oportunidades', 'presupuestos'],
				labels: ['Leads', 'Oportunidades', 'Presupuestos'],
				parseTime: false,
				gridLineColor: 'transparent',
				lineColors: ['#1D69D6', '#8B5CF6', '#22C55E'],
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

		var leadsCanvas = document.getElementById('chart-leads-por-estado');
		if (leadsCanvas) {
			chartInstances.leads = new Chart(leadsCanvas, {
				type: 'doughnut',
				data: {
					labels: datos.leads_por_estado.map(function (p) { return p.etiqueta; }),
					datasets: [{ data: datos.leads_por_estado.map(function (p) { return p.cantidad; }), backgroundColor: COLORES }],
				},
				options: { responsive: true, maintainAspectRatio: false },
			});
		}

		var oportunidadesCanvas = document.getElementById('chart-oportunidades-por-etapa');
		if (oportunidadesCanvas) {
			chartInstances.oportunidades = new Chart(oportunidadesCanvas, {
				type: 'bar',
				data: {
					labels: datos.oportunidades_por_etapa.map(function (p) { return p.etiqueta; }),
					datasets: [{ label: 'Cantidad', data: datos.oportunidades_por_etapa.map(function (p) { return p.cantidad; }), backgroundColor: '#8B5CF6' }],
				},
				options: { responsive: true, scales: { y: { beginAtZero: true } } },
			});
		}

		var presupuestosCanvas = document.getElementById('chart-presupuestos-por-estado');
		if (presupuestosCanvas) {
			chartInstances.presupuestos = new Chart(presupuestosCanvas, {
				type: 'bar',
				data: {
					labels: datos.presupuestos_por_estado.map(function (p) { return p.etiqueta; }),
					datasets: [{ label: 'Cantidad', data: datos.presupuestos_por_estado.map(function (p) { return p.cantidad; }), backgroundColor: '#22C55E' }],
				},
				options: { responsive: true, scales: { y: { beginAtZero: true } } },
			});
		}
	}

	renderizarCharts();

	// --- Filtro de periodo/canal/comercial/fase/comparativa: recarga por AJAX ---

	var $form = $('#informe-comercial-filtro-form');
	if (!$form.length) {
		return;
	}

	var $contenido = $('#informe-comercial-contenido');
	var $mostrando = $('#informe-comercial-rango-mostrando');

	window.history.replaceState(
		{ informeComercialParams: Object.fromEntries(new URLSearchParams(window.location.search)) },
		'',
		window.location.href
	);

	function formatearFecha(fechaIso) {
		var partes = fechaIso.split('-');
		return partes[2] + '/' + partes[1] + '/' + partes[0];
	}

	function estadoFiltros() {
		var params = { preset: $form.find('input[name="preset"]:checked').val() || 'mes' };

		if (params.preset === 'personalizado') {
			params.desde = $('#informe-comercial-rango-desde').val();
			params.hasta = $('#informe-comercial-rango-hasta').val();
		}

		var canal = $('#informe-comercial-canal').val();
		if (canal) {
			params.canal_id = canal;
		}

		var $comercial = $('#informe-comercial-comercial');
		if ($comercial.length && $comercial.val()) {
			params.comercial_id = $comercial.val();
		}

		var fase = $('#informe-comercial-fase').val();
		if (fase) {
			params.fase = fase;
		}

		if ($('#informe-comercial-comparar').is(':checked')) {
			params.comparar = '1';
		}

		return params;
	}

	function actualizarBarraFiltro(periodo) {
		$form.find('input[name="preset"]').prop('checked', false);
		$('#ic-preset-' + periodo.preset).prop('checked', true);

		if (periodo.preset === 'personalizado') {
			$('#informe-comercial-rango-personalizado').removeClass('d-none');
			$('#informe-comercial-rango-input').val(formatearFecha(periodo.desde) + ' - ' + formatearFecha(periodo.hasta));
			$('#informe-comercial-rango-desde').val(periodo.desde);
			$('#informe-comercial-rango-hasta').val(periodo.hasta);
		} else {
			$('#informe-comercial-rango-personalizado').addClass('d-none');
		}

		$mostrando.html('<i class="fas fa-calendar-alt me-1"></i>' + periodo.etiqueta);
	}

	function aplicarFiltro(paramsExtra, empujarHistorial) {
		var params = $.extend({}, estadoFiltros(), paramsExtra || {});

		$contenido.addClass('informe-comercial-cargando');

		$.ajax({
			url: window.location.pathname,
			method: 'GET',
			data: params,
			headers: { Accept: 'application/json' },
			dataType: 'json',
		}).done(function (respuesta) {
			destruirCharts();
			$contenido.html(respuesta.html);
			window.informeComercialData = respuesta.graficos;
			renderizarCharts();
			actualizarBarraFiltro(respuesta.periodo);

			if (respuesta.aviso && typeof window.showToast === 'function') {
				window.showToast('warning', respuesta.aviso);
			}

			if (empujarHistorial !== false) {
				var query = $.param(params);
				var url = window.location.pathname + (query ? '?' + query : '');
				window.history.pushState({ informeComercialParams: params }, '', url);
			}
		}).fail(function () {
			var query = $.param(params);
			window.location.href = window.location.pathname + (query ? '?' + query : '');
		}).always(function () {
			$contenido.removeClass('informe-comercial-cargando');
		});
	}

	$form.find('input[name="preset"]').on('change', function () {
		var preset = $(this).val();

		if (preset === 'personalizado') {
			$('#informe-comercial-rango-personalizado').removeClass('d-none');
			$('#informe-comercial-rango-input').trigger('focus');
			return;
		}

		$('#informe-comercial-rango-personalizado').addClass('d-none');
		aplicarFiltro({ preset: preset });
	});

	$('#informe-comercial-canal, #informe-comercial-comercial, #informe-comercial-fase').on('change', function () {
		aplicarFiltro();
	});

	$('#informe-comercial-comparar').on('change', function () {
		aplicarFiltro();
	});

	if (typeof $.fn.daterangepicker === 'function') {
		$('#informe-comercial-rango-input').daterangepicker({
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
			$('#informe-comercial-rango-desde').val(desde.format('YYYY-MM-DD'));
			$('#informe-comercial-rango-hasta').val(hasta.format('YYYY-MM-DD'));
			aplicarFiltro({
				preset: 'personalizado',
				desde: desde.format('YYYY-MM-DD'),
				hasta: hasta.format('YYYY-MM-DD'),
			});
		});
	}

	window.addEventListener('popstate', function (event) {
		var params = (event.state && event.state.informeComercialParams) || {};
		aplicarFiltro(params, false);
	});

	// --- Exportación: respeta los filtros activos, no una lista de IDs (no hay DataTable aquí) ---

	function csrfToken() {
		var meta = document.querySelector('meta[name="csrf-token"]');
		return meta ? meta.getAttribute('content') : '';
	}

	function nombreDesdeCabecera(header, fallback) {
		var match = /filename="?([^"]+)"?/.exec(header || '');
		return match ? match[1] : fallback;
	}

	$('#btn-exportar-informe-comercial').on('click', function () {
		var $boton = $(this);
		var textoOriginal = $boton.html();
		$boton.prop('disabled', true).text('Exportando...');

		fetch(window.informeComercialExportarUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': csrfToken(),
				Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			},
			body: JSON.stringify(estadoFiltros()),
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('export-failed');
				}

				var nombre = nombreDesdeCabecera(response.headers.get('Content-Disposition'), 'informe-comercial.xlsx');

				return response.blob().then(function (blob) {
					return { blob: blob, nombre: nombre };
				});
			})
			.then(function (resultado) {
				var url = window.URL.createObjectURL(resultado.blob);
				var enlace = document.createElement('a');
				enlace.href = url;
				enlace.download = resultado.nombre;
				document.body.appendChild(enlace);
				enlace.click();
				enlace.remove();
				window.URL.revokeObjectURL(url);
			})
			.catch(function () {
				if (typeof window.showToast === 'function') {
					window.showToast('danger', 'No se pudo generar el fichero de exportación.');
				}
			})
			.finally(function () {
				$boton.prop('disabled', false).html(textoOriginal);
			});
	});
})(jQuery);
