(function ($) {
	'use strict';

	$(function () {
		var $body = $('#lineas-body');
		var $template = $('#linea-template');
		var $form = $('#compra-form');

		if (!$body.length || !$template.length || !$form.length) {
			return;
		}

		function addLinea(datos) {
			datos = datos || {};

			var $row = $($template[0].content.cloneNode(true));

			if (datos.articulo_id) {
				$row.find('.linea-articulo').val(datos.articulo_id);
			}
			$row.find('.linea-concepto').val(datos.concepto || '');
			$row.find('.linea-unidad').val(datos.unidad || '');
			$row.find('.linea-cantidad').val(datos.cantidad || 1);
			$row.find('.linea-precio').val(datos.precio_unitario || 0);
			$row.find('.linea-impuesto').val(datos.tipo_impositivo != null ? datos.tipo_impositivo : 21);

			$body.append($row);
		}

		$('#btn-add-linea').on('click', function () {
			addLinea();
		});

		$body.on('change', '.linea-articulo', function () {
			var $option = $(this).find('option:selected');
			var $row = $(this).closest('tr');

			if ($option.val()) {
				$row.find('.linea-concepto').val($option.data('nombre'));
				$row.find('.linea-unidad').val($option.data('unidad') || '');
				$row.find('.linea-precio').val($option.data('precio') || 0);
				$row.find('.linea-impuesto').val($option.data('tipo-impositivo') != null ? $option.data('tipo-impositivo') : 21);
			}
		});

		$body.on('click', '.btn-remove-linea', function () {
			$(this).closest('tr').remove();
		});

		var iniciales = window.compraLineasIniciales || [];

		if (iniciales.length) {
			iniciales.forEach(addLinea);
		} else {
			addLinea();
		}

		$form.on('submit', function () {
			$body.find('.linea-row').each(function (index) {
				var $row = $(this);
				$row.find('.linea-articulo').attr('name', 'lineas[' + index + '][articulo_id]');
				$row.find('.linea-concepto').attr('name', 'lineas[' + index + '][concepto]');
				$row.find('.linea-unidad').attr('name', 'lineas[' + index + '][unidad]');
				$row.find('.linea-cantidad').attr('name', 'lineas[' + index + '][cantidad]');
				$row.find('.linea-precio').attr('name', 'lineas[' + index + '][precio_unitario]');
				$row.find('.linea-impuesto').attr('name', 'lineas[' + index + '][tipo_impositivo]');
			});
		});
	});
})(jQuery);
