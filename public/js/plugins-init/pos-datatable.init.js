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
		return row.cualificada
			? '<span class="badge light badge-info">Cualificada</span>'
			: '<span class="badge light badge-secondary">Simple</span>';
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

		return '<span class="badge light badge-primary mb-1">Dividido</span>' + detalle;
	}

	function renderAcciones(data, type, row) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Ver / Imprimir' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li><button type="button" class="dropdown-item btn-ver-ticket" data-pdf-url="' + row.pdf_ticket_url + '">Ticket (80 mm)</button></li>' +
					'<li><button type="button" class="dropdown-item btn-ver-ticket" data-pdf-url="' + row.pdf_a4_url + '">Formato A4</button></li>' +
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
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron tickets',
				emptyTable: 'Todavía no hay tickets emitidos',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
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

		return table;
	};

	$(function () {
		window.initTicketsDataTable();
	});
})(jQuery);
