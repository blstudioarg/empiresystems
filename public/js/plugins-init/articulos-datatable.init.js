(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	function formatMoneda(value) {
		var numero = parseFloat(value);
		return isNaN(numero) ? '' : numero.toFixed(2) + ' €';
	}

	function formatPorcentaje(value) {
		var numero = parseFloat(value);
		return isNaN(numero) ? '' : numero + '%';
	}

	function renderNombre(data, type, row) {
		if (type !== 'display') {
			return data;
		}

		var thumb = row.imagen_url
			? '<img src="' + escapeAttr(row.imagen_url) + '" class="rounded me-2" style="width:2rem;height:2rem;object-fit:cover;" alt="">'
			: '';

		return '<div class="d-flex align-items-center">' + thumb + '<span>' + escapeHtml(data) + '</span></div>';
	}

	window.updateArticulosCards = function (totales) {
		if (!totales) {
			return;
		}

		$('[data-metric="total"]').text(totales.total);
		$('[data-metric="productos"]').text(totales.productos);
		$('[data-metric="servicios"]').text(totales.servicios);
	};

	function renderAcciones(data, type, row) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' +
					'<li>' +
						'<button type="button" class="dropdown-item btn-edit-articulo" data-bs-toggle="modal" data-bs-target="#articuloModal"' +
							' data-id="' + escapeAttr(row.id) + '"' +
							' data-update-url="' + escapeAttr(row.update_url) + '"' +
							' data-tipo="' + escapeAttr(row.tipo) + '"' +
							' data-sku="' + escapeAttr(row.sku) + '"' +
							' data-nombre="' + escapeAttr(row.nombre) + '"' +
							' data-descripcion="' + escapeAttr(row.descripcion) + '"' +
							' data-imagen-url="' + escapeAttr(row.imagen_url) + '"' +
							' data-unidad="' + escapeAttr(row.unidad) + '"' +
							' data-categoria-id="' + escapeAttr(row.categoria_id) + '"' +
							' data-precio="' + escapeAttr(row.precio) + '"' +
							' data-tipo-impositivo="' + escapeAttr(row.tipo_impositivo) + '"' +
							' data-gestion-stock="' + (row.gestion_stock ? '1' : '0') + '"' +
							' data-stock-actual="' + escapeAttr(row.stock_actual) + '"' +
							' data-stock-minimo="' + escapeAttr(row.stock_minimo) + '"' +
							' data-recargo="' + (row.aplica_recargo_equivalencia ? '1' : '0') + '"' +
						'>Editar</button>' +
					'</li>' +
					'<li><hr class="dropdown-divider"></li>' +
					'<li>' +
						'<button type="button" class="dropdown-item text-danger btn-delete-articulo"' +
							' data-id="' + escapeAttr(row.id) + '"' +
							' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
						'>Eliminar</button>' +
					'</li>' +
				'</ul>' +
			'</div>'
		);
	}

	window.initArticulosDataTable = function () {
		var $table = $('#articulos-table');

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
					window.updateArticulosCards(json.totales);
					return json.data;
				},
			},
			columns: [
				{ data: 'sku', render: escapeHtml },
				{ data: 'nombre', render: renderNombre },
				{ data: 'tipo_label', render: escapeHtml },
				{ data: 'precio', render: function (data) { return formatMoneda(data); } },
				{ data: 'tipo_impositivo', render: function (data) { return formatPorcentaje(data); } },
				{ data: null, orderable: false, render: renderAcciones },
			],
			buttons: [
				{
					extend: 'colvis',
					text: 'Columnas',
					className: 'btn btn-outline-secondary',
					columns: ':not(:last-child)',
				},
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
				infoEmpty: 'Mostrando 0 a 0 de 0 registros',
				infoFiltered: '(filtrado de _MAX_ registros totales)',
				zeroRecords: 'No se encontraron artículos',
				emptyTable: 'Todavía no hay productos ni servicios en esta cuenta',
				processing: 'Cargando...',
				paginate: {
					first: 'Primero',
					last: 'Último',
					next: 'Siguiente',
					previous: 'Anterior',
				},
			},
		});

		table.buttons().container().appendTo('#articulos-colvis');

		return table;
	};

	$(function () {
		window.initArticulosDataTable();
	});
})(jQuery);
