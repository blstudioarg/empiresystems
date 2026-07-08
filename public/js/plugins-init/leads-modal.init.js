(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#leadModal');
		var $form = $('#lead-form');
		var state = window.leadFormState || {};

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
				$form.find('[name="' + field + '"]').addClass('is-invalid');
				$form.find('[data-error-for="' + field + '"]').text(messages[0]);
			});
		}

		function refreshListado() {
			var $table = $('#leads-table');

			if ($.fn.DataTable.isDataTable($table)) {
				$table.DataTable().ajax.reload(null, false);
			}
		}

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$('#lead_method').val('POST');
			$form.attr('action', state.storeUrl);
			$('#leadModalLabel').text('Agregar lead');
		}

		function fillForm(data) {
			clearErrors();
			$('#lead_method').val('PUT');
			$form.attr('action', data.updateUrl);
			$form.find('#nombre').val(data.nombre);
			$form.find('#empresa').val(data.empresa);
			$form.find('#email').val(data.email);
			$form.find('#telefono').val(data.telefono);
			$form.find('#asignado_a').val(data.asignadoA || '');
			$('#leadModalLabel').text('Editar lead');
		}

		$(document).on('click', '.btn-add-lead', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-lead', function () {
			var $btn = $(this);

			fillForm({
				updateUrl: $btn.data('update-url'),
				nombre: $btn.data('nombre'),
				empresa: $btn.data('empresa'),
				email: $btn.data('email'),
				telefono: $btn.data('telefono'),
				asignadoA: $btn.data('asignado-a'),
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
					window.showToast('success', response.message || 'Operación realizada correctamente.');
					refreshListado();
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						showErrors(xhr.responseJSON.errors || {});
					} else {
						window.showToast('danger', 'Ocurrió un error inesperado. Inténtalo de nuevo.');
					}
				});
		});

		$(document).on('click', '.btn-delete-lead', function () {
			var url = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar este lead? Esta acción no se puede deshacer.', function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: $form.find('input[name="_token"]').val() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Lead eliminado correctamente.');
						refreshListado();
					})
					.fail(function () {
						window.showToast('danger', 'No se pudo eliminar el lead. Inténtalo de nuevo.');
					});
			});
		});
	});
})(jQuery);
