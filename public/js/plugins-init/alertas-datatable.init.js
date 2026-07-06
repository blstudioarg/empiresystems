(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var estadoBadges = {
		nueva: 'badge-danger',
		vista: 'badge-warning',
		resuelta: 'badge-success',
	};

	function renderEstado(data, type, row) {
		var badge = estadoBadges[row.estado] || 'badge-secondary';

		return '<span class="badge light ' + badge + '">' + escapeHtml(row.estado_label) + '</span>';
	}

	function renderDetalle(data, type, row) {
		if (row.tipo === 'fichaje_fuera_de_rango') {
			return escapeHtml(row.fichaje_fecha) + ' — ' + escapeHtml(row.distancia_metros) + ' m';
		}

		return escapeHtml(row.referencia_fecha || row.fichaje_fecha || '—');
	}

	window.updateAlertasCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="nuevas"]').text(totales.nuevas);
		$('[data-metric="resueltas"]').text(totales.resueltas);
	};

	function renderAcciones(data, type, row) {
		var items = [];

		if (row.estado !== 'vista' && row.estado !== 'resuelta') {
			items.push('<li><button type="button" class="dropdown-item btn-cambiar-estado-alerta" data-url="' + row.update_url + '" data-estado="vista">Marcar como vista</button></li>');
		}

		if (row.estado !== 'resuelta') {
			items.push('<li><button type="button" class="dropdown-item btn-cambiar-estado-alerta" data-url="' + row.update_url + '" data-estado="resuelta">Marcar como resuelta</button></li>');
		}

		if (!items.length) {
			return '';
		}

		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown">Acciones</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' + items.join('') + '</ul>' +
			'</div>'
		);
	}

	$(function () {
		var $table = $('#alertas-table');

		if (!$table.length) {
			return;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: window.location.href,
				headers: { Accept: 'application/json' },
				dataSrc: function (json) {
					window.updateAlertasCards(json.totales);
					return json.data;
				},
			},
			columns: [
				{ data: 'miembro', render: escapeHtml },
				{ data: 'tipo_label', render: escapeHtml },
				{ data: null, orderable: false, render: renderDetalle },
				{ data: null, orderable: false, render: renderEstado },
				{ data: null, orderable: false, render: renderAcciones },
			],
			order: [],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron alertas',
				emptyTable: 'Sin alertas registradas',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		$table.on('click', '.btn-cambiar-estado-alerta', function () {
			var $btn = $(this);

			$.ajax({
				url: $btn.data('url'),
				type: 'PATCH',
				dataType: 'json',
				headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
				data: { estado: $btn.data('estado') },
			})
				.done(function (response) {
					window.showToast('success', response.message || 'Alerta actualizada correctamente.');
					table.ajax.reload(null, false);
				})
				.fail(function (xhr) {
					window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo actualizar la alerta.');
				});
		});
	});
})(jQuery);
