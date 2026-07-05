(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	function renderEstado(data) {
		return data
			? '<span class="badge badge-success">Activa</span>'
			: '<span class="badge badge-light">Inactiva</span>';
	}

	function renderAcciones(data, type, row) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Acciones</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li>' +
						'<button type="button" class="dropdown-item btn-edit-plantilla" data-bs-toggle="modal" data-bs-target="#plantillaModal"' +
							' data-id="' + escapeAttr(row.id) + '"' +
							' data-update-url="' + escapeAttr(row.update_url) + '"' +
							' data-titulo="' + escapeAttr(row.titulo) + '"' +
							' data-asunto="' + escapeAttr(row.asunto) + '"' +
							' data-cuerpo="' + escapeAttr(row.cuerpo) + '"' +
							' data-activa="' + (row.activa ? '1' : '0') + '"' +
						'>Editar</button>' +
					'</li>' +
					'<li><hr class="dropdown-divider"></li>' +
					'<li>' +
						'<button type="button" class="dropdown-item text-danger btn-delete-plantilla"' +
							' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
						'>Eliminar</button>' +
					'</li>' +
				'</ul>' +
			'</div>'
		);
	}

	window.initPlantillasDataTable = function () {
		var $table = $('#plantillas-table');
		if (!$table.length) {
			return null;
		}

		return $table.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: {
				url: window.location.href,
				dataSrc: function (json) {
					if (json.totales) {
						$('[data-metric="total"]').text(json.totales.total);
						$('[data-metric="activas"]').text(json.totales.activas);
						$('[data-metric="inactivas"]').text(json.totales.inactivas);
					}
					return json.data;
				},
			},
			columns: [
				{ data: 'titulo', render: escapeHtml },
				{ data: 'asunto', render: escapeHtml },
				{ data: 'activa', render: renderEstado },
				{ data: 'modificado', render: escapeHtml },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron plantillas',
				emptyTable: 'Todavía no has creado ninguna plantilla',
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
		window.initPlantillasDataTable();
	});
})(jQuery);
