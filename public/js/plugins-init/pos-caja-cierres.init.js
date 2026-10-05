/**
 * Historial de cierres de caja (feature 048, US5). DataTable client-side: un cierre por día,
 * unas centenas de filas al año por tenant.
 */
(function ($) {
	'use strict';

	var S = window.cajaCierresState;
	if (!S) { return; }

	function esc(v) { return $('<div>').text(v === null || v === undefined ? '' : v).html(); }
	function euros(v) {
		return (parseFloat(v) || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
	}
	function fecha(iso) {
		// "2026-10-03 22:14" → "03/10/2026 22:14" (ya viene en la zona del tenant).
		var p = (iso || '').split(' ');
		var d = (p[0] || '').split('-');
		return d.length === 3 ? d[2] + '/' + d[1] + '/' + d[0] + ' ' + (p[1] || '') : esc(iso);
	}

	var BADGES = {
		cuadra: '<span class="badge light badge-success">' + __t('Cuadra') + '</span>',
		sobra: '<span class="badge light badge-warning">' + __t('Sobrante') + '</span>',
		falta: '<span class="badge light badge-danger">' + __t('Faltante') + '</span>',
	};

	function renderDiferencia(data, type, row) {
		if (type !== 'display') { return parseFloat(row.descuadre); }
		var d = parseFloat(row.descuadre) || 0;
		var signo = d > 0 ? '+' : '';
		return '<div class="caja-cierres-dif ' + esc(row.estado) + '">' + signo + euros(d) + '</div>'
			+ '<div class="mt-1">' + (BADGES[row.estado] || '') + '</div>';
	}

	function renderAcciones(data, type, row) {
		var items = '';
		if (row.informe_url_ticket) {
			items += '<li><button type="button" class="dropdown-item btn-ver-informe" data-url="' + esc(row.informe_url_ticket) + '">' + __t('Ver informe (80 mm)') + '</button></li>';
		}
		if (row.informe_url_a4) {
			items += '<li><button type="button" class="dropdown-item btn-ver-informe" data-url="' + esc(row.informe_url_a4) + '">' + __t('Ver informe (A4)') + '</button></li>';
		}
		return '<div class="dropdown">'
			+ '<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' + __t('Acciones') + '</button>'
			+ '<ul class="dropdown-menu dropdown-menu-end">' + items + '</ul></div>';
	}

	function pintarResumen(r) {
		if (!r) { return; }
		$('[data-metric="cierres"]').text(r.cierres);
		$('[data-metric="facturado"]').text(euros(r.facturado));
		var d = parseFloat(r.descuadre) || 0;
		// El rail de la card lo pone la clase de color del número (docs/04, cards de métricas).
		$('#cierres-descuadre')
			.text((d > 0 ? '+' : '') + euros(d))
			.toggleClass('text-danger', d < 0)
			.toggleClass('text-success', d === 0);
	}

	$(function () {
		var $table = $('#cierres-table');

		$table.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: {
				url: S.url,
				headers: { Accept: 'application/json' },
				dataSrc: function (json) { pintarResumen(json.resumen_mes); return json.data; },
			},
			order: [[0, 'desc']],
			columns: [
				{ data: 'cerrada_at', render: function (d, type) { return type === 'display' ? fecha(d) : d; } },
				{ data: 'abierta_por', render: function (d, type, row) { return type === 'display' ? esc(d) + '<div class="text-muted small">' + fecha(row.abierta_at) + '</div>' : d; } },
				{ data: 'cerrada_por', render: esc },
				{ data: 'num_tickets' },
				{ data: 'total_facturado', render: function (d, type) { return type === 'display' ? euros(d) : parseFloat(d); } },
				{ data: 'efectivo_esperado', render: function (d, type) { return type === 'display' ? euros(d) : parseFloat(d); } },
				{ data: 'efectivo_contado', render: function (d, type) { return type === 'display' ? euros(d) : parseFloat(d); } },
				{ data: null, render: renderDiferencia },
				{ data: null, orderable: false, searchable: false, render: renderAcciones },
			],
			language: {
				search: __t('Buscar:'),
				lengthMenu: __t('Mostrar _MENU_ registros'),
				info: __t('Mostrando _START_ a _END_ de _TOTAL_ cierres'),
				infoEmpty: __t('Mostrando 0 a 0 de 0 cierres'),
				infoFiltered: __t('(filtrado de _MAX_ cierres totales)'),
				zeroRecords: __t('No se encontraron cierres'),
				emptyTable: __t('Todavía no se cerró ninguna caja'),
				processing: __t('Cargando...'),
				paginate: { first: __t('Primero'), last: __t('Último'), next: __t('Siguiente'), previous: __t('Anterior') },
			},
		});

		$table.on('click', '.btn-ver-informe', function () {
			$('#cajaInformeFrame').attr('src', $(this).data('url'));
			bootstrap.Modal.getOrCreateInstance($('#cajaInformeModal')[0]).show();
		});
		$('#cajaInformeModal').on('hidden.bs.modal', function () { $('#cajaInformeFrame').attr('src', ''); });
	});
})(jQuery);
