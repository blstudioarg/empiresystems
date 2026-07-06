(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var estadoLabels = {
		borrador: 'Borrador',
		emitida: 'Emitida',
		pagada: 'Pagada',
		vencida: 'Vencida',
		anulada: 'Anulada',
		rectificada: 'Rectificada',
	};

	var estadoBadges = {
		borrador: 'badge-secondary',
		emitida: 'badge-primary',
		pagada: 'badge-success',
		vencida: 'badge-danger',
		anulada: 'badge-dark',
		rectificada: 'badge-warning',
	};

	window.updateFacturasCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="importe_total"]').text(totales.importe_total);
	};

	var modalidadRectificacionLabels = {
		diferencias: 'Por diferencias',
		sustitucion: 'Por sustitución',
	};

	function renderTotal(data, type, row) {
		// Para ordenar/buscar usamos el importe efectivo (el que realmente se cobra).
		if (type !== 'display') {
			return row.es_rectificada ? row.total_efectivo : row.total;
		}

		if (!row.es_rectificada) {
			return escapeHtml(row.total) + ' €';
		}

		var modalidad = modalidadRectificacionLabels[row.modalidad_rectificacion] || 'Rectificada';

		// Total nominal tachado + importe efectivo (neto) destacado, para cobrar desde la
		// original sin tener que buscar la rectificativa ni deducir la modalidad.
		return (
			'<div><del class="text-muted">' + escapeHtml(row.total) + ' €</del></div>' +
			'<div class="fw-bold">' + escapeHtml(row.total_efectivo) + ' €</div>' +
			'<div class="small text-muted">' + escapeHtml(modalidad) + '</div>'
		);
	}

	function renderEstado(data, type, row) {
		var badge = estadoBadges[row.estado] || 'badge-secondary';
		var label = estadoLabels[row.estado] || row.estado;

		var html = '<span class="badge light ' + badge + '">' + escapeHtml(label) + '</span>';

		if (row.modalidad_rectificacion) {
			var modalidad = modalidadRectificacionLabels[row.modalidad_rectificacion] || row.modalidad_rectificacion;
			html += '<div class="small text-muted">' + escapeHtml(modalidad) + '</div>';
		}

		if (row.enviada) {
			html += '<div class="mt-1"><span class="badge light badge-info">Enviada</span></div>';
		}

		return html;
	}

	var estadoCobroLabels = {
		pendiente: 'Pendiente',
		parcial: 'Parcial',
		cobrada: 'Cobrada',
	};

	var estadoCobroBadges = {
		pendiente: 'badge-secondary',
		parcial: 'badge-warning',
		cobrada: 'badge-success',
	};

	function renderCobro(data, type, row) {
		// Las facturas que no admiten cobros (borradores, anuladas, originales rectificadas por
		// sustitución, notas de crédito por diferencias) no muestran estado ni saldo: el cobro
		// no aplica sobre ellas.
		if (!row.admite_cobros || !row.estado_cobro) {
			return '';
		}

		var badge = estadoCobroBadges[row.estado_cobro] || 'badge-secondary';
		var label = estadoCobroLabels[row.estado_cobro] || row.estado_cobro;

		return (
			'<span class="badge light ' + badge + '">' + escapeHtml(label) + '</span>' +
			'<div class="small text-muted">Saldo: ' + escapeHtml(row.saldo_pendiente) + ' €</div>'
		);
	}

	var metodoLabels = {
		transferencia: 'Transferencia',
		tarjeta: 'Tarjeta',
		efectivo: 'Efectivo',
		domiciliacion: 'Domiciliación',
	};

	function renderGrupoFiltro(data, type, row) {
		if (row.es_borrador) {
			return 'borrador';
		}

		return row.es_rectificativa ? 'rectificativa' : 'emitida';
	}

	function renderAcciones(data, type, row) {
		var items = [
			'<li>' +
				'<button type="button" class="dropdown-item btn-ver-factura" data-pdf-url="' + row.pdf_url + '">Ver</button>' +
			'</li>',
		];

		if (row.es_borrador) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-emitir-factura"' +
						' data-emitir-url="' + row.emitir_url + '"' +
					'>Emitir</button>' +
				'</li>'
			);
			items.push('<li><a class="dropdown-item" href="' + row.edit_url + '">Editar</a></li>');
			items.push('<li><hr class="dropdown-divider"></li>');
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item text-danger btn-delete-factura"' +
						' data-id="' + row.id + '"' +
						' data-delete-url="' + row.delete_url + '"' +
					'>Eliminar</button>' +
				'</li>'
			);
		}

		if (row.rectificar_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-rectificar-factura"' +
						' data-rectificar-url="' + row.rectificar_url + '"' +
					'>Rectificar</button>' +
				'</li>'
			);
		}

		if (row.cobros_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-ver-cobros"' +
						' data-cobros-url="' + row.cobros_url + '"' +
						' data-pago-url="' + (row.pago_url || '') + '"' +
						' data-es-rectificada="' + (row.es_rectificada ? '1' : '') + '"' +
						' data-modalidad="' + escapeHtml(row.modalidad_rectificacion || '') + '"' +
						' data-total-nominal="' + escapeHtml(row.total || '') + '"' +
						' data-total-efectivo="' + escapeHtml(row.total_efectivo || '') + '"' +
					'>Cobros</button>' +
				'</li>'
			);
		}

		if (row.enviar_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-enviar-factura"' +
						' data-enviar-url="' + row.enviar_url + '"' +
						' data-cliente-email="' + escapeHtml(row.cliente_email || '') + '"' +
					'>Enviar por email</button>' +
				'</li>'
			);
		}

		if (row.facturae_descargar_url) {
			items.push('<li><hr class="dropdown-divider"></li>');
			items.push(
				'<li>' +
					'<a class="dropdown-item" href="' + row.facturae_descargar_url + '">Descargar Facturae</a>' +
				'</li>'
			);
		}

		if (row.facturae_generar_enviar_url) {
			items.push(
				'<li>' +
					'<button type="button" class="dropdown-item btn-generar-enviar-facturae"' +
						' data-generar-enviar-url="' + row.facturae_generar_enviar_url + '"' +
						' data-cliente-email="' + escapeHtml(row.cliente_email || '') + '"' +
					'>Generar y enviar Facturae</button>' +
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

	window.initFacturasDataTable = function () {
		var $table = $('#facturas-table');

		if (!$table.length) {
			return null;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: {
				url: window.location.href,
				dataSrc: function (json) {
					window.updateFacturasCards(json.totales);
					window.facturasEmailConfigurado = json.email_configurado;
					return json.data;
				},
			},
			columns: [
				{ data: 'identificador', render: escapeHtml },
				{ data: 'cliente', render: escapeHtml },
				{ data: 'fecha_expedicion', render: escapeHtml },
				{ data: null, render: renderTotal },
				{ data: null, orderable: false, render: renderEstado },
				{ data: null, orderable: false, render: renderCobro },
				{ data: null, orderable: false, render: renderAcciones },
				{ data: null, visible: false, searchable: true, orderable: false, render: renderGrupoFiltro },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron facturas',
				emptyTable: 'Todavía no hay facturas en esta cuenta',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		// stateSave persiste la búsqueda de la columna oculta entre recargas; sincronizamos
		// el botón activo con lo restaurado para no mostrar "Todas" resaltado con un filtro
		// distinto ya aplicado.
		(function sincronizarFiltroActivo() {
			var grupoRestaurado = table.column(7).search().replace(/^\^|\$$/g, '');
			var $restaurado = $('.btn-filtro-factura[data-filtro-factura="' + grupoRestaurado + '"]');

			if ($restaurado.length) {
				$('.btn-filtro-factura').removeClass('active');
				$restaurado.addClass('active');
			}
		})();

		$('.btn-filtro-factura').on('click', function () {
			var grupo = $(this).data('filtro-factura');

			$('.btn-filtro-factura').removeClass('active');
			$(this).addClass('active');

			table.column(7).search(grupo ? '^' + grupo + '$' : '', true, false).draw();
		});

		$table.on('click', '.btn-ver-factura', function () {
			var pdfUrl = $(this).data('pdf-url');
			var $modal = $('#facturaPdfModal');

			$('#facturaPdfFrame').attr('src', pdfUrl);
			bootstrap.Modal.getOrCreateInstance($modal[0]).show();
		});

		$('#facturaPdfModal').on('hidden.bs.modal', function () {
			$('#facturaPdfFrame').attr('src', '');
		});

		$table.on('click', '.btn-emitir-factura', function () {
			var emitirUrl = $(this).data('emitir-url');

			window.confirmDelete(
				'¿Emitir esta factura? Se asignará número fiscal y no podrá editarse ni borrarse después.',
				function () {
					return $.ajax({
						url: emitirUrl,
						type: 'POST',
						headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
					})
						.done(function (response) {
							window.showToast('success', response.message);
							table.ajax.reload();
						})
						.fail(function (xhr) {
							window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo emitir la factura.');
						});
				},
				{ confirmLabel: 'Emitir', confirmClass: 'btn-primary', icon: 'invoice' }
			);
		});

		$table.on('click', '.btn-rectificar-factura', function () {
			var rectificarUrl = $(this).data('rectificar-url');
			var $form = $('#rectificarFacturaForm');

			$form.attr('action', rectificarUrl);
			$form[0].reset();
			window.setButtonLoading($form.find('button[type="submit"]'), false);

			bootstrap.Modal.getOrCreateInstance(document.getElementById('rectificarFacturaModal')).show();
		});

		// Submit normal de página completa (sin AJAX): solo feedback visual + evita doble
		// submit, no hace falta restaurar el botón porque la página navega a otra factura.
		$('#rectificarFacturaForm').on('submit', function () {
			window.setButtonLoading($(this).find('button[type="submit"]'), true);
		});

		var $cobrosModal = $('#cobrosModal');
		var cobrosTable = null;
		var csrfHeaders = function () {
			return { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') };
		};

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
			$cobrosModal.data('cobros-url', cobrosUrl);

			$.getJSON(cobrosUrl, function (response) {
				$('#cobroSaldoPendiente').text(response.saldo_pendiente);
				$('#cobroPagarRestante').data('saldo-pendiente', response.saldo_pendiente);

				var $tabla = initCobrosTable();
				$tabla.clear().rows.add(response.data).draw();
			});
		}

		$table.on('click', '.btn-ver-cobros', function () {
			var $btn = $(this);
			var cobrosUrl = $btn.data('cobros-url');
			var pagoUrl = $btn.data('pago-url');
			var $form = $('#registrarCobroForm');

			$form.attr('action', pagoUrl || '');
			$form[0].reset();
			$('#cobroFecha').val(new Date().toISOString().slice(0, 10));
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

		$('#cobroPagarRestante').on('click', function () {
			$('#cobroImporte').val($(this).data('saldo-pendiente'));
		});

		$('#registrarCobroForm').on('submit', function (e) {
			e.preventDefault();

			var $form = $(this);
			var $submit = $form.find('button[type="submit"]');

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
					table.ajax.reload(null, false);
				})
				.fail(function (xhr) {
					window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo registrar el cobro.');
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
						table.ajax.reload(null, false);
					})
					.fail(function (xhr) {
						window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo anular el cobro.');
					});
			}, { confirmLabel: 'Anular', confirmClass: 'btn-danger' });
		});

		$table.on('click', '.btn-enviar-factura', function () {
			if (window.facturasEmailConfigurado === false) {
				window.showToast('error', 'Configura primero tu correo en Configuración → Email.');
				return;
			}

			var enviarUrl = $(this).data('enviar-url');
			var clienteEmail = $(this).data('cliente-email');
			var $form = $('#enviarFacturaForm');

			$form.attr('action', enviarUrl);
			$form[0].reset();
			$('#enviarDestinatario').val(clienteEmail || '');
			$form.find('[data-error-for]').text('');

			bootstrap.Modal.getOrCreateInstance(document.getElementById('enviarFacturaModal')).show();
		});

		$('#enviarFacturaForm').on('submit', function (e) {
			e.preventDefault();

			var $form = $(this);
			var $submit = $('#enviarFacturaSubmit');

			window.withButtonLoading($submit, function () {
				return $.ajax({
					url: $form.attr('action'),
					type: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
					data: $form.serialize(),
				});
			})
				.done(function (response) {
					window.showToast('success', response.message);
					bootstrap.Modal.getInstance(document.getElementById('enviarFacturaModal')).hide();
					table.ajax.reload(null, false);
				})
				.fail(function (xhr) {
					if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.destinatario) {
						$form.find('[data-error-for="destinatario"]').text(xhr.responseJSON.errors.destinatario[0]);
						return;
					}

					window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo enviar la factura.');
				});
		});

		$table.on('click', '.btn-generar-enviar-facturae', function () {
			if (window.facturasEmailConfigurado === false) {
				window.showToast('error', 'Configura primero tu correo en Configuración → Email.');
				return;
			}

			var generarEnviarUrl = $(this).data('generar-enviar-url');
			var clienteEmail = $(this).data('cliente-email');
			var $form = $('#generarEnviarFacturaeForm');

			$form.attr('action', generarEnviarUrl);
			$form[0].reset();
			$('#generarEnviarFacturaeDestinatario').val(clienteEmail || '');
			$form.find('[data-error-for]').text('');

			bootstrap.Modal.getOrCreateInstance(document.getElementById('generarEnviarFacturaeModal')).show();
		});

		$('#generarEnviarFacturaeForm').on('submit', function (e) {
			e.preventDefault();

			var $form = $(this);
			var $submit = $('#generarEnviarFacturaeSubmit');
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
					window.showToast(response.tipo || 'success', response.message);
					bootstrap.Modal.getInstance(document.getElementById('generarEnviarFacturaeModal')).hide();
					table.ajax.reload(null, false);
				})
				.fail(function (xhr) {
					if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.destinatario) {
						$form.find('[data-error-for="destinatario"]').text(xhr.responseJSON.errors.destinatario[0]);
						return;
					}

					window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo generar el Facturae.');
				});
		});

		$table.on('click', '.btn-delete-factura', function () {
			var deleteUrl = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar esta factura en borrador?', function () {
				return $.ajax({
					url: deleteUrl,
					type: 'DELETE',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
				})
					.done(function (response) {
						window.showToast('success', response.message);
						table.ajax.reload();
					})
					.fail(function (xhr) {
						window.showToast('error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo eliminar la factura.');
					});
			});
		});

		return table;
	};

	$(function () {
		window.initFacturasDataTable();
	});
})(jQuery);
