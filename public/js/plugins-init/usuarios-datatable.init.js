(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	var ROL_LABELS = {
		super_admin: 'Super admin',
		admin: 'Administrador',
		usuario: 'Usuario',
	};

	var ESTADO_META = {
		pendiente: { label: 'Pendiente', clase: 'badge-warning' },
		aprobado: { label: 'Aprobado', clase: 'badge-success' },
		rechazado: { label: 'Rechazado', clase: 'badge-danger' },
	};

	var rolesDisponibles = [];

	window.updateUsuariosCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="pendientes"]').text(totales.pendientes);
		$('[data-metric="activos"]').text(totales.activos);
	};

	function renderRol(data) {
		return escapeHtml(ROL_LABELS[data] || data);
	}

	function renderEstado(data) {
		var meta = ESTADO_META[data] || { label: data, clase: 'badge-secondary' };
		return '<span class="badge ' + meta.clase + '">' + escapeHtml(meta.label) + '</span>';
	}

	function renderRolAsignado(data, type, row) {
		var asignadoId = row.rol_asignado ? row.rol_asignado.id : '';
		var options = '<option value="">Sin rol</option>';

		$.each(rolesDisponibles, function (_, rol) {
			var selected = String(rol.id) === String(asignadoId) ? ' selected' : '';
			options += '<option value="' + rol.id + '"' + selected + '>' + escapeHtml(rol.name) + '</option>';
		});

		return (
			'<select class="form-select form-select-sm rol-select" data-rol-url="' + escapeAttr(row.rol_url) + '">' +
				options +
			'</select>'
		);
	}

	function renderAcciones(data, type, row) {
		if (row.es_actual) {
			return '<span class="text-muted">—</span>';
		}

		var items = '';

		if (row.estado !== 'aprobado') {
			items +=
				'<li>' +
					'<button type="button" class="dropdown-item btn-aprobar-usuario"' +
						' data-aprobar-url="' + escapeAttr(row.aprobar_url) + '"' +
					'>Aprobar</button>' +
				'</li>';
		}

		if (row.estado !== 'rechazado') {
			if (items) {
				items += '<li><hr class="dropdown-divider"></li>';
			}

			items +=
				'<li>' +
					'<button type="button" class="dropdown-item text-danger btn-rechazar-usuario"' +
						' data-rechazar-url="' + escapeAttr(row.rechazar_url) + '"' +
					'>Rechazar</button>' +
				'</li>';
		}

		if (!items) {
			return '<span class="text-muted">—</span>';
		}

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					items +
				'</ul>' +
			'</div>'
		);
	}

	function refreshListado() {
		var $table = $('#usuarios-table');

		if ($.fn.DataTable.isDataTable($table)) {
			$table.DataTable().ajax.reload(null, false);
		}
	}

	function patchAccion($btn, url, mensajeExito, mensajeError) {
		window.withButtonLoading($btn, function () {
			return $.ajax({
				url: url,
				method: 'POST',
				data: {
					_method: 'PATCH',
					_token: (window.usuariosState && window.usuariosState.csrfToken) || $('meta[name="csrf-token"]').attr('content'),
				},
				dataType: 'json',
				headers: { Accept: 'application/json' },
			});
		})
			.done(function (response) {
				window.showToast('success', (response && response.message) || mensajeExito);
				refreshListado();
			})
			.fail(function () {
				window.showToast('danger', mensajeError);
			});
	}

	window.initUsuariosDataTable = function () {
		var $table = $('#usuarios-table');

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
					window.updateUsuariosCards(json.totales);
					rolesDisponibles = json.roles_disponibles || [];
					return json.data;
				},
			},
			columns: [
				{ data: 'name', render: escapeHtml },
				{ data: 'email', render: escapeHtml },
				{ data: 'rol', render: renderRol },
				{ data: 'estado', render: renderEstado },
				{ data: null, orderable: false, render: renderRolAsignado },
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
				zeroRecords: 'No se encontraron usuarios',
				emptyTable: 'Todavía no hay usuarios en esta cuenta',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		table.buttons().container().appendTo('#usuarios-colvis');

		return table;
	};

	$(function () {
		window.initUsuariosDataTable();

		$(document).on('click', '.btn-aprobar-usuario', function () {
			patchAccion($(this), $(this).data('aprobar-url'), 'Usuario aprobado correctamente.', 'No se pudo aprobar el usuario. Inténtalo de nuevo.');
		});

		$(document).on('click', '.btn-rechazar-usuario', function () {
			patchAccion($(this), $(this).data('rechazar-url'), 'Usuario rechazado correctamente.', 'No se pudo rechazar el usuario. Inténtalo de nuevo.');
		});

		$(document).on('change', '.rol-select', function () {
			var $select = $(this);
			var roleId = $select.val() || null;

			$select.prop('disabled', true);

			$.ajax({
				url: $select.data('rol-url'),
				method: 'POST',
				data: {
					_method: 'PATCH',
					role_id: roleId,
					_token: (window.usuariosState && window.usuariosState.csrfToken) || $('meta[name="csrf-token"]').attr('content'),
				},
				dataType: 'json',
				headers: { Accept: 'application/json' },
			})
				.done(function (response) {
					window.showToast('success', (response && response.message) || 'Rol asignado correctamente.');
					refreshListado();
				})
				.fail(function (xhr) {
					var mensaje = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo asignar el rol. Inténtalo de nuevo.';
					window.showToast('danger', mensaje);
					refreshListado();
				})
				.always(function () {
					$select.prop('disabled', false);
				});
		});
	});
})(jQuery);
