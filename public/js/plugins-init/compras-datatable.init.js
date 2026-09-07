(function ($) {
	'use strict';

	var estadoB2bLabels = {
		recibida: 'Recibida',
		aceptada: 'Aceptada',
		rechazada: 'Rechazada',
		pagada: 'Pagada',
	};

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	/**
	 * Columna "Acciones": un único dropdown con todo lo que se puede hacer sobre la compra
	 * (docs/04-front-guidelines.md, "Columna Acciones de los listados"). Qué items aparecen lo
	 * decide el backend emitiendo o no cada `*_url` según el estado/origen de la fila, para no
	 * duplicar aquí las reglas que el controller vuelve a aplicar al ejecutar la acción.
	 */
	function renderAcciones(data, type, row) {
		var items = ['<li><a class="dropdown-item" href="' + row.show_url + '">Ver</a></li>'];

		if (row.edit_url) {
			items.push('<li><a class="dropdown-item" href="' + row.edit_url + '">Editar</a></li>');
		}

		if (row.confirmar_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-confirmar-compra"' +
						' data-confirmar-url="' + row.confirmar_url + '"' +
					'>Confirmar (repone stock)</button>' +
				'</li>'
			);
		}

		if (row.anular_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-anular-compra"' +
						' data-anular-url="' + row.anular_url + '"' +
					'>Anular (revierte stock)</button>' +
				'</li>'
			);
		}

		if (row.documento_descargar_url) {
			items.push('<li><a class="dropdown-item" href="' + row.documento_descargar_url + '">Descargar documento original</a></li>');
		}

		if (row.facturae_descargar_url) {
			items.push('<li><a class="dropdown-item" href="' + row.facturae_descargar_url + '">Descargar XML Facturae</a></li>');
		}

		// Estado B2B: solo en compras recibidas por Facturae. Un item por estado destino (el
		// actual se omite) en vez de un <select> dentro del dropdown, que obligaría a un segundo
		// clic para "aplicar" y no encaja con el patrón de items del dropdown de acciones.
		if (row.estado_b2b_url) {
			items.push('<li><hr class="dropdown-divider"></li>');
			items.push('<li><h6 class="dropdown-header">Estado B2B</h6></li>');

			$.each(estadoB2bLabels, function (valor, etiqueta) {
				if (row.estado_b2b === valor) {
					return;
				}

				items.push(
					'<li>' +
						'<button type="button" class="dropdown-item btn-estado-b2b-compra"' +
							' data-estado-b2b-url="' + row.estado_b2b_url + '"' +
							' data-estado-b2b="' + valor + '"' +
						'>Marcar como ' + escapeHtml(etiqueta.toLowerCase()) + '</button>' +
					'</li>'
				);
			});
		}

		if (row.delete_url) {
			items.push('<li><hr class="dropdown-divider"></li>');
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item text-danger btn-eliminar-compra"' +
						' data-delete-url="' + row.delete_url + '"' +
					'>Eliminar</button>' +
				'</li>'
			);
		}

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					items.join('') +
				'</ul>' +
			'</div>'
		);
	}

	function peticion(url, metodo, datos) {
		return $.ajax({
			url: url,
			method: metodo,
			dataType: 'json',
			data: datos,
			headers: {
				Accept: 'application/json',
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
			},
		});
	}

	function errorDe(xhr) {
		return (xhr.responseJSON && xhr.responseJSON.message) || 'Ocurrió un error inesperado.';
	}

	window.initComprasDataTable = function () {
		var $table = $('#compras-table');

		if (!$table.length) {
			return null;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: window.location.pathname,
				// El filtro se pasa como query param vía ajax.data (DataTables no admite una
				// función en ajax.url: jQuery la castearía a string y pediría una URL inválida).
				data: function (d) {
					var estado = $('#filtro-estado-b2b').val();
					if (estado) {
						d.estado_b2b = estado;
					}
				},
				dataSrc: function (json) {
					if (json.totales) {
						$('[data-metric="total"]').text(json.totales.total);
						$('[data-metric="confirmadas"]').text(json.totales.confirmadas);
						$('[data-metric="importe_total"]').text(json.totales.importe_total);
					}

					return json.data;
				},
			},
			columns: [
				{ data: 'proveedor' },
				{ data: 'numero_documento' },
				{ data: 'fecha' },
				{ data: 'estado' },
				{ data: null, orderable: false, render: function (data, type, row) {
					return row.estado_b2b ? (estadoB2bLabels[row.estado_b2b] || row.estado_b2b) : '-';
				} },
				{ data: 'total' },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron compras',
				emptyTable: 'Todavía no hay compras',
				processing: 'Cargando...',
				paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
			},
		});

		$('#filtro-estado-b2b').on('change', function () {
			table.ajax.reload();
		});

		// Recarga sin resetear la paginación: la acción cambia el estado de una fila concreta,
		// no hay que devolver al usuario a la primera página de la tabla.
		function recargar() {
			table.ajax.reload(null, false);
		}

		// Handlers delegados en el documento: el dropdown se re-renderiza entero en cada draw.
		$(document).on('click', '#compras-table .btn-confirmar-compra', function () {
			var url = $(this).data('confirmar-url');

			window.confirmDelete('¿Confirmar esta compra? Se generarán entradas de stock por cada línea con artículo gestionado.', function () {
				return peticion(url, 'POST')
					.done(function (response) {
						window.showToast('success', response.message || 'Compra confirmada correctamente.');
						recargar();
					})
					.fail(function (xhr) {
						window.showToast('error', errorDe(xhr));
					});
			}, { confirmLabel: 'Confirmar', confirmClass: 'btn-primary', icon: 'invoice' });
		});

		$(document).on('click', '#compras-table .btn-anular-compra', function () {
			var url = $(this).data('anular-url');

			window.confirmDelete('¿Anular esta compra? Se revertirá el stock generado al confirmarla.', function () {
				return peticion(url, 'POST')
					.done(function (response) {
						window.showToast('success', response.message || 'Compra anulada correctamente.');
						recargar();
					})
					.fail(function (xhr) {
						window.showToast('error', errorDe(xhr));
					});
			}, { confirmLabel: 'Anular' });
		});

		$(document).on('click', '#compras-table .btn-eliminar-compra', function () {
			var url = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar esta compra? Solo se pueden eliminar compras en borrador.', function () {
				return peticion(url, 'DELETE')
					.done(function (response) {
						window.showToast('success', response.message || 'Compra eliminada correctamente.');
						recargar();
					})
					.fail(function (xhr) {
						window.showToast('error', errorDe(xhr));
					});
			});
		});

		// Cambiar el estado B2B no es destructivo ni irreversible (se puede volver a cambiar en
		// cualquier momento), así que va directo, sin modal de confirmación.
		$(document).on('click', '#compras-table .btn-estado-b2b-compra', function () {
			var $boton = $(this);

			peticion($boton.data('estado-b2b-url'), 'PATCH', { estado_b2b: $boton.data('estado-b2b') })
				.done(function (response) {
					window.showToast('success', response.message || 'Estado actualizado correctamente.');
					recargar();
				})
				.fail(function (xhr) {
					window.showToast('error', errorDe(xhr));
				});
		});

		return table;
	};

	$(function () {
		window.initComprasDataTable();
	});
})(jQuery);
