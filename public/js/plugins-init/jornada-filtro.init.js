(function ($) {
	'use strict';

	$(function () {
		var $form = $('#jornada-filtro-form');

		if (!$form.length) {
			return;
		}

		var $resultado = $('#jornada-resultado');

		function aplicarFiltro() {
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
					window.showToast('danger', 'No se pudo cargar el informe de jornada.');
				})
				.always(function () {
					$resultado.css('opacity', 1);
				});
		}

		window.recargarJornada = aplicarFiltro;

		$form.on('submit', function (event) {
			event.preventDefault();
			aplicarFiltro();
		});

		$form.find('#miembro_id').on('change', function () {
			aplicarFiltro();
		});
	});

	// Delegado sobre document: tanto "Corregir" como "Exportar PDF" se re-renderizan en cada AJAX
	// de #jornada-resultado, así que un binding directo sobre los nodos se perdería al reemplazar
	// el HTML.
	$(document).on('click', '.btn-ver-pdf-jornada', function () {
		var $modal = $('#jornadaPdfModal');

		$('#jornadaPdfFrame').attr('src', $(this).data('pdf-url'));
		bootstrap.Modal.getOrCreateInstance($modal[0]).show();
	});

	$('#jornadaPdfModal').on('hidden.bs.modal', function () {
		$('#jornadaPdfFrame').attr('src', '');
	});

	$(document).on('click', '.btn-corregir-fichaje', function () {
		var $modal = $('#corregirFichajeModal');
		var $form = $('#corregirFichajeForm');

		$form.attr('action', $(this).data('url'));
		$('#corregir-tipo').val($(this).data('tipo'));
		$('#corregir-ocurrido-at').val($(this).data('ocurrido-at'));
		$('#corregir-motivo').val('');

		bootstrap.Modal.getOrCreateInstance($modal[0]).show();
	});

	$(document).on('submit', '#corregirFichajeForm', function (event) {
		event.preventDefault();

		var $form = $(this);
		var $submitBtn = $form.find('button[type="submit"]');
		var $modal = $('#corregirFichajeModal');

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
				bootstrap.Modal.getInstance($modal[0]).hide();
				window.showToast('success', response.message || 'Corrección registrada correctamente.');

				if (typeof window.recargarJornada === 'function') {
					window.recargarJornada();
				}
			})
			.fail(function (xhr) {
				window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo registrar la corrección.');
			});
	});
})(jQuery);
