(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var estadoLabels = {
		borrador: 'Borrador',
		emitida: 'Emitida',
		pagada: 'Pagada',
		vencida: 'Vencida',
		anulada: 'Anulada',
		rectificada: 'Rectificada',
	};

	var estadoBadges = {
		borrador: 'badge-light-secondary',
		emitida: 'badge-light-primary',
		pagada: 'badge-light-success',
		vencida: 'badge-light-danger',
		anulada: 'badge-light-dark',
		rectificada: 'badge-light-warning',
	};

	window.updateFacturasCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="importe_total"]').text(totales.importe_total);
	};

	function renderEstado(data, type, row) {
		var badge = estadoBadges[row.estado] || 'badge-light-secondary';
		var label = estadoLabels[row.estado] || row.estado;

		return '<span class="badge ' + badge + '">' + escapeHtml(label) + '</span>';
	}

	function renderAcciones(data, type, row) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li>' +
						'<button type="button" class="dropdown-item btn-ver-factura" data-pdf-url="' + row.pdf_url + '">Ver</button>' +
					'</li>' +
					'<li><a class="dropdown-item" href="' + row.edit_url + '">Editar</a></li>' +
					'<li><hr class="dropdown-divider"></li>' +
					'<li>' +
						'<button type="button" class="dropdown-item text-danger btn-delete-factura"' +
							' data-id="' + row.id + '"' +
							' data-delete-url="' + row.delete_url + '"' +
						'>Eliminar</button>' +
					'</li>' +
				'</ul>' +
			'</div>'
		);
	}

	window.initFacturasDataTable = function () {
		var $table = $('#facturas-table');

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
					window.updateFacturasCards(json.totales);
					return json.data;
				},
			},
			columns: [
				{ data: 'identificador', render: escapeHtml },
				{ data: 'cliente', render: escapeHtml },
				{ data: 'fecha_expedicion', render: escapeHtml },
				{ data: 'total', render: escapeHtml },
				{ data: null, orderable: false, render: renderEstado },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron facturas',
				emptyTable: 'Todavía no hay facturas en esta cuenta',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		$table.on('click', '.btn-ver-factura', function () {
			var pdfUrl = $(this).data('pdf-url');
			var $modal = $('#facturaPdfModal');

			$('#facturaPdfFrame').attr('src', pdfUrl);
			bootstrap.Modal.getOrCreateInstance($modal[0]).show();
		});

		$('#facturaPdfModal').on('hidden.bs.modal', function () {
			$('#facturaPdfFrame').attr('src', '');
		});

		$table.on('click', '.btn-delete-factura', function () {
			var deleteUrl = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar esta factura en borrador?', function () {
				$.ajax({
					url: deleteUrl,
					type: 'DELETE',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
					success: function (response) {
						window.showToast('success', response.message);
						table.ajax.reload();
					},
					error: function (xhr) {
						window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo eliminar la factura.');
					},
				});
			});
		});

		return table;
	};

	$(function () {
		window.initFacturasDataTable();
	});
})(jQuery);
