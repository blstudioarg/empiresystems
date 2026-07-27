/**
 * Ojo mostrar/ocultar genérico para CUALQUIER input de password (markup: ver
 * docs/04-front-guidelines.md, sección "Input de contraseña: ojo mostrar/ocultar (obligatorio)").
 *
 * El handler equivalente en custom.js (`handleshowPass`) quedó hardcodeado a un único id
 * (`#dz-password`, copiado del template original) y solo sirve para el password del login. Este
 * usa delegación de eventos sobre `document`, así que funciona con cualquier cantidad de inputs de
 * password en la página, incluidos los inyectados dinámicamente después de cargar (filas de un
 * modal, formularios AJAX, etc.), sin necesitar un id único por input.
 */
(function ($) {
	'use strict';

	$(document).on('click', '.show-pass', function () {
		var $toggle = $(this);
		var $input = $toggle.closest('.position-relative').find('input').first();

		if (!$input.length) {
			return;
		}

		$toggle.toggleClass('active');
		$input.attr('type', $input.attr('type') === 'password' ? 'text' : 'password');
	});
})(jQuery);
