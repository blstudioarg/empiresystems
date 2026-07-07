(function ($) {
	'use strict';

	var state = window.rolesState || {};
	var rolesById = {};

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function buildUrl(template, id) {
		return template.replace('__ID__', id);
	}

	window.updateRolesCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="roles"]').text(totales.roles);
		$('[data-metric="usuarios_con_rol"]').text(totales.usuarios_con_rol);
		$('[data-metric="permisos_catalogo"]').text(totales.permisos_catalogo);
	};

	function renderNombre(data, type, row) {
		var badge = row.es_administrador
			? ' <span class="badge badge-light-primary ms-1">Administrador</span>'
			: '';

		return escapeHtml(row.name) + badge;
	}

	function renderPermisos(data, type, row) {
		return row.num_permisos + ' de ' + (state.totalPermisos || row.num_permisos);
	}

	function renderDefecto(data, type, row) {
		return (
			'<div class="form-check form-switch mb-0">' +
				'<input type="checkbox" class="form-check-input rol-defecto-switch" data-id="' + row.id + '"' +
					(row.es_defecto ? ' checked' : '') + '>' +
			'</div>'
		);
	}

	function renderAcciones(data, type, row) {
		var eliminar = row.es_administrador
			? ''
			: (
				'<li><hr class="dropdown-divider"></li>' +
				'<li><button type="button" class="dropdown-item text-danger btn-delete-rol" data-id="' + row.id + '">Eliminar</button></li>'
			);

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li><button type="button" class="dropdown-item btn-edit-rol" data-bs-toggle="modal" data-bs-target="#rolModal" data-id="' + row.id + '">Editar</button></li>' +
					eliminar +
				'</ul>' +
			'</div>'
		);
	}

	window.initRolesDataTable = function () {
		var $table = $('#roles-table');

		if (!$table.length) {
			return null;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: state.indexUrl,
				dataSrc: function (json) {
					window.updateRolesCards(json.totales);
					state.totalPermisos = json.totales.permisos_catalogo;

					rolesById = {};
					$.each(json.data, function (_, rol) {
						rolesById[rol.id] = rol;
					});

					return json.data;
				},
			},
			columns: [
				{ data: null, render: renderNombre },
				{ data: null, orderable: false, render: renderPermisos },
				{ data: 'num_usuarios' },
				{ data: null, orderable: false, render: renderDefecto },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron roles',
				emptyTable: 'Todavía no hay roles',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		return table;
	};

	function refreshListado() {
		var $table = $('#roles-table');

		if ($.fn.DataTable.isDataTable($table)) {
			$table.DataTable().ajax.reload(null, false);
		}
	}

	$(function () {
		window.initRolesDataTable();

		var $modal = $('#rolModal');
		var $form = $('#rol-form');

		if (!$modal.length || !$form.length) {
			return;
		}

		var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
		var $permisoCheckboxes = $form.find('.rol-permiso-checkbox');
		var $nombreInput = $form.find('#rol_name');

		function clearErrors() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		function showErrors(errors) {
			clearErrors();

			$.each(errors, function (field, messages) {
				var normalizado = field.replace(/^permisos\.\d+$/, 'permisos');
				var $feedback = $form.find('[data-error-for="' + normalizado + '"]');
				$feedback.text(messages[0]);

				var $field = $form.find('[name="' + field + '"]');
				$field.addClass('is-invalid');
			});
		}

		function sincronizarMaster($grupo) {
			var $hijos = $grupo.find('.rol-permiso-checkbox');
			var $master = $grupo.find('.rol-modulo-group__master');

			if (!$master.length) {
				return;
			}

			var total = $hijos.length;
			var marcados = $hijos.filter(':checked').length;

			$master.prop('checked', marcados === total);
			$master.prop('indeterminate', marcados > 0 && marcados < total);
		}

		function actualizarProteccionAdministrador(esAdministrador) {
			$nombreInput.prop('readonly', esAdministrador);

			$form.find('#permiso_ver-roles, #permiso_ver-usuarios').each(function () {
				var $checkbox = $(this);
				$checkbox.prop('disabled', esAdministrador);

				if (esAdministrador) {
					$checkbox.prop('checked', true);
				}
			});
		}

		$form.on('change', '.rol-modulo-group__master', function () {
			var $master = $(this);
			var $grupo = $master.closest('.rol-modulo-group');

			$grupo.find('.rol-permiso-checkbox').prop('checked', $master.is(':checked'));
		});

		$form.on('change', '.rol-permiso-checkbox', function () {
			sincronizarMaster($(this).closest('.rol-modulo-group'));
		});

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$permisoCheckboxes.prop('checked', false).prop('disabled', false);
			$form.find('.rol-modulo-group__master').prop('checked', false).prop('indeterminate', false);
			$nombreInput.prop('readonly', false);
			$form.find('#rol_method').val('POST');
			$form.attr('action', state.storeUrl);
			$('#rolModalLabel').text('Agregar rol');
		}

		function fillForm(rol) {
			clearErrors();
			$form.find('#rol_method').val('PUT');
			$form.attr('action', buildUrl(state.updateUrlTemplate, rol.id));
			$nombreInput.val(rol.name);

			$permisoCheckboxes.each(function () {
				var $checkbox = $(this);
				$checkbox.prop('disabled', false).prop('checked', rol.permisos.indexOf($checkbox.val()) !== -1);
			});
			$form.find('.rol-modulo-group').each(function () {
				sincronizarMaster($(this));
			});

			actualizarProteccionAdministrador(rol.es_administrador);
			$('#rolModalLabel').text('Editar rol');
		}

		$(document).on('click', '.btn-add-rol', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-rol', function () {
			var rol = rolesById[$(this).data('id')];

			if (rol) {
				fillForm(rol);
			}
		});

		$form.on('submit', function (event) {
			event.preventDefault();

			var $submitBtn = $form.find('button[type="submit"]');

			window.withButtonLoading($submitBtn, function () {
				return $.ajax({
					url: $form.attr('action'),
					method: 'POST',
					data: $form.serialize(),
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (response) {
					modal.hide();
					window.showToast('success', response.message || 'Operación realizada correctamente.');
					refreshListado();
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						showErrors((xhr.responseJSON && xhr.responseJSON.errors) || {});
					} else {
						var mensaje = (xhr.responseJSON && xhr.responseJSON.message) || 'Ocurrió un error inesperado. Inténtalo de nuevo.';
						window.showToast('danger', mensaje);
					}
				});
		});

		$(document).on('click', '.btn-delete-rol', function () {
			var id = $(this).data('id');
			var url = buildUrl(state.deleteUrlTemplate, id);

			window.confirmDelete('¿Eliminar este rol? Esta acción no se puede deshacer.', function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: $form.find('input[name="_token"]').val() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Rol eliminado correctamente.');
						refreshListado();
					})
					.fail(function (xhr) {
						var mensaje = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar el rol. Inténtalo de nuevo.';
						window.showToast('danger', mensaje);
					});
			});
		});

		$(document).on('change', '.rol-defecto-switch', function () {
			var $switch = $(this);
			var id = $switch.data('id');
			var url = buildUrl(state.defectoUrlTemplate, id);

			$switch.prop('disabled', true);

			$.ajax({
				url: url,
				method: 'POST',
				data: { _method: 'PATCH', _token: $form.find('input[name="_token"]').val() },
				dataType: 'json',
				headers: { Accept: 'application/json' },
			})
				.done(function (response) {
					window.showToast('success', response.message || 'Rol por defecto actualizado.');
					refreshListado();
				})
				.fail(function (xhr) {
					var mensaje = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar el rol por defecto.';
					window.showToast('danger', mensaje);
					refreshListado();
				})
				.always(function () {
					$switch.prop('disabled', false);
				});
		});
	});
})(jQuery);
