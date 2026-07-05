(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var ESTADO_BADGE = {
		borrador: 'badge-light',
		en_curso: 'badge-warning',
		finalizada: 'badge-success',
	};

	var ESTADO_LABEL = {
		borrador: 'Borrador',
		en_curso: 'En curso',
		finalizada: 'Finalizada',
	};

	function renderEstado(data) {
		var cls = ESTADO_BADGE[data] || 'badge-light';
		var label = ESTADO_LABEL[data] || data;
		return '<span class="badge ' + cls + '">' + escapeHtml(label) + '</span>';
	}

	function renderAcciones(data, type, row) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Acciones</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li><a class="dropdown-item" href="' + escapeHtml(row.show_url) + '">Ver detalle</a></li>' +
				'</ul>' +
			'</div>'
		);
	}

	$(function () {
		var $table = $('#campanas-table');

		if (!$table.length) {
			return;
		}

		$table.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			order: [[5, 'desc']],
			ajax: {
				url: window.location.href,
				dataSrc: function (json) {
					if (json.totales) {
						$('[data-metric="total"]').text(json.totales.total);
						$('[data-metric="enviados"]').text(json.totales.enviados);
						$('[data-metric="fallidos"]').text(json.totales.fallidos);
					}
					return json.data;
				},
			},
			columns: [
				{ data: 'asunto', render: escapeHtml },
				{ data: 'estado', render: renderEstado },
				{ data: 'enviados', render: escapeHtml },
				{ data: 'fallidos', render: escapeHtml },
				{ data: 'total', render: escapeHtml },
				{ data: 'fecha', render: escapeHtml },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron campañas',
				emptyTable: 'Todavía no has creado ninguna campaña',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});
	});
})(jQuery);
