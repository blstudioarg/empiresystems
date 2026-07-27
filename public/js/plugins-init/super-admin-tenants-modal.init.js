(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#tenantModal');
		var $form = $('#tenant-form');
		var state = window.tenantFormState || {};

		if (!$modal.length || !$form.length) {
			return;
		}

		var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);

		function clearErrors() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		function showErrors(errors) {
			clearErrors();

			$.each(errors, function (field, messages) {
				var $field = $form.find('[name="' + field + '"]');
				var $feedback = $form.find('[data-error-for="' + field + '"]');
				$field.addClass('is-invalid');
				$feedback.text(messages[0]);
			});
		}

		function showAlert(type, message) {
			window.showToast(type, message);
		}

		var $provincia = $form.find('#provincia');
		var $ciudad = $form.find('#ciudad');

		function loadLocalidades(provinciaId, selected) {
			if (!provinciaId) {
				$ciudad.html('<option value="">Selecciona una provincia primero</option>');
				return;
			}

			$ciudad.prop('disabled', true).html('<option value="">Cargando...</option>');

			$.ajax({
				url: state.localidadesUrl,
				method: 'GET',
				data: { provincia_id: provinciaId },
				dataType: 'json',
			})
				.done(function (localidades) {
					var options = '<option value="">Selecciona una localidad</option>';

					$.each(localidades, function (_, localidad) {
						options += '<option value="' + localidad.nombre + '">' + localidad.nombre + '</option>';
					});

					$ciudad.html(options);

					if (selected) {
						$ciudad.val(selected);
					}
				})
				.fail(function () {
					$ciudad.html('<option value="">No se pudieron cargar las localidades</option>');
				})
				.always(function () {
					$ciudad.prop('disabled', false);
				});
		}

		$provincia.on('change', function () {
			var provinciaId = $provincia.find('option:selected').data('provincia-id');
			loadLocalidades(provinciaId, null);
		});

		function refreshListado() {
			var $table = $('#tenants-table');

			if ($.fn.DataTable.isDataTable($table)) {
				$table.DataTable().ajax.reload(null, false);
			}
		}

		var $adminFields = $form.find('.admin-field');
		var $adminEmail = $form.find('#admin_email');
		var $adminPassword = $form.find('#admin_password');
		var $usuariosSection = $form.find('.usuarios-tenant-field');
		var $usuariosBody = $form.find('#usuarios-tenant-body');

		function escapeHtml(value) {
			return $('<div>').text(value === null || value === undefined ? '' : value).html();
		}

		function renderUsuariosError(message) {
			$usuariosBody.html('<tr><td colspan="5" class="text-center text-danger">' + escapeHtml(message) + '</td></tr>');
		}

		function renderUsuarios(usuarios) {
			if (!usuarios.length) {
				$usuariosBody.html('<tr><td colspan="5" class="text-center text-muted">Este tenant todavía no tiene usuarios.</td></tr>');
				return;
			}

			var rows = usuarios.map(function (usuario) {
				return (
					'<tr data-usuario-row data-update-url="' + escapeHtml(usuario.update_url) + '">' +
						'<td>' + escapeHtml(usuario.name) + '</td>' +
						'<td>' + escapeHtml(usuario.rol) + '</td>' +
						'<td>' +
							'<input type="email" class="form-control form-control-sm" data-usuario-email value="' + escapeHtml(usuario.email) + '">' +
							'<div class="invalid-feedback d-block" data-usuario-error-email></div>' +
						'</td>' +
						'<td>' +
							'<div class="position-relative">' +
								'<input type="password" class="form-control form-control-sm" data-usuario-password placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">' +
								'<span class="show-pass eye"><i class="fa fa-eye-slash"></i><i class="fa fa-eye"></i></span>' +
							'</div>' +
							'<div class="invalid-feedback d-block" data-usuario-error-password></div>' +
						'</td>' +
						'<td>' +
							'<button type="button" class="btn btn-sm btn-outline-primary" data-usuario-guardar>Guardar</button>' +
						'</td>' +
					'</tr>'
				);
			});

			$usuariosBody.html(rows.join(''));
		}

		function cargarUsuarios(usuariosUrl) {
			if (!usuariosUrl) {
				renderUsuariosError('No se pudo determinar la URL de usuarios de este tenant.');
				return;
			}

			$usuariosBody.html('<tr><td colspan="5" class="text-center text-muted">Cargando usuarios...</td></tr>');

			$.ajax({ url: usuariosUrl, method: 'GET', dataType: 'json', headers: { Accept: 'application/json' } })
				.done(function (response) {
					renderUsuarios(response.data || []);
				})
				.fail(function () {
					renderUsuariosError('No se pudieron cargar los usuarios de este tenant.');
				});
		}

		$usuariosBody.on('click', '[data-usuario-guardar]', function () {
			var $btn = $(this);
			var $row = $btn.closest('tr');
			var updateUrl = $row.data('update-url');
			var $emailInput = $row.find('[data-usuario-email]');
			var $passwordInput = $row.find('[data-usuario-password]');
			var $emailError = $row.find('[data-usuario-error-email]');
			var $passwordError = $row.find('[data-usuario-error-password]');

			$emailInput.removeClass('is-invalid');
			$passwordInput.removeClass('is-invalid');
			$emailError.text('');
			$passwordError.text('');

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: updateUrl,
					method: 'POST',
					data: {
						_method: 'PUT',
						_token: $('meta[name="csrf-token"]').attr('content') || $form.find('input[name="_token"]').val(),
						email: $emailInput.val(),
						password: $passwordInput.val(),
					},
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (response) {
					$passwordInput.val('');
					showAlert('success', response.message || 'Usuario actualizado correctamente.');
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						var errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};

						if (errors.email) {
							$emailInput.addClass('is-invalid');
							$emailError.text(errors.email[0]);
						}

						if (errors.password) {
							$passwordInput.addClass('is-invalid');
							$passwordError.text(errors.password[0]);
						}
					} else {
						showAlert('danger', 'Ocurrió un error inesperado. Inténtalo de nuevo.');
					}
				});
		});

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$form.find('#tenant_method').val('POST');
			$form.attr('action', state.storeUrl);
			$ciudad.html('<option value="">Selecciona una provincia primero</option>');
			$adminFields.removeClass('d-none');
			$adminEmail.prop('required', true);
			$adminPassword.prop('required', true);
			$usuariosSection.addClass('d-none');
			$usuariosBody.html('');
			$('#tenantModalLabel').text('Agregar tenant');
		}

		function fillForm(data) {
			clearErrors();
			$form.find('#tenant_method').val('PUT');
			$form.attr('action', data.updateUrl);
			$form.find('#dominio').val(data.dominio);
			$form.find('#nombre_comercial').val(data.nombreComercial);
			$form.find('#razon_social').val(data.razonSocial);
			$form.find('#nif').val(data.nif);
			$form.find('#direccion').val(data.direccion);
			$form.find('#cp').val(data.cp);
			$provincia.val(data.provincia);
			var provinciaId = $provincia.find('option:selected').data('provincia-id');
			loadLocalidades(provinciaId, data.ciudad);
			$form.find('#pais').val(data.pais);
			$form.find('#regimen_impositivo').val(data.regimenImpositivo);
			$form.find('#email').val(data.email);
			$form.find('#activo').prop('checked', data.activo === '1');
			// Editar un tenant no crea ni modifica su administrador inicial (research.md D5); los
			// usuarios ya existentes del tenant se gestionan aparte, en la sección de abajo.
			$adminFields.addClass('d-none');
			$adminEmail.prop('required', false).val('');
			$adminPassword.prop('required', false).val('');
			$usuariosSection.removeClass('d-none');
			cargarUsuarios(data.usuariosUrl);
			$('#tenantModalLabel').text('Editar tenant');
		}

		$(document).on('click', '.btn-add-tenant', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-tenant', function () {
			var $btn = $(this);

			fillForm({
				updateUrl: $btn.data('update-url'),
				dominio: $btn.data('dominio'),
				nombreComercial: $btn.data('nombre-comercial'),
				razonSocial: $btn.data('razon-social'),
				nif: $btn.data('nif'),
				direccion: $btn.data('direccion'),
				cp: $btn.data('cp'),
				ciudad: $btn.data('ciudad'),
				provincia: $btn.data('provincia'),
				pais: $btn.data('pais'),
				regimenImpositivo: $btn.data('regimen-impositivo'),
				email: $btn.data('email'),
				activo: String($btn.data('activo')),
				usuariosUrl: $btn.data('usuarios-url'),
			});
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
					showAlert('success', response.message || 'Operación realizada correctamente.');
					refreshListado();
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						showErrors(xhr.responseJSON.errors || {});
					} else {
						showAlert('danger', 'Ocurrió un error inesperado. Inténtalo de nuevo.');
					}
				});
		});

		$(document).on('click', '.btn-delete-tenant', function () {
			var url = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar este tenant? Esta acción no se puede deshacer.', function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') || $form.find('input[name="_token"]').val() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (response) {
						showAlert('success', response.message || 'Tenant eliminado correctamente.');
						refreshListado();
					})
					.fail(function (xhr) {
						showAlert('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar el tenant. Inténtalo de nuevo.');
					});
			});
		});
	});
})(jQuery);
