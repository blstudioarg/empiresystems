(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#proveedorModal');
		var $form = $('#proveedor-form');
		var state = window.proveedorFormState || {};

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
			var $table = $('#proveedores-table');

			if ($.fn.DataTable.isDataTable($table)) {
				$table.DataTable().ajax.reload(null, false);
			}
		}

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$form.find('#proveedor_method').val('POST');
			$form.attr('action', state.storeUrl);
			$ciudad.html('<option value="">Selecciona una provincia primero</option>');
			$('#proveedorModalLabel').text('Agregar proveedor');
		}

		function fillForm(data) {
			clearErrors();
			$form.find('#proveedor_method').val('PUT');
			$form.attr('action', data.updateUrl);
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
			$form.find('#notas').val(data.notas);
			$('#proveedorModalLabel').text('Editar proveedor');
		}

		$(document).on('click', '.btn-add-proveedor', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-proveedor', function () {
			var $btn = $(this);

			fillForm({
				updateUrl: $btn.data('update-url'),
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
				notas: $btn.data('notas'),
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

		$(document).on('click', '.btn-delete-proveedor', function () {
			var url = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar este proveedor? Esta acción no se puede deshacer desde la pantalla.', function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') || $form.find('input[name="_token"]').val() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (response) {
						showAlert('success', response.message || 'Proveedor eliminado correctamente.');
						refreshListado();
					})
					.fail(function () {
						showAlert('danger', 'No se pudo eliminar el proveedor. Inténtalo de nuevo.');
					});
			});
		});
	});
})(jQuery);
