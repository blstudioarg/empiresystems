(function ($) {
	'use strict';

	/**
	 * Reemplazo del `confirm()` nativo del navegador para confirmar acciones irreversibles
	 * (borrado y otras, p. ej. emitir factura). Por defecto se comporta como confirmación de
	 * borrado (ícono papelera, botón rojo "Eliminar"); pasar `opciones` para adaptarlo a otra
	 * acción sin duplicar el modal.
	 * Uso: window.confirmDelete('¿Eliminar este cliente?', function () { ...ajax de borrado... });
	 * Uso con opciones: window.confirmDelete('¿Emitir esta factura?', onConfirm, {
	 *     confirmLabel: 'Emitir', confirmClass: 'btn-primary', icon: 'invoice',
	 * });
	 * Ver docs/04-front-guidelines.md ("Confirmación de eliminación").
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

		$boton
			.off('click')
			.on('click', function () {
				bootstrap.Modal.getOrCreateInstance($modal[0]).hide();
				onConfirm();
			});

		bootstrap.Modal.getOrCreateInstance($modal[0]).show();
	};
})(jQuery);
