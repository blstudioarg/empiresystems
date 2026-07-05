(function ($) {
	'use strict';

	/**
	 * Reemplazo del `confirm()` nativo del navegador para confirmar acciones irreversibles
	 * (borrado y otras, p. ej. emitir factura). Por defecto se comporta como confirmación de
	 * borrado (ícono papelera, botón rojo "Eliminar"); pasar `opciones` para adaptarlo a otra
	 * acción sin duplicar el modal.
	 *
	 * `onConfirm` DEBE devolver el jqXHR/Promise de la petición (p. ej. `return $.ajax({...})`)
	 * — el modal usa `window.withButtonLoading` (ver button-loading.js) sobre el propio botón de
	 * confirmación y permanece abierto con spinner hasta que la petición termina, recién ahí se
	 * cierra. Si `onConfirm` no devuelve nada, el modal se cierra igual (sin loading visible).
	 *
	 * Uso: window.confirmDelete('¿Eliminar este cliente?', function () {
	 *     return $.ajax({ ...ajax de borrado... });
	 * });
	 * Uso con opciones: window.confirmDelete('¿Emitir esta factura?', onConfirm, {
	 *     confirmLabel: 'Emitir', confirmClass: 'btn-primary', icon: 'invoice',
	 * });
	 * Ver docs/04-front-guidelines.md ("Confirmación de eliminación" y "Estado de carga en botones").
	 */
	window.confirmDelete = function (message, onConfirm, opciones) {
		var $modal = $('#confirmDeleteModal');

		if (!$modal.length) {
			return;
		}

		opciones = opciones || {};

		$('#confirmDeleteMessage').text(message);

		var $boton = $('#confirmDeleteButton')
			.text(opciones.confirmLabel || 'Eliminar')
			.removeClass('btn-danger btn-primary')
			.addClass(opciones.confirmClass || 'btn-danger');

		var icono = document.getElementById('confirmDeleteIcon');
		if (icono) {
			icono.setAttribute('src', icono.dataset.baseSrc + '/' + (opciones.icon || 'wired-outline-185-trash-bin-hover-empty') + '.json');
		}

		var modalInstance = bootstrap.Modal.getOrCreateInstance($modal[0]);

		$boton
			.off('click')
			.on('click', function () {
				window.withButtonLoading($boton, function () {
					return onConfirm();
				}).always(function () {
					modalInstance.hide();
				});
			});

		modalInstance.show();
	};
})(jQuery);
