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

	function updatePresupuestosCards(totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="pendientes"]').text(totales.pendientes);
		$('[data-metric="importe_aceptado"]').text(renderTotal(totales.importe_aceptado));
	}

	var estadoBadges = {
		borrador: 'badge-secondary',
		enviado: 'badge-primary',
		aceptado: 'badge-success',
		rechazado: 'badge-danger',
		caducado: 'badge-dark',
		facturado: 'badge-warning',
	};

	function renderEstado(data, type, row) {
		var badge = estadoBadges[row.estado] || 'badge-secondary';

		return '<span class="badge light ' + badge + '">' + escapeHtml(row.estado_label) + '</span>';
	}

	function renderAcciones(data, type, row) {
		var items = [
			'<li>' +
				'<button type="button" class="dropdown-item btn-ver-presupuesto" data-pdf-url="' + escapeAttr(row.pdf_url) + '">Ver</button>' +
			'</li>',
		];

		if (row.es_editable) {
			items.push('<li><a class="dropdown-item" href="' + escapeAttr(row.edit_url) + '">Editar</a></li>');
		}

		if (row.estado !== 'facturado' && row.estado !== 'rechazado' && row.estado !== 'caducado') {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-cambiar-estado-presupuesto"' +
						' data-numero="' + escapeAttr(row.numero) + '"' +
						' data-estado="' + escapeAttr(row.estado) + '"' +
						' data-estado-url="' + escapeAttr(row.estado_url) + '"' +
						' data-convertir-url="' + escapeAttr(row.convertir_url) + '"' +
					'>Cambiar estado</button>' +
				'</li>'
			);
		}

		items.push(
			'<li>' +
				'<button type="button" class="dropdown-item btn-enviar-presupuesto"' +
					' data-enviar-url="' + escapeAttr(row.enviar_url) + '"' +
					' data-receptor-email="' + escapeAttr(row.receptor_email || '') + '"' +
				'>Enviar por email</button>' +
			'</li>'
		);

		if (row.es_eliminable) {
			items.push('<li><hr class="dropdown-divider"></li>');
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item text-danger btn-delete-presupuesto"' +
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
		var $table = $('#presupuestos-table');

		if (!$table.length) {
			return;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: window.presupuestosIndexUrl,
				dataSrc: function (json) {
					window.presupuestosEmailConfigurado = json.email_configurado;
					updatePresupuestosCards(json.totales);
					return json.data;
				},
			},
			columns: [
				{ data: 'numero', render: escapeHtml },
				{ data: 'receptor', render: escapeHtml },
				{ data: null, orderable: false, render: renderEstado },
				{ data: 'fecha_emision', render: escapeHtml },
				{ data: 'total', render: renderTotal },
				{ data: null, orderable: false, render: renderAcciones },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron presupuestos',
				emptyTable: 'Todavía no hay presupuestos en esta cuenta',
				processing: 'Cargando...',
				paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
			},
		});

		function csrfHeaders() {
			return { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') };
		}

		// Ver: PDF embebido en modal, igual que facturas.
		$table.on('click', '.btn-ver-presupuesto', function () {
			var pdfUrl = $(this).data('pdf-url');

			$('#presupuestoPdfFrame').attr('src', pdfUrl);
			bootstrap.Modal.getOrCreateInstance(document.getElementById('presupuestoPdfModal')).show();
		});

		$('#presupuestoPdfModal').on('hidden.bs.modal', function () {
			$('#presupuestoPdfFrame').attr('src', '');
		});

		// Enviar por email: mismo patrón que facturas (modal con destinatario prefilled).
		$table.on('click', '.btn-enviar-presupuesto', function () {
			if (window.presupuestosEmailConfigurado === false) {
				window.showToast('danger', 'Configura primero tu correo en Configuración → Email.');
				return;
			}

			var enviarUrl = $(this).data('enviar-url');
			var receptorEmail = $(this).data('receptor-email');
			var $form = $('#enviarPresupuestoForm');

			$form.attr('action', enviarUrl);
			$form[0].reset();
			$('#enviarPresupuestoDestinatario').val(receptorEmail || '');
			$form.find('[data-error-for]').text('');

			bootstrap.Modal.getOrCreateInstance(document.getElementById('enviarPresupuestoModal')).show();
		});

		$('#enviarPresupuestoForm').on('submit', function (event) {
			event.preventDefault();

			var $form = $(this);
			var $submit = $('#enviarPresupuestoSubmit');

			window.withButtonLoading($submit, function () {
				return $.ajax({
					url: $form.attr('action'),
					method: 'POST',
					dataType: 'json',
					headers: $.extend({ Accept: 'application/json' }, csrfHeaders()),
					data: $form.serialize(),
				});
			})
				.done(function (response) {
					window.showToast('success', response.message || 'Presupuesto enviado correctamente.');
					bootstrap.Modal.getInstance(document.getElementById('enviarPresupuestoModal')).hide();
				})
				.fail(function (xhr) {
					if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.destinatario) {
						$form.find('[data-error-for="destinatario"]').text(xhr.responseJSON.errors.destinatario[0]);
						return;
					}

					window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo enviar el presupuesto.');
				});
		});

		// Cambiar estado: un modal, contenido armado según el estado actual de la fila.
		$table.on('click', '.btn-cambiar-estado-presupuesto', function () {
			var $btn = $(this);
			var estado = $btn.data('estado');
			var estadoUrl = $btn.data('estado-url');
			var convertirUrl = $btn.data('convertir-url');

			$('#estadoPresupuestoNumero').text($btn.data('numero'));
			$('#estadoPresupuestoBody').data('estado-url', estadoUrl);

			$('#estadoBtnEnviado, #estadoBtnAceptado, #estadoBtnRechazado, #estadoFormConvertir').addClass('d-none');

			if (estado === 'borrador') {
				$('#estadoBtnEnviado').removeClass('d-none');
			} else if (estado === 'enviado') {
				$('#estadoBtnAceptado, #estadoBtnRechazado').removeClass('d-none');
			} else if (estado === 'aceptado') {
				$('#estadoFormConvertir').attr('action', convertirUrl).removeClass('d-none');
			}

			bootstrap.Modal.getOrCreateInstance(document.getElementById('estadoPresupuestoModal')).show();
		});

		function cambiarEstado($boton, estado) {
			var estadoUrl = $('#estadoPresupuestoBody').data('estado-url');

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
					bootstrap.Modal.getInstance(document.getElementById('estadoPresupuestoModal')).hide();
					table.ajax.reload(null, false);
				})
				.fail(function (xhr) {
					window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar el estado.');
				});
		}

		$('#estadoBtnEnviado').on('click', function () { cambiarEstado($(this), 'enviado'); });
		$('#estadoBtnAceptado').on('click', function () { cambiarEstado($(this), 'aceptado'); });
		$('#estadoBtnRechazado').on('click', function () { cambiarEstado($(this), 'rechazado'); });

		// Convertir a factura: form nativo (no AJAX) — navega a facturas.edit, un recurso
		// distinto, igual que "Emitir" no aplica acá porque sí cambia de pantalla de verdad.
		$('#estadoFormConvertir').on('submit', function () {
			window.setButtonLoading($(this).find('button[type="submit"]'), true);
		});

		// Eliminar
		$table.on('click', '.btn-delete-presupuesto', function () {
			var deleteUrl = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar este presupuesto?', function () {
				return $.ajax({
					url: deleteUrl,
					method: 'POST',
					data: { _method: 'DELETE' },
					dataType: 'json',
					headers: $.extend({ Accept: 'application/json' }, csrfHeaders()),
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Presupuesto eliminado correctamente.');
						table.ajax.reload(null, false);
					})
					.fail(function (xhr) {
						window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar el presupuesto.');
					});
			});
		});
	});
})(jQuery);
