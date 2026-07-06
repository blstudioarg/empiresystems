(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function renderEstado(data, type, row) {
		return row.activo
			? '<span class="badge light badge-success">Activo</span>'
			: '<span class="badge light badge-secondary">Baja</span>';
	}

	window.updateMiembrosEquipoCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="activos"]').text(totales.activos);
		$('[data-metric="con_ubicacion"]').text(totales.con_ubicacion);
	};

	function renderAcciones(data, type, row) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown">Acciones</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li>' +
						'<button type="button" class="dropdown-item btn-edit-miembro-equipo"' +
							' data-id="' + row.id + '"' +
							' data-update-url="' + row.update_url + '"' +
							' data-user-id="' + row.user_id + '"' +
							' data-puesto="' + escapeHtml(row.puesto || '') + '"' +
							' data-trabajo-direccion="' + escapeHtml(row.trabajo_direccion || '') + '"' +
							' data-trabajo-latitud="' + (row.trabajo_latitud ?? '') + '"' +
							' data-trabajo-longitud="' + (row.trabajo_longitud ?? '') + '"' +
							' data-distancia-max-metros="' + row.distancia_max_metros + '"' +
							' data-casa-direccion="' + escapeHtml(row.casa_direccion || '') + '"' +
							' data-casa-latitud="' + (row.casa_latitud ?? '') + '"' +
							' data-casa-longitud="' + (row.casa_longitud ?? '') + '"' +
						'>Editar</button>' +
					'</li>' +
					'<li><hr class="dropdown-divider"></li>' +
					'<li><button type="button" class="dropdown-item text-danger btn-delete-miembro" data-delete-url="' + row.delete_url + '">Dar de baja</button></li>' +
				'</ul>' +
			'</div>'
		);
	}

	$(function () {
		var $table = $('#miembros-equipo-table');

		if (!$table.length) {
			return;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: window.location.href,
				headers: { Accept: 'application/json' },
				dataSrc: function (json) {
					window.updateMiembrosEquipoCards(json.totales);
					window.miembrosEquipoState = {
						usuarios: json.usuarios || [],
						asignados: (json.data || []).map(function (fila) { return fila.user_id; }),
					};
					return json.data;
				},
			},
			columns: [
				{ data: 'nombre', render: escapeHtml },
				{ data: 'puesto', render: function (data) { return escapeHtml(data || '—'); } },
				{ data: 'distancia_max_metros', render: function (data) { return escapeHtml(data) + ' m'; } },
				{ data: 'distancia_casa_trabajo_metros', render: function (data) { return data ? escapeHtml(data) + ' m' : '—'; } },
				{ data: null, orderable: false, render: renderEstado },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron miembros',
				emptyTable: 'Todavía no hay miembros de equipo en este tenant',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		window.miembrosEquipoTable = table;

		$table.on('click', '.btn-delete-miembro', function () {
			var deleteUrl = $(this).data('delete-url');

			window.confirmDelete('¿Dar de baja a este miembro? Sus fichajes históricos se conservan.', function () {
				return $.ajax({
					url: deleteUrl,
					type: 'DELETE',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Miembro dado de baja correctamente.');
						table.ajax.reload(null, false);
					})
					.fail(function (xhr) {
						window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo dar de baja al miembro.');
					});
			}, { confirmLabel: 'Dar de baja' });
		});
	});
})(jQuery);
