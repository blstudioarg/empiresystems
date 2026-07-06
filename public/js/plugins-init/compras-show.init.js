(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function renderAcciones(estado, urls) {
		var $acciones = $('#compra-acciones');
		var html = '';

		if (estado === 'borrador') {
			html += '<a href="' + urls.editUrl + '" class="btn btn-outline-primary">Editar</a>';
			html += '<button type="button" class="btn btn-primary" id="btn-confirmar-compra">Confirmar (repone stock)</button>';
		}

		if (estado === 'confirmada') {
			html += '<button type="button" class="btn btn-danger" id="btn-anular-compra">Anular (revierte stock)</button>';
		}

		$acciones.html(html);
	}

	function renderMovimientos(movimientos) {
		var $contenedor = $('#compra-movimientos');

		if (!movimientos || !movimientos.length) {
			$contenedor.empty();
			return;
		}

		var html = '<h6>Movimientos de stock generados</h6><ul>';
		$.each(movimientos, function (_, m) {
			html += '<li>' + escapeHtml(m.tipo.charAt(0).toUpperCase() + m.tipo.slice(1)) +
				' de ' + m.cantidad + ' — stock resultante ' + m.stock_resultante + '</li>';
		});
		html += '</ul>';

		$contenedor.html(html);
	}

	function actualizarEstado(response) {
		$('#compra-estado-badge').text(response.estado.charAt(0).toUpperCase() + response.estado.slice(1));

		renderAcciones(response.estado, {
			editUrl: response.edit_url,
			confirmarUrl: response.confirmar_url,
			anularUrl: response.anular_url,
		});

		window.compraEstadoState.confirmarUrl = response.confirmar_url;
		window.compraEstadoState.anularUrl = response.anular_url;
		window.compraEstadoState.editUrl = response.edit_url;

		renderMovimientos(response.movimientos);
	}

	function ejecutarAccion(url) {
		return $.ajax({
			url: url,
			method: 'POST',
			dataType: 'json',
			headers: { Accept: 'application/json' },
		})
			.done(function (response) {
				window.showToast('success', response.message || 'Operación realizada correctamente.');
				actualizarEstado(response);
			})
			.fail(function (xhr) {
				window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'Ocurrió un error inesperado.');
			});
	}

	$(function () {
		$(document).on('click', '#btn-confirmar-compra', function () {
			window.confirmDelete('¿Confirmar esta compra? Se generarán entradas de stock por cada línea con artículo gestionado.', function () {
				return ejecutarAccion(window.compraEstadoState.confirmarUrl);
			}, { confirmLabel: 'Confirmar', confirmClass: 'btn-primary', icon: 'invoice' });
		});

		$(document).on('click', '#btn-anular-compra', function () {
			window.confirmDelete('¿Anular esta compra? Se revertirá el stock generado al confirmarla.', function () {
				return ejecutarAccion(window.compraEstadoState.anularUrl);
			});
		});

		$(document).on('change', '#compra-estado-b2b', function () {
			var $select = $(this);

			$.ajax({
				url: window.compraEstadoState.estadoB2bUrl,
				method: 'PATCH',
				dataType: 'json',
				headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
				data: { estado_b2b: $select.val() },
			})
				.done(function (response) {
					window.showToast('success', response.message);
				})
				.fail(function (xhr) {
					window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar el estado.');
				});
		});
	});
})(jQuery);
