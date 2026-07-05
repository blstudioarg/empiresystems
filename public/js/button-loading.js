/*
 * Estado de carga estándar para cualquier botón que dispare un evento que puede tardar
 * (AJAX, fetch, envío de un form). Ver docs/04-front-guidelines.md ("Estado de carga en
 * botones") — todo botón nuevo que dispare una petición async debe usar esto.
 *
 * window.setButtonLoading(button, true|false)
 *   Deshabilita el botón y le agrega un spinner (o lo restaura). Si el botón tiene
 *   `data-loading-text="Enviando..."`, también cambia el texto mientras carga.
 *
 * window.withButtonLoading(button, requestFn)
 *   Envuelve una petición: activa el loading, llama a requestFn() (que debe devolver el
 *   jqXHR/Promise/fetch de la petición) y restaura el botón cuando termina (éxito o error).
 *   Devuelve la misma promesa, así que se puede encadenar .done()/.fail() o .then()/.catch()
 *   normalmente.
 *
 * Uso típico (reemplaza el patrón manual $btn.prop('disabled', true) / .always(...)):
 *   window.withButtonLoading($submitBtn, function () {
 *       return $.ajax({ url: ..., ... });
 *   })
 *       .done(function (response) { ... })
 *       .fail(function (xhr) { ... });
 */
(function ($) {
	'use strict';

	window.setButtonLoading = function (button, loading) {
		var $btn = $(button);

		if (loading) {
			if ($btn.data('loading-busy')) {
				return;
			}

			$btn.data('loading-busy', true);
			$btn.prop('disabled', true);

			var loadingText = $btn.data('loading-text');
			if (loadingText) {
				$btn.data('loading-original-text', $btn.text());
				$btn.text(loadingText);
			}

			$btn.prepend('<span class="spinner-border spinner-border-sm me-1 js-btn-loading-spinner" role="status" aria-hidden="true"></span>');
		} else {
			$btn.prop('disabled', false);
			$btn.removeData('loading-busy');
			$btn.find('.js-btn-loading-spinner').remove();

			var originalText = $btn.data('loading-original-text');
			if (originalText !== undefined) {
				$btn.text(originalText);
				$btn.removeData('loading-original-text');
			}
		}
	};

	window.withButtonLoading = function (button, requestFn) {
		var $btn = $(button);

		window.setButtonLoading($btn, true);

		return $.when(requestFn()).always(function () {
			window.setButtonLoading($btn, false);
		});
	};
})(jQuery);
