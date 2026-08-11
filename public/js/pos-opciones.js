/**
 * POS (TPV) — modal de selección de opciones de un artículo (feature 038, US4).
 *
 * Reglas de grupo (obligatorio, mín./máx.) se validan aquí para dar feedback inmediato, pero el
 * servidor las **revalida** al guardar la cuenta (Principio III: nunca confiar en que el cliente
 * hizo bien su trabajo).
 */
window.PosApp.registrar('opciones', function (PosApp) {
	'use strict';

	var $modalEl = document.getElementById('posOpcionesModal');
	if (!$modalEl) { return {}; } // Capacidad apagada: el modal ni se renderiza.

	var modal = bootstrap.Modal.getOrCreateInstance($modalEl);
	var $grupos = document.getElementById('pos-opciones-grupos');
	var $confirmar = document.getElementById('pos-opciones-confirmar');

	var cache = {};       // articulo_id -> { grupos: [...] } (respuesta del servidor)
	var seleccion = {};   // grupo_id -> [opcion_id, ...]
	var grupoActual = []; // grupos del artículo abierto ahora mismo
	var btnActual = null; // botón .pos-articulo que abrió el modal

	function cumpleGrupo(grupo) {
		var elegidas = (seleccion[grupo.grupo_id] || []).length;
		var min = grupo.obligatorio ? Math.max(1, grupo.min_selecciones) : grupo.min_selecciones;
		return elegidas >= min;
	}

	function cumpleTodo() {
		return grupoActual.every(cumpleGrupo);
	}

	function toggleOpcion(grupo, opcionId) {
		var actuales = seleccion[grupo.grupo_id] || [];
		var idx = actuales.indexOf(opcionId);
		var max = grupo.max_selecciones; // null = sin límite

		if (idx !== -1) {
			actuales.splice(idx, 1);
		} else {
			if (max === 1) {
				actuales = [opcionId]; // radio: la nueva reemplaza a la anterior
			} else if (max === null || actuales.length < max) {
				actuales.push(opcionId);
			} else {
				return; // ya está en el máximo permitido
			}
		}

		seleccion[grupo.grupo_id] = actuales;
		render();
	}

	function render() {
		$grupos.innerHTML = '';

		grupoActual.forEach(function (grupo) {
			var $bloque = document.createElement('div');
			$bloque.className = 'pos-opciones-grupo';

			var titulo = document.createElement('div');
			titulo.className = 'pos-opciones-grupo-titulo';
			titulo.innerHTML = '<span>' + PosApp.escapeHtml(grupo.nombre) + '</span>' +
				(grupo.obligatorio ? '<span class="req">Obligatorio</span>' : '');
			$bloque.appendChild(titulo);

			var grid = document.createElement('div');
			grid.className = 'pos-opciones-grid';

			grupo.opciones.forEach(function (opcion) {
				var activa = (seleccion[grupo.grupo_id] || []).indexOf(opcion.opcion_id) !== -1;
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'pos-metodo' + (activa ? ' activo' : '');
				btn.innerHTML = '<span>' + PosApp.escapeHtml(opcion.nombre) + '</span>' +
					(opcion.precio > 0 ? '<span class="precio">+' + PosApp.format(opcion.precio) + ' €</span>' : '');
				btn.addEventListener('click', function () { toggleOpcion(grupo, opcion.opcion_id); });
				grid.appendChild(btn);
			});

			$bloque.appendChild(grid);
			$grupos.appendChild($bloque);
		});

		$confirmar.disabled = !cumpleTodo();
	}

	function abrirParaArticulo(btn) {
		var articuloId = btn.getAttribute('data-id');
		btnActual = btn;
		seleccion = {};

		function abrirConDatos(data) {
			grupoActual = data.grupos;
			render();
			modal.show();
		}

		if (cache[articuloId]) {
			abrirConDatos(cache[articuloId]);
			return;
		}

		fetch('/pos/articulos/' + articuloId + '/opciones', {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				cache[articuloId] = data;
				abrirConDatos(data);
			})
			.catch(function () {
				window.showToast('error', 'No se pudieron cargar las opciones de este artículo.');
			});
	}

	function init() {
		$confirmar.addEventListener('click', function () {
			if (!cumpleTodo() || !btnActual) { return; }

			var elegidas = [];
			grupoActual.forEach(function (grupo) {
				(seleccion[grupo.grupo_id] || []).forEach(function (opcionId) {
					var opcion = grupo.opciones.filter(function (o) { return o.opcion_id === opcionId; })[0];
					if (opcion) { elegidas.push(opcion); }
				});
			});

			PosApp.modulos.ticket.addArticulo(btnActual, elegidas);
			modal.hide();
		});

		$modalEl.addEventListener('hidden.bs.modal', function () {
			btnActual = null;
			seleccion = {};
		});
	}

	return { init: init, abrirParaArticulo: abrirParaArticulo };
});
