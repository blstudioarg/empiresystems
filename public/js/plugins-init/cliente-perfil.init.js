/**
 * Perfil del cliente (feature 035).
 *
 * Tres responsabilidades: el formulario editable de "Datos generales" (AJAX, mismo patrón que
 * profile/show.blade.php), las cinco DataTables de las pestañas de documentos, y los gráficos
 * del resumen financiero (Chart.js, mismo stack que el dashboard).
 */
(function ($) {
	'use strict';

	var state = window.clientePerfilState || {};

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	// --- Idioma compartido por las cinco tablas ---

	function idioma(vacio) {
		return {
			search: 'Buscar:',
			lengthMenu: 'Mostrar _MENU_ registros',
			info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
			infoEmpty: 'Mostrando 0 a 0 de 0 registros',
			infoFiltered: '(filtrado de _MAX_ registros totales)',
			zeroRecords: 'No se encontraron resultados',
			emptyTable: vacio,
			processing: 'Cargando...',
			paginate: {
				first: 'Primero',
				last: 'Último',
				next: 'Siguiente',
				previous: 'Anterior',
			},
		};
	}

	function crearTabla(selector, recurso, columnas, vacio, ordenInicial) {
		var $tabla = $(selector);

		if (!$tabla.length) {
			return null;
		}

		return $tabla.DataTable({
			responsive: true,
			processing: true,
			ajax: {
				url: state.recursoUrl,
				// El recurso va en `data`, no interpolado en `url`: DataTables no acepta una
				// función en ajax.url y arma una URL inválida (ver docs/04-front-guidelines.md).
				data: { recurso: recurso },
				dataSrc: 'data',
				headers: { Accept: 'application/json' },
			},
			columns: columnas,
			order: ordenInicial || [[0, 'desc']],
			language: idioma(vacio),
		});
	}

	// --- Badges de estado (mismos colores que los listados de cada módulo) ---

	var badgesEstadoCobro = {
		pendiente: 'badge-secondary',
		parcial: 'badge-warning',
		cobrada: 'badge-success',
	};

	var etiquetasEstadoCobro = {
		pendiente: 'Pendiente',
		parcial: 'Parcial',
		cobrada: 'Cobrada',
	};

	var badgesEstadoDocumento = {
		borrador: 'badge-secondary',
		enviado: 'badge-info',
		aceptado: 'badge-success',
		rechazado: 'badge-danger',
		caducado: 'badge-dark',
		facturado: 'badge-primary',
		entregado: 'badge-success',
		anulado: 'badge-dark',
	};

	var badgesEtapa = {
		nueva: 'badge-info',
		en_negociacion: 'badge-warning',
		ganada: 'badge-success',
		perdida: 'badge-danger',
	};

	function badge(clase, texto) {
		return '<span class="badge light ' + clase + '">' + escapeHtml(texto) + '</span>';
	}

	// Columna de fecha: se muestra dd/mm/aaaa pero se ordena por el ISO (`fecha_orden`), si no
	// DataTables ordenaría alfabéticamente el texto formateado.
	function columnaFecha() {
		return {
			data: 'fecha',
			render: function (data, type, row) {
				if (type === 'sort' || type === 'type') {
					return row.fecha_orden || '';
				}
				return escapeHtml(data);
			},
		};
	}

	function columnaImporte(campoTexto, campoOrden) {
		return {
			data: campoTexto,
			render: function (data, type, row) {
				if (type === 'sort' || type === 'type') {
					return row[campoOrden] || 0;
				}
				return escapeHtml(data);
			},
		};
	}

	// Acciones: siempre dropdown único (docs/04-front-guidelines.md → "Columna Acciones").
	function dropdownAcciones(items) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">' +
					'Acciones' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' + items + '</ul>' +
			'</div>'
		);
	}

	function itemVerPdf(url, titulo) {
		return (
			'<li><button type="button" class="dropdown-item btn-ver-documento"' +
			' data-pdf-url="' + escapeAttr(url) + '"' +
			' data-titulo="' + escapeAttr(titulo) + '">Ver</button></li>'
		);
	}

	function itemVerEnlace(url) {
		return '<li><a class="dropdown-item" href="' + escapeAttr(url) + '">Ver</a></li>';
	}

	// --- Tablas ---

	crearTabla('#facturas-cliente-table', 'facturas', [
		{ data: 'identificador', render: escapeHtml },
		columnaFecha(),
		columnaImporte('total', 'total_orden'),
		{
			data: 'estado_cobro',
			render: function (data) {
				return badge(badgesEstadoCobro[data] || 'badge-secondary', etiquetasEstadoCobro[data] || data);
			},
		},
		{
			data: null,
			orderable: false,
			render: function (data, type, row) {
				return dropdownAcciones(itemVerPdf(row.pdf_url, 'Factura ' + row.identificador));
			},
		},
	], 'Este cliente todavía no tiene facturas.', [[1, 'desc']]);

	crearTabla('#presupuestos-cliente-table', 'presupuestos', [
		{ data: 'identificador', render: escapeHtml },
		columnaFecha(),
		columnaImporte('total', 'total_orden'),
		{
			data: 'estado_label',
			render: function (data, type, row) {
				return badge(badgesEstadoDocumento[row.estado] || 'badge-secondary', data);
			},
		},
		{
			data: null,
			orderable: false,
			render: function (data, type, row) {
				return dropdownAcciones(itemVerPdf(row.pdf_url, 'Presupuesto ' + row.identificador));
			},
		},
	], 'Este cliente todavía no tiene presupuestos.', [[1, 'desc']]);

	crearTabla('#albaranes-cliente-table', 'albaranes', [
		{ data: 'identificador', render: escapeHtml },
		columnaFecha(),
		columnaImporte('total', 'total_orden'),
		{
			data: 'estado_label',
			render: function (data, type, row) {
				return badge(badgesEstadoDocumento[row.estado] || 'badge-secondary', data);
			},
		},
		{
			data: null,
			orderable: false,
			render: function (data, type, row) {
				// Los albaranes no tienen PDF propio: su detalle es una vista de la app.
				return dropdownAcciones(itemVerEnlace(row.show_url));
			},
		},
	], 'Este cliente todavía no tiene albaranes.', [[1, 'desc']]);

	crearTabla('#oportunidades-cliente-table', 'oportunidades', [
		{ data: 'titulo', render: escapeHtml },
		{
			data: 'etapa_label',
			render: function (data, type, row) {
				return badge(badgesEtapa[row.etapa] || 'badge-secondary', data);
			},
		},
		columnaImporte('importe', 'importe_orden'),
		{
			data: null,
			orderable: false,
			render: function (data, type, row) {
				return dropdownAcciones(itemVerEnlace(row.show_url));
			},
		},
	], 'Este cliente todavía no tiene oportunidades.', [[2, 'desc']]);

	crearTabla('#actividad-cliente-table', 'actividad', [
		columnaFecha(),
		{ data: 'tipo', render: escapeHtml },
		{ data: 'etiqueta', render: escapeHtml },
		{
			data: null,
			orderable: false,
			render: function (data, type, row) {
				return dropdownAcciones(
					row.es_pdf ? itemVerPdf(row.url, row.etiqueta) : itemVerEnlace(row.url)
				);
			},
		},
	], 'Este cliente todavía no tiene actividad registrada.', [[0, 'desc']]);

	// Una DataTable creada dentro de una pestaña oculta mide 0px de ancho y reparte mal las
	// columnas; al mostrarse la pestaña hay que recalcularlas.
	$('#perfil-cliente-tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (evento) {
		$($(evento.target).data('bs-target'))
			.find('table')
			.each(function () {
				if ($.fn.DataTable.isDataTable(this)) {
					$(this).DataTable().columns.adjust().responsive.recalc();
				}
			});
	});

	// --- Vista previa de documentos en modal (nunca otra pestaña) ---

	$(document).on('click', '.btn-ver-documento', function () {
		var $boton = $(this);

		$('#documentoPdfModalLabel').text($boton.data('titulo') || 'Vista previa del documento');
		$('#documentoPdfFrame').attr('src', $boton.data('pdf-url'));
		bootstrap.Modal.getOrCreateInstance(document.getElementById('documentoPdfModal')).show();
	});

	// Vaciar el src al cerrar: si no, al reabrir se ve un instante el documento anterior.
	$('#documentoPdfModal').on('hidden.bs.modal', function () {
		$('#documentoPdfFrame').attr('src', '');
	});

	// --- Datos generales editables ---

	$(function () {
		var $form = $('#cliente-perfil-form');

		if (!$form.length) {
			return;
		}

		var $provincia = $form.find('#provincia');
		var $ciudad = $form.find('#ciudad');
		var cliente = state.cliente || {};

		function limpiarErrores() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		function mostrarErrores(errores) {
			limpiarErrores();

			$.each(errores, function (campo, mensajes) {
				$form.find('[name="' + campo + '"]').addClass('is-invalid');
				$form.find('[data-error-for="' + campo + '"]').text(mensajes[0]);
			});
		}

		function cargarLocalidades(provinciaId, seleccionada) {
			if (!provinciaId) {
				$ciudad.html('<option value="">Selecciona una provincia primero</option>');
				return;
			}

			$ciudad.prop('disabled', true).html('<option value="">Cargando...</option>');

			$.ajax({
				url: state.localidadesUrl,
				method: 'GET',
				data: { provincia_id: provinciaId },
				dataType: 'json',
			})
				.done(function (localidades) {
					var opciones = '<option value="">Selecciona una localidad</option>';

					$.each(localidades, function (_, localidad) {
						opciones += '<option value="' + escapeAttr(localidad.nombre) + '">' + escapeHtml(localidad.nombre) + '</option>';
					});

					$ciudad.html(opciones);

					if (seleccionada) {
						$ciudad.val(seleccionada);
					}
				})
				.fail(function () {
					$ciudad.html('<option value="">No se pudieron cargar las localidades</option>');
				})
				.always(function () {
					$ciudad.prop('disabled', false);
				});
		}

		$provincia.on('change', function () {
			cargarLocalidades($provincia.find('option:selected').data('provincia-id'), null);
		});

		// El partial _form.blade.php se declara vacío (sirve para alta y edición); acá lo
		// rellenamos con el cliente actual.
		$form.find('#tipo').val(cliente.tipo);
		$form.find('#nombre').val(cliente.nombre);
		$form.find('#razon_social').val(cliente.razon_social);
		$form.find('#nif').val(cliente.nif);
		$form.find('#direccion').val(cliente.direccion);
		$form.find('#cp').val(cliente.cp);
		$form.find('#pais').val(cliente.pais);
		$form.find('#email').val(cliente.email);
		$form.find('#telefono').val(cliente.telefono);
		$form.find('#notas').val(cliente.notas);
		$form.find('#aplica_recargo_equivalencia').prop('checked', !!cliente.aplica_recargo_equivalencia);
		$provincia.val(cliente.provincia);
		cargarLocalidades($provincia.find('option:selected').data('provincia-id'), cliente.ciudad);

		$form.on('submit', function (evento) {
			evento.preventDefault();

			var $boton = $('#btn-guardar-cliente');

			window.withButtonLoading($boton, function () {
				return $.ajax({
					url: state.updateUrl,
					method: 'POST',
					data: $form.serialize() + '&_method=PUT&_token=' + encodeURIComponent($('meta[name="csrf-token"]').attr('content')),
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (respuesta) {
					limpiarErrores();
					window.showToast('success', respuesta.message || 'Cliente actualizado correctamente.');
					// La cabecera del perfil (nombre, NIF, email) se renderiza en servidor:
					// recargamos para que refleje los datos recién guardados.
					window.location.reload();
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						mostrarErrores(xhr.responseJSON.errors || {});
						window.showToast('error', 'Revisa los campos marcados.');
						return;
					}

					window.showToast('error', 'No se pudo actualizar el cliente. Inténtalo de nuevo.');
				});
		});
	});

	// --- Gráficos del resumen financiero ---

	$(function () {
		var graficos = state.graficos;

		if (!graficos || typeof window.Chart === 'undefined') {
			return;
		}

		var canvasEvolucion = document.getElementById('chart-cliente-evolucion');
		if (canvasEvolucion) {
			new Chart(canvasEvolucion, {
				type: 'bar',
				data: {
					labels: graficos.evolucion.map(function (p) { return p.etiqueta; }),
					datasets: [
						{
							label: 'Facturado',
							data: graficos.evolucion.map(function (p) { return p.facturado; }),
							backgroundColor: '#1D69D6',
						},
						{
							label: 'Cobrado',
							data: graficos.evolucion.map(function (p) { return p.cobrado; }),
							backgroundColor: '#22C55E',
						},
					],
				},
				options: {
					responsive: true,
					scales: { y: { beginAtZero: true } },
				},
			});
		}

		var canvasCobro = document.getElementById('chart-cliente-cobro');
		if (canvasCobro) {
			new Chart(canvasCobro, {
				type: 'doughnut',
				data: {
					labels: graficos.cobro.map(function (p) { return p.etiqueta; }),
					datasets: [{
						data: graficos.cobro.map(function (p) { return p.importe; }),
						backgroundColor: ['#22C55E', '#F59E0B', '#EF4444'],
					}],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
				},
			});
		}
	});
})(jQuery);
