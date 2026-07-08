(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#oportunidadModal');
		var $form = $('#oportunidad-form');
		var state = window.oportunidadFormState || {};

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

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$('#oportunidad_method').val('POST');
			$form.attr('action', state.storeUrl);
			$('#oportunidadModalLabel').text('Nueva oportunidad');
			$('#oportunidad-receptor-wrap').removeClass('d-none');
		}

		function fillForm(oportunidad) {
			clearErrors();
			$('#oportunidad_method').val('PUT');
			$form.attr('action', window.oportunidadesIndexUrl + '/' + oportunidad.id);
			$('#op_titulo').val(oportunidad.titulo);
			$('#op_importe').val(oportunidad.importe_estimado);
			$('#op_asignado').val(oportunidad.asignado_a || '');
			$('#op_notas').val(oportunidad.notas || '');
			// El receptor (lead/cliente) no se puede cambiar al editar (UpdateOportunidadRequest
			// no acepta esos campos) — se oculta para no sugerir que sí es editable.
			$('#oportunidad-receptor-wrap').addClass('d-none');
			$('#oportunidadModalLabel').text('Editar oportunidad');
		}

		function preseleccionarReceptor() {
			if (state.queryLeadId) {
				$('#op_lead_id').val(state.queryLeadId);
			}
			if (state.queryClienteId) {
				$('#op_cliente_id').val(state.queryClienteId);
			}
		}

		$(document).on('click', '.btn-add-oportunidad', function () {
			resetForm();
			preseleccionarReceptor();
		});

		$(document).on('click', '.btn-edit-oportunidad', function (event) {
			event.preventDefault();
			event.stopPropagation();

			var oportunidad = window.oportunidadesCache[$(this).data('id')];

			if (!oportunidad) {
				return;
			}

			fillForm(oportunidad);
			modal.show();
		});

		$('#op_lead_id').on('change', function () {
			if ($(this).val()) {
				$('#op_cliente_id').val('');
			}
		});
		$('#op_cliente_id').on('change', function () {
			if ($(this).val()) {
				$('#op_lead_id').val('');
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
					window.cargarPipelineOportunidades();
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						showErrors((xhr.responseJSON && xhr.responseJSON.errors) || {});
					} else {
						window.showToast('danger', 'Ocurrió un error inesperado. Inténtalo de nuevo.');
					}
				});
		});

		// Auto-apertura vía query string: llegar desde "+ Nueva oportunidad" de un lead/cliente,
		// o desde "Editar" en la ficha de la oportunidad — ambos casos evitan una página aparte.
		if (state.queryEditar) {
			window.cargarPipelineOportunidades().done(function () {
				var oportunidad = window.oportunidadesCache[state.queryEditar];

				if (oportunidad) {
					fillForm(oportunidad);
					modal.show();
				}
			});
		} else if (state.queryLeadId || state.queryClienteId) {
			resetForm();
			preseleccionarReceptor();
			modal.show();
		}
	});
})(jQuery);
