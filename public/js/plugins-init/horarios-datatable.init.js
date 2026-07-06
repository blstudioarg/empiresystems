(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function renderEstado(data, type, row) {
		return row.activo
			? '<span class="badge light badge-success">Activo</span>'
			: '<span class="badge light badge-secondary">Inactivo</span>';
	}

	function renderAcciones(data, type, row) {
		var deleteDisabled = row.num_asignaciones > 0;

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown">Acciones</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li><button type="button" class="dropdown-item btn-edit-horario" data-horario=\'' + JSON.stringify(row).replace(/'/g, '&apos;') + '\'>Editar</button></li>' +
					'<li><hr class="dropdown-divider"></li>' +
					'<li><button type="button" class="dropdown-item text-danger btn-delete-horario"' + (deleteDisabled ? ' disabled title="Tiene asignaciones: reasigna a los miembros antes de eliminar"' : '') + ' data-delete-url="' + row.delete_url + '">Eliminar</button></li>' +
				'</ul>' +
			'</div>'
		);
	}

	$(function () {
		var $table = $('#horarios-table');

		if (!$table.length) {
			return;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: window.location.href,
				headers: { Accept: 'application/json' },
				dataSrc: 'data',
			},
			columns: [
				{ data: 'nombre', render: escapeHtml },
				{ data: 'horas_semana', render: function (data) { return escapeHtml(data) + ' h'; } },
				{ data: 'num_asignaciones' },
				{ data: null, orderable: false, render: renderEstado },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron horarios',
				emptyTable: 'Todavía no hay horarios definidos en este tenant',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		window.horariosTable = table;

		$table.on('click', '.btn-delete-horario', function () {
			var $btn = $(this);

			if ($btn.is('[disabled]')) {
				return;
			}

			var deleteUrl = $btn.data('delete-url');

			window.confirmDelete('¿Eliminar este horario?', function () {
				return $.ajax({
					url: deleteUrl,
					type: 'DELETE',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Horario eliminado correctamente.');
						table.ajax.reload(null, false);
					})
					.fail(function (xhr) {
						window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar el horario.');
					});
			});
		});
	});
})(jQuery);
