(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	window.updateTicketsCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="importe_total"]').text(totales.importe_total);
	};

	function renderTipo(data, type, row) {
		var tipo = row.cualificada
			? '<span class="badge light badge-info">' + __t('Cualificada') + '</span>'
			: '<span class="badge light badge-secondary">' + __t('Simple') + '</span>';

		// Anulado (feature 051): el ticket sigue en el listado con su número.
		return row.anulada ? tipo + ' <span class="badge light badge-danger">' + __t('Anulado') + '</span>' : tipo;
	}

	function renderTotal(data, type, row) {
		return escapeHtml(row.total) + ' €';
	}

	// Desglose de cómo se cobró el ticket en caja. Un solo método → su etiqueta; varios (pago
	// dividido) → cada método con su importe, más un chip "Dividido".
	function renderPagos(data, type, row) {
		var pagos = row.pagos || [];

		if (!pagos.length) {
			return '<span class="text-muted">—</span>';
		}

		if (pagos.length === 1) {
			return escapeHtml(pagos[0].metodo_label);
		}

		var detalle = pagos.map(function (p) {
			return '<div class="text-nowrap">' + escapeHtml(p.metodo_label) + ' <span class="text-muted">' + escapeHtml(p.importe) + ' €</span></div>';
		}).join('');

		return '<span class="badge light badge-primary mb-1">' + __t('Dividido') + '</span>' + detalle;
	}

	function renderAcciones(data, type, row) {
		// «Anular» solo si el backend da la URL (docs/04 § "Columna Acciones"), separado por ser
		// destructivo.
		var anular = row.anular_url
			? '<li><hr class="dropdown-divider"></li>' +
				'<li><button type="button" class="dropdown-item text-danger btn-anular-ticket" data-anular-url="' + escapeHtml(row.anular_url) + '"' +
				' data-numero="' + escapeHtml(row.identificador) + '">' + __t('Anular') + '</button></li>'
			: '';

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					__t('Ver / Imprimir') +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li><button type="button" class="dropdown-item btn-ver-ticket" data-pdf-url="' + row.pdf_ticket_url + '">' + __t('Ticket (80 mm)') + '</button></li>' +
					'<li><button type="button" class="dropdown-item btn-ver-ticket" data-pdf-url="' + row.pdf_a4_url + '">' + __t('Formato A4') + '</button></li>' +
					anular +
				'</ul>' +
			'</div>'
		);
	}

	window.initTicketsDataTable = function () {
		var $table = $('#tickets-table');

		if (!$table.length) {
			return null;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: {
				url: window.location.href,
				dataSrc: function (json) {
					window.updateTicketsCards(json.totales);
					return json.data;
				},
			},
			order: [[0, 'desc']],
			columns: [
				{ data: 'identificador', render: escapeHtml },
				{ data: 'receptor', render: escapeHtml },
				{ data: 'fecha_expedicion', render: escapeHtml },
				{ data: null, render: renderTotal },
				{ data: null, orderable: false, render: renderPagos },
				{ data: null, orderable: false, render: renderTipo },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: __t('Buscar:'),
				lengthMenu: __t('Mostrar _MENU_ registros'),
				info: __t('Mostrando _START_ a _END_ de _TOTAL_ registros'),
				infoEmpty: __t('Mostrando 0 a 0 de 0 registros'),
				infoFiltered: __t('(filtrado de _MAX_ registros totales)'),
				zeroRecords: __t('No se encontraron tickets'),
				emptyTable: __t('Todavía no hay tickets emitidos'),
				processing: __t('Cargando...'),
				paginate: {
					first: __t('Primero'),
					last: __t('Último'),
					next: __t('Siguiente'),
					previous: __t('Anterior'),
				},
			},
		});

		$table.on('click', '.btn-ver-ticket', function () {
			var pdfUrl = $(this).data('pdf-url');
			var $modal = $('#ticketPdfModal');

			$('#ticketPdfFrame').attr('src', pdfUrl);
			bootstrap.Modal.getOrCreateInstance($modal[0]).show();
		});

		$('#ticketPdfModal').on('hidden.bs.modal', function () {
			$('#ticketPdfFrame').attr('src', '');
		});

		// Anular (feature 051): modal con motivo obligatorio.
		$table.on('click', '.btn-anular-ticket', function () {
			var $form = $('#ticketAnularForm');

			$form.attr('action', $(this).data('anular-url'));
			$form[0].reset();
			$form.find('.is-invalid').removeClass('is-invalid');
			$('#ticketAnularNumero').text(__t('Ticket :numero', { numero: $(this).data('numero') }));

			bootstrap.Modal.getOrCreateInstance(document.getElementById('ticketAnularModal')).show();
		});

		$('#ticketAnularForm').on('submit', function (e) {
			e.preventDefault();

			var $form = $(this);
			var $motivo = $('#ticketAnularMotivo');

			if (!$motivo.val().trim()) {
				$motivo.addClass('is-invalid');
				$form.find('[data-error-for="motivo"]').text(__t('Escribe el motivo de la anulación.'));
				return;
			}

			window.withButtonLoading($('#ticketAnularConfirmar'), function () {
				return $.ajax({
					url: $form.attr('action'),
					type: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
					data: { motivo: $motivo.val() },
				});
			})
				.done(function (res) {
					bootstrap.Modal.getInstance(document.getElementById('ticketAnularModal')).hide();
					window.showToast('success', res.message || __t('Ticket anulado.'));
					table.ajax.reload(null, false);
				})
				.fail(function (xhr) {
					var errores = xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors;
					if (errores && errores.motivo) {
						$motivo.addClass('is-invalid');
						$form.find('[data-error-for="motivo"]').text(errores.motivo[0]);
						return;
					}
					window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || __t('No se pudo anular el ticket.'));
				});
		});

		return table;
	};

	$(function () {
		window.initTicketsDataTable();
	});
})(jQuery);
