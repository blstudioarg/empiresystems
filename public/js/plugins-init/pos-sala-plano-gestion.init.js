/**
 * Panel de gestión de zonas y mesas dentro del modo edición del plano (feature 039, a pedido del
 * usuario): crear/renombrar/eliminar sin salir de la Sala ni ir a Configuración → POS. Reutiliza
 * los mismos endpoints que esa pantalla (`configuracion.pos.zonas.*` / `configuracion.pos.mesas.*`,
 * mismo permiso `ver-configuracion` que ya gatea el botón "Editar plano").
 *
 * Pensado tablet-friendly: filas de 52px con el nombre editable inline (guardado automático al
 * salir del campo, patrón ya aceptado en el proyecto para edición sin botón de submit — ver
 * `configuracion-apariencia.init.js`), sin modal aparte para renombrar. Alta con fila nueva en la
 * parte superior de la lista; baja con el modal de confirmación genérico (`confirmDelete`).
 *
 * Se apoya en `window.PosPlano` (expuesto por `pos-sala-plano.init.js`) para reflejar altas/
 * renombrados/bajas de mesas en el lienzo sin pisar posiciones que el usuario ya arrastró y no
 * guardó todavía.
 */
(function ($) {
	'use strict';

	var state = window.posPlanoGestionState || {};

	var $toggle = document.getElementById('pos-plano-toggle');
	var $zonaNuevaBtn = document.getElementById('pos-plano-zona-nueva');
	var $mesaNuevaBtn = document.getElementById('pos-plano-mesa-nueva');
	var $zonasLista = document.getElementById('pos-plano-zonas-lista');
	var $mesasLista = document.getElementById('pos-plano-mesas-lista');

	if (!$toggle || !$zonasLista || !$mesasLista || !window.PosPlano) { return; }

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

	function jsonHeaders() {
		return { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') };
	}

	function ajaxJson(url, method, payload) {
		return $.ajax({
			url: url,
			method: method,
			dataType: 'json',
			contentType: 'application/json',
			data: payload === undefined ? undefined : JSON.stringify(payload),
			headers: jsonHeaders(),
		});
	}

	function mensajeError(xhr, fallback) {
		if (xhr.responseJSON && xhr.responseJSON.message) { return xhr.responseJSON.message; }
		if (xhr.responseJSON && xhr.responseJSON.errors) {
			var primero = Object.values(xhr.responseJSON.errors)[0];
			if (primero && primero[0]) { return primero[0]; }
		}
		return fallback;
	}

	// ────────────────────────────── Zonas ──────────────────────────────

	function pintarZonas(zonas) {
		if (zonas.length === 0) {
			$zonasLista.innerHTML = '<p class="plano-gestion-vacio">Todavía no hay zonas.</p>';
			return;
		}

		var activa = window.PosPlano.zonaActiva();

		$zonasLista.innerHTML = zonas.map(function (z) {
			return '<div class="plano-gestion-item' + (String(z.id) === String(activa) ? ' activa' : '') + '" data-zona-id="' + z.id + '">' +
				'<input type="text" name="zona_nombre_' + z.id + '" autocomplete="off" value="' + escapeHtml(z.nombre) + '" data-original="' + escapeHtml(z.nombre) + '"' +
				' data-update-url="' + z.update_url + '" aria-label="Nombre de la zona">' +
				'<span class="plano-gestion-meta">' + z.mesas + '</span>' +
				'<button type="button" class="btn-eliminar" data-delete-url="' + z.delete_url + '"' +
				' data-nombre="' + escapeHtml(z.nombre) + '" title="Eliminar zona"><i class="fas fa-trash"></i></button>' +
				'</div>';
		}).join('');
	}

	function cargarZonasPanel() {
		return $.getJSON(state.zonasIndexUrl).done(function (json) { pintarZonas(json.data); });
	}

	$zonasLista.addEventListener('focusout', function (e) {
		var input = e.target.closest('input');
		if (!input) { return; }

		// La fila de alta (creada por el botón "+") no tiene `data-update-url`: su alta la confirma
		// el usuario con el check, no este handler. Sin este filtro, el handler delegado también
		// dispara al salir de ese input y termina haciendo un PUT a `null` — que jQuery resuelve
		// contra la URL de la página actual, dando un 405 encubierto (bug real, encontrado en
		// pruebas manuales tras mover el alta de zonas a este panel).
		var updateUrl = input.getAttribute('data-update-url');
		if (!updateUrl) { return; }

		var nuevo = input.value.trim();
		var original = input.getAttribute('data-original');
		if (!nuevo || nuevo === original) { input.value = original; return; }

		ajaxJson(updateUrl, 'PUT', { nombre: nuevo })
			.done(function () {
				input.setAttribute('data-original', nuevo);
				window.showToast('success', 'Zona actualizada.');
				document.getElementById('pos-sala-refrescar').click();
			})
			.fail(function (xhr) {
				input.value = original;
				window.showToast('danger', mensajeError(xhr, 'No se pudo renombrar la zona.'));
			});
	});

	$zonasLista.addEventListener('click', function (e) {
		var btn = e.target.closest('.btn-eliminar');
		if (!btn) { return; }

		var nombre = btn.getAttribute('data-nombre');
		var url = btn.getAttribute('data-delete-url');

		window.confirmDelete('¿Eliminar la zona «' + nombre + '»?', function () {
			return ajaxJson(url, 'DELETE')
				.done(function () {
					window.showToast('success', 'Zona eliminada.');
					cargarZonasPanel();
					document.getElementById('pos-sala-refrescar').click();
				})
				.fail(function (xhr) {
					window.showToast('danger', mensajeError(xhr, 'No se pudo eliminar la zona.'));
				});
		});
	});

	/**
	 * Fila de alta con confirmación explícita: input + botón de check + botón de cancelar.
	 *
	 * Antes el alta se disparaba en el `blur` del input —había que escribir el nombre y sacar el
	 * foco—, un gesto que nadie adivina y que además creaba registros al salir del input sin
	 * querer. Ahora **perder el foco no crea nada**: el alta ocurre solo si el usuario la confirma
	 * (check o Enter), y Escape o la X la descartan.
	 *
	 * `autocomplete="off"` + `name` únicos siguen siendo necesarios: sin eso Chrome sugiere el
	 * nombre de la última zona/mesa creada en un input sin `name` (hallado en pruebas manuales) y
	 * un Tab puede aceptar la sugerencia.
	 *
	 * @param alConfirmar function(nombre, alFallar) — `alFallar()` devuelve la fila a estado
	 *        editable conservando lo escrito, para poder corregir un nombre repetido sin volver a
	 *        teclearlo entero.
	 */
	function filaDeAlta(campo, placeholder, etiqueta, alConfirmar) {
		var fila = document.createElement('div');
		fila.className = 'plano-gestion-item plano-gestion-alta';
		fila.innerHTML = '<input type="text" name="' + campo + '" autocomplete="off" placeholder="' +
			placeholder + '" aria-label="' + etiqueta + '">' +
			'<button type="button" class="btn-confirmar" title="Crear" aria-label="Crear" disabled>' +
			'<i class="fas fa-check"></i></button>' +
			'<button type="button" class="btn-cancelar-alta" title="Cancelar" aria-label="Cancelar">' +
			'<i class="fas fa-xmark"></i></button>';

		var $input = fila.querySelector('input');
		var $ok = fila.querySelector('.btn-confirmar');
		var enviando = false;

		function sincronizar() { $ok.disabled = $input.value.trim() === ''; }

		function confirmar() {
			var nombre = $input.value.trim();
			// El guard de `enviando` evita el alta doble cuando el Enter y el clic en el check
			// llegan casi a la vez.
			if (!nombre || enviando) { return; }

			enviando = true;
			$ok.disabled = true;
			$input.readOnly = true;

			alConfirmar(nombre, function alFallar() {
				enviando = false;
				$input.readOnly = false;
				sincronizar();
				$input.focus();
			});
		}

		$input.addEventListener('input', sincronizar);
		$input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); confirmar(); }
			if (e.key === 'Escape') { e.preventDefault(); fila.remove(); }
		});
		$ok.addEventListener('click', confirmar);
		fila.querySelector('.btn-cancelar-alta').addEventListener('click', function () { fila.remove(); });

		return { fila: fila, input: $input };
	}

	$zonaNuevaBtn.addEventListener('click', function () {
		var alta = filaDeAlta('zona_nueva_nombre', 'Nombre de la zona…', 'Nombre de la nueva zona',
			function (nombre, alFallar) {
				ajaxJson(state.zonasStoreUrl, 'POST', { nombre: nombre })
					.done(function () {
						window.showToast('success', 'Zona creada.');
						// Repinta la lista entera, así que la fila de alta desaparece sola.
						cargarZonasPanel();
						document.getElementById('pos-sala-refrescar').click();
					})
					.fail(function (xhr) {
						window.showToast('danger', mensajeError(xhr, 'No se pudo crear la zona.'));
						alFallar();
					});
			});

		$zonasLista.prepend(alta.fila);
		alta.input.focus();
	});

	// ────────────────────────────── Mesas (de la zona activa) ──────────────────────────────

	function pintarMesas(mesas) {
		if (mesas.length === 0) {
			$mesasLista.innerHTML = '<p class="plano-gestion-vacio">Esta zona todavía no tiene mesas.</p>';
			return;
		}

		$mesasLista.innerHTML = mesas.map(function (m) {
			return '<div class="plano-gestion-item" data-mesa-id="' + m.id + '">' +
				'<input type="text" name="mesa_nombre_' + m.id + '" autocomplete="off" value="' + escapeHtml(m.nombre) + '" data-original="' + escapeHtml(m.nombre) + '"' +
				' data-update-url="' + m.update_url + '" data-zona-id="' + m.zona_id + '" aria-label="Nombre de la mesa">' +
				(m.ocupada ? '<span class="plano-gestion-meta">Ocupada</span>' : '') +
				'<button type="button" class="btn-eliminar" data-delete-url="' + m.delete_url + '"' +
				' data-nombre="' + escapeHtml(m.nombre) + '" data-zona-id="' + m.zona_id + '" title="Eliminar mesa"><i class="fas fa-trash"></i></button>' +
				'</div>';
		}).join('');
	}

	function cargarMesasPanel() {
		var zid = window.PosPlano.zonaActiva();
		if (!zid) { $mesasLista.innerHTML = ''; return $.Deferred().resolve().promise(); }

		return $.getJSON(state.mesasIndexUrl, { zona_id: zid }).done(function (json) { pintarMesas(json.data); });
	}

	$mesasLista.addEventListener('focusout', function (e) {
		var input = e.target.closest('input');
		if (!input) { return; }

		// Misma razón que en la lista de zonas: la fila de alta no tiene `data-update-url` y su
		// alta se confirma con el check, no aquí.
		var updateUrl = input.getAttribute('data-update-url');
		if (!updateUrl) { return; }

		var nuevo = input.value.trim();
		var original = input.getAttribute('data-original');
		if (!nuevo || nuevo === original) { input.value = original; return; }

		var zonaId = input.getAttribute('data-zona-id');
		var mesaId = input.closest('.plano-gestion-item').getAttribute('data-mesa-id');

		ajaxJson(updateUrl, 'PUT', { zona_id: zonaId, nombre: nuevo })
			.done(function () {
				input.setAttribute('data-original', nuevo);
				window.showToast('success', 'Mesa actualizada.');
				window.PosPlano.renombrarMesa(zonaId, mesaId, nuevo);
			})
			.fail(function (xhr) {
				input.value = original;
				window.showToast('danger', mensajeError(xhr, 'No se pudo renombrar la mesa.'));
			});
	});

	$mesasLista.addEventListener('click', function (e) {
		var btn = e.target.closest('.btn-eliminar');
		if (!btn) { return; }

		var nombre = btn.getAttribute('data-nombre');
		var url = btn.getAttribute('data-delete-url');
		var zonaId = btn.getAttribute('data-zona-id');
		var mesaId = btn.closest('.plano-gestion-item').getAttribute('data-mesa-id');

		window.confirmDelete('¿Eliminar la mesa «' + nombre + '»?', function () {
			return ajaxJson(url, 'DELETE')
				.done(function () {
					window.showToast('success', 'Mesa eliminada.');
					window.PosPlano.quitarMesa(zonaId, mesaId);
					cargarMesasPanel();
					document.getElementById('pos-sala-refrescar').click();
				})
				.fail(function (xhr) {
					window.showToast('danger', mensajeError(xhr, 'No se pudo eliminar la mesa.'));
				});
		});
	});

	$mesaNuevaBtn.addEventListener('click', function () {
		var zid = window.PosPlano.zonaActiva();
		if (!zid) {
			window.showToast('warning', 'Elegí primero una zona.');
			return;
		}

		var alta = filaDeAlta('mesa_nueva_nombre', 'Nombre de la mesa…', 'Nombre de la nueva mesa',
			function (nombre, alFallar) {
				ajaxJson(state.mesasStoreUrl, 'POST', { zona_id: zid, nombre: nombre })
					.done(function (respuesta) {
						window.showToast('success', 'Mesa creada.');
						cargarMesasPanel();

						// La posición (fila/columna) la asigna el servidor (FR-014): se toma del
						// estado completo de la sala (mismo refresco que ya usa "Actualizar") en vez
						// de pisar las posiciones que el usuario ya arrastró en esta sesión.
						document.addEventListener('pos-sala:actualizado', function alReactualizar(e) {
							document.removeEventListener('pos-sala:actualizado', alReactualizar);
							var mesaNueva = e.detail.mesas.filter(function (m) { return String(m.id) === String(respuesta.id); })[0];
							if (mesaNueva) { window.PosPlano.agregarMesa(zid, mesaNueva); }
						});
						document.getElementById('pos-sala-refrescar').click();
					})
					.fail(function (xhr) {
						window.showToast('danger', mensajeError(xhr, 'No se pudo crear la mesa.'));
						alFallar();
					});
			});

		$mesasLista.prepend(alta.fila);
		alta.input.focus();
	});

	// ────────────────────────────── Orquestación ──────────────────────────────

	function refrescarPanel() {
		if (!window.PosPlano.estaEditando()) { return; }
		cargarZonasPanel();
		cargarMesasPanel();
	}

	// El click en "Editar plano" activa el modo edición en `pos-sala-plano.init.js` (registrado
	// antes que este archivo, así que corre primero) y recién entonces `zonaActiva()` está listo.
	$toggle.addEventListener('click', function () {
		if (window.PosPlano.estaEditando()) { refrescarPanel(); }
	});

	document.addEventListener('pos-sala:zona-cambiada', function () {
		if (window.PosPlano.estaEditando()) { cargarMesasPanel(); pintarZonaActivaEnLista(); }
	});

	function pintarZonaActivaEnLista() {
		var activa = window.PosPlano.zonaActiva();
		$zonasLista.querySelectorAll('.plano-gestion-item').forEach(function (item) {
			item.classList.toggle('activa', item.getAttribute('data-zona-id') === String(activa));
		});
	}
})(jQuery);
