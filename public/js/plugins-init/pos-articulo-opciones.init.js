/**
 * Sub-listado editable de opciones de artículo (feature 038, US3), embebido en el modal de
 * alta/edición de `articulos/index.blade.php`.
 *
 * Sigue el patrón "sub-listado editable embebido en un modal de edición" de
 * docs/04-front-guidelines.md: la colección no cabe en `data-*` de un botón, así que se
 * pide/guarda por un endpoint aparte (`GET/PUT /articulos/{articulo}/opciones`). Solo aplica en
 * edición: un artículo recién creado no tiene id todavía.
 */
(function ($) {
	'use strict';

	var $seccion = $('.pos-articulo-opciones-field');

	if (!$seccion.length) { return; } // Capacidad apagada: el bloque ni se renderiza.

	var $gruposLista = $('#pos-articulo-grupos-lista');
	var $opcionesLista = $('#pos-articulo-opciones-lista');
	var $opcionAdd = $('#pos-articulo-opcion-add');
	var $opcionPrecioAdd = $('#pos-articulo-opcion-precio-add');
	var $opcionAddBtn = $('#pos-articulo-opcion-add-btn');
	var $guardarBtn = $('#pos-articulo-opciones-guardar');

	var articuloId = null;
	var disponibles = { grupos: [], opciones: [] };
	var asignados = { grupos: [], opciones: [] }; // { grupo_id, nombre, orden } / { opcion_id, nombre, grupo_nombre, precio }

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function csrf() {
		return $('meta[name="csrf-token"]').attr('content');
	}

	// Solo lectura: no hay selector de grupos. Se asignan/retiran solos según haya o no
	// opciones de ese grupo en "Opciones y precio" (ver $opcionAddBtn y el botón de quitar fila).
	function renderGrupos() {
		$gruposLista.empty();

		if (!asignados.grupos.length) {
			$gruposLista.append('<span class="pos-opts-empty">Sin grupos asignados todavía.</span>');
		}

		asignados.grupos.forEach(function (grupo) {
			$gruposLista.append($('<span class="pos-opts-chip"></span>').text(grupo.nombre));
		});
	}

	function renderOpciones() {
		$opcionesLista.empty();

		if (!asignados.opciones.length) {
			$opcionesLista.append('<p class="pos-opts-empty mb-0">Sin opciones asignadas todavía.</p>');
		}

		asignados.opciones.forEach(function (opcion, i) {
			var $fila = $('<div class="pos-opts-row"></div>');
			$fila.append(
				$('<div class="pos-opts-row-info"></div>')
					.append($('<span class="pos-opts-row-name"></span>').text(opcion.nombre))
					.append($('<span class="pos-opts-row-group"></span>').text(opcion.grupo_nombre || ''))
			);
			$fila.append(
				$('<label class="pos-opts-row-price"></label>')
					.append(
						$('<input type="number" step="0.01" min="0" aria-label="Precio">')
							.val(opcion.precio)
							.on('input', function () { opcion.precio = $(this).val(); })
					)
					.append('<span>€</span>')
			);
			$fila.append(
				$('<button type="button" class="pos-opts-row-remove" aria-label="Quitar opción" title="Quitar"><i class="fas fa-times"></i></button>')
					.on('click', function () {
						asignados.opciones.splice(i, 1);

						// Simétrico al alta automática: si esa era la última opción de su grupo,
						// el grupo deja de tener sentido asignado (quedaría vacío y el modal de
						// venta lo filtra igual) — se retira solo para que "Grupos asignados"
						// siempre refleje 1:1 lo que realmente tiene opciones.
						if (opcion.grupo_id && !asignados.opciones.some(function (o) { return o.grupo_id === opcion.grupo_id; })) {
							asignados.grupos = asignados.grupos.filter(function (g) { return g.grupo_id !== opcion.grupo_id; });
							renderGrupos();
						}

						renderOpciones();
					})
			);
			$opcionesLista.append($fila);
		});

		$opcionAdd.empty().append('<option value="">+ Añadir opción…</option>');
		disponibles.opciones.forEach(function (o) {
			$opcionAdd.append(
				$('<option></option>').val(o.id).text(o.nombre + (o.grupo_nombre ? ' (' + o.grupo_nombre + ')' : ''))
					.attr('data-precio-defecto', o.precio_defecto)
					.attr('data-grupo-nombre', o.grupo_nombre || '')
			);
		});
	}

	function cargar(id) {
		articuloId = id;

		return $.ajax({
			url: $seccion.data('index-url-template').replace('__ID__', id),
			headers: { Accept: 'application/json' },
			dataType: 'json',
		}).done(function (res) {
			disponibles = res.disponibles;
			asignados.grupos = res.grupos.map(function (g) { return { grupo_id: g.grupo_id, nombre: g.nombre, orden: g.orden }; });
			asignados.opciones = res.opciones.map(function (o) {
				return { opcion_id: o.opcion_id, nombre: o.nombre, grupo_id: o.grupo_id, grupo_nombre: o.grupo_nombre, precio: o.precio, orden: o.orden };
			});
			renderGrupos();
			renderOpciones();
		});
	}

	function limpiar() {
		articuloId = null;
		disponibles = { grupos: [], opciones: [] };
		asignados = { grupos: [], opciones: [] };
		$gruposLista.empty();
		$opcionesLista.empty();
	}

	$opcionAddBtn.on('click', function () {
		var id = parseInt($opcionAdd.val(), 10);
		if (!id) { return; }
		if (asignados.opciones.some(function (o) { return o.opcion_id === id; })) {
			window.showToast('error', 'Esa opción ya está asignada.');
			return;
		}
		var $opt = $opcionAdd.find('option:selected');
		var precio = $opcionPrecioAdd.val();
		var opcionDisponible = disponibles.opciones.filter(function (o) { return o.id === id; })[0];

		asignados.opciones.push({
			opcion_id: id,
			nombre: $opt.text().replace(/\s*\(.*\)$/, ''),
			grupo_id: opcionDisponible ? opcionDisponible.grupo_id : null,
			grupo_nombre: $opt.data('grupo-nombre'),
			precio: precio !== '' ? precio : $opt.data('precio-defecto'),
			orden: asignados.opciones.length,
		});

		// El servidor solo ofrece en el POS las opciones cuyo GRUPO está asignado al artículo
		// (PosController::opcionesArticulo filtra por $articulo->posGrupos). Sin esto, una opción
		// añadida sin haber añadido antes su grupo queda invisible en el modal de venta sin que
		// nada lo avise aquí.
		if (opcionDisponible && opcionDisponible.grupo_id && !asignados.grupos.some(function (g) { return g.grupo_id === opcionDisponible.grupo_id; })) {
			asignados.grupos.push({ grupo_id: opcionDisponible.grupo_id, nombre: opcionDisponible.grupo_nombre, orden: asignados.grupos.length });
			renderGrupos();
		}

		renderOpciones();
		$opcionAdd.val('');
		$opcionPrecioAdd.val('');
	});

	$guardarBtn.on('click', function () {
		if (!articuloId) { return; }

		window.withButtonLoading($guardarBtn, function () {
			return $.ajax({
				url: $seccion.data('sync-url-template').replace('__ID__', articuloId),
				method: 'POST',
				dataType: 'json',
				headers: { Accept: 'application/json' },
				data: {
					_method: 'PUT',
					_token: csrf(),
					grupos: asignados.grupos.map(function (g, i) { return { grupo_id: g.grupo_id, orden: i }; }),
					opciones: asignados.opciones.map(function (o, i) { return { opcion_id: o.opcion_id, precio: o.precio, orden: i }; }),
				},
			});
		})
			.done(function (res) {
				window.showToast('success', res.message || 'Opciones guardadas.');
			})
			.fail(function () {
				window.showToast('error', 'No se pudieron guardar las opciones.');
			});
	});

	// Se engancha a los mismos eventos que articulos-modal.init.js: mostrar/cargar en edición,
	// ocultar/limpiar en alta.
	$(document).on('click', '.btn-edit-articulo', function () {
		var id = $(this).data('id');
		$seccion.prop('hidden', false);
		cargar(id);
	});

	$(document).on('click', '.btn-add-articulo', function () {
		$seccion.prop('hidden', true);
		limpiar();
	});
})(jQuery);
