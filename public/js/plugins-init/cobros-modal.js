/**
 * Módulo compartido del modal de cobros (feature 043, research D6). Extraído de
 * facturas-datatable.init.js a comportamiento constante: initFacturasDataTable() delega aquí en
 * vez de duplicar esta lógica. cobros-datatable.init.js lo usa también.
 *
 * window.initCobrosModal({ onCambio }) inicializa los handlers una única vez (aunque se llame
 * desde más de una vista/tabla) y devuelve utilidades para abrir el modal desde el botón
 * "Cobros" de cualquier DataTable con las mismas data-* que ya usaba facturas.
 *
 * onCambio(): callback invocado tras registrar o anular un cobro con éxito. Es el único punto de
 * variación entre pantallas: en Facturas recarga su propia tabla; en Cobros recarga la tabla y el
 * resumen para que no queden desincronizados.
 */
(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var modalidadRectificacionLabels = {
		diferencias: 'Por diferencias',
		sustitucion: 'Por sustitución',
	};

	var metodoLabels = {
		transferencia: 'Transferencia',
		tarjeta: 'Tarjeta',
		efectivo: 'Efectivo',
		domiciliacion: 'Domiciliación',
	};

	var inicializado = false;
	var cobrosTable = null;
	var onCambioActual = function () {};

	function csrfHeaders() {
		return { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') };
	}

	function renderCobroEstado(data, type, row) {
		return row.vigente
			? '<span class="badge light badge-success">Vigente</span>'
			: '<span class="badge light badge-dark">Anulado</span>';
	}

	function renderCobroAccion(data, type, row) {
		return row.vigente
			? '<button type="button" class="btn btn-link text-danger p-0 btn-anular-pago" data-anular-url="' + row.anular_url + '">Anular</button>'
			: '';
	}

	function initCobrosTable() {
		if (cobrosTable) {
			return cobrosTable;
		}

		cobrosTable = $('#cobros-table').DataTable({
			responsive: true,
			data: [],
			columns: [
				{ data: 'fecha', render: escapeHtml },
				{ data: 'metodo', render: function (data) { return escapeHtml(metodoLabels[data] || data); } },
				{ data: 'referencia', render: function (data) { return escapeHtml(data || '-'); } },
				{ data: 'importe', render: function (data) { return escapeHtml(data) + ' €'; }, className: 'text-end' },
				{ data: null, orderable: false, render: renderCobroEstado },
				{ data: null, orderable: false, render: renderCobroAccion },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'Sin cobros registrados',
				emptyTable: 'Sin cobros registrados',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		return cobrosTable;
	}

	function cargarCobros(cobrosUrl) {
		$('#cobrosModal').data('cobros-url', cobrosUrl);

		$.getJSON(cobrosUrl, function (response) {
			$('#cobroSaldoPendiente').text(response.saldo_pendiente);
			$('#cobroPagarRestante').data('saldo-pendiente', response.saldo_pendiente);
			// FR-018: el importe se propone por defecto como el saldo pendiente de la factura.
			$('#cobroImporte').val(response.saldo_pendiente);

			var $tabla = initCobrosTable();
			$tabla.clear().rows.add(response.data).draw();
		});
	}

	window.initCobrosModal = function (opciones) {
		opciones = opciones || {};
		onCambioActual = typeof opciones.onCambio === 'function' ? opciones.onCambio : function () {};

		if (inicializado) {
			// Ya hay handlers globales de document; solo actualizamos el callback de cambio.
			return;
		}

		inicializado = true;

		var $cobrosModal = $('#cobrosModal');

		$(document).on('click', '.btn-ver-cobros', function () {
			var $btn = $(this);
			var cobrosUrl = $btn.data('cobros-url');
			var pagoUrl = $btn.data('pago-url');
			var $form = $('#registrarCobroForm');

			$form.attr('action', pagoUrl || '');
			$form[0].reset();
			$('#cobroFecha').val(new Date().toISOString().slice(0, 10));
			$form.find('[data-error-for]').text('');
			$form.toggle(!!pagoUrl);

			var $contexto = $('#cobroContextoRectificada');

			if ($btn.data('es-rectificada')) {
				var modalidad = modalidadRectificacionLabels[$btn.data('modalidad')] || 'rectificada';
				$contexto
					.html(
						'Factura <strong>' + modalidad.toLowerCase() + '</strong>. El importe a cobrar es el ' +
						'efectivo (<strong>' + escapeHtml($btn.data('total-efectivo')) + ' €</strong>), no el ' +
						'total original (' + escapeHtml($btn.data('total-nominal')) + ' €). Los cobros se ' +
						'gestionan siempre desde esta factura original.'
					)
					.removeClass('d-none');
			} else {
				$contexto.addClass('d-none').empty();
			}

			cargarCobros(cobrosUrl);

			bootstrap.Modal.getOrCreateInstance($cobrosModal[0]).show();
		});

		$cobrosModal.on('shown.bs.modal', function () {
			if (cobrosTable) {
				cobrosTable.responsive.recalc();
			}
		});

		$(document).on('click', '#cobroPagarRestante', function () {
			$('#cobroImporte').val($(this).data('saldo-pendiente'));
		});

		$(document).on('submit', '#registrarCobroForm', function (e) {
			e.preventDefault();

			var $form = $(this);
			var $submit = $form.find('button[type="submit"]');
			$form.find('[data-error-for]').text('');

			window.withButtonLoading($submit, function () {
				return $.ajax({
					url: $form.attr('action'),
					type: 'POST',
					dataType: 'json',
					headers: $.extend({ Accept: 'application/json' }, csrfHeaders()),
					data: $form.serialize(),
				});
			})
				.done(function (response) {
					window.showToast('success', response.message);
					$form[0].reset();
					$('#cobroFecha').val(new Date().toISOString().slice(0, 10));
					cargarCobros($cobrosModal.data('cobros-url'));
					onCambioActual();
				})
				.fail(function (xhr) {
					var mensaje = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo registrar el cobro.';

					// 422 de PagoInvalidoException: se mapea al campo importe además del toast
					// (guía "Estado de carga en botones (AJAX)" / patrón de invalid-feedback).
					if (xhr.status === 422) {
						$form.find('[data-error-for="importe"]').text(mensaje);
					}

					window.showToast('error', mensaje);
				});
		});

		$cobrosModal.on('click', '.btn-anular-pago', function () {
			var anularUrl = $(this).data('anular-url');

			window.confirmDelete('¿Anular este cobro? El saldo pendiente de la factura se recalculará.', function () {
				return $.ajax({
					url: anularUrl,
					type: 'POST',
					dataType: 'json',
					headers: $.extend({ Accept: 'application/json' }, csrfHeaders()),
				})
					.done(function (response) {
						window.showToast('success', response.message);
						cargarCobros($cobrosModal.data('cobros-url'));
						onCambioActual();
					})
					.fail(function (xhr) {
						window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo anular el cobro.');
					});
			}, { confirmLabel: 'Anular', confirmClass: 'btn-danger' });
		});
	};
})(jQuery);
