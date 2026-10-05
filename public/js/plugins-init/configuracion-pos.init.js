/**
 * Configuración → POS (feature 038): flags del módulo de hostelería; idioma del POS y
 * traducciones (feature 050).
 *
 * El CRUD de zonas y mesas vive en POS → Sala (plano arrastrable, feature 039), no aquí.
 */
(function ($) {
	'use strict';

	var state = window.posConfigState || {};

	function csrf() {
		return $('meta[name="csrf-token"]').attr('content');
	}

	function initFormulario() {
		var $form = $('#pos-config-form');

		if (!$form.length) { return; }

		var $maestro = $('#pos_hosteleria_activo');

		function reflejarDependientes() {
			$('.pos-config-dependiente').toggleClass('apagado', !$maestro.is(':checked'));
		}

		$maestro.on('change', reflejarDependientes);
		reflejarDependientes();

		$form.on('submit', function (e) {
			e.preventDefault();

			var $btn = $('#pos-config-guardar');

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: state.updateUrl,
					method: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json' },
					data: {
						_token: csrf(),
						_method: 'PUT',
						hosteleria_activo: $maestro.is(':checked') ? 1 : 0,
						opciones_activo: $('#pos_opciones_activo').is(':checked') ? 1 : 0,
						cobro_dividido_activo: $('#pos_cobro_dividido_activo').is(':checked') ? 1 : 0,
						suplemento_zona_activo: $('#pos_suplemento_zona_activo').is(':checked') ? 1 : 0,
						mesa_olvidada_min: $('#pos_mesa_olvidada_min').val(),
						caja_umbral_descuadre: $('#pos_caja_umbral_descuadre').val(),
					},
				});
			})
				.done(function (res) {
					window.showToast('success', res.message || 'Configuración guardada.');
				})
				.fail(function (xhr) {
					// El 422 más habitual aquí es "hay N cuentas abiertas" (FR-006): se muestra
					// tal cual porque el mensaje del servidor ya dice cuántas son.
					var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo guardar la configuración.';
					window.showToast('error', msg);
				});
		});
	}

	/**
	 * Idioma del POS (feature 050): se guarda solo, sin arrastrar los flags del módulo. Si con el
	 * idioma nuevo quedan textos sin traducir todavía, se avisa (se verán en español hasta que se
	 * traduzcan solos).
	 */
	function initIdioma() {
		var $form = $('#pos-idioma-form');

		if (!$form.length) { return; }

		$form.on('submit', function (e) {
			e.preventDefault();

			var $btn = $('#pos-idioma-guardar');

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: state.updateUrl,
					method: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json' },
					data: { _token: csrf(), _method: 'PUT', idioma: $('#pos_idioma').val() },
				});
			})
				.done(function (res) {
					window.showToast('success', 'Idioma del POS guardado.');

					var $aviso = $('#pos-idioma-pendientes');
					if (res.idioma !== 'es' && res.traducciones_pendientes > 0) {
						$aviso.text('Hay ' + res.traducciones_pendientes + ' textos del POS todavía sin traducir: se verán en español hasta que se traduzcan automáticamente.').removeClass('d-none');
					} else {
						$aviso.addClass('d-none');
					}

					$(document).trigger('pos-idioma:cambiado', [res.idioma]);
				})
				.fail(function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo guardar el idioma.';
					window.showToast('error', msg);
				});
		});
	}

	// ── Traducciones del POS (feature 050, US4) ──────────────────────────────────────────

	var ORIGENES = {
		automatica: '<span class="badge light badge-secondary">Automática</span>',
		corregida: '<span class="badge light badge-success">Corregida</span>',
		pendiente: '<span class="badge light badge-warning">Pendiente</span>',
	};

	var tablaTraducciones = null;
	var filaEditada = null;

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function urlTraduccion(hash) {
		return state.traduccionUrlTemplate.replace(/0{64}$/, hash);
	}

	function variablesDe(texto) {
		return (texto.match(/:[A-Za-z_][A-Za-z0-9_]*/g) || []).filter(function (v, i, todas) {
			return todas.indexOf(v) === i;
		});
	}

	function renderAccionesTraduccion(data, type, row) {
		var acciones = '<li><button type="button" class="dropdown-item btn-edit-traduccion">Corregir</button></li>';

		if (row.origen === 'corregida') {
			acciones += '<li><hr class="dropdown-divider"></li>' +
				'<li><button type="button" class="dropdown-item text-danger btn-restaurar-traduccion" data-hash="' + escapeHtml(row.hash) + '">Restaurar automática</button></li>';
		}

		return '<div class="dropdown" data-hash="' + escapeHtml(row.hash) + '">' +
			'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Acciones</button>' +
			'<ul class="dropdown-menu dropdown-menu-end">' + acciones + '</ul>' +
			'</div>';
	}

	function filaPorHash(hash) {
		return tablaTraducciones.rows().data().toArray().filter(function (f) { return f.hash === hash; })[0];
	}

	function initTablaTraducciones() {
		var $table = $('#pos-traducciones-table');

		if (!$table.length) { return; }
		if (tablaTraducciones) { tablaTraducciones.ajax.reload(null, false); return; }

		tablaTraducciones = $table.DataTable({
			responsive: true,
			processing: true,
			ajax: { url: state.traduccionesUrl, headers: { Accept: 'application/json' }, dataSrc: 'data' },
			order: [[0, 'asc']],
			columns: [
				{ data: 'texto', className: 'pos-traduccion-texto', render: function (d) { return escapeHtml(d); } },
				{ data: 'traduccion', className: 'pos-traduccion-texto', render: function (d) { return d ? escapeHtml(d) : '<span class="text-muted">—</span>'; } },
				{ data: 'origen', render: function (d, type) { return type === 'display' ? (ORIGENES[d] || '') : d; } },
				{ data: null, orderable: false, searchable: false, render: renderAccionesTraduccion },
			],
			language: {
				search: 'Buscar:',
				lengthMenu: 'Mostrar _MENU_ registros',
				info: 'Mostrando _START_ a _END_ de _TOTAL_ textos',
				infoEmpty: 'Mostrando 0 a 0 de 0 textos',
				infoFiltered: '(filtrado de _MAX_ textos totales)',
				zeroRecords: 'No se encontraron textos',
				emptyTable: 'Todavía no hay textos del POS registrados',
				processing: 'Cargando...',
				paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
			},
		});

		// La fila se busca por el hash del dropdown y no por el `<tr>`: con `responsive` el dropdown
		// puede acabar en la fila hija, que no tiene datos propios.
		$(document).on('click', '#pos-traducciones-table .btn-edit-traduccion', function () {
			abrirModalTraduccion(filaPorHash($(this).closest('[data-hash]').data('hash')));
		});

		$(document).on('click', '#pos-traducciones-table .btn-restaurar-traduccion', function () {
			var hash = $(this).data('hash');

			window.confirmDelete('¿Restaurar la traducción automática? Se descarta tu corrección de este texto.', function () {
				return $.ajax({
					url: urlTraduccion(hash),
					method: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json' },
					data: { _token: csrf(), _method: 'DELETE' },
				})
					.done(function () {
						window.showToast('success', 'Traducción automática restaurada.');
						tablaTraducciones.ajax.reload(null, false);
					})
					.fail(function (xhr) {
						window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo restaurar la traducción.');
					});
			}, { confirmLabel: 'Restaurar automática' });
		});
	}

	function abrirModalTraduccion(fila) {
		if (!fila) { return; }
		filaEditada = fila;

		var $form = $('#pos-traduccion-form');
		$form.find('.is-invalid').removeClass('is-invalid');
		$form.find('[data-error-for]').text('');

		$('#pos-traduccion-original').text(fila.texto);
		$('#pos-traduccion-automatica').text(fila.automatica || 'Todavía sin traducción automática.');
		$('#pos_traduccion_texto').val(fila.traduccion || fila.automatica || '');

		var variables = variablesDe(fila.texto);
		$('#pos-traduccion-variables')
			.toggleClass('d-none', variables.length === 0)
			.html(variables.length
				? 'Conserva tal cual: ' + variables.map(function (v) { return '<code>' + escapeHtml(v) + '</code>'; }).join(', ') + ' (ahí va el dato: la mesa, el importe…).'
				: '');
		$('#pos-traduccion-html').toggleClass('d-none', !fila.es_html);

		bootstrap.Modal.getOrCreateInstance(document.getElementById('posTraduccionModal')).show();
	}

	function initModalTraduccion() {
		var $form = $('#pos-traduccion-form');

		if (!$form.length) { return; }

		$form.on('submit', function (e) {
			e.preventDefault();
			if (!filaEditada) { return; }

			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');

			window.withButtonLoading($('#pos-traduccion-guardar'), function () {
				return $.ajax({
					url: urlTraduccion(filaEditada.hash),
					method: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json' },
					data: { _token: csrf(), _method: 'PUT', traduccion: $('#pos_traduccion_texto').val() },
				});
			})
				.done(function () {
					bootstrap.Modal.getOrCreateInstance(document.getElementById('posTraduccionModal')).hide();
					window.showToast('success', 'Traducción corregida.');
					tablaTraducciones.ajax.reload(null, false);
				})
				.fail(function (xhr) {
					var errores = xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors;
					if (errores && errores.traduccion) {
						$('#pos_traduccion_texto').addClass('is-invalid');
						$form.find('[data-error-for="traduccion"]').text(errores.traduccion[0]);
						return;
					}
					window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo guardar la traducción.');
				});
		});
	}

	/**
	 * La sección solo existe con el POS en otro idioma. La tabla se inicializa al mostrar la
	 * pestaña (dentro de una pestaña oculta DataTables mide mal las columnas) y se recarga al
	 * cambiar de idioma.
	 */
	function initTraducciones() {
		var $seccion = $('#pos-traducciones');

		if (!$seccion.length) { return; }

		function visible() { return !!state.idioma && state.idioma !== 'es'; }

		function refrescar() {
			$seccion.toggleClass('d-none', !visible());
			if (visible() && $('#tab-pos').hasClass('active')) { initTablaTraducciones(); }
		}

		$('#tab-pos-btn').on('shown.bs.tab', refrescar);
		$(document).on('pos-idioma:cambiado', function (e, idioma) {
			state.idioma = idioma;
			refrescar();
		});

		initModalTraduccion();
		refrescar();
	}

	$(function () {
		initFormulario();
		initIdioma();
		initTraducciones();
	});
})(jQuery);
