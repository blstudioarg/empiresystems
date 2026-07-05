(function ($) {
	'use strict';

	var state = window.facturaFormState || {};
	var articulosCache = null;

	function formatoMoneda(valor) {
		return (valor || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
	}

	function limpiarErrores() {
		$('.invalid-feedback').text('');
		$('.is-invalid').removeClass('is-invalid');
	}

	function mostrarErrores(errores) {
		limpiarErrores();

		$.each(errores || {}, function (campo, mensajes) {
			var $target = $('[data-error-for="' + campo + '"]');
			if ($target.length) {
				$target.text(mensajes[0]);
			}
		});
	}

	function crearFilaLinea(datos) {
		datos = datos || {};

		var $template = $('#linea-template');
		var $row = $($template.prop('content')).find('.linea-row').clone();

		$row.find('.linea-concepto').val(datos.concepto || '');
		$row.find('.linea-unidad').val(datos.unidad || '');
		$row.find('.linea-cantidad').val(datos.cantidad !== undefined ? datos.cantidad : 1);
		$row.find('.linea-precio').val(datos.precio_unitario !== undefined ? datos.precio_unitario : 0);
		$row.find('.linea-descuento').val(datos.descuento_porcentaje !== undefined && datos.descuento_porcentaje !== null ? datos.descuento_porcentaje : 0);
		$row.find('.linea-tipo').val(datos.tipo_impositivo !== undefined ? datos.tipo_impositivo : state.regimen.tipoPorDefecto);

		if (datos.articulo_id) {
			$row.attr('data-articulo-id', datos.articulo_id);
		}

		return $row;
	}

	function precargarDatosCliente() {
		var $option = $('#cliente_id option:selected');

		if (!$option.val()) {
			return;
		}

		var campos = {
			cliente_nombre: 'nombre',
			cliente_razon_social: 'razon-social',
			cliente_nif: 'nif',
			cliente_direccion: 'direccion',
			cliente_cp: 'cp',
			cliente_ciudad: 'ciudad',
			cliente_provincia: 'provincia',
			cliente_pais: 'pais',
		};

		$.each(campos, function (idCampo, dataAttr) {
			$('#' + idCampo).val($option.data(dataAttr) || '');
		});
	}

	function recalcularPreview() {
		var aplicaRecargo = $('#cliente_id option:selected').data('recargo') == 1;
		var irpf = parseFloat($('#irpf_porcentaje').val()) || 0;

		var baseTotal = 0;
		var impuestoTotal = 0;
		var recargoTotal = 0;
		var desglose = {};

		$('#lineas-body .linea-row').each(function () {
			var $row = $(this);
			var cantidad = parseFloat($row.find('.linea-cantidad').val()) || 0;
			var precio = parseFloat($row.find('.linea-precio').val()) || 0;
			var descuento = parseFloat($row.find('.linea-descuento').val()) || 0;
			var tipo = parseFloat($row.find('.linea-tipo').val()) || 0;

			var base = Math.round(cantidad * precio * 100) / 100;
			if (descuento > 0) {
				base = Math.round(base * (1 - descuento / 100) * 100) / 100;
			}

			var cuota = Math.round(base * tipo / 100 * 100) / 100;

			$row.find('.linea-base').text(formatoMoneda(base));

			baseTotal += base;
			impuestoTotal += cuota;

			var claveImpuesto = state.regimen.label + ' ' + tipo + '%';
			desglose[claveImpuesto] = (desglose[claveImpuesto] || 0) + cuota;

			if (state.regimen.aplicaRecargo && aplicaRecargo) {
				var tipoRecargo = { 21: 5.2, 10: 1.4, 4: 0.5 }[tipo] || 0;
				if (tipoRecargo > 0) {
					var cuotaRecargo = Math.round(base * tipoRecargo / 100 * 100) / 100;
					recargoTotal += cuotaRecargo;

					var claveRecargo = 'Recargo eq. ' + tipoRecargo + '%';
					desglose[claveRecargo] = (desglose[claveRecargo] || 0) + cuotaRecargo;
				}
			}
		});

		baseTotal = Math.round(baseTotal * 100) / 100;
		impuestoTotal = Math.round(impuestoTotal * 100) / 100;
		recargoTotal = Math.round(recargoTotal * 100) / 100;
		var irpfCuota = irpf > 0 ? Math.round(baseTotal * irpf / 100 * 100) / 100 : 0;
		var total = Math.round((baseTotal + impuestoTotal + recargoTotal - irpfCuota) * 100) / 100;

		$('#preview-irpf').text('-' + formatoMoneda(irpfCuota));
		$('#preview-total').text(formatoMoneda(total));
		$('#preview-total-bar').text(formatoMoneda(total));

		var $desgloseBody = $('#preview-desglose').empty();
		$desgloseBody.append(
			'<tr><td>Base imponible</td><td class="text-end">' + formatoMoneda(baseTotal) + '</td></tr>'
		);
		$.each(desglose, function (etiqueta, cuota) {
			$desgloseBody.append(
				'<tr><td>' + etiqueta + '</td><td class="text-end">' + formatoMoneda(cuota) + '</td></tr>'
			);
		});
	}

	function cargarArticulos() {
		if (articulosCache) {
			return $.Deferred().resolve(articulosCache).promise();
		}

		if (!state.articulosUrl) {
			return $.Deferred().resolve([]).promise();
		}

		// .then() (a diferencia de .done()) transforma el valor resuelto: la promesa devuelta
		// debe resolver con el array de artículos, no con la respuesta JSON completa.
		return $.getJSON(state.articulosUrl).then(function (response) {
			articulosCache = response.data || [];
			return articulosCache;
		});
	}

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function acentoPorTipo(tipo) {
		return tipo === 'servicio' ? '#8a5cf6' : '#1d69d6';
	}

	function iconoPorTipo(tipo) {
		return tipo === 'servicio' ? 'fa-briefcase' : 'fa-box';
	}

	function actualizarContadoresFiltro() {
		var articulos = articulosCache || [];
		var totales = { todos: articulos.length, producto: 0, servicio: 0 };

		articulos.forEach(function (articulo) {
			if (articulo.tipo === 'servicio') { totales.servicio += 1; }
			else { totales.producto += 1; }
		});

		$.each(totales, function (tipo, total) {
			$('[data-count-tipo="' + tipo + '"]').text(total);
		});
	}

	function renderCatalogo() {
		var $lista = $('#catalogo-lista');
		var texto = ($('#catalogo-buscador').val() || '').toLowerCase().trim();
		var filtroTipo = $('.factura-catalogo-filtros .btn.active').data('filtro-tipo') || 'todos';

		var articulos = (articulosCache || []).filter(function (articulo) {
			var coincideTexto = !texto
				|| (articulo.nombre || '').toLowerCase().indexOf(texto) !== -1
				|| (articulo.sku || '').toLowerCase().indexOf(texto) !== -1;
			var coincideTipo = filtroTipo === 'todos' || articulo.tipo === filtroTipo;

			return coincideTexto && coincideTipo;
		});

		var html = '' +
			'<div class="catalogo-item catalogo-item-libre" data-linea-libre="1">' +
				'<i class="fas fa-plus"></i> Línea libre (sin artículo)' +
			'</div>';

		if (!articulos.length) {
			html += '<div class="catalogo-vacio">Ningún artículo coincide con la búsqueda.</div>';
		} else {
			articulos.forEach(function (articulo) {
				var acento = acentoPorTipo(articulo.tipo);

				html += '' +
					'<div class="catalogo-item" data-articulo-id="' + articulo.id + '" style="--factura-accent: ' + acento + ';">' +
						'<div class="nombre-wrap">' +
							'<i class="fas ' + iconoPorTipo(articulo.tipo) + ' tipo-icon"></i>' +
							'<div>' +
								'<div class="nombre">' + escapeHtml(articulo.nombre) + '</div>' +
								'<div class="meta">' + escapeHtml(articulo.tipo === 'servicio' ? 'Servicio' : 'Producto') + (articulo.sku ? ' · ' + escapeHtml(articulo.sku) : '') + '</div>' +
							'</div>' +
						'</div>' +
						'<div class="precio">' + formatoMoneda(parseFloat(articulo.precio) || 0) + '</div>' +
					'</div>';
			});
		}

		$lista.html(html);
	}

	$(function () {
		var $lineasBody = $('#lineas-body');

		(state.lineasIniciales && state.lineasIniciales.length ? state.lineasIniciales : [{}]).forEach(function (linea) {
			$lineasBody.append(crearFilaLinea(linea));
		});

		renderCatalogo();

		recalcularPreview();

		if (state.erroresValidacion && Object.keys(state.erroresValidacion).length) {
			mostrarErrores(state.erroresValidacion);
		}

		cargarArticulos().done(function () {
			actualizarContadoresFiltro();
			renderCatalogo();
		});

		$('#catalogo-buscador').on('input', renderCatalogo);

		$('.factura-catalogo-filtros').on('click', '.btn', function () {
			$('.factura-catalogo-filtros .btn').removeClass('active');
			$(this).addClass('active');
			renderCatalogo();
		});

		// Destello breve de confirmación: en el ítem del catálogo tocado y en la fila que
		// acaba de aparecer en la tabla, para que quede claro qué se añadió sin interrumpir
		// el flujo (mismo patrón introducido en pos-form.js).
		function destellar($el) {
			$el.removeClass('just-added');
			void $el[0].offsetWidth;
			$el.addClass('just-added');
			setTimeout(function () { $el.removeClass('just-added'); }, 600);
		}

		$('#catalogo-lista').on('click', '.catalogo-item', function () {
			var $item = $(this);
			destellar($item);

			if ($item.data('linea-libre')) {
				var $filaLibre = crearFilaLinea();
				$lineasBody.append($filaLibre);
				destellar($filaLibre);
				recalcularPreview();

				return;
			}

			var articuloId = $item.data('articulo-id');
			var articulo = (articulosCache || []).find(function (item) {
				return item.id === articuloId;
			});

			if (!articulo) {
				return;
			}

			var $fila = crearFilaLinea({
				articulo_id: articulo.id,
				concepto: articulo.nombre,
				unidad: articulo.unidad,
				cantidad: 1,
				precio_unitario: articulo.precio,
				tipo_impositivo: articulo.tipo_impositivo,
			});
			$lineasBody.append($fila);
			destellar($fila);

			recalcularPreview();
		});

		$lineasBody.on('click', '.btn-remove-linea', function () {
			if ($lineasBody.find('.linea-row').length <= 1) {
				return;
			}
			$(this).closest('.linea-row').remove();
			recalcularPreview();
		});

		$lineasBody.on('input change', 'input', recalcularPreview);
		$('#cliente_id, #irpf_porcentaje').on('input change', recalcularPreview);
		$('#cliente_id').on('change', precargarDatosCliente);

		$('#factura-form').on('submit', function () {
			var $form = $(this);

			// Descartar filas vacías (sin concepto ni artículo asociado) antes de nombrarlas:
			// el formulario arranca siempre con una fila en blanco y al elegir artículos del
			// catálogo se añaden filas nuevas, dejando la inicial vacía. Si se enviara, su
			// `concepto` vacío rompe la validación `lineas.*.concepto required`. Se conserva al
			// menos una fila para que, si todo está vacío, la validación `lineas min:1` avise.
			var $filas = $form.find('.linea-row');
			$filas.each(function () {
				var $row = $(this);
				var concepto = $.trim($row.find('.linea-concepto').val() || '');
				var articuloId = $row.attr('data-articulo-id') || '';

				if (!concepto && !articuloId && $form.find('.linea-row').length > 1) {
					$row.remove();
				}
			});

			$form.find('.linea-row').each(function (index) {
				var $row = $(this);
				var articuloId = $row.attr('data-articulo-id') || '';

				$row.find('.linea-concepto').attr('name', 'lineas[' + index + '][concepto]');
				$row.find('.linea-unidad').attr('name', 'lineas[' + index + '][unidad]');
				$row.find('.linea-cantidad').attr('name', 'lineas[' + index + '][cantidad]');
				$row.find('.linea-precio').attr('name', 'lineas[' + index + '][precio_unitario]');
				$row.find('.linea-descuento').attr('name', 'lineas[' + index + '][descuento_porcentaje]');
				$row.find('.linea-tipo').attr('name', 'lineas[' + index + '][tipo_impositivo]');

				if (articuloId) {
					if (!$row.find('input[type=hidden].linea-articulo-id').length) {
						$row.append('<input type="hidden" class="linea-articulo-id" value="' + articuloId + '">');
					}
					$row.find('.linea-articulo-id').attr('name', 'lineas[' + index + '][articulo_id]');
				}
			});
		});
	});
})(jQuery);
