(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#clienteModal');
		var $form = $('#cliente-form');
		var state = window.clienteFormState || {};

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
			var $table = $('#clientes-table');

			if ($.fn.DataTable.isDataTable($table)) {
				$table.DataTable().ajax.reload(null, false);
			}
		}

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$form.find('#cliente_method').val('POST');
			$form.attr('action', state.storeUrl);
			$ciudad.html('<option value="">Selecciona una provincia primero</option>');
			$('#clienteModalLabel').text('Agregar cliente');
		}

		function fillForm(data) {
			clearErrors();
			$form.find('#cliente_method').val('PUT');
			$form.attr('action', data.updateUrl);
			$form.find('#tipo').val(data.tipo);
			$form.find('#nombre').val(data.nombre);
			$form.find('#razon_social').val(data.razonSocial);
			$form.find('#nif').val(data.nif);
			$form.find('#direccion').val(data.direccion);
			$form.find('#cp').val(data.cp);
			$provincia.val(data.provincia);
			var provinciaId = $provincia.find('option:selected').data('provincia-id');
			loadLocalidades(provinciaId, data.ciudad);
			$form.find('#pais').val(data.pais);
			$form.find('#email').val(data.email);
			$form.find('#telefono').val(data.telefono);
			$form.find('#aplica_recargo_equivalencia').prop('checked', data.recargo === '1');
			$form.find('#notas').val(data.notas);
			$('#clienteModalLabel').text('Editar cliente');
		}

		$(document).on('click', '.btn-add-cliente', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-cliente', function () {
			var $btn = $(this);

			fillForm({
				updateUrl: $btn.data('update-url'),
				tipo: $btn.data('tipo'),
				nombre: $btn.data('nombre'),
				razonSocial: $btn.data('razon-social'),
				nif: $btn.data('nif'),
				direccion: $btn.data('direccion'),
				cp: $btn.data('cp'),
				ciudad: $btn.data('ciudad'),
				provincia: $btn.data('provincia'),
				pais: $btn.data('pais'),
				email: $btn.data('email'),
				telefono: $btn.data('telefono'),
				recargo: String($btn.data('recargo')),
				notas: $btn.data('notas'),
			});
		});

		$form.on('submit', function (event) {
			event.preventDefault();

			var $submitBtn = $form.find('button[type="submit"]');
			$submitBtn.prop('disabled', true);

			$.ajax({
				url: $form.attr('action'),
				method: 'POST',
				data: $form.serialize(),
				dataType: 'json',
				headers: { Accept: 'application/json' },
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
				})
				.always(function () {
					$submitBtn.prop('disabled', false);
				});
		});

		$(document).on('click', '.btn-delete-cliente', function () {
			var url = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar este cliente? Esta acción no se puede deshacer desde la pantalla.', function () {
				$.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') || $form.find('input[name="_token"]').val() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (response) {
						showAlert('success', response.message || 'Cliente eliminado correctamente.');
						refreshListado();
					})
					.fail(function () {
						showAlert('danger', 'No se pudo eliminar el cliente. Inténtalo de nuevo.');
					});
			});
		});
	});
})(jQuery);
