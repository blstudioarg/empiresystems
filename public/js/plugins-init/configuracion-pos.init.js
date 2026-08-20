/**
 * Configuración → POS (feature 038): flags del módulo de hostelería.
 *
 * El CRUD de zonas y mesas vive en POS → Sala (plano arrastrable, feature 039), no aquí.
 */
(function ($) {
	'use strict';

	var state = window.posConfigState || {};

	function csrf() {
		return $('meta[name="csrf-token"]').attr('content');
	}

	function initFormulario() {
		var $form = $('#pos-config-form');

		if (!$form.length) { return; }

		var $maestro = $('#pos_hosteleria_activo');

		function reflejarDependientes() {
			$('.pos-config-dependiente').toggleClass('apagado', !$maestro.is(':checked'));
		}

		$maestro.on('change', reflejarDependientes);
		reflejarDependientes();

		$form.on('submit', function (e) {
			e.preventDefault();

			var $btn = $('#pos-config-guardar');

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: state.updateUrl,
					method: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json' },
					data: {
						_token: csrf(),
						_method: 'PUT',
						hosteleria_activo: $maestro.is(':checked') ? 1 : 0,
						opciones_activo: $('#pos_opciones_activo').is(':checked') ? 1 : 0,
						cobro_dividido_activo: $('#pos_cobro_dividido_activo').is(':checked') ? 1 : 0,
						suplemento_zona_activo: $('#pos_suplemento_zona_activo').is(':checked') ? 1 : 0,
						mesa_olvidada_min: $('#pos_mesa_olvidada_min').val(),
					},
				});
			})
				.done(function (res) {
					window.showToast('success', res.message || 'Configuración guardada.');
				})
				.fail(function (xhr) {
					// El 422 más habitual aquí es "hay N cuentas abiertas" (FR-006): se muestra
					// tal cual porque el mensaje del servidor ya dice cuántas son.
					var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo guardar la configuración.';
					window.showToast('error', msg);
				});
		});
	}

	$(function () {
		initFormulario();
	});
})(jQuery);
