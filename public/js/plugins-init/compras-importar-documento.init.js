/**
 * Importar compras desde documentos interpretados por IA (feature 044).
 *
 * La cola avanza documento a documento y en serie: cada `interpretar` es una llamada al modelo, y
 * hacerlas a la vez reventaría el `max_execution_time` del hosting compartido. Un documento que
 * falla no aborta el resto salvo que el fallo sea de la cuenta (clave inválida o cuota agotada),
 * donde seguir intentando no tiene sentido.
 *
 * Los totales que se pintan al editar son aproximación de UX: el importe que se guarda lo calcula
 * siempre el servidor (Principio III).
 */
(function () {
	'use strict';

	window.initImportacionDocumentosCompra = function (opciones) {
		var urls = opciones.urls;
		var $modal = $('#importarDocumentoModal');
		var modal = document.getElementById('importarDocumentoModal');

		var cola = [];
		var indice = 0;
		var propuesta = null;
		var resultados = { creadas: 0, descartadas: 0, fallidas: [] };

		function csrf() {
			return $('meta[name="csrf-token"]').attr('content');
		}

		function paso(cual) {
			$('#doc-paso-subir, #doc-paso-interpretando, #doc-paso-propuesta, #doc-paso-resumen').addClass('d-none');
			$('#doc-paso-' + cual).removeClass('d-none');

			$('#doc-btn-subir').toggleClass('d-none', cual !== 'subir');
			$('#doc-btn-crear').toggleClass('d-none', cual !== 'propuesta');
			$('#doc-btn-descartar').toggleClass('d-none', cual !== 'propuesta');
			$('#doc-btn-cerrar').text(cual === 'resumen' ? 'Cerrar' : 'Cancelar');
		}

		function reiniciar() {
			cola = [];
			indice = 0;
			propuesta = null;
			resultados = { creadas: 0, descartadas: 0, fallidas: [] };
			$('#importarDocumentoForm')[0].reset();
			$('#doc-rechazados').addClass('d-none').empty();
			paso('subir');
		}

		function contador() {
			return cola.length > 1 ? '(' + (indice + 1) + ' de ' + cola.length + ')' : '';
		}

		function euros(valor) {
			return (Number(valor) || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
		}

		// ------------------------------------------------------------------ subida del lote

		$('#importarDocumentoForm').on('submit', function (e) {
			e.preventDefault();

			var datos = new FormData(this);

			window
				.withButtonLoading($('#doc-btn-subir'), function () {
					return $.ajax({
						url: urls.subir,
						method: 'POST',
						data: datos,
						processData: false,
						contentType: false,
						dataType: 'json',
						headers: { Accept: 'application/json' },
					});
				})
				.done(function (respuesta) {
					pintarRechazados(respuesta.rechazados);
					cola = respuesta.documentos || [];
					indice = 0;

					if (cola.length === 0) {
						return;
					}

					interpretarActual();
				})
				.fail(function (xhr) {
					var cuerpo = xhr.responseJSON || {};

					if (cuerpo.documentos !== undefined || cuerpo.rechazados !== undefined) {
						pintarRechazados(cuerpo.rechazados);
						return;
					}

					window.showToast('error', mensajeDe(xhr, 'No se pudieron subir los documentos.'));
				});
		});

		function pintarRechazados(rechazados) {
			var $caja = $('#doc-rechazados');

			if (!rechazados || rechazados.length === 0) {
				$caja.addClass('d-none').empty();
				return;
			}

			var html = '<ul class="list-unstyled mb-0 text-danger">';
			rechazados.forEach(function (r) {
				html += '<li><strong>' + escapar(r.archivo_nombre) + '</strong>: ' + escapar(r.motivo) + '</li>';
			});
			$caja.removeClass('d-none').html(html + '</ul>');
		}

		// ------------------------------------------------------------------ interpretación

		function interpretarActual() {
			var documento = cola[indice];

			$('#doc-interpretando-nombre').text(documento.archivo_nombre);
			$('#doc-interpretando-contador').text(contador());
			paso('interpretando');

			$.ajax({
				url: urls.interpretar.replace('__TOKEN__', documento.token),
				method: 'POST',
				dataType: 'json',
				data: { archivo_nombre: documento.archivo_nombre },
				headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
			})
				.done(function (respuesta) {
					propuesta = respuesta;
					pintarPropuesta();
				})
				.fail(function (xhr) {
					var cuerpo = xhr.responseJSON || {};
					var codigo = cuerpo.codigo;

					resultados.fallidas.push({
						nombre: documento.archivo_nombre,
						motivo: cuerpo.motivo || cuerpo.message || 'No se pudo interpretar.',
					});

					// Un problema de la cuenta no se arregla con el documento siguiente.
					if (codigo === 'clave_invalida' || codigo === 'limite_excedido') {
						window.showToast('error', cuerpo.message || 'El servicio de IA no está disponible.');
						mostrarResumen();
						return;
					}

					window.showToast('warning', cuerpo.message || 'No se pudo interpretar el documento.');
					siguiente();
				});
		}

		// ------------------------------------------------------------------ propuesta

		function pintarPropuesta() {
			$('#doc-propuesta-nombre').text(propuesta.archivo_nombre);
			$('#doc-propuesta-contador').text(contador());
			$('#doc-numero').val(propuesta.numero_documento || '');
			$('#doc-fecha').val(propuesta.fecha || '');

			pintarAvisos();
			pintarProveedor();
			pintarLineas();
			recalcular();

			paso('propuesta');
		}

		function pintarAvisos() {
			var $caja = $('#doc-avisos').empty();

			(propuesta.avisos || []).forEach(function (aviso) {
				$caja.append(
					$('<div class="alert alert-warning py-2 px-3 mb-2"></div>').text(aviso.mensaje)
				);
			});

			if ((propuesta.campos_ilegibles || []).length > 0) {
				$caja.append(
					$('<div class="alert alert-info py-2 px-3 mb-2"></div>').text(
						'Hay ' + propuesta.campos_ilegibles.length + ' campo(s) que no se pudieron leer. Completalos antes de crear.'
					)
				);
			}
		}

		function pintarProveedor() {
			var proveedor = propuesta.proveedor || {};
			var $bloque = $('#doc-proveedor-bloque').empty();
			var $select = $('<select class="form-control" id="doc-proveedor"></select>');

			$select.append('<option value="">— Elegí un proveedor —</option>');
			(opciones.proveedores || []).forEach(function (p) {
				$select.append($('<option></option>').val(p.id).text(p.nombre));
			});

			if (proveedor.proveedor_id) {
				$select.val(String(proveedor.proveedor_id));
			}

			$bloque.append($select);

			if (proveedor.criterio === 'nombre') {
				$bloque.append(
					'<small class="text-warning d-block mt-1">Coincidencia por nombre' +
						(proveedor.similitud ? ' (' + Math.round(proveedor.similitud) + '%)' : '') +
						'. Confirmá que es el correcto.</small>'
				);
			} else if (proveedor.criterio === 'sin_coincidencia') {
				pintarAltaProveedor($bloque, proveedor.datos_nuevos || {});
			}
		}

		/**
		 * FR-019: si no se reconoce al emisor, se ofrece darlo de alta con lo que la IA leyó, ya
		 * cargado y editable. El alta se confirma con un check explícito — nunca por `blur`, según
		 * la guía "Alta inline en un listado".
		 */
		function pintarAltaProveedor($bloque, datos) {
			var leido = datos.nombre || datos.razon_social;

			$bloque.append(
				'<small class="text-muted d-block mt-1">' +
					(leido ? 'No se reconoció a «' + escapar(leido) + '».' : 'No se reconoció al proveedor.') +
					' Elegí uno de la lista o <a href="#" id="doc-abrir-alta">creá uno nuevo con los datos del documento</a>.</small>'
			);

			var $alta = $(
				'<div id="doc-alta-proveedor" class="border rounded p-2 mt-2 d-none">' +
					'<div class="row g-2">' +
						'<div class="col-md-6"><label class="form-label mb-1">Nombre *</label><input type="text" class="form-control" id="doc-nuevo-nombre"></div>' +
						'<div class="col-md-6"><label class="form-label mb-1">NIF</label><input type="text" class="form-control" id="doc-nuevo-nif"></div>' +
						'<div class="col-md-12"><label class="form-label mb-1">Razón social</label><input type="text" class="form-control" id="doc-nuevo-razon"></div>' +
						'<div class="col-md-12"><label class="form-label mb-1">Dirección</label><input type="text" class="form-control" id="doc-nuevo-direccion"></div>' +
						'<div class="col-md-3"><label class="form-label mb-1">CP</label><input type="text" class="form-control" id="doc-nuevo-cp"></div>' +
						'<div class="col-md-4"><label class="form-label mb-1">Ciudad</label><input type="text" class="form-control" id="doc-nuevo-ciudad"></div>' +
						'<div class="col-md-5"><label class="form-label mb-1">Provincia</label><input type="text" class="form-control" id="doc-nuevo-provincia"></div>' +
						'<div class="col-md-6"><label class="form-label mb-1">Email</label><input type="email" class="form-control" id="doc-nuevo-email"></div>' +
						'<div class="col-md-6"><label class="form-label mb-1">Teléfono</label><input type="text" class="form-control" id="doc-nuevo-telefono"></div>' +
					'</div>' +
					'<div class="d-flex justify-content-end gap-2 mt-2">' +
						'<button type="button" class="btn btn-outline-secondary btn-sm" id="doc-alta-cancelar" title="Descartar el alta">&times;</button>' +
						'<button type="button" class="btn btn-primary btn-sm" id="doc-alta-confirmar" title="Usar estos datos">&check; Usar estos datos</button>' +
					'</div>' +
				'</div>'
			);

			$bloque.append($alta);

			// Precargado con lo que se leyó del documento; todo editable antes de confirmar.
			$alta.find('#doc-nuevo-nombre').val(datos.nombre || datos.razon_social || '');
			$alta.find('#doc-nuevo-nif').val(datos.nif || '');
			$alta.find('#doc-nuevo-razon').val(datos.razon_social || '');
			$alta.find('#doc-nuevo-direccion').val(datos.direccion || '');
			$alta.find('#doc-nuevo-cp').val(datos.cp || '');
			$alta.find('#doc-nuevo-ciudad').val(datos.ciudad || '');
			$alta.find('#doc-nuevo-provincia').val(datos.provincia || '');
			$alta.find('#doc-nuevo-email').val(datos.email || '');
			$alta.find('#doc-nuevo-telefono').val(datos.telefono || '');

			sincronizarBotonAlta();
		}

		function altaAbierta() {
			var $alta = $('#doc-alta-proveedor');

			return $alta.length > 0 && !$alta.hasClass('d-none');
		}

		function sincronizarBotonAlta() {
			// El check queda deshabilitado mientras no haya nombre: es el único campo obligatorio.
			$('#doc-alta-confirmar').prop('disabled', !$('#doc-nuevo-nombre').val());
		}

		$(document).on('click', '#doc-abrir-alta', function (e) {
			e.preventDefault();
			$('#doc-alta-proveedor').removeClass('d-none');
			$('#doc-proveedor').val('').prop('disabled', true);
			$('#doc-nuevo-nombre').trigger('focus');
			sincronizarBotonAlta();
		});

		$(document).on('input', '#doc-nuevo-nombre', sincronizarBotonAlta);

		$(document).on('click', '#doc-alta-cancelar', cerrarAlta);

		function cerrarAlta() {
			$('#doc-alta-proveedor').addClass('d-none');
			$('#doc-proveedor').prop('disabled', false);
		}

		// Enter confirma, Escape descarta. Perder el foco NO hace nada (guía de front).
		$(document).on('keydown', '#doc-alta-proveedor input', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();

				if ($('#doc-nuevo-nombre').val()) {
					$('#doc-btn-crear').trigger('click');
				}
			} else if (e.key === 'Escape') {
				e.preventDefault();
				cerrarAlta();
			}
		});

		function pintarLineas() {
			var $cuerpo = $('#doc-lineas-tabla tbody').empty();

			(propuesta.lineas || []).forEach(function (linea) {
				$cuerpo.append(filaLinea(linea));
			});
		}

		function filaLinea(linea) {
			var $fila = $('<tr></tr>');
			var articulo = linea.articulo || {};

			var $concepto = $('<td></td>').append($('<input type="text" class="form-control doc-concepto">').val(linea.concepto || ''));

			// Selector de artículo: una línea sin artículo es válida (no mueve stock).
			var $select = $('<select class="form-control form-select doc-articulo mt-1"></select>');
			$select.append('<option value="">— Sin artículo (línea libre) —</option>');

			(opciones.articulos || []).forEach(function (a) {
				$select.append($('<option></option>').val(a.id).text(a.nombre).attr('data-stock', a.mueve_stock ? '1' : ''));
			});

			if (articulo.articulo_id) {
				$select.val(String(articulo.articulo_id));
			}

			$concepto.append($select);

			if (articulo.criterio === 'nombre') {
				$concepto.append(
					'<small class="text-warning d-block">Sugerido por nombre' +
						(articulo.similitud ? ' (' + Math.round(articulo.similitud) + '%)' : '') +
						' — confirmá que es el correcto.</small>'
				);
			} else if (articulo.criterio === 'referencia') {
				$concepto.append('<small class="text-success d-block">Emparejado por referencia del proveedor.</small>');
			}

			$fila.append($concepto);
			$fila.append($('<td></td>').append($('<input type="text" class="form-control doc-unidad">').val(linea.unidad || '')));
			$fila.append($('<td></td>').append($('<input type="number" step="any" min="0" class="form-control doc-cantidad">').val(linea.cantidad)));
			$fila.append($('<td></td>').append($('<input type="number" step="any" min="0" class="form-control doc-precio">').val(linea.precio_unitario)));

			var $tipo = $('<input type="number" step="any" min="0" class="form-control doc-tipo">').val(linea.tipo_impositivo);

			if (linea.tipo_impositivo_coherente === false) {
				$tipo.addClass('border-warning').attr('title', 'Este tipo no es habitual en el régimen de tu empresa.');
			}

			$fila.append($('<td></td>').append($tipo));
			$fila.append($('<td class="text-end doc-base"></td>').text(euros(linea.base)));
			$fila.append(
				$('<td></td>').append(
					$('<button type="button" class="btn btn-outline-danger btn-sm doc-quitar" title="Quitar línea">&times;</button>')
				)
			);

			return $fila;
		}

		$('#doc-anadir-linea').on('click', function () {
			$('#doc-lineas-tabla tbody').append(filaLinea({}));
		});

		$('#doc-lineas-tabla').on('click', '.doc-quitar', function () {
			$(this).closest('tr').remove();
			recalcular();
		});

		$('#doc-lineas-tabla').on('input', 'input', recalcular);
		$('#doc-lineas-tabla').on('change', '.doc-articulo', avisarDeStock);

		function lineasDelFormulario() {
			var lineas = [];

			$('#doc-lineas-tabla tbody tr').each(function () {
				var $f = $(this);

				lineas.push({
					articulo_id: $f.find('.doc-articulo').val() || null,
					concepto: $f.find('.doc-concepto').val(),
					unidad: $f.find('.doc-unidad').val() || null,
					cantidad: $f.find('.doc-cantidad').val(),
					precio_unitario: $f.find('.doc-precio').val(),
					tipo_impositivo: $f.find('.doc-tipo').val(),
				});
			});

			return lineas;
		}

		function recalcular() {
			var base = 0;
			var cuota = 0;

			$('#doc-lineas-tabla tbody tr').each(function () {
				var $f = $(this);
				var lineaBase = Math.round((Number($f.find('.doc-cantidad').val()) || 0) * (Number($f.find('.doc-precio').val()) || 0) * 100) / 100;
				// base × tipo/100 redondeado a 2 decimales se simplifica a round(base × tipo)/100.
				var lineaCuota = Math.round(lineaBase * (Number($f.find('.doc-tipo').val()) || 0)) / 100;

				$f.find('.doc-base').text(euros(lineaBase));
				base += lineaBase;
				cuota += lineaCuota;
			});

			$('#doc-total-base').text(euros(base));
			$('#doc-total-cuota').text(euros(cuota));
			$('#doc-total-total').text(euros(base + cuota));

			avisarDeStock();
		}

		/**
		 * Confirmar la compra más tarde moverá inventario: conviene decirlo antes de crearla, no
		 * después.
		 */
		function avisarDeStock() {
			var conStock = $('#doc-lineas-tabla tbody .doc-articulo')
				.filter(function () {
					return $(this).find('option:selected').data('stock') === 1;
				}).length;

			$('#doc-aviso-stock').remove();

			if (conStock > 0) {
				$('#doc-lineas-tabla').after(
					$('<small id="doc-aviso-stock" class="text-muted d-block mb-2"></small>').text(
						conStock + ' línea(s) moverán inventario cuando confirmes la compra.'
					)
				);
			}
		}

		// ------------------------------------------------------------------ crear / descartar

		$('#doc-btn-crear').on('click', function () {
			enviarCreacion(false);
		});

		function enviarCreacion(confirmarDuplicado) {
			var crearProveedor = altaAbierta();

			var carga = {
				proveedor_id: crearProveedor ? null : $('#doc-proveedor').val() || null,
				crear_proveedor: crearProveedor,
				proveedor_nuevo: crearProveedor
					? {
							nombre: $('#doc-nuevo-nombre').val(),
							razon_social: $('#doc-nuevo-razon').val() || null,
							nif: $('#doc-nuevo-nif').val() || null,
							direccion: $('#doc-nuevo-direccion').val() || null,
							cp: $('#doc-nuevo-cp').val() || null,
							ciudad: $('#doc-nuevo-ciudad').val() || null,
							provincia: $('#doc-nuevo-provincia').val() || null,
							email: $('#doc-nuevo-email').val() || null,
							telefono: $('#doc-nuevo-telefono').val() || null,
					  }
					: null,
				numero_documento: $('#doc-numero').val() || null,
				fecha: $('#doc-fecha').val(),
				confirmar_duplicado: confirmarDuplicado,
				lineas: lineasDelFormulario(),
			};

			window
				.withButtonLoading($('#doc-btn-crear'), function () {
					return $.ajax({
						url: urls.crear.replace('__TOKEN__', cola[indice].token),
						method: 'POST',
						dataType: 'json',
						contentType: 'application/json',
						data: JSON.stringify(carga),
						headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
					});
				})
				.done(function (respuesta) {
					resultados.creadas++;
					window.showToast('success', respuesta.message);
					siguiente();
				})
				.fail(function (xhr) {
					if (xhr.status === 409) {
						var existente = (xhr.responseJSON || {}).compra_existente || {};

						if (window.confirm('Ya existe una compra con el mismo proveedor, número y fecha. ¿Crearla igualmente?')) {
							enviarCreacion(true);
						}

						return;
					}

					window.showToast('error', mensajeDe(xhr, 'No se pudo crear la compra.'));
				});
		}

		$('#doc-btn-descartar').on('click', function () {
			$.ajax({
				url: urls.descartar.replace('__TOKEN__', cola[indice].token),
				method: 'DELETE',
				dataType: 'json',
				headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
			}).always(function () {
				resultados.descartadas++;
				siguiente();
			});
		});

		// ------------------------------------------------------------------ avance de la cola

		function siguiente() {
			indice++;

			if (indice < cola.length) {
				interpretarActual();
				return;
			}

			mostrarResumen();
		}

		function mostrarResumen() {
			var partes = [];

			if (resultados.creadas > 0) {
				partes.push(resultados.creadas + ' compra(s) creada(s)');
			}
			if (resultados.descartadas > 0) {
				partes.push(resultados.descartadas + ' descartada(s)');
			}
			if (resultados.fallidas.length > 0) {
				partes.push(resultados.fallidas.length + ' con problemas');
			}

			$('#doc-resumen-titulo').text(partes.length ? partes.join(', ') + '.' : 'No se procesó ningún documento.');

			var $detalle = $('#doc-resumen-detalle').empty();

			resultados.fallidas.forEach(function (fallo) {
				$detalle.append(
					$('<li class="text-danger mb-1"></li>').text(fallo.nombre + ': ' + fallo.motivo)
				);
			});

			paso('resumen');

			if (resultados.creadas > 0 && opciones.tabla) {
				$(opciones.tabla).DataTable().ajax.reload(null, false);
			}
		}

		// ------------------------------------------------------------------ utilidades

		function mensajeDe(xhr, porDefecto) {
			var cuerpo = xhr.responseJSON || {};

			if (cuerpo.errors) {
				var primera = Object.keys(cuerpo.errors)[0];

				return cuerpo.errors[primera][0];
			}

			return cuerpo.message || porDefecto;
		}

		function escapar(texto) {
			return $('<div></div>').text(texto == null ? '' : texto).html();
		}

		modal.addEventListener('hidden.bs.modal', reiniciar);
	};
})();
