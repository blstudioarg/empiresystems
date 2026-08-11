/**
 * Opciones de artículo (feature 038): DataTables de grupos y opciones + su modal único de
 * alta/edición, siguiendo el patrón por defecto del proyecto.
 */
(function ($) {
	'use strict';

	var state = window.posOpcionesState || {};

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

	function idioma(que) {
		return {
			search: 'Buscar:',
			lengthMenu: 'Mostrar _MENU_ registros',
			info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
			infoEmpty: 'Mostrando 0 a 0 de 0 registros',
			infoFiltered: '(filtrado de _MAX_ registros totales)',
			zeroRecords: 'No se encontraron ' + que,
			emptyTable: 'Todavía no hay ' + que,
			processing: 'Cargando...',
			paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
		};
	}

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

		$(document).on('click', opciones.delBtn, function () {
			var $btn = $(this);
			var url = $btn.data('delete-url');

			window.confirmDelete('¿Eliminar «' + $btn.data('nombre') + '»? Esta acción no se puede deshacer.', function () {
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
						// El 422 útil aquí es "se usa en N artículos" (FR-047): se muestra literal.
						window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar.');
					});
			}, { confirmLabel: 'Eliminar' });
		});
	}

	$(function () {
		if (!$('#pos-grupos-table').length) { return; }

		var tablaGrupos = $('#pos-grupos-table').DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: { url: state.gruposIndexUrl, headers: { Accept: 'application/json' }, dataSrc: 'data' },
			columns: [
				{ data: 'nombre', render: escapeHtml },
				{
					data: null,
					orderable: false,
					render: function (data, type, row) {
						var max = row.max_selecciones === null ? 'sin límite' : row.max_selecciones;
						var etiqueta = 'mín. ' + row.min_selecciones + ' · máx. ' + max;

						return row.obligatorio
							? '<span class="badge badge-primary light">Obligatorio</span> <span class="text-muted small">' + etiqueta + '</span>'
							: '<span class="text-muted small">' + etiqueta + '</span>';
					},
				},
				{ data: 'opciones' },
				{ data: 'articulos' },
				{
					data: null,
					orderable: false,
					render: function (data, type, row) {
						return dropdown(
							'<li><button type="button" class="dropdown-item btn-edit-pos-grupo" data-bs-toggle="modal" data-bs-target="#posGrupoModal"' +
								' data-update-url="' + escapeAttr(row.update_url) + '"' +
								' data-nombre="' + escapeAttr(row.nombre) + '"' +
								' data-obligatorio="' + (row.obligatorio ? '1' : '0') + '"' +
								' data-min="' + escapeAttr(row.min_selecciones) + '"' +
								' data-max="' + escapeAttr(row.max_selecciones === null ? '' : row.max_selecciones) + '"' +
								' data-orden="' + escapeAttr(row.orden) + '">Editar</button></li>' +
							'<li><hr class="dropdown-divider"></li>' +
							'<li><button type="button" class="dropdown-item text-danger btn-del-pos-grupo"' +
								' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
								' data-nombre="' + escapeAttr(row.nombre) + '">Eliminar</button></li>'
						);
					},
				},
			],
			language: idioma('grupos de opciones'),
		});

		var tablaOpciones = $('#pos-opciones-table').DataTable({
			responsive: true,
			processing: true,
			stateSave: true,
			stateDuration: -1,
			ajax: { url: state.opcionesIndexUrl, headers: { Accept: 'application/json' }, dataSrc: 'data' },
			columns: [
				{ data: 'nombre', render: escapeHtml },
				{ data: 'grupo_nombre', render: escapeHtml },
				{
					data: null,
					render: function (data, type, row) { return escapeHtml(row.precio_defecto) + ' €'; },
				},
				{
					data: null,
					render: function (data, type, row) {
						return row.articulo_vinculado_nombre
							? escapeHtml(row.articulo_vinculado_nombre)
							: '<span class="text-muted">—</span>';
					},
				},
				{ data: 'articulos' },
				{
					data: null,
					orderable: false,
					render: function (data, type, row) {
						return dropdown(
							'<li><button type="button" class="dropdown-item btn-edit-pos-opcion" data-bs-toggle="modal" data-bs-target="#posOpcionModal"' +
								' data-update-url="' + escapeAttr(row.update_url) + '"' +
								' data-grupo-id="' + escapeAttr(row.grupo_id) + '"' +
								' data-nombre="' + escapeAttr(row.nombre) + '"' +
								' data-precio="' + escapeAttr(row.precio_defecto) + '"' +
								' data-articulo-id="' + escapeAttr(row.articulo_vinculado_id === null ? '' : row.articulo_vinculado_id) + '"' +
								' data-orden="' + escapeAttr(row.orden) + '">Editar</button></li>' +
							'<li><hr class="dropdown-divider"></li>' +
							'<li><button type="button" class="dropdown-item text-danger btn-del-pos-opcion"' +
								' data-delete-url="' + escapeAttr(row.delete_url) + '"' +
								' data-nombre="' + escapeAttr(row.nombre) + '">Eliminar</button></li>'
						);
					},
				},
			],
			language: idioma('opciones'),
		});

		function recargarTodo() {
			tablaGrupos.ajax.reload(null, false);
			tablaOpciones.ajax.reload(null, false);
		}

		initModal({
			modal: '#posGrupoModal',
			form: '#pos-grupo-form',
			methodInput: '#pos_grupo_method',
			title: '#posGrupoModalLabel',
			addBtn: '.btn-add-pos-grupo',
			editBtn: '.btn-edit-pos-grupo',
			delBtn: '.btn-del-pos-grupo',
			storeUrl: state.gruposStoreUrl,
			tituloAlta: 'Añadir grupo',
			tituloEdicion: 'Editar grupo',
			onFill: function ($form, $btn) {
				$form.find('#pos_grupo_nombre').val($btn.data('nombre'));
				$form.find('#pos_grupo_obligatorio').prop('checked', String($btn.data('obligatorio')) === '1');
				$form.find('#pos_grupo_min').val($btn.data('min'));
				$form.find('#pos_grupo_max').val($btn.data('max'));
				$form.find('#pos_grupo_orden').val($btn.data('orden'));
			},
			recargar: recargarTodo,
		});

		initModal({
			modal: '#posOpcionModal',
			form: '#pos-opcion-form',
			methodInput: '#pos_opcion_method',
			title: '#posOpcionModalLabel',
			addBtn: '.btn-add-pos-opcion',
			editBtn: '.btn-edit-pos-opcion',
			delBtn: '.btn-del-pos-opcion',
			storeUrl: state.opcionesStoreUrl,
			tituloAlta: 'Añadir opción',
			tituloEdicion: 'Editar opción',
			onFill: function ($form, $btn) {
				$form.find('#pos_opcion_grupo').val($btn.data('grupo-id'));
				$form.find('#pos_opcion_nombre').val($btn.data('nombre'));
				$form.find('#pos_opcion_precio').val($btn.data('precio'));
				$form.find('#pos_opcion_articulo').val($btn.data('articulo-id') || '');
				$form.find('#pos_opcion_orden').val($btn.data('orden'));
			},
			recargar: recargarTodo,
		});
	});
})(jQuery);
