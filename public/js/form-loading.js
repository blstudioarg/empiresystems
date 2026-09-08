/*
 * Estado de carga para formularios PLANOS (los que navegan al enviarse, sin AJAX): login,
 * registro y cualquier otro form que se marque con `data-loading-form`.
 *
 *   <form method="POST" action="..." data-loading-form>
 *       <button type="submit" data-loading-text="Ingresando...">Iniciar sesión</button>
 *
 * Es el caso `setButtonLoading(btn, true)` sin restaurar que describe
 * docs/04-front-guidelines.md ("Estado de carga en botones"): la página navega o recarga, así que
 * no hay nada que devolver a su estado. Se declara por atributo en el Blade y no con un handler
 * por vista para no repetir el mismo `$('#form').on('submit', ...)` en cada pantalla.
 *
 * No usa `withButtonLoading`: no hay promesa que esperar, el envío lo hace el navegador.
 */
(function ($) {
	'use strict';

	$(document).on('submit', 'form[data-loading-form]', function () {
		var $submit = $(this).find('button[type="submit"], input[type="submit"]').first();

		if ($submit.length) {
			window.setButtonLoading($submit, true);
		}
	});

	// Al volver con el botón "atrás", el navegador puede restaurar la página desde la bfcache tal
	// como quedó: con el botón deshabilitado y girando, sin forma de reenviar el formulario.
	$(window).on('pageshow', function (event) {
		if (!event.originalEvent || !event.originalEvent.persisted) {
			return;
		}

		$('form[data-loading-form]')
			.find('button[type="submit"], input[type="submit"]')
			.each(function () {
				window.setButtonLoading(this, false);
			});
	});
})(jQuery);
