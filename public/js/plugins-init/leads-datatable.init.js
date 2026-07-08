(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	var filtroActivo = 'todos';

	window.updateLeadsCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="sin_asignar"]').text(totales.sin_asignar);
		$('[data-metric="cualificados"]').text(totales.cualificados);
	};

	function renderAcciones(data, type, row) {
		var updateUrl = window.leadFormState.indexUrl + '/' + row.id;

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li><button type="button" class="dropdown-item btn-ver-ficha-lead" data-id="' + escapeAttr(row.id) + '">Ver ficha</button></li>' +
					'<li>' +
						'<button type="button" class="dropdown-item btn-edit-lead" data-bs-toggle="modal" data-bs-target="#leadModal"' +
							' data-id="' + escapeAttr(row.id) + '"' +
							' data-update-url="' + escapeAttr(updateUrl) + '"' +
							' data-nombre="' + escapeAttr(row.nombre) + '"' +
							' data-empresa="' + escapeAttr(row.empresa) + '"' +
							' data-email="' + escapeAttr(row.email) + '"' +
							' data-telefono="' + escapeAttr(row.telefono) + '"' +
							' data-asignado-a="' + escapeAttr(row.asignado_a) + '"' +
						'>Editar</button>' +
					'</li>' +
					'<li><hr class="dropdown-divider"></li>' +
					'<li>' +
						'<button type="button" class="dropdown-item text-danger btn-delete-lead"' +
							' data-delete-url="' + escapeAttr(updateUrl) + '"' +
						'>Eliminar</button>' +
					'</li>' +
				'</ul>' +
			'</div>'
		);
	}

	window.initLeadsDataTable = function () {
		var $table = $('#leads-table');

		if (!$table.length) {
			return null;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: window.leadFormState.indexUrl,
				data: function (d) {
					d.filtro = filtroActivo;
				},
				dataSrc: function (json) {
					window.updateLeadsCards(json.totales);
					return json.data;
				},
			},
			columns: [
				{ data: 'nombre', render: escapeHtml },
				{ data: 'empresa', render: escapeHtml },
				{ data: 'email', render: escapeHtml },
				{ data: 'telefono', render: escapeHtml },
				{ data: 'estado_label', render: escapeHtml },
				{ data: 'origen_label', render: escapeHtml },
				{ data: 'asignado_nombre', render: function (value) { return value ? escapeHtml(value) : '<span class="text-muted">Sin asignar</span>'; } },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron leads',
				emptyTable: 'Todavía no hay leads en esta cuenta',
				processing: 'Cargando...',
				paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
			},
		});

		$(document).on('click', '.btn-filtro-leads', function () {
			$('.btn-filtro-leads').removeClass('active');
			$(this).addClass('active');
			filtroActivo = $(this).data('filtro');
			table.ajax.reload(null, true);
		});

		return table;
	};

	$(function () {
		window.initLeadsDataTable();
	});
})(jQuery);
