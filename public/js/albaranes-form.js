(function ($) {
	'use strict';

	var state = window.albaranFormState || {};
	var articulosCache = null;

	function formatoMoneda(valor) {
		return (valor || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
	}

	function formatoCantidad(valor) {
		return (valor || 0).toLocaleString('es-ES', { maximumFractionDigits: 4 });
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

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function modoActivo() {
		return $('#modo').val();
	}

	function setModo(modo) {
		$('#modo').val(modo);
		$('.albaran-modo-toggle .btn').removeClass('active');
		$('.albaran-modo-toggle .btn[data-modo="' + modo + '"]').addClass('active');
		$('#panel-presupuesto').toggleClass('d-none', modo !== 'presupuesto');
		$('#panel-directo').toggleClass('d-none', modo !== 'directo');
		recalcularPreview();
	}

	// --- Panel "desde presupuesto" ---

	function crearFilaPresupuesto(linea) {
		var $template = $('#linea-template-presupuesto');
		var $row = $($template.prop('content')).find('.linea-row').clone();

		$row.attr('data-presupuesto-linea-id', linea.presupuesto_linea_id);
		if (linea.articulo_id) {
			$row.attr('data-articulo-id', linea.articulo_id);
		}
		$row.find('.linea-concepto-texto').text(linea.concepto);
		$row.find('.linea-pendiente-hint').text(formatoCantidad(linea.cantidad_pendiente) + (linea.unidad ? ' ' + linea.unidad : ''));
		$row.find('.linea-precio-texto').text(formatoMoneda(linea.precio_unitario));
		$row.find('.linea-cantidad')
			.attr('max', linea.cantidad_pendiente)
			.attr('data-concepto', linea.concepto)
			.attr('data-unidad', linea.unidad || '')
			.attr('data-precio', linea.precio_unitario)
			.attr('data-descuento', linea.descuento_porcentaje || 0)
			.attr('data-tipo', linea.tipo_impositivo)
			.val(linea.cantidad !== undefined ? linea.cantidad : linea.cantidad_pendiente);

		return $row;
	}

	function renderLineasPresupuesto(lineas) {
		var $body = $('#lineas-body-presupuesto').empty();

		(lineas || []).forEach(function (linea) {
			$body.append(crearFilaPresupuesto(linea));
		});

		recalcularPreview();
	}

	function cargarPresupuestoSeleccionado() {
		var id = $('#presupuesto_id').val();
		var presupuesto = id ? state.presupuestos[id] : null;

		renderLineasPresupuesto(presupuesto ? presupuesto.lineas : []);
	}

	// --- Panel "directo a cliente" ---

	function crearFilaDirecta(datos) {
		datos = datos || {};

		var $template = $('#linea-template-directo');
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

	function cargarArticulos() {
		if (articulosCache) {
			return $.Deferred().resolve(articulosCache).promise();
		}

		if (!state.articulosUrl) {
			return $.Deferred().resolve([]).promise();
		}

		return $.getJSON(state.articulosUrl).then(function (response) {
			articulosCache = response.data || [];
			return articulosCache;
		});
	}

	function acentoPorTipo(tipo) {
		return tipo === 'servicio' ? '#8a5cf6' : '#1d69d6';
	}

	function iconoPorTipo(tipo) {
		return tipo === 'servicio' ? 'fa-briefcase' : 'fa-box';
	}

	function renderCatalogo() {
		var $lista = $('#catalogo-lista');
		var texto = ($('#catalogo-buscador').val() || '').toLowerCase().trim();

		var articulos = (articulosCache || []).filter(function (articulo) {
			return !texto
				|| (articulo.nombre || '').toLowerCase().indexOf(texto) !== -1
				|| (articulo.sku || '').toLowerCase().indexOf(texto) !== -1;
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
					'<div class="catalogo-item" data-articulo-id="' + articulo.id + '" style="--albaran-accent: ' + acento + ';">' +
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

	// --- Preview de totales (aproximación UX; el servidor recalcula siempre) ---

	function recalcularPreview() {
		var modo = modoActivo();
		var $filas = modo === 'presupuesto' ? $('#lineas-body-presupuesto .linea-row') : $('#lineas-body-directo .linea-row');
		var aplicaRecargo = modo === 'directo' && $('#cliente_id option:selected').data('recargo') == 1;

		var baseTotal = 0;
		var impuestoTotal = 0;
		var recargoTotal = 0;
		var desglose = {};

		$filas.each(function () {
			var $row = $(this);
			var cantidad, precio, descuento, tipo;

			if (modo === 'presupuesto') {
				var $cantidadInput = $row.find('.linea-cantidad');
				var max = parseFloat($cantidadInput.attr('max')) || 0;
				cantidad = parseFloat($cantidadInput.val()) || 0;
				if (cantidad > max) {
					cantidad = max;
					$cantidadInput.val(max);
				}
				precio = parseFloat($cantidadInput.attr('data-precio')) || 0;
				descuento = parseFloat($cantidadInput.attr('data-descuento')) || 0;
				tipo = parseFloat($cantidadInput.attr('data-tipo')) || 0;
			} else {
				cantidad = parseFloat($row.find('.linea-cantidad').val()) || 0;
				precio = parseFloat($row.find('.linea-precio').val()) || 0;
				descuento = parseFloat($row.find('.linea-descuento').val()) || 0;
				tipo = parseFloat($row.find('.linea-tipo').val()) || 0;
			}

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
		var total = Math.round((baseTotal + impuestoTotal + recargoTotal) * 100) / 100;

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

	$(function () {
		setModo(state.modoInicial || 'presupuesto');

		if (modoActivo() === 'presupuesto') {
			if (state.lineasIniciales && state.lineasIniciales.length) {
				renderLineasPresupuesto(state.lineasIniciales);
			} else {
				cargarPresupuestoSeleccionado();
			}
		} else {
			var $lineasBodyDirecto = $('#lineas-body-directo');
			(state.lineasIniciales && state.lineasIniciales.length ? state.lineasIniciales : [{}]).forEach(function (linea) {
				$lineasBodyDirecto.append(crearFilaDirecta(linea));
			});
			recalcularPreview();
		}

		renderCatalogo();

		if (state.erroresValidacion && Object.keys(state.erroresValidacion).length) {
			mostrarErrores(state.erroresValidacion);
		}

		cargarArticulos().done(function () {
			renderCatalogo();
		});

		if (!state.modoBloqueado) {
			$('.albaran-modo-toggle .btn').on('click', function () {
				setModo($(this).data('modo'));
			});
		}

		$('#presupuesto_id').on('change', cargarPresupuestoSeleccionado);
		$('#lineas-body-presupuesto').on('input change', '.linea-cantidad', recalcularPreview);

		$('#catalogo-buscador').on('input', renderCatalogo);

		function destellar($el) {
			$el.removeClass('just-added');
			void $el[0].offsetWidth;
			$el.addClass('just-added');
			setTimeout(function () { $el.removeClass('just-added'); }, 600);
		}

		$('#catalogo-lista').on('click', '.catalogo-item', function () {
			var $item = $(this);
			var $lineasBodyDirecto = $('#lineas-body-directo');

			if ($item.data('linea-libre')) {
				var $filaLibre = crearFilaDirecta();
				$lineasBodyDirecto.append($filaLibre);
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

			var $fila = crearFilaDirecta({
				articulo_id: articulo.id,
				concepto: articulo.nombre,
				unidad: articulo.unidad,
				cantidad: 1,
				precio_unitario: articulo.precio,
				tipo_impositivo: articulo.tipo_impositivo,
			});
			$lineasBodyDirecto.append($fila);
			destellar($fila);

			recalcularPreview();
		});

		$('#lineas-body-directo').on('click', '.btn-remove-linea', function () {
			if ($('#lineas-body-directo .linea-row').length <= 1) {
				return;
			}
			$(this).closest('.linea-row').remove();
			recalcularPreview();
		});

		$('#lineas-body-directo').on('input change', 'input', recalcularPreview);
		$('#cliente_id').on('change', recalcularPreview);

		$('#albaran-form').on('submit', function () {
			var $form = $(this);
			var modo = modoActivo();

			if (modo === 'presupuesto') {
				var index = 0;
				$form.find('#lineas-body-presupuesto .linea-row').each(function () {
					var $row = $(this);
					var $cantidadInput = $row.find('.linea-cantidad');
					var cantidad = parseFloat($cantidadInput.val()) || 0;

					if (cantidad <= 0) {
						return;
					}

					$row.append('<input type="hidden" class="campo-generado" name="lineas[' + index + '][presupuesto_linea_id]" value="' + $row.data('presupuesto-linea-id') + '">');
					if ($row.data('articulo-id')) {
						$row.append('<input type="hidden" class="campo-generado" name="lineas[' + index + '][articulo_id]" value="' + $row.data('articulo-id') + '">');
					}
					$row.append('<input type="hidden" class="campo-generado" name="lineas[' + index + '][concepto]" value="' + $cantidadInput.attr('data-concepto') + '">');
					$row.append('<input type="hidden" class="campo-generado" name="lineas[' + index + '][unidad]" value="' + $cantidadInput.attr('data-unidad') + '">');
					$row.append('<input type="hidden" class="campo-generado" name="lineas[' + index + '][cantidad]" value="' + cantidad + '">');
					$row.append('<input type="hidden" class="campo-generado" name="lineas[' + index + '][precio_unitario]" value="' + $cantidadInput.attr('data-precio') + '">');
					$row.append('<input type="hidden" class="campo-generado" name="lineas[' + index + '][descuento_porcentaje]" value="' + $cantidadInput.attr('data-descuento') + '">');
					$row.append('<input type="hidden" class="campo-generado" name="lineas[' + index + '][tipo_impositivo]" value="' + $cantidadInput.attr('data-tipo') + '">');

					index++;
				});

				return;
			}

			var $filas = $form.find('#lineas-body-directo .linea-row');
			$filas.each(function () {
				var $row = $(this);
				var concepto = $.trim($row.find('.linea-concepto').val() || '');
				var articuloId = $row.attr('data-articulo-id') || '';

				if (!concepto && !articuloId && $form.find('#lineas-body-directo .linea-row').length > 1) {
					$row.remove();
				}
			});

			$form.find('#lineas-body-directo .linea-row').each(function (index) {
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
