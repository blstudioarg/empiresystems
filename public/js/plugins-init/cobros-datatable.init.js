(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var estadoCobroLabels = {
		pendiente: 'Pendiente',
		parcial: 'Parcial',
		cobrada: 'Cobrada',
	};

	var estadoCobroBadges = {
		pendiente: 'badge-warning',
		parcial: 'badge-info',
		cobrada: 'badge-success',
	};

	var modalidadRectificacionLabels = {
		diferencias: 'Por diferencias',
		sustitucion: 'Por sustitución',
	};

	function renderIdentificador(data, type, row) {
		if (type !== 'display') {
			return data;
		}

		var html = escapeHtml(data) + (row.serie ? ' <span class="text-muted small">(' + escapeHtml(row.serie) + ')</span>' : '');

		return html;
	}

	function renderVencimiento(data, type, row) {
		if (type !== 'display') {
			return data || '';
		}

		if (!row.fecha_vencimiento) {
			return '<span class="text-muted">Sin vencimiento</span>';
		}

		var html = escapeHtml(row.fecha_vencimiento);

		if (row.vencida) {
			html += '<div class="small text-danger">Vencida (' + row.dias_retraso + ' días)</div>';
		}

		return html;
	}

	function renderTotalCobrable(data, type, row) {
		if (type !== 'display') {
			return row.total_cobrable;
		}

		if (!row.es_rectificada) {
			return escapeHtml(row.total_cobrable) + ' €';
		}

		// FR-016/edge case: el total efectivo de una original rectificada no es su total bruto;
		// se muestra el contexto para que no parezca un error de importe.
		var modalidad = modalidadRectificacionLabels[row.modalidad_rectificacion] || 'Rectificada';

		return (
			'<div><del class="text-muted">' + escapeHtml(row.total_nominal) + ' €</del></div>' +
			'<div class="fw-bold">' + escapeHtml(row.total_cobrable) + ' €</div>' +
			'<div class="small text-muted">' + escapeHtml(modalidad) + '</div>'
		);
	}

	function renderEstadoCobro(data, type, row) {
		var badge = estadoCobroBadges[row.estado_cobro] || 'badge-secondary';
		var label = estadoCobroLabels[row.estado_cobro] || row.estado_cobro;

		var html = '<span class="badge light ' + badge + '">' + escapeHtml(label) + '</span>';

		if (row.vencida) {
			html += '<div class="mt-1"><span class="badge light badge-danger">Vencida (' + row.dias_retraso + ' días)</span></div>';
		}

		return html;
	}

	function renderAcciones(data, type, row) {
		var items = [];

		if (row.pago_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-ver-cobros"' +
						' data-cobros-url="' + row.cobros_url + '"' +
						' data-pago-url="' + row.pago_url + '"' +
						' data-es-rectificada="' + (row.es_rectificada ? '1' : '') + '"' +
						' data-modalidad="' + escapeHtml(row.modalidad_rectificacion || '') + '"' +
						' data-total-nominal="' + escapeHtml(row.total_nominal || '') + '"' +
						' data-total-efectivo="' + escapeHtml(row.total_cobrable || '') + '"' +
					'>Registrar cobro</button>' +
				'</li>'
			);
		}

		if (row.cobros_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-ver-cobros"' +
						' data-cobros-url="' + row.cobros_url + '"' +
						' data-pago-url="' + (row.pago_url || '') + '"' +
						' data-es-rectificada="' + (row.es_rectificada ? '1' : '') + '"' +
						' data-modalidad="' + escapeHtml(row.modalidad_rectificacion || '') + '"' +
						' data-total-nominal="' + escapeHtml(row.total_nominal || '') + '"' +
						' data-total-efectivo="' + escapeHtml(row.total_cobrable || '') + '"' +
					'>Cobros</button>' +
				'</li>'
			);
		}

		if (row.pdf_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-ver-factura-cobros" data-pdf-url="' + row.pdf_url + '">Ver factura</button>' +
				'</li>'
			);
		}

		if (!items.length) {
			items.push('<li><span class="dropdown-item disabled">Sin acciones</span></li>');
		}

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					items.join('') +
				'</ul>' +
			'</div>'
		);
	}

	function filtrosActuales() {
		var preset = $('input[name="cobros-preset"]:checked').val() || 'mes';

		var filtros = {
			estado_cobro: $('.btn-filtro-cobro.active').data('estado-cobro') || '',
			cliente_id: $('#cobros-filtro-cliente').val() || '',
			serie_id: $('#cobros-filtro-serie').val() || '',
			solo_vencidas: $('#cobros-filtro-vencidas').is(':checked') ? 1 : 0,
			preset: preset,
		};

		if (preset === 'personalizado') {
			filtros.desde = $('#cobros-rango-desde').val();
			filtros.hasta = $('#cobros-rango-hasta').val();
		}

		return filtros;
	}

	function cargarResumen() {
		$.getJSON(window.cobrosUrls.resumen, filtrosActuales(), function (resumen) {
			$('[data-metric="pendiente_total"]').text(resumen.pendiente_total + ' €');
			$('[data-metric="cobrado_periodo"]').text(resumen.cobrado_periodo + ' €');
			$('[data-metric="vencido_total"]').text(resumen.vencido_total + ' €');
			$('[data-metric="facturas_pendientes"]').text(resumen.facturas_pendientes);
		});
	}

	window.initCobrosDataTable = function () {
		var $table = $('#cobros-facturas-table');

		if (!$table.length) {
			return null;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			serverSide: true,
			ajax: {
				url: window.cobrosUrls.listado,
				// Los filtros dinámicos SIEMPRE en ajax.data (nunca una función en ajax.url —
				// memoria feedback_datatables_ajax_url_function).
				data: function (d) {
					return $.extend({}, d, filtrosActuales());
				},
				dataSrc: 'data',
				headers: { Accept: 'application/json' },
			},
			columns: [
				{ data: 'identificador', orderable: false, render: renderIdentificador },
				{ data: 'cliente', render: escapeHtml },
				{ data: 'fecha_expedicion', render: escapeHtml },
				{ data: 'fecha_vencimiento', render: renderVencimiento },
				{ data: 'total_cobrable', render: renderTotalCobrable },
				{ data: 'monto_cobrado', render: function (data) { return escapeHtml(data) + ' €'; } },
				{ data: 'saldo_pendiente', render: function (data) { return escapeHtml(data) + ' €'; } },
				{ data: 'estado_cobro', orderable: false, render: renderEstadoCobro },
				{ data: null, orderable: false, render: renderAcciones },
			],
			order: [[3, 'asc']],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron facturas',
				emptyTable: 'Todavía no hay facturas pendientes de cobro',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		function recargarTodo() {
			table.ajax.reload();
			cargarResumen();
		}

		cargarResumen();

		$('.btn-filtro-cobro').on('click', function () {
			$('.btn-filtro-cobro').removeClass('active');
			$(this).addClass('active');
			recargarTodo();
		});

		$('#cobros-filtro-vencidas, #cobros-filtro-cliente, #cobros-filtro-serie').on('change', recargarTodo);

		$('input[name="cobros-preset"]').on('change', function () {
			var preset = $(this).val();

			if (preset === 'personalizado') {
				$('#cobros-rango-personalizado').removeClass('d-none');
				$('#cobros-rango-input').trigger('focus');
				return;
			}

			$('#cobros-rango-personalizado').addClass('d-none');
			// FR-007: solo el resumen depende del rango; la tabla no.
			cargarResumen();
		});

		if (typeof $.fn.daterangepicker === 'function') {
			$('#cobros-rango-input').daterangepicker({
				autoUpdateInput: false,
				locale: { format: 'DD/MM/YYYY', cancelLabel: 'Borrar', applyLabel: 'Aplicar' },
			});

			$('#cobros-rango-input').on('apply.daterangepicker', function (ev, picker) {
				$(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
				$('#cobros-rango-desde').val(picker.startDate.format('YYYY-MM-DD'));
				$('#cobros-rango-hasta').val(picker.endDate.format('YYYY-MM-DD'));
				cargarResumen();
			});
		}

		// Modal de cobros compartido (research D6): "cambio" recarga tabla y resumen, para que
		// nunca queden desincronizados.
		if (window.initCobrosModal) {
			window.initCobrosModal({ onCambio: recargarTodo });
		}

		$table.on('click', '.btn-ver-factura-cobros', function () {
			var pdfUrl = $(this).data('pdf-url');
			$('#facturaPdfFrame').attr('src', pdfUrl);
			bootstrap.Modal.getOrCreateInstance(document.getElementById('facturaPdfModal')).show();
		});

		// FR-028: cerrar el modal de la factura no debe alterar filtros/orden/página del listado
		// (solo se limpia el iframe, no se llama a table.ajax.reload()).
		$('#facturaPdfModal').on('hidden.bs.modal', function () {
			$('#facturaPdfFrame').attr('src', '');
		});

		return table;
	};

	$(function () {
		window.initCobrosDataTable();
	});
})(jQuery);
