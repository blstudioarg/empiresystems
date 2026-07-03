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
		borrador: 'badge-secondary',
		emitida: 'badge-primary',
		pagada: 'badge-success',
		vencida: 'badge-danger',
		anulada: 'badge-dark',
		rectificada: 'badge-warning',
	};

	window.updateFacturasCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="importe_total"]').text(totales.importe_total);
	};

	function renderEstado(data, type, row) {
		var badge = estadoBadges[row.estado] || 'badge-secondary';
		var label = estadoLabels[row.estado] || row.estado;

		return '<span class="badge light ' + badge + '">' + escapeHtml(label) + '</span>';
	}

	function renderAcciones(data, type, row) {
		var items = [
			'<li>' +
				'<button type="button" class="dropdown-item btn-ver-factura" data-pdf-url="' + row.pdf_url + '">Ver</button>' +
			'</li>',
		];

		if (row.es_borrador) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-emitir-factura"' +
						' data-emitir-url="' + row.emitir_url + '"' +
					'>Emitir</button>' +
				'</li>'
			);
			items.push('<li><a class="dropdown-item" href="' + row.edit_url + '">Editar</a></li>');
			items.push('<li><hr class="dropdown-divider"></li>');
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item text-danger btn-delete-factura"' +
						' data-id="' + row.id + '"' +
						' data-delete-url="' + row.delete_url + '"' +
					'>Eliminar</button>' +
				'</li>'
			);
		}

		if (row.rectificar_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-rectificar-factura"' +
						' data-rectificar-url="' + row.rectificar_url + '"' +
					'>Rectificar</button>' +
				'</li>'
			);
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

		$table.on('click', '.btn-emitir-factura', function () {
			var emitirUrl = $(this).data('emitir-url');

			window.confirmDelete(
				'¿Emitir esta factura? Se asignará número fiscal y no podrá editarse ni borrarse después.',
				function () {
					$.ajax({
						url: emitirUrl,
						type: 'POST',
						headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
						success: function (response) {
							window.showToast('success', response.message);
							table.ajax.reload();
						},
						error: function (xhr) {
							window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo emitir la factura.');
						},
					});
				},
				{ confirmLabel: 'Emitir', confirmClass: 'btn-primary', icon: 'invoice' }
			);
		});

		$table.on('click', '.btn-rectificar-factura', function () {
			var rectificarUrl = $(this).data('rectificar-url');
			var $form = $('#rectificarFacturaForm');

			$form.attr('action', rectificarUrl);
			$form[0].reset();

			bootstrap.Modal.getOrCreateInstance(document.getElementById('rectificarFacturaModal')).show();
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
