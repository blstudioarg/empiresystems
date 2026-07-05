(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#cuentaBancariaModal');
		var $form = $('#cuenta-bancaria-form');
		var state = window.cuentaBancariaState || {};

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

		function csrf() {
			return $('meta[name="csrf-token"]').attr('content') || $form.find('input[name="_token"]').val();
		}

		function refreshListado() {
			var $table = $('#cuentas-bancarias-table');

			if ($.fn.DataTable.isDataTable($table)) {
				$table.DataTable().ajax.reload(null, false);
			}
		}

		function setBanco(bancoId) {
			var instance = window.BancoSelect && window.BancoSelect.get('cuenta_banco_id');

			if (instance) {
				instance.setValue(bancoId || '');
			} else {
				$form.find('#cuenta_banco_id').val(bancoId || '');
			}
		}

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$form.find('#cuenta_method').val('POST');
			$form.attr('action', state.storeUrl);
			setBanco('');
			$('#cuentaBancariaModalLabel').text('Añadir cuenta bancaria');
		}

		function fillForm(data) {
			clearErrors();
			$form.find('#cuenta_method').val('PUT');
			$form.attr('action', data.updateUrl);
			setBanco(data.bancoId);
			$form.find('#cuenta_alias').val(data.alias);
			$form.find('#cuenta_iban').val(data.iban);
			$form.find('#cuenta_titular').val(data.titular);
			$('#cuentaBancariaModalLabel').text('Editar cuenta bancaria');
		}

		$(document).on('click', '.btn-add-cuenta', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-cuenta', function () {
			var $btn = $(this);

			fillForm({
				updateUrl: $btn.data('update-url'),
				bancoId: $btn.data('banco-id'),
				alias: $btn.data('alias'),
				iban: $btn.data('iban'),
				titular: $btn.data('titular'),
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

		$(document).on('click', '.btn-desactivar-cuenta', function () {
			var url = $(this).data('delete-url');

			window.confirmDelete('¿Desactivar esta cuenta bancaria? Dejará de ofrecerse al crear facturas, pero podrás reactivarla.', function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: csrf() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Cuenta bancaria desactivada.');
						refreshListado();
					})
					.fail(function () {
						window.showToast('danger', 'No se pudo desactivar la cuenta. Inténtalo de nuevo.');
					});
			}, {
				confirmLabel: 'Desactivar',
			});
		});

		$(document).on('click', '.btn-reactivar-cuenta', function () {
			var $btn = $(this);
			var url = $btn.data('restore-url');

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _token: csrf() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (response) {
					window.showToast('success', response.message || 'Cuenta bancaria reactivada.');
					refreshListado();
				})
				.fail(function () {
					window.showToast('danger', 'No se pudo reactivar la cuenta. Inténtalo de nuevo.');
				});
		});
	});
})(jQuery);
