(function ($) {
	'use strict';

	/**
	 * Reemplazo del `confirm()` nativo del navegador para confirmaciones de eliminación.
	 * Uso: window.confirmDelete('¿Eliminar este cliente?', function () { ...ajax de borrado... });
	 * Ver docs/04-front-guidelines.md ("Confirmación de eliminación").
	 */
	window.confirmDelete = function (message, onConfirm) {
		var $modal = $('#confirmDeleteModal');

		if (!$modal.length) {
			return;
		}

		$('#confirmDeleteMessage').text(message);

		$('#confirmDeleteButton')
			.off('click')
			.on('click', function () {
				bootstrap.Modal.getOrCreateInstance($modal[0]).hide();
				onConfirm();
			});

		bootstrap.Modal.getOrCreateInstance($modal[0]).show();
	};
})(jQuery);
