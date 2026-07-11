(function ($) {
	'use strict';

	var state = window.albaranShowState || {};

	function csrfHeaders() {
		return { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') };
	}

	function cambiarEstado(estado) {
		return $.ajax({
			url: state.estadoUrl,
			method: 'PUT',
			dataType: 'json',
			headers: $.extend({ Accept: 'application/json' }, csrfHeaders()),
			data: { estado: estado },
		})
			.done(function () {
				window.location.reload();
			})
			.fail(function (xhr) {
				window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar el estado.');
			});
	}

	$(function () {
		$('#btn-entregar').on('click', function () {
			window.withButtonLoading($(this), function () { return cambiarEstado('entregado'); });
		});

		$('#btn-anular').on('click', function () {
			window.confirmDelete(
				'¿Anular este albarán? Se revertirá el movimiento de stock generado al entregarlo.',
				function () { return cambiarEstado('anulado'); },
				{ confirmLabel: 'Anular', confirmClass: 'btn-danger', icon: 'wired-outline-185-trash-bin-hover-empty' }
			);
		});
	});
})(jQuery);
