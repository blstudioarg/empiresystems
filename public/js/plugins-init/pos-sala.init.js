/**
 * Sala del POS (feature 038): carga el estado de todas las mesas de una vez y filtra por zona en
 * cliente.
 *
 * El filtro es client-side a propósito: el JSON completo de la sala son unas decenas de mesas, y
 * volver al servidor por cada toque de pestaña añadiría latencia visible en tablet sin ahorrar
 * nada. Lo que NO se calcula aquí es el estado "olvidada": lo decide el servidor (si dependiera
 * del reloj de la tablet, dos dispositivos mostrarían cosas distintas).
 */
(function () {
	'use strict';

	var state = window.posSalaState || {};

	var $zonas = document.getElementById('pos-sala-zonas');
	var $mesas = document.getElementById('pos-sala-mesas');
	var $vacia = document.getElementById('pos-sala-vacia');
	var $refrescar = document.getElementById('pos-sala-refrescar');
	var $vista = document.getElementById('pos-sala-vista');
	var $planoServicio = document.getElementById('pos-plano-servicio');

	if (!$zonas || !$mesas) { return; }

	var datos = { zonas: [], mesas: [] };
	var zonaActiva = ''; // '' = todas

	// Vista de la Sala (feature 041): 'tarjetas' (la de siempre, por defecto) o 'plano'.
	// La preferencia es de INTERFAZ, no de negocio: vive en `localStorage` y no viaja al servidor.
	// La clave lleva el id del usuario porque en hostelería varias personas comparten la misma
	// tablet y no deben pisarse la preferencia (FR-003).
	var VISTAS = ['tarjetas', 'plano'];
	var claveVista = 'pos-sala-vista:' + (state.userId || 'anon');

	function leerVistaGuardada() {
		try {
			var guardada = window.localStorage.getItem(claveVista);
			return VISTAS.indexOf(guardada) !== -1 ? guardada : 'tarjetas';
		} catch (e) {
			// Navegador con el almacenamiento bloqueado: la vista de siempre y a trabajar.
			return 'tarjetas';
		}
	}

	function guardarVista(vista) {
		try { window.localStorage.setItem(claveVista, vista); } catch (e) { /* sin persistencia: no es crítico */ }
	}

	var vistaActiva = leerVistaGuardada();

	// ── Resumen plegable de metricas ───────────────────────────────────────────────────────
	//
	// Nace CERRADO: en la tablet de sala lo util es el plano, y cuatro tarjetas ocupando la
	// primera pantalla empujaban las mesas fuera de la vista. Quien lo abra, se le recuerda —
	// misma clave por usuario que la vista, porque varias personas comparten la misma tablet y no
	// deben pisarse la preferencia.
	var $resumenToggle = document.getElementById('pos-sala-resumen-toggle');
	var $resumenPanel = document.getElementById('pos-sala-cards-collapse');
	var claveResumen = 'pos-sala-resumen:' + (state.userId || 'anon');

	function leerResumenGuardado() {
		try {
			// Solo un 'abierto' explicito abre: cualquier otra cosa (nunca guardado, valor raro,
			// almacenamiento bloqueado) cae en cerrado, que es el defecto pedido.
			return window.localStorage.getItem(claveResumen) === 'abierto';
		} catch (e) {
			return false;
		}
	}

	function aplicarResumen(abierto) {
		if (!$resumenPanel || !$resumenToggle) { return; }

		$resumenPanel.classList.toggle('abierto', abierto);
		$resumenToggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
	}

	if ($resumenToggle && $resumenPanel) {
		aplicarResumen(leerResumenGuardado());

		$resumenToggle.addEventListener('click', function () {
			var abierto = !$resumenPanel.classList.contains('abierto');

			aplicarResumen(abierto);

			try {
				window.localStorage.setItem(claveResumen, abierto ? 'abierto' : 'cerrado');
			} catch (e) { /* sin persistencia: no es critico */ }
		});
	}

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

	function formatoImporte(valor) {
		return parseFloat(valor || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function mesasVisibles() {
		return datos.mesas.filter(function (m) {
			return zonaActiva === '' || String(m.zona_id) === String(zonaActiva);
		});
	}

	function pintarZonas() {
		var total = datos.mesas.length;

		// "Todas" no tiene plano que dibujar (la rejilla es de UNA zona): en vista de plano se
		// deshabilita en vez de ocultarse, para que la fila de filtros no cambie de tamaño al
		// alternar de vista (D6).
		var todasDeshabilitado = vistaActiva === 'plano' ? ' disabled title="El plano se ve por zonas"' : '';

		var html = '<button type="button" class="pos-filtro' + (zonaActiva === '' ? ' active' : '') + '"' +
			' data-zona="" aria-pressed="' + (zonaActiva === '' ? 'true' : 'false') + '"' + todasDeshabilitado + '>' +
			'Todas <span class="badge-count">' + total + '</span></button>';

		datos.zonas.forEach(function (zona) {
			var activa = String(zonaActiva) === String(zona.id);
			var suplemento = parseFloat(zona.suplemento || 0);

			html += '<button type="button" class="pos-filtro' + (activa ? ' active' : '') + '"' +
				' data-zona="' + zona.id + '" aria-pressed="' + (activa ? 'true' : 'false') + '">' +
				escapeHtml(zona.nombre) +
				(suplemento > 0 ? ' <span class="badge-count">+' + formatoImporte(suplemento) + '%</span>' : '') +
				' <span class="badge-count">' + zona.total_mesas + '</span></button>';
		});

		$zonas.innerHTML = html;
	}

	function pintarMesas() {
		var visibles = mesasVisibles();

		// Mismo motivo que en `aplicarVista`: el cartel de "no hay mesas" pertenece a la vista de
		// lectura, y con el editor abierto no debe asomar por encima de él. Se da justo al crear
		// una zona nueva desde el panel de gestión, que es cuando la zona activa se queda sin
		// mesas y el editor está abierto por definición.
		var editandoPlano = !!(window.PosPlano && window.PosPlano.estaEditando && window.PosPlano.estaEditando());

		$vacia.classList.toggle('d-none', editandoPlano || visibles.length > 0);

		$mesas.innerHTML = visibles.map(function (mesa) {
			// Misma regla de estado que el plano: implementación única en `pos-plano-dibujo.js`
			// (feature 041). Duplicarla es lo que permitiría que tarjeta y plano se contradigan.
			var clase = window.PosPlanoDibujo.claseEstado(mesa);

			var badge = '';
			if (mesa.olvidada) {
				badge = '<span class="estado-badge">Sin tocar</span>';
			} else if (mesa.estado === 'ocupada') {
				badge = '<span class="estado-badge">Abierta</span>';
			}

			var cuerpo = mesa.estado === 'libre'
				? '<span class="importe">Libre</span><span class="meta">Toca para abrir cuenta</span>'
				: '<span class="importe">' + formatoImporte(mesa.pendiente) + ' €</span>' +
				  '<span class="meta">Hace ' + mesa.abierta_hace_min + ' min</span>';

			return '<button type="button" class="pos-mesa ' + clase + '" data-url="' + escapeHtml(mesa.abrir_url) + '"' +
				' data-mesa-id="' + mesa.id + '">' +
				badge +
				'<span class="nombre">' + escapeHtml(mesa.nombre) + '</span>' +
				cuerpo +
				'</button>';
		}).join('');
	}

	/**
	 * Métricas de cabecera. Se cuentan **una sola vez** aquí, sobre todas las mesas del tenant y con
	 * la misma regla de estado que dibujan las dos vistas: así el total de la cabecera no puede
	 * contradecir lo que se ve abajo, sea plano o tarjetas (FR-018).
	 */
	function pintarCards() {
		var conteo = { libre: 0, ocupada: 0, olvidada: 0 };

		datos.mesas.forEach(function (mesa) {
			conteo[window.PosPlanoDibujo.claseEstado(mesa)]++;
		});

		var libres = conteo.libre, ocupadas = conteo.ocupada, olvidadas = conteo.olvidada;

		$('[data-metric="total"]').text(datos.mesas.length);
		$('[data-metric="libres"]').text(libres);
		$('[data-metric="ocupadas"]').text(ocupadas);
		$('[data-metric="olvidadas"]').text(olvidadas);
	}

	function cargar() {
		return fetch(state.estadoUrl, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (r) { return r.json(); })
			.then(function (json) {
				datos = json;
				window.posSalaData = datos;
				pintarCards();
				// `aplicarVista` pinta zonas y mesas: se llama en lugar de pintarlas aquí para que
				// la vista guardada quede aplicada ANTES del primer pintado, sin parpadeo
				// tarjetas -> plano en cada entrada a la Sala.
				aplicarVista(false);
				document.dispatchEvent(new CustomEvent('pos-sala:actualizado', { detail: datos }));
			})
			.catch(function () {
				window.showToast('error', 'No se pudo cargar el estado de la sala.');
			});
	}

	$zonas.addEventListener('click', function (e) {
		var btn = e.target.closest('.pos-filtro');
		if (!btn) { return; }
		zonaActiva = btn.getAttribute('data-zona') || '';
		pintarZonas();
		pintarMesas();
		document.dispatchEvent(new CustomEvent('pos-sala:zona-cambiada', { detail: { zonaId: zonaActiva } }));
	});

	// El init del plano (feature 039) necesita saber qué zona está activa sin duplicar el estado.
	window.posSalaZonaActiva = function () { return zonaActiva; };

	/**
	 * A dónde lleva tocar una mesa. Mesa libre: se abre el TPV con la mesa preseleccionada; el POS
	 * crea la cuenta al guardar la primera línea, para no dejar cuentas vacías por cada toque en la
	 * sala. Mesa ocupada: su cuenta.
	 *
	 * Lo consumen **las dos vistas** (tarjeta y plano, feature 041): la forma de garantizar que
	 * las dos lleven al mismo sitio es que compartan la decisión, no que la repitan (FR-013/FR-014).
	 */
	function destinoMesa(mesa) {
		if (!mesa || !mesa.abrir_url) { return null; }
		var url = mesa.abrir_url;
		return mesa.estado === 'libre'
			? url + (url.indexOf('?') === -1 ? '?' : '&') + 'mesa=' + mesa.id
			: url;
	}

	window.posSalaDestinoMesa = destinoMesa;
	window.posSalaMesaPorId = function (id) {
		return datos.mesas.filter(function (m) { return String(m.id) === String(id); })[0] || null;
	};

	$mesas.addEventListener('click', function (e) {
		var btn = e.target.closest('.pos-mesa');
		if (!btn) { return; }

		// El destino se resuelve en el momento del toque a partir del elemento tocado, nunca de un
		// índice capturado antes (FR-017).
		var destino = destinoMesa(window.posSalaMesaPorId(btn.getAttribute('data-mesa-id')));
		if (destino) { window.location.href = destino; }
	});

	/**
	 * Alternado de vista (feature 041). La rejilla de tarjetas y el lienzo de servicio son
	 * hermanos: solo uno está visible a la vez. El editor de plano (`.pos-plano-wrap`) es un tercer
	 * contenedor y no se abre ni se cierra desde aquí — pero sí hay que **respetarlo**: mientras
	 * está abierto, los dos contenedores de lectura se quedan ocultos, sea cual sea la vista
	 * elegida.
	 *
	 * Sin esa condición aparecían DOS planos en pantalla (el de servicio arriba, el editor abajo):
	 * `activarEdicion()` oculta el de lectura al entrar, pero cualquier refresco posterior de la
	 * sala —crear una zona o una mesa desde el panel de gestión dispara uno— volvía a pasar por
	 * aquí y le devolvía la visibilidad, porque esta función solo miraba `vistaActiva`. El editor
	 * seguía debajo con los cambios sin guardar, así que además parecía que la edición se había
	 * perdido.
	 */
	function aplicarVista(anunciar) {
		// "Todas" no aplica en el plano: se resuelve a la primera zona disponible y se refleja en
		// el filtro disparando el evento que ya existe, para no duplicar el estado de "qué zona
		// está activa" (D6).
		if (vistaActiva === 'plano' && zonaActiva === '' && datos.zonas.length > 0) {
			zonaActiva = datos.zonas[0].id;
			document.dispatchEvent(new CustomEvent('pos-sala:zona-cambiada', { detail: { zonaId: zonaActiva } }));
		}

		// `window.PosPlano` solo existe con permiso de configuración (el editor sale por su guard
		// antes de publicarlo), así que se comprueba su existencia y no solo su respuesta.
		var editandoPlano = !!(window.PosPlano && window.PosPlano.estaEditando && window.PosPlano.estaEditando());

		$mesas.classList.toggle('d-none', editandoPlano || vistaActiva !== 'tarjetas');
		if ($planoServicio) { $planoServicio.classList.toggle('d-none', editandoPlano || vistaActiva !== 'plano'); }

		if ($vista) {
			$vista.querySelectorAll('[data-vista]').forEach(function (btn) {
				var activo = btn.getAttribute('data-vista') === vistaActiva;
				btn.classList.toggle('active', activo);
				btn.setAttribute('aria-pressed', activo ? 'true' : 'false');
			});
		}

		pintarZonas();
		pintarMesas();

		if (anunciar !== false) {
			document.dispatchEvent(new CustomEvent('pos-sala:vista-cambiada', { detail: { vista: vistaActiva } }));
		}
	}

	window.posSalaVistaActiva = function () { return vistaActiva; };
	// El editor de plano la usa al salir del modo edición, para devolver la Sala a la vista que el
	// usuario tenía elegida en vez de asumir tarjetas (feature 041).
	window.posSalaAplicarVista = function () { aplicarVista(); };

	if ($vista) {
		$vista.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-vista]');
			if (!btn) { return; }

			var vista = btn.getAttribute('data-vista');
			if (VISTAS.indexOf(vista) === -1 || vista === vistaActiva) { return; }

			vistaActiva = vista;
			guardarVista(vista);
			aplicarVista();
		});
	}

	if ($refrescar) {
		$refrescar.addEventListener('click', function () {
			window.withButtonLoading($refrescar, cargar);
		});
	}

	cargar();
})();
