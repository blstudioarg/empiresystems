/**
 * Configuración → POS (feature 038): flags del módulo + CRUD de zonas y mesas.
 *
 * Sigue el patrón por defecto del proyecto: DataTable con datos por AJAX, un único modal
 * reutilizado para alta y edición (datos de la fila en `data-*`), y toastr para todo aviso.
 */
(function ($) {
	'use strict';

	var state = window.posConfigState || {};

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function escapeAttr(value) {
		return escapeHtml(value).replace(/"/g, '&quot;');
	}

	function csrf() {
		return $('meta[name="csrf-token"]').attr('content');
	}

	function dropdown(acciones) {
		return (
			'<div class="dropdown">' +
				'<button type="button" class="btn btn-primary light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Acciones</button>' +
				'<ul class="dropdown-menu dropdown-menu-end">' + acciones + '</ul>' +
			'</div>'
		);
	}

	// ── Flags del módulo ────────────────────────────────────────────────
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

	// ── Zonas ───────────────────────────────────────────────────────────
	function initZonas() {
		var $tabla = $('#pos-zonas-table');

		if (!$tabla.length || !state.zonasIndexUrl) { return null; }

		var tabla = $tabla.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: { url: state.zonasIndexUrl, headers: { Accept: 'application/json' }, dataSrc: 'data' },
			columns: [
				{ data: 'nombre', render: escapeHtml },
				{ data: 'mesas' },
				{
					data: null,
					render: function (data, type, row) {
						return state.suplementoZonaActivo
							? escapeHtml(row.suplemento_porcentaje) + ' %'
							: '<span class="text-muted">—</span>';
					},
				},
				{ data: 'orden' },
				{
					data: null,
					orderable: false,
					render: function (data, type, row) {
						return dropdown(
							'<li><button type="button" class="dropdown-item btn-edit-pos-zona" data-bs-toggle="modal" data-bs-target="#posZonaModal"' +
								' data-update-url="' + escapeAttr(row.update_url) + '"' +
								' data-nombre="' + escapeAttr(row.nombre) + '"' +
								' data-suplemento="' + escapeAttr(row.suplemento_porcentaje) + '"' +
								' data-orden="' + escapeAttr(row.orden) + '">Editar</button></li>' +
							'<li><hr class="dropdown-divider"></li>' +
							'<li><button type="button" class="dropdown-item text-danger btn-del-pos-zona"' +
								' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
								' data-nombre="' + escapeAttr(row.nombre) + '">Eliminar</button></li>'
						);
					},
				},
			],
			language: idioma('zonas'),
		});

		return tabla;
	}

	// ── Mesas ───────────────────────────────────────────────────────────
	function initMesas() {
		var $tabla = $('#pos-mesas-table');

		if (!$tabla.length || !state.mesasIndexUrl) { return null; }

		return $tabla.DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: { url: state.mesasIndexUrl, headers: { Accept: 'application/json' }, dataSrc: 'data' },
			columns: [
				{ data: 'nombre', render: escapeHtml },
				{ data: 'zona_nombre', render: escapeHtml },
				{
					data: null,
					render: function (data, type, row) {
						return row.ocupada
							? '<span class="badge badge-success light">Ocupada</span>'
							: '<span class="badge badge-secondary light">Libre</span>';
					},
				},
				{ data: 'orden' },
				{
					data: null,
					orderable: false,
					render: function (data, type, row) {
						return dropdown(
							'<li><button type="button" class="dropdown-item btn-edit-pos-mesa" data-bs-toggle="modal" data-bs-target="#posMesaModal"' +
								' data-update-url="' + escapeAttr(row.update_url) + '"' +
								' data-zona-id="' + escapeAttr(row.zona_id) + '"' +
								' data-nombre="' + escapeAttr(row.nombre) + '"' +
								' data-orden="' + escapeAttr(row.orden) + '">Editar</button></li>' +
							'<li><hr class="dropdown-divider"></li>' +
							'<li><button type="button" class="dropdown-item text-danger btn-del-pos-mesa"' +
								' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
								' data-nombre="' + escapeAttr(row.nombre) + '">Eliminar</button></li>'
						);
					},
				},
			],
			language: idioma('mesas'),
		});
	}

	function idioma(que) {
		return {
			search: 'Buscar:',
			lengthMenu: 'Mostrar _MENU_ registros',
			info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
			infoEmpty: 'Mostrando 0 a 0 de 0 registros',
			infoFiltered: '(filtrado de _MAX_ registros totales)',
			zeroRecords: 'No se encontraron ' + que,
			emptyTable: que === 'zonas'
				? 'Todavía no hay zonas. Crea al menos una para poder añadir mesas.'
				: 'Todavía no hay mesas.',
			processing: 'Cargando...',
			paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
		};
	}

	// ── Modales (uno por entidad, reutilizado para alta y edición) ──────
	function initModal(opciones) {
		var $form = $(opciones.form);

		if (!$form.length) { return; }

		var modal = bootstrap.Modal.getOrCreateInstance($(opciones.modal)[0]);

		function limpiarErrores() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		$(document).on('click', opciones.addBtn, function () {
			limpiarErrores();
			$form[0].reset();
			$form.find(opciones.methodInput).val('POST');
			$form.attr('action', opciones.storeUrl);
			$(opciones.title).text(opciones.tituloAlta);
			if (opciones.onReset) { opciones.onReset($form); }
		});

		$(document).on('click', opciones.editBtn, function () {
			limpiarErrores();
			var $btn = $(this);
			$form.find(opciones.methodInput).val('PUT');
			$form.attr('action', $btn.data('update-url'));
			$(opciones.title).text(opciones.tituloEdicion);
			opciones.onFill($form, $btn);
		});

		$form.on('submit', function (e) {
			e.preventDefault();

			window.withButtonLoading($form.find('button[type="submit"]'), function () {
				return $.ajax({
					url: $form.attr('action'),
					method: 'POST',
					data: $form.serialize(),
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (res) {
					modal.hide();
					window.showToast('success', res.message || 'Guardado.');
					opciones.recargar();
				})
				.fail(function (xhr) {
					if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
						limpiarErrores();
						$.each(xhr.responseJSON.errors, function (campo, mensajes) {
							$form.find('[name="' + campo + '"]').addClass('is-invalid');
							$form.find('[data-error-for="' + campo + '"]').text(mensajes[0]);
						});
						return;
					}
					window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo guardar.');
				});
		});

		// Borrado: confirmación explícita (acción irreversible) y el 422 del servidor —
		// "tiene mesas", "tiene una cuenta abierta"— se muestra tal cual, que es lo útil.
		$(document).on('click', opciones.delBtn, function () {
			var $btn = $(this);
			var url = $btn.data('delete-url');
			var nombre = $btn.data('nombre');

			window.confirmDelete('¿Eliminar «' + nombre + '»? Esta acción no se puede deshacer.', function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: csrf() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (res) {
						window.showToast('success', res.message || 'Eliminado.');
						opciones.recargar();
					})
					.fail(function (xhr) {
						window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar.');
					});
			}, { confirmLabel: 'Eliminar' });
		});
	}

	$(function () {
		if (!$('#pos-config-form').length) { return; }

		initFormulario();

		var tablaZonas = initZonas();
		var tablaMesas = initMesas();

		function recargarZonas() {
			if (tablaZonas) { tablaZonas.ajax.reload(null, false); }
			cargarSelectZonas();
		}

		function recargarMesas() {
			if (tablaMesas) { tablaMesas.ajax.reload(null, false); }
		}

		// El select de zonas del modal de mesa se refresca desde el mismo endpoint que la tabla:
		// una zona recién creada tiene que poder usarse sin recargar la página.
		function cargarSelectZonas(seleccionada) {
			return $.ajax({ url: state.zonasIndexUrl, headers: { Accept: 'application/json' }, dataType: 'json' })
				.done(function (res) {
					var $select = $('#pos_mesa_zona');
					$select.empty();
					(res.data || []).forEach(function (zona) {
						$select.append($('<option>').val(zona.id).text(zona.nombre));
					});
					if (seleccionada) { $select.val(seleccionada); }
				});
		}

		cargarSelectZonas();

		// El suplemento por zona solo se pide si la capacidad está activada (FR-004).
		$('#pos-zona-suplemento-wrap').toggleClass('d-none', !state.suplementoZonaActivo);

		initModal({
			modal: '#posZonaModal',
			form: '#pos-zona-form',
			methodInput: '#pos_zona_method',
			title: '#posZonaModalLabel',
			addBtn: '.btn-add-pos-zona',
			editBtn: '.btn-edit-pos-zona',
			delBtn: '.btn-del-pos-zona',
			storeUrl: state.zonasStoreUrl,
			tituloAlta: 'Añadir zona',
			tituloEdicion: 'Editar zona',
			onFill: function ($form, $btn) {
				$form.find('#pos_zona_nombre').val($btn.data('nombre'));
				$form.find('#pos_zona_suplemento').val($btn.data('suplemento'));
				$form.find('#pos_zona_orden').val($btn.data('orden'));
			},
			recargar: recargarZonas,
		});

		initModal({
			modal: '#posMesaModal',
			form: '#pos-mesa-form',
			methodInput: '#pos_mesa_method',
			title: '#posMesaModalLabel',
			addBtn: '.btn-add-pos-mesa',
			editBtn: '.btn-edit-pos-mesa',
			delBtn: '.btn-del-pos-mesa',
			storeUrl: state.mesasStoreUrl,
			tituloAlta: 'Añadir mesa',
			tituloEdicion: 'Editar mesa',
			onReset: function () { cargarSelectZonas(); },
			onFill: function ($form, $btn) {
				cargarSelectZonas($btn.data('zona-id'));
				$form.find('#pos_mesa_nombre').val($btn.data('nombre'));
				$form.find('#pos_mesa_orden').val($btn.data('orden'));
			},
			recargar: recargarMesas,
		});

		// Las tablas viven dentro de una tab oculta al cargar: DataTables calcula mal los anchos
		// mientras el contenedor está en display:none.
		$('#tab-pos-btn').on('shown.bs.tab', function () {
			[tablaZonas, tablaMesas].forEach(function (t) {
				if (!t) { return; }
				t.columns.adjust();
				if (t.responsive) { t.responsive.recalc(); }
			});
		});
	});
})(jQuery);
