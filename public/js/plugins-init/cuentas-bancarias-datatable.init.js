(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	function renderEstado(data, type, row) {
		return row.activa
			? '<span class="badge badge-success light">Activa</span>'
			: '<span class="badge badge-secondary light">Inactiva</span>';
	}

	function renderAcciones(data, type, row) {
		var acciones =
			'<li>' +
				'<button type="button" class="dropdown-item btn-edit-cuenta" data-bs-toggle="modal" data-bs-target="#cuentaBancariaModal"' +
					' data-id="' + escapeAttr(row.id) + '"' +
					' data-update-url="' + escapeAttr(row.update_url) + '"' +
					' data-banco-id="' + escapeAttr(row.banco_id) + '"' +
					' data-alias="' + escapeAttr(row.alias) + '"' +
					' data-iban="' + escapeAttr(row.iban) + '"' +
					' data-titular="' + escapeAttr(row.titular) + '"' +
				'>Editar</button>' +
			'</li>';

		if (row.activa) {
			acciones +=
				'<li><hr class="dropdown-divider"></li>' +
				'<li>' +
					'<button type="button" class="dropdown-item text-danger btn-desactivar-cuenta"' +
						' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
					'>Desactivar</button>' +
				'</li>';
		} else {
			acciones +=
				'<li><hr class="dropdown-divider"></li>' +
				'<li>' +
					'<button type="button" class="dropdown-item text-success btn-reactivar-cuenta"' +
						' data-restore-url="' + escapeAttr(row.restore_url) + '"' +
					'>Reactivar</button>' +
				'</li>';
		}

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' + acciones + '</ul>' +
			'</div>'
		);
	}

	window.initCuentasBancariasDataTable = function () {
		var $table = $('#cuentas-bancarias-table');

		if (!$table.length || !window.cuentaBancariaState) {
			return null;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: {
				url: window.cuentaBancariaState.indexUrl,
				headers: { Accept: 'application/json' },
				dataSrc: 'data',
			},
			columns: [
				{ data: 'alias', render: escapeHtml },
				{ data: 'banco', render: escapeHtml },
				{ data: 'iban', render: escapeHtml, className: 'font-monospace' },
				{ data: 'titular', render: escapeHtml },
				{ data: null, orderable: false, render: renderEstado },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron cuentas bancarias',
				emptyTable: 'Todavía no hay cuentas bancarias configuradas',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		// La tabla vive dentro de una tab oculta al cargar; DataTables calcula mal los anchos de
		// columna mientras el contenedor está en display:none. Recalcular al mostrarse la tab.
		$('#tab-facturacion-btn').on('shown.bs.tab', function () {
			table.columns.adjust();
			if (table.responsive) {
				table.responsive.recalc();
			}
		});

		return table;
	};

	$(function () {
		window.initCuentasBancariasDataTable();
	});
})(jQuery);
