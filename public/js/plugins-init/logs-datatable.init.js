(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var ACCION_BADGES = {
		login: 'badge-success',
		logout: 'badge-secondary',
		alta: 'badge-success',
		baja: 'badge-danger',
		modificacion: 'badge-warning',
	};

	function renderAccion(data, type, row) {
		var clase = ACCION_BADGES[data] || 'badge-secondary';

		return '<span class="badge ' + clase + '">' + escapeHtml(row.accion_label) + '</span>';
	}

	function renderResultado(data, type, row) {
		var clase = data === 'fallo' ? 'badge-danger' : 'badge-success';

		return '<span class="badge ' + clase + '">' + escapeHtml(row.resultado_label) + '</span>';
	}

	function renderOGuion(data) {
		return data ? escapeHtml(data) : '<span class="text-muted">—</span>';
	}

	window.initLogsDataTable = function () {
		var $table = $('#logs-table');

		if (!$table.length) {
			return null;
		}

		return $table.DataTable({
			responsive: true,
			processing: true,
			serverSide: true,
			order: [[0, 'desc']],
			ajax: {
				url: window.location.href,
				type: 'GET',
			},
			columns: [
				{ data: 'fecha' },
				{ data: 'usuario_nombre', render: escapeHtml },
				{ data: 'accion', render: renderAccion },
				{ data: 'resultado', render: renderResultado },
				{ data: 'descripcion', orderable: false, render: escapeHtml },
				{ data: 'ip_origen', orderable: false, render: renderOGuion },
				{ data: 'navegador', orderable: false, render: renderOGuion },
				{ data: 'ubicacion', orderable: false, render: renderOGuion },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron eventos',
				emptyTable: 'Todavía no hay actividad registrada en esta cuenta',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});
	};

	$(function () {
		window.initLogsDataTable();
	});
})(jQuery);
