(function ($) {
	'use strict';

	$(function () {
		var $form = $('#mi-jornada-filtro-form');

		if (!$form.length) {
			return;
		}

		var $resultado = $('#mi-jornada-resultado');

		$form.on('submit', function (event) {
			event.preventDefault();

			$resultado.css('opacity', 0.5);

			$.ajax({
				url: $form.attr('action'),
				method: 'GET',
				data: $form.serialize(),
				headers: { Accept: 'application/json' },
				dataType: 'json',
			})
				.done(function (respuesta) {
					$resultado.html(respuesta.html);
				})
				.fail(function () {
					window.showToast('danger', 'No se pudo cargar tu jornada.');
				})
				.always(function () {
					$resultado.css('opacity', 1);
				});
		});
	});

	// Delegado sobre document: el botón "Exportar PDF" se re-renderiza en cada AJAX de
	// #mi-jornada-resultado, así que un binding directo sobre el nodo se perdería al reemplazar
	// el HTML.
	$(document).on('click', '.btn-ver-pdf-mi-jornada', function () {
		var $modal = $('#miJornadaPdfModal');

		$('#miJornadaPdfFrame').attr('src', $(this).data('pdf-url'));
		bootstrap.Modal.getOrCreateInstance($modal[0]).show();
	});

	$('#miJornadaPdfModal').on('hidden.bs.modal', function () {
		$('#miJornadaPdfFrame').attr('src', '');
	});
})(jQuery);
