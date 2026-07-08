(function ($) {
	'use strict';

	$(function () {
		var state = window.oportunidadShowState || {};
		var csrf = $('meta[name="csrf-token"]').attr('content');

		if (!state.etapaUrl) {
			return;
		}

		function cambiarEtapa($boton, payload) {
			return window.withButtonLoading($boton, function () {
				return $.ajax({
					url: state.etapaUrl,
					method: 'PUT',
					data: $.extend({ _token: csrf }, payload),
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			});
		}

		function aplicarEstadoCerrado(response) {
			$('#oportunidad-etapa-badge').text(response.etapa_label);

			if (response.es_terminal) {
				$('#oportunidad-acciones').hide();
				$('#oportunidad-nuevo-presupuesto').toggle(response.etapa !== 'perdida');

				if (response.motivo_perdida) {
					$('#oportunidad-motivo-dt, #oportunidad-motivo-valor').show();
					$('#oportunidad-motivo-valor').text(response.motivo_perdida);
				}
			} else {
				$('#oportunidad-btn-negociacion-wrap').toggle(response.etapa === 'nueva');
			}
		}

		$('#btn-en-negociacion').on('click', function () {
			cambiarEtapa($(this), { etapa: 'en_negociacion' })
				.done(function (response) {
					aplicarEstadoCerrado(response);
					window.showToast('success', response.message || 'Etapa actualizada correctamente.');
				})
				.fail(function (xhr) {
					window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar la etapa.');
				});
		});

		$('#btn-ganar').on('click', function () {
			cambiarEtapa($(this), { etapa: 'ganada' })
				.done(function (response) {
					aplicarEstadoCerrado(response);
					window.showToast('success', response.message || 'Oportunidad marcada como ganada.');
				})
				.fail(function (xhr) {
					window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar la etapa.');
				});
		});

		$('#btn-perder').on('click', function () {
			var $motivo = $('#oportunidad-motivo-perdida-input');
			var motivo = $motivo.val().trim();

			if (!motivo) {
				$motivo.addClass('is-invalid').trigger('focus');
				window.showToast('danger', 'Indica el motivo de la pérdida.');
				return;
			}

			$motivo.removeClass('is-invalid');

			cambiarEtapa($(this), { etapa: 'perdida', motivo_perdida: motivo })
				.done(function (response) {
					aplicarEstadoCerrado(response);
					window.showToast('success', response.message || 'Oportunidad marcada como perdida.');
				})
				.fail(function (xhr) {
					window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar la etapa.');
				});
		});
	});
})(jQuery);
