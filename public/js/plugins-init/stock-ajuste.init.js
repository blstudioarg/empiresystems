(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#ajusteStockModal');
		var $form = $('#ajuste-stock-form');

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
					clearErrors();
					$form[0].reset();
					window.showToast('success', response.message || 'Ajuste registrado correctamente.');

					if (window.kardexTable) {
						window.kardexTable.ajax.reload(null, false);
					}
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						showErrors(xhr.responseJSON.errors || {});
					} else {
						window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'Ocurrió un error inesperado.');
					}
				});
		});
	});
})(jQuery);
