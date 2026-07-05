(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#plantillaModal');
		var $form = $('#plantilla-form');
		var state = window.plantillaFormState || {};

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
			var $table = $('#plantillas-table');
			if ($.fn.DataTable.isDataTable($table)) {
				$table.DataTable().ajax.reload(null, false);
			}
		}

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$form.find('#plantilla_method').val('POST');
			$form.attr('action', state.storeUrl);
			$form.find('#plantilla_activa').prop('checked', true);
			$('#plantillaModalLabel').text('Nueva plantilla');
		}

		function fillForm(data) {
			clearErrors();
			$form.find('#plantilla_method').val('PUT');
			$form.attr('action', data.updateUrl);
			$form.find('#plantilla_titulo').val(data.titulo);
			$form.find('#plantilla_asunto').val(data.asunto);
			$form.find('#plantilla_cuerpo').val(data.cuerpo);
			$form.find('#plantilla_activa').prop('checked', data.activa === '1');
			$('#plantillaModalLabel').text('Editar plantilla');
		}

		$(document).on('click', '.btn-add-plantilla', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-plantilla', function () {
			var $btn = $(this);
			fillForm({
				updateUrl: $btn.data('update-url'),
				titulo: $btn.data('titulo'),
				asunto: $btn.data('asunto'),
				cuerpo: $btn.data('cuerpo'),
				activa: String($btn.data('activa')),
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
						window.showToast('error', 'Ocurrió un error inesperado. Inténtalo de nuevo.');
					}
				});
		});

		$(document).on('click', '.btn-delete-plantilla', function () {
			var url = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar esta plantilla? Las campañas ya creadas conservan su copia.', function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Plantilla eliminada correctamente.');
						refreshListado();
					})
					.fail(function () {
						window.showToast('error', 'No se pudo eliminar la plantilla. Inténtalo de nuevo.');
					});
			});
		});
	});
})(jQuery);
