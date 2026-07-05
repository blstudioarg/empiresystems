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
		if (row.resultado === 'fallo') {
			return '<span class="badge badge-danger">' + escapeHtml(row.accion_label) + ' (denegado)</span>';
		}

		var clase = ACCION_BADGES[data] || 'badge-secondary';

		return '<span class="badge ' + clase + '">' + escapeHtml(row.accion_label) + '</span>';
	}

	function renderDescripcion(data, type, row) {
		var html = escapeHtml(data);

		if (row.ip_origen) {
			html += '<br><small class="text-muted">IP: ' + escapeHtml(row.ip_origen) + '</small>';
		}

		return html;
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
				{ data: 'descripcion', orderable: false, render: renderDescripcion },
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
