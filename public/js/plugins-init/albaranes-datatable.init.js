(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	function renderTotal(value) {
		return Number(value).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
	}

	function updateAlbaranesCards(totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="entregados"]').text(totales.entregados);
		$('[data-metric="pendientes_facturar"]').text(totales.pendientes_facturar);
	}

	var estadoBadges = {
		borrador: 'badge-secondary',
		entregado: 'badge-primary',
		facturado: 'badge-success',
		anulado: 'badge-danger',
	};

	function renderEstado(data, type, row) {
		var badge = estadoBadges[row.estado] || 'badge-secondary';

		return '<span class="badge light ' + badge + '">' + escapeHtml(row.estado_label) + '</span>';
	}

	function renderSeleccion(data, type, row) {
		if (!row.es_convertible) {
			return '';
		}

		return '<input type="checkbox" class="form-check-input albaran-checkbox" data-id="' + row.id
			+ '" data-cliente-id="' + escapeAttr(row.cliente_id) + '">';
	}

	function renderAcciones(data, type, row) {
		var items = [
			'<li><a class="dropdown-item" href="' + escapeAttr(row.show_url) + '">Ver</a></li>',
		];

		if (row.es_editable) {
			items.push('<li><a class="dropdown-item" href="' + escapeAttr(row.edit_url) + '">Editar</a></li>');
		}

		if (row.es_editable || row.es_anulable) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-cambiar-estado-albaran"' +
						' data-numero="' + escapeAttr(row.numero) + '"' +
						' data-estado="' + escapeAttr(row.estado) + '"' +
						' data-estado-url="' + escapeAttr(row.estado_url) + '"' +
					'>Cambiar estado</button>' +
				'</li>'
			);
		}

		if (row.es_eliminable) {
			items.push('<li><hr class="dropdown-divider"></li>');
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item text-danger btn-delete-albaran"' +
						' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
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

	$(function () {
		var $table = $('#albaranes-table');

		if (!$table.length) {
			return;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: window.albaranesIndexUrl,
				dataSrc: function (json) {
					updateAlbaranesCards(json.totales);
					return json.data;
				},
			},
			columns: [
				{ data: null, orderable: false, render: renderSeleccion },
				{ data: 'numero', render: escapeHtml },
				{ data: 'receptor', render: escapeHtml },
				{ data: null, orderable: false, render: renderEstado },
				{ data: 'fecha_entrega', render: escapeHtml },
				{ data: 'total', render: renderTotal },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron albaranes',
				emptyTable: 'Todavía no hay albaranes en esta cuenta',
				processing: 'Cargando...',
				paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
			},
		});

		function csrfHeaders() {
			return { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') };
		}

		// Selección múltiple para "Convertir a factura": habilitado solo si hay ≥1 marcado y
		// todos comparten el mismo cliente (mismo criterio que valida el backend, FR-009).
		function actualizarBotonConvertir() {
			var $marcados = $table.find('.albaran-checkbox:checked');
			var $btn = $('#btn-convertir-albaranes');

			if (!$marcados.length) {
				$btn.prop('disabled', true);
				return;
			}

			var clientes = $marcados.map(function () { return $(this).data('cliente-id'); }).get();
			var mismoCliente = clientes.every(function (id) { return id === clientes[0]; });

			$btn.prop('disabled', !mismoCliente);
			$btn.attr('title', mismoCliente ? '' : 'Los albaranes seleccionados deben ser del mismo cliente');
		}

		$table.on('change', '.albaran-checkbox', actualizarBotonConvertir);

		$('#btn-convertir-albaranes').on('click', function () {
			var $btn = $(this);
			var ids = $table.find('.albaran-checkbox:checked').map(function () { return $(this).data('id'); }).get();

			if (!ids.length) {
				return;
			}

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: window.albaranesConvertirUrl,
					method: 'POST',
					dataType: 'json',
					headers: $.extend({ Accept: 'application/json' }, csrfHeaders()),
					data: { albaran_ids: ids },
				});
			})
				.done(function (response) {
					window.showToast('success', response.message || 'Albaranes convertidos en factura borrador.');
					window.location.href = response.redirect_url;
				})
				.fail(function (xhr) {
					window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudieron convertir los albaranes seleccionados.');
				});
		});

		// Cambiar estado: un modal, contenido armado según el estado actual de la fila.
		$table.on('click', '.btn-cambiar-estado-albaran', function () {
			var $btn = $(this);
			var estado = $btn.data('estado');
			var estadoUrl = $btn.data('estado-url');

			$('#estadoAlbaranNumero').text($btn.data('numero'));
			$('#estadoAlbaranBody').data('estado-url', estadoUrl);

			$('#estadoBtnEntregado, #estadoBtnAnulado').addClass('d-none');

			if (estado === 'borrador') {
				$('#estadoBtnEntregado').removeClass('d-none');
			} else if (estado === 'entregado') {
				$('#estadoBtnAnulado').removeClass('d-none');
			}

			bootstrap.Modal.getOrCreateInstance(document.getElementById('estadoAlbaranModal')).show();
		});

		function cambiarEstado($boton, estado) {
			var estadoUrl = $('#estadoAlbaranBody').data('estado-url');

			window.withButtonLoading($boton, function () {
				return $.ajax({
					url: estadoUrl,
					method: 'PUT',
					dataType: 'json',
					headers: $.extend({ Accept: 'application/json' }, csrfHeaders()),
					data: { estado: estado },
				});
			})
				.done(function (response) {
					window.showToast('success', response.message || 'Estado actualizado correctamente.');
					bootstrap.Modal.getInstance(document.getElementById('estadoAlbaranModal')).hide();
					table.ajax.reload(null, false);
				})
				.fail(function (xhr) {
					window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar el estado.');
				});
		}

		$('#estadoBtnEntregado').on('click', function () { cambiarEstado($(this), 'entregado'); });
		$('#estadoBtnAnulado').on('click', function () { cambiarEstado($(this), 'anulado'); });

		// Eliminar
		$table.on('click', '.btn-delete-albaran', function () {
			var deleteUrl = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar este albarán?', function () {
				return $.ajax({
					url: deleteUrl,
					method: 'POST',
					data: { _method: 'DELETE' },
					dataType: 'json',
					headers: $.extend({ Accept: 'application/json' }, csrfHeaders()),
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Albarán eliminado correctamente.');
						table.ajax.reload(null, false);
					})
					.fail(function (xhr) {
						window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar el albarán.');
					});
			});
		});
	});
})(jQuery);
