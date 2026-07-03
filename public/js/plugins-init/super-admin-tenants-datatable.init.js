(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	window.updateTenantsCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="activos"]').text(totales.activos);
	};

	function renderEstado(data, type, row) {
		return row.activo
			? '<span class="badge badge-light-success">Activo</span>'
			: '<span class="badge badge-light-secondary">Inactivo</span>';
	}

	function renderAcciones(data, type, row) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li>' +
						'<button type="button" class="dropdown-item btn-edit-tenant" data-bs-toggle="modal" data-bs-target="#tenantModal"' +
							' data-update-url="' + escapeAttr(row.update_url) + '"' +
							' data-dominio="' + escapeAttr(row.dominio) + '"' +
							' data-nombre-comercial="' + escapeAttr(row.nombre_comercial) + '"' +
							' data-razon-social="' + escapeAttr(row.razon_social) + '"' +
							' data-nif="' + escapeAttr(row.nif) + '"' +
							' data-direccion="' + escapeAttr(row.direccion) + '"' +
							' data-cp="' + escapeAttr(row.cp) + '"' +
							' data-ciudad="' + escapeAttr(row.ciudad) + '"' +
							' data-provincia="' + escapeAttr(row.provincia) + '"' +
							' data-pais="' + escapeAttr(row.pais) + '"' +
							' data-regimen-impositivo="' + escapeAttr(row.regimen_impositivo) + '"' +
							' data-email="' + escapeAttr(row.email) + '"' +
							' data-activo="' + (row.activo ? '1' : '0') + '"' +
						'>Ver/Editar</button>' +
					'</li>' +
					'<li><hr class="dropdown-divider"></li>' +
					'<li>' +
						'<button type="button" class="dropdown-item text-danger btn-delete-tenant"' +
							' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
						'>Eliminar</button>' +
					'</li>' +
				'</ul>' +
			'</div>'
		);
	}

	window.initTenantsDataTable = function () {
		var $table = $('#tenants-table');

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
					window.updateTenantsCards(json.totales);
					return json.data;
				},
			},
			columns: [
				{ data: 'nombre_comercial', render: escapeHtml },
				{ data: 'dominio', render: escapeHtml },
				{ data: 'nif', render: escapeHtml },
				{ data: null, orderable: false, render: renderEstado },
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
				zeroRecords: 'No se encontraron tenants',
				emptyTable: 'Todavía no hay tenants',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		table.buttons().container().appendTo('#tenants-colvis');

		return table;
	};

	$(function () {
		window.initTenantsDataTable();
	});
})(jQuery);
