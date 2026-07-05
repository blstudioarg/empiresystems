(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	window.updateProveedoresCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
	};

	function renderAcciones(data, type, row) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li>' +
						'<button type="button" class="dropdown-item btn-edit-proveedor" data-bs-toggle="modal" data-bs-target="#proveedorModal"' +
							' data-id="' + escapeAttr(row.id) + '"' +
							' data-update-url="' + escapeAttr(row.update_url) + '"' +
							' data-nombre="' + escapeAttr(row.nombre) + '"' +
							' data-razon-social="' + escapeAttr(row.razon_social) + '"' +
							' data-nif="' + escapeAttr(row.nif) + '"' +
							' data-direccion="' + escapeAttr(row.direccion) + '"' +
							' data-cp="' + escapeAttr(row.cp) + '"' +
							' data-ciudad="' + escapeAttr(row.ciudad) + '"' +
							' data-provincia="' + escapeAttr(row.provincia) + '"' +
							' data-pais="' + escapeAttr(row.pais) + '"' +
							' data-email="' + escapeAttr(row.email) + '"' +
							' data-telefono="' + escapeAttr(row.telefono) + '"' +
							' data-notas="' + escapeAttr(row.notas) + '"' +
						'>Editar</button>' +
					'</li>' +
					'<li><hr class="dropdown-divider"></li>' +
					'<li>' +
						'<button type="button" class="dropdown-item text-danger btn-delete-proveedor"' +
							' data-id="' + escapeAttr(row.id) + '"' +
							' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
						'>Eliminar</button>' +
					'</li>' +
				'</ul>' +
			'</div>'
		);
	}

	window.initProveedoresDataTable = function () {
		var $table = $('#proveedores-table');

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
					window.updateProveedoresCards(json.totales);
					return json.data;
				},
			},
			columns: [
				{ data: 'nombre', render: escapeHtml },
				{ data: 'nif', render: escapeHtml },
				{ data: 'email', render: escapeHtml },
				{ data: 'telefono', render: escapeHtml },
				{ data: 'ciudad', render: escapeHtml },
				{ data: null, orderable: false, render: renderAcciones },
			],
			buttons: [
				{
					extend: 'colvis',
					text: 'Columnas',
					className: 'btn btn-outline-secondary',
					columns: ':not(:last-child)',
				},
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron proveedores',
				emptyTable: 'Todavía no hay proveedores en esta cuenta',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		table.buttons().container().appendTo('#proveedores-colvis');

		return table;
	};

	$(function () {
		window.initProveedoresDataTable();
	});
})(jQuery);
