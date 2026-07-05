(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	window.updateStockCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="articulos_gestionados"]').text(totales.articulos_gestionados);
		$('[data-metric="movimientos"]').text(totales.movimientos);
		$('[data-metric="alertas"]').text(totales.alertas);
	};

	window.renderAlertasStock = function (alertas) {
		var $row = $('#alertas-stock-row');
		var $body = $('#alertas-stock-body');

		if (!alertas || !alertas.length) {
			$row.hide();
			$body.empty();
			return;
		}

		var html = '';
		$.each(alertas, function (_, alerta) {
			html += '<span class="badge bg-warning text-dark">' +
				escapeHtml(alerta.nombre) + ': ' + alerta.stock_actual + ' / mín. ' + alerta.stock_minimo +
				'</span>';
		});

		$body.html(html);
		$row.show();
	};

	window.renderArticulosAjusteSelect = function (articulos) {
		var $select = $('#ajuste_articulo_id');
		var seleccionActual = $select.val();
		var options = '<option value="">Selecciona un artículo</option>';

		$.each(articulos || [], function (_, articulo) {
			options += '<option value="' + articulo.id + '">' + escapeHtml(articulo.nombre) + ' (stock actual: ' + articulo.stock_actual + ')</option>';
		});

		$select.html(options);

		if (seleccionActual) {
			$select.val(seleccionActual);
		}
	};

	window.initKardexDataTable = function () {
		var $table = $('#kardex-table');

		if (!$table.length) {
			return null;
		}

		var table = $table.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			order: [],
			ajax: {
				url: window.stockIndexUrl,
				dataSrc: function (json) {
					window.updateStockCards(json.totales);
					window.renderAlertasStock(json.alertas);
					window.renderArticulosAjusteSelect(json.articulos);
					return json.data;
				},
			},
			columns: [
				{ data: 'ocurrido_at', render: escapeHtml },
				{ data: 'articulo', render: escapeHtml },
				{ data: 'tipo', render: function (data) { return escapeHtml(data.charAt(0).toUpperCase() + data.slice(1)); } },
				{ data: 'cantidad' },
				{ data: 'stock_resultante' },
				{ data: 'origen', render: function (data) { return escapeHtml(data.replace('_', ' ')); } },
				{ data: 'motivo', render: escapeHtml },
			],
			createdRow: function (row, data) {
				if (parseFloat(data.stock_resultante) < 0) {
					$(row).addClass('table-danger');
				}
			},
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron movimientos',
				emptyTable: 'Todavía no hay movimientos de stock',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		return table;
	};

	$(function () {
		window.kardexTable = window.initKardexDataTable();
	});
})(jQuery);
