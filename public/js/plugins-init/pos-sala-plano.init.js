/**
 * Plano de sala arrastrable (feature 039). Se apoya en `window.posSalaData` (cargado por
 * `pos-sala.init.js`) para las mesas/zonas y añade un "modo edición" propio: rejilla de 8×6
 * celdas por zona, arrastre con jQuery UI `draggable` (asa obligatoria, sin dependencia nueva —
 * docs/04-front-guidelines.md), reacomodo por colisión en cliente (previsualización, D3 de
 * research.md) y guardado explícito por botón (D4), nunca por evento de arrastre.
 *
 * El arrastre solo toca el estado en memoria (`pendiente`); nada se persiste hasta "Guardar
 * plano". Cambiar de zona o recargar sin guardar descarta los cambios (FR-009).
 */
(function () {
	'use strict';

	var CELL = 96;
	var GAP = 12;
	var COLS = 8;
	var ROWS = 6;
	var STEP = CELL + GAP;

	var FORMAS = [
		{ valor: 'redonda', etiqueta: 'Redonda' },
		{ valor: 'cuadrada', etiqueta: 'Cuadrada' },
		{ valor: 'barra', etiqueta: 'Barra' },
	];

	var state = window.posPlanoState || {};
	if (!state.puedeEditar) { return; }

	var $toggle = document.getElementById('pos-plano-toggle');
	var $guardar = document.getElementById('pos-plano-guardar');
	var $wrap = document.getElementById('pos-plano-wrap');
	var $canvas = document.getElementById('pos-plano-canvas');
	var $fueraRejilla = document.getElementById('pos-plano-fuera-rejilla');
	var $popover = document.getElementById('plano-popover');
	var $popoverFormas = document.getElementById('plano-popover-formas');
	var $mesasLectura = document.getElementById('pos-sala-mesas');
	var $zonasTabs = document.getElementById('pos-sala-zonas');

	if (!$toggle || !$wrap || !$canvas) { return; }

	document.documentElement.style.setProperty('--plano-cols', COLS);
	document.documentElement.style.setProperty('--plano-rows', ROWS);
	document.documentElement.style.setProperty('--plano-cell', CELL + 'px');
	document.documentElement.style.setProperty('--plano-gap', GAP + 'px');

	var editando = false;
	var zonaId = null;
	var versionZona = 1;
	var pendiente = []; // [{id, fila, columna, ancho, alto, forma, estado, olvidada}]
	var mesaSeleccionada = null; // id de la mesa con el popover abierto

	// Estado pendiente POR ZONA: cambiar de pestaña sin guardar ya NO descarta el arrastre de la
	// zona anterior (a pedido del usuario) — solo se descarta al salir del modo edición por
	// completo o al recargar la página. `pendiente` siempre apunta al array de la zona activa
	// (mismo objeto guardado aquí), así que mutarlo ya deja el cambio reflejado en el mapa.
	var pendientePorZona = {};
	var versionPorZona = {};

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

	function mesaPorId(id) {
		return pendiente.filter(function (m) { return String(m.id) === String(id); })[0] || null;
	}

	/** ¿Se solapan dos rectángulos alineados a ejes? (AABB, D3 de research.md — misma regla que el servidor.) */
	function solapan(a, b) {
		return a.columna < b.columna + b.ancho && b.columna < a.columna + a.ancho &&
			a.fila < b.fila + b.alto && b.fila < a.fila + a.alto;
	}

	/** ¿Está libre el rectángulo completo, ignorando la mesa `ignorarId`? */
	function rectanguloLibre(fila, columna, ancho, alto, ignorarId) {
		var candidato = { fila: fila, columna: columna, ancho: ancho, alto: alto };
		return !pendiente.some(function (m) {
			return String(m.id) !== String(ignorarId) && solapan(candidato, m);
		});
	}

	/** ¿Cabe el rectángulo en la rejilla Y sin pisar a nadie? (G2 + G3.) */
	function rectanguloValido(r, ignorarId) {
		if (r.fila < 0 || r.columna < 0 || r.ancho < 1 || r.alto < 1) { return false; }
		if (r.columna + r.ancho > COLS || r.fila + r.alto > ROWS) { return false; }
		return rectanguloLibre(r.fila, r.columna, r.ancho, r.alto, ignorarId);
	}

	/**
	 * Origen libre más cercano (distancia euclídea) donde cabe el rectángulo COMPLETO, no solo una
	 * celda suelta (D4). `null` si no hay hueco: con rectángulos ese caso deja de ser extremo (una
	 * barra de 3×1 necesita tres celdas contiguas), de ahí que el aviso al usuario explique el motivo.
	 */
	function huecoMasCercano(filaOrigen, columnaOrigen, ancho, alto, ignorarId) {
		var mejor = null;
		var mejorDist = Infinity;

		for (var f = 0; f <= ROWS - alto; f++) {
			for (var c = 0; c <= COLS - ancho; c++) {
				if (!rectanguloLibre(f, c, ancho, alto, ignorarId)) { continue; }
				var dist = Math.pow(f - filaOrigen, 2) + Math.pow(c - columnaOrigen, 2);
				if (dist < mejorDist) {
					mejorDist = dist;
					mejor = { fila: f, columna: c };
				}
			}
		}

		return mejor;
	}

	/**
	 * Ancho/alto en px de una mesa de `ancho`×`alto` celdas. Aritmética del encaje (D1): con
	 * `grid: [STEP, STEP]` el widget solo emite múltiplos del paso, y `n * STEP - GAP` es
	 * exactamente el tamaño que ocupa la mesa dejando el hueco de separación entre celdas.
	 *
	 * La forma ya NO decide el tamaño (feature 040): decide solo el aspecto (border-radius) y el
	 * reparto de sillas. Una `barra` puede ser 1×1 y una `cuadrada` 3×2.
	 */
	function dimensiones(ancho, alto) {
		return { width: ancho * STEP - GAP, height: alto * STEP - GAP };
	}

	/** Rectángulo (en celdas) → caja en px dentro del lienzo. */
	function aPx(r) {
		var dim = dimensiones(r.ancho, r.alto);
		return { left: r.columna * STEP, top: r.fila * STEP, width: dim.width, height: dim.height };
	}

	/** Caja en px (lo que emite el widget) → rectángulo en celdas. */
	function aCeldas(pos, size) {
		return {
			fila: Math.round(pos.top / STEP),
			columna: Math.round(pos.left / STEP),
			ancho: Math.max(1, Math.round((size.width + GAP) / STEP)),
			alto: Math.max(1, Math.round((size.height + GAP) / STEP)),
		};
	}

	/**
	 * Puntos de silla alrededor del perímetro, en porcentaje del tamaño del elemento (D9): dos
	 * plazas por celda de ancho (una arriba y otra abajo) y dos por celda de alto (una a cada lado),
	 * de modo que el número de sillas comunica el tamaño real de la mesa.
	 *
	 * La `barra` solo lleva sillas en el lado largo, como una barra real, y ahí sí van dos por celda.
	 *
	 * Fuera de alcance a propósito: esto NO es la capacidad de comensales como dato de negocio, es
	 * una señal visual.
	 */
	function sillasParaMesa(forma, ancho, alto) {
		var puntos = [];
		var i;

		if (forma === 'barra') {
			var largo = ancho >= alto ? ancho : alto;
			var plazas = Math.max(2, largo * 2);
			for (i = 0; i < plazas; i++) {
				var p = ((i + 0.5) / plazas) * 100;
				puntos.push(ancho >= alto ? [p, -6] : [-6, p]);
			}
			return puntos;
		}

		for (i = 0; i < ancho; i++) {
			var x = ((i + 0.5) / ancho) * 100;
			puntos.push([x, -6]);
			puntos.push([x, 106]);
		}
		for (i = 0; i < alto; i++) {
			var y = ((i + 0.5) / alto) * 100;
			puntos.push([-6, y]);
			puntos.push([106, y]);
		}

		return puntos;
	}

	function posicionPx(fila, columna) {
		return { left: columna * STEP, top: fila * STEP };
	}

	function renderMesaHtml(mesa) {
		var caja = aPx(mesa);
		var clase = mesa.estado === 'libre' ? 'libre' : (mesa.olvidada ? 'olvidada' : 'ocupada');

		var sillasHtml = sillasParaMesa(mesa.forma, mesa.ancho, mesa.alto).map(function (p) {
			return '<span class="silla" style="left:' + p[0] + '%;top:' + p[1] + '%;transform:translate(-50%,-50%);"></span>';
		}).join('');

		// `estirada` sustituye a la antigua forma `rectangular`: el borde se redondea menos en cuanto
		// la mesa deja de ser un cuadrado, sin que el usuario tenga que elegir nada.
		var estirada = mesa.ancho !== mesa.alto ? ' estirada' : '';

		return '<div class="plano-mesa ' + clase + ' forma-' + mesa.forma + estirada + '" data-mesa-id="' + mesa.id + '"' +
			' style="left:' + caja.left + 'px;top:' + caja.top + 'px;width:' + caja.width + 'px;height:' + caja.height + 'px;">' +
			sillasHtml +
			'<span class="plano-mesa-nombre">' + escapeHtml(mesa.nombre) + '</span>' +
			'<span class="plano-mesa-handle" title="Arrastrar"><i class="fas fa-arrows-up-down-left-right"></i></span>' +
			'</div>';
	}

	function pintarCanvas() {
		$canvas.innerHTML = pendiente.map(renderMesaHtml).join('');

		$canvas.querySelectorAll('.plano-mesa').forEach(function (el) {
			$(el).draggable({
				handle: '.plano-mesa-handle',
				containment: 'parent',
				grid: [STEP, STEP],
				distance: 6,
				stack: '.plano-mesa',
				stop: function () { onDragStop(el); },
			});

			activarResizable(el);

			el.addEventListener('click', function (e) {
				if (e.target.closest('.plano-mesa-handle') || e.target.closest('.ui-resizable-handle')) { return; }
				abrirPopover(el.getAttribute('data-mesa-id'));
			});
		});
	}

	var temporizadorBloqueo = null;

	/**
	 * Feedback de bloqueo (D7/FR-009): sombra exterior + micro-desplazamiento de rechazo. **Nunca**
	 * color de borde: `docs/04-front-guidelines.md` lo reserva para el estado libre/ocupada/olvidada
	 * y teñirlo haría parecer que la mesa cambió de estado durante el arrastre. Tampoco un toast: se
	 * dispararía decenas de veces en un solo arrastre.
	 */
	function marcarBloqueo(el) {
		el.classList.add('plano-mesa-bloqueada');
		clearTimeout(temporizadorBloqueo);
		temporizadorBloqueo = setTimeout(function () {
			el.classList.remove('plano-mesa-bloqueada');
		}, 320);
	}

	/**
	 * Redimensionado por el borde (US1/US2). El clamp va en `resize` y no en `stop` (D2): FR-007
	 * pide que el borde **no avance** al chocar, no que rebote al soltar.
	 *
	 * Siete asas, no ocho: se renuncia a `ne` porque ahí vive el asa circular de arrastre (D6).
	 */
	function activarResizable(el) {
		var id = el.getAttribute('data-mesa-id');
		var mesa = mesaPorId(id);
		if (!mesa) { return; }

		var ultimoValido = null;
		var rectInicial = null;

		/**
		 * Ejes que el asa agarrada tiene derecho a mover. Un asa de lado (`e`, `w`, `n`, `s`) toca
		 * UN eje; solo las esquinas tocan los dos.
		 *
		 * Sin esto, cualquier ruido en las medidas que devuelve el widget (bordes, padding,
		 * `box-sizing`, el redondeo del propio `grid`) podía colarse en el eje que el gesto ni
		 * siquiera estaba tocando: arrastrar el lado derecho de una mesa de 1×1 la dejaba en 2×2 en
		 * vez de 2×1. El eje del gesto es una restricción real, no una heurística, así que se
		 * aplica antes de cualquier otro cálculo.
		 */
		function ejesDelAsa() {
			var inst = $(el).resizable('instance') || $(el).data('ui-resizable');
			var axis = (inst && inst.axis) || '';

			return {
				x: axis.indexOf('e') !== -1 || axis.indexOf('w') !== -1,
				y: axis.indexOf('n') !== -1 || axis.indexOf('s') !== -1,
			};
		}

		$(el).resizable({
			handles: 'n,e,s,w,nw,se,sw',
			grid: [STEP, STEP],
			containment: '#pos-plano-canvas',
			minWidth: CELL,
			minHeight: CELL,
			start: function () {
				cerrarPopover();
				ultimoValido = { fila: mesa.fila, columna: mesa.columna, ancho: mesa.ancho, alto: mesa.alto };
				rectInicial = { fila: mesa.fila, columna: mesa.columna, ancho: mesa.ancho, alto: mesa.alto };
			},
			resize: function (e, ui) {
				// `ui.position`/`ui.size` son las MISMAS referencias que el widget vuelca al DOM
				// justo después de este callback, así que mutarlas basta para detener el borde.
				var cand = aCeldas(ui.position, ui.size);

				// El eje que el asa no toca se congela en el valor con el que empezó el gesto.
				var ejes = ejesDelAsa();
				if (!ejes.x) { cand.columna = rectInicial.columna; cand.ancho = rectInicial.ancho; }
				if (!ejes.y) { cand.fila = rectInicial.fila; cand.alto = rectInicial.alto; }

				// Clamp POR EJE (FR-004): al arrastrar una esquina el candidato puede ser inválido
				// en horizontal y válido en vertical; revertir el rectángulo entero bloquearía
				// también la dirección que sí tiene hueco.
				var pruebas = [
					cand,
					{ fila: cand.fila, alto: cand.alto, columna: ultimoValido.columna, ancho: ultimoValido.ancho },
					{ fila: ultimoValido.fila, alto: ultimoValido.alto, columna: cand.columna, ancho: cand.ancho },
					ultimoValido,
				];

				var elegido = ultimoValido;
				for (var i = 0; i < pruebas.length; i++) {
					if (rectanguloValido(pruebas[i], id)) { elegido = pruebas[i]; break; }
				}

				if (elegido !== cand) { marcarBloqueo(el); }
				ultimoValido = elegido;

				var caja = aPx(elegido);
				ui.position.left = caja.left;
				ui.position.top = caja.top;
				ui.size.width = caja.width;
				ui.size.height = caja.height;
			},
			stop: function () {
				// Solo estado en memoria: el guardado sigue siendo explícito por botón (FR-018).
				mesa.fila = ultimoValido.fila;
				mesa.columna = ultimoValido.columna;
				mesa.ancho = ultimoValido.ancho;
				mesa.alto = ultimoValido.alto;
				// Repintar recalcula también las sillas, para que la previsualización del tamaño
				// nuevo sea inmediata sin necesidad de guardar (US3).
				pintarCanvas();
			},
		});
	}

	function onDragStop(el) {
		var id = el.getAttribute('data-mesa-id');
		var mesa = mesaPorId(id);
		if (!mesa) { return; }

		var left = parseInt(el.style.left, 10) || 0;
		var top = parseInt(el.style.top, 10) || 0;
		var columna = Math.round(left / STEP);
		var fila = Math.round(top / STEP);
		// El destino se acota por el rectángulo completo, no por la celda de origen: una barra de
		// 3×1 no puede empezar en la columna 7 aunque esa celda exista.
		columna = Math.max(0, Math.min(COLS - mesa.ancho, columna));
		fila = Math.max(0, Math.min(ROWS - mesa.alto, fila));

		// Instantánea para poder cancelar el movimiento entero si algún desplazado no cabe.
		var original = pendiente.map(function (m) {
			return { mesa: m, fila: m.fila, columna: m.columna };
		});

		function revertir() {
			original.forEach(function (o) { o.mesa.fila = o.fila; o.mesa.columna = o.columna; });
		}

		mesa.fila = fila;
		mesa.columna = columna;

		// Mover una mesa encima de otra sí desplaza a la vecina (FR-008): es una intención clara
		// ("ponla aquí"), a diferencia de agrandar contra ella, que se bloquea (FR-007).
		var ocupantes = pendiente.filter(function (m) {
			return String(m.id) !== String(id) && solapan(m, mesa);
		});

		var cabenTodos = ocupantes.every(function (ocupante) {
			var destino = huecoMasCercano(ocupante.fila, ocupante.columna, ocupante.ancho, ocupante.alto, ocupante.id);
			if (!destino) { return false; }
			ocupante.fila = destino.fila;
			ocupante.columna = destino.columna;
			return true;
		});

		if (!cabenTodos) {
			revertir();
			pintarCanvas();
			window.showToast('warning', 'No hay espacio suficiente para la mesa desplazada. Movimiento cancelado.');
			return;
		}

		pintarCanvas();
	}

	function cerrarPopover() {
		mesaSeleccionada = null;
		$popover.classList.remove('abierto');
	}

	function abrirPopover(id) {
		var mesa = mesaPorId(id);
		if (!mesa) { return; }

		mesaSeleccionada = id;

		$popoverFormas.innerHTML = FORMAS.map(function (f) {
			var activo = f.valor === mesa.forma;
			return '<button type="button" class="btn btn-sm ' + (activo ? 'btn-primary' : 'btn-outline-secondary') + '" data-forma="' + f.valor + '">' + f.etiqueta + '</button>';
		}).join('');

		var pos = posicionPx(mesa.fila, mesa.columna);
		var left = Math.min(pos.left, $canvas.clientWidth - 15 * 16);
		$popover.style.left = Math.max(0, left) + 'px';
		$popover.style.top = (pos.top + CELL + 8) + 'px';
		$popover.classList.add('abierto');
	}

	$popover.addEventListener('click', function (e) {
		var btnForma = e.target.closest('[data-forma]');
		if (!btnForma) { return; }

		var mesa = mesaPorId(mesaSeleccionada);
		if (!mesa) { return; }

		mesa.forma = btnForma.getAttribute('data-forma');

		pintarCanvas();
		abrirPopover(mesaSeleccionada);
	});

	document.addEventListener('click', function (e) {
		if (!$popover.classList.contains('abierto')) { return; }
		if (e.target.closest('#plano-popover') || e.target.closest('.plano-mesa')) { return; }
		cerrarPopover();
	});

	/**
	 * Carga la zona `id` en el lienzo. Si ya había edición pendiente de esa zona en esta misma
	 * sesión de edición (el usuario había arrastrado algo y cambió de pestaña sin guardar), se
	 * recupera tal cual estaba — no se vuelve a derivar desde el servidor. `forzar: true` invalida
	 * ese caché (se usa tras un 409, cuando lo que hay en memoria ya sabemos que está obsoleto).
	 */
	function cargarZona(id, forzar) {
		cerrarPopover();
		zonaId = id;

		var datos = window.posSalaData || { zonas: [], mesas: [] };
		var mesasZona = datos.mesas.filter(function (m) { return String(m.zona_id) === String(id); });

		if (forzar) {
			delete pendientePorZona[id];
			delete versionPorZona[id];
		}

		if (pendientePorZona[id]) {
			pendiente = pendientePorZona[id];
			versionZona = versionPorZona[id];
		} else {
			var zona = datos.zonas.filter(function (z) { return String(z.id) === String(id); })[0];
			versionZona = zona ? zona.version : 1;
			versionPorZona[id] = versionZona;

			pendiente = mesasZona
				.filter(function (m) { return m.fila !== null && m.columna !== null; })
				.map(function (m) {
					return {
						id: m.id, nombre: m.nombre, fila: m.fila, columna: m.columna,
						ancho: m.ancho_celdas || 1, alto: m.alto_celdas || 1,
						forma: m.forma, estado: m.estado, olvidada: m.olvidada,
					};
				});
			pendientePorZona[id] = pendiente;
		}

		var fuera = mesasZona.length - pendiente.length;
		$fueraRejilla.textContent = fuera > 0
			? fuera + ' mesa(s) de esta zona no tienen posición en la rejilla (creadas cuando ya estaba llena) y no aparecen en el plano.'
			: '';

		pintarCanvas();
	}

	function activarEdicion() {
		if (editando) { return; }

		// Sesión de edición nueva: el caché de zonas pendientes es de la sesión anterior (ya
		// guardada o descartada al salir), no debe arrastrarse a esta.
		pendientePorZona = {};
		versionPorZona = {};

		var zid = window.posSalaZonaActiva ? window.posSalaZonaActiva() : '';
		if (!zid) {
			var datos = window.posSalaData || { zonas: [] };
			if (datos.zonas.length > 0) {
				zid = datos.zonas[0].id;
				// Selecciona la pestaña de esa zona para que quede consistente con la vista de lectura.
				var btn = $zonasTabs.querySelector('.pos-filtro[data-zona="' + zid + '"]');
				if (btn) { btn.click(); }
			}
			// Sin zonas todavía: se entra igual al modo edición con el lienzo vacío, para que el
			// panel de gestión (a la derecha) permita crear la primera desde acá — es el único
			// lugar donde se crean zonas/mesas desde que se sacó el CRUD de Configuración → POS.
		}

		editando = true;
		$toggle.classList.add('d-none');
		$guardar.classList.remove('d-none');
		$mesasLectura.classList.add('d-none');
		$wrap.classList.add('activo');
		cargarZona(zid);
	}

	function desactivarEdicion() {
		editando = false;
		cerrarPopover();
		pendientePorZona = {};
		versionPorZona = {};
		$toggle.classList.remove('d-none');
		$guardar.classList.add('d-none');
		$mesasLectura.classList.remove('d-none-plano', 'd-none');
		$wrap.classList.remove('activo');
	}

	$toggle.addEventListener('click', activarEdicion);

	document.addEventListener('pos-sala:zona-cambiada', function (e) {
		// Cambiar de pestaña de zona YA NO descarta lo pendiente: `cargarZona` recupera el estado
		// en memoria de esa zona si lo había, y solo deriva de `posSalaData` la primera vez.
		if (editando) { cargarZona(e.detail.zonaId); }
	});

	document.addEventListener('pos-sala:actualizado', function () {
		// Un refresco manual (botón "Actualizar") no debe pisar ediciones en curso: si el usuario
		// está en modo edición, se limita a refrescar el `version` disponible por si cambió.
		if (!editando) { return; }
	});

	$guardar.addEventListener('click', function () {
		if (!zonaId) { return; }

		var payload = {
			version: versionZona,
			mesas: pendiente.map(function (m) {
				return {
					id: m.id, fila: m.fila, columna: m.columna,
					ancho_celdas: m.ancho, alto_celdas: m.alto, forma: m.forma,
				};
			}),
		};

		var url = state.guardarUrlTemplate.replace('__ZONA__', zonaId);

		window.withButtonLoading($guardar, function () {
			return $.ajax({
				url: url,
				method: 'PUT',
				dataType: 'json',
				contentType: 'application/json',
				data: JSON.stringify(payload),
				headers: {
					Accept: 'application/json',
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				},
			});
		})
			.done(function (respuesta) {
				window.showToast('success', respuesta.message || 'Plano guardado.');
				versionZona = respuesta.version;
				desactivarEdicion();
				// Recarga el estado de la sala para que la vista de lectura refleje el plano nuevo.
				document.getElementById('pos-sala-refrescar').click();
			})
			.fail(function (xhr) {
				if (xhr.status === 409) {
					window.showToast('warning', (xhr.responseJSON && xhr.responseJSON.message) ||
						'El plano se modificó desde otro dispositivo. Recárgalo antes de guardar.');
					// Recarga el plano vigente de esta zona en vez de dejar al usuario reintentar
					// a ciegas contra un `version` que ya sabemos desactualizado.
					document.getElementById('pos-sala-refrescar').click();
					// `forzar: true` invalida el caché en memoria de esta zona: lo que había ahí ya
					// sabemos que quedó obsoleto (409), así que se re-deriva del estado recién
					// refrescado en vez de mostrar de nuevo lo que acaba de fallar.
					setTimeout(function () { if (editando) { cargarZona(zonaId, true); } }, 400);
					return;
				}

				var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo guardar el plano.';
				window.showToast('danger', msg);
			});
	});

	/**
	 * Soporte táctil (D5, FR-019). jQuery UI 1.11.1 **no lo tiene**: su widget `mouse` escucha
	 * `mousedown`/`mousemove` y los navegadores móviles solo emiten eventos de ratón emulados para
	 * *taps*, no durante un arrastre — por eso el arrastre de posición de la feature 039 tampoco
	 * funcionaba con el dedo.
	 *
	 * En vez de vendorizar `jquery.ui.touch-punch` (sin mantenimiento desde 2014, Principio V), se
	 * reenvían aquí los eventos táctiles de las asas —la circular de arrastre y las de borde— como
	 * los eventos de ratón que el widget espera. El `preventDefault()` es lo que impide que el
	 * navegador se quede el gesto como scroll de la página bajo el dedo.
	 */
	var ASAS = '.plano-mesa-handle, .ui-resizable-handle';
	var tocandoAsa = false;

	function reenviarTactil(e) {
		if (e.type === 'touchstart') {
			if (!e.target.closest || !e.target.closest(ASAS)) { return; }
			tocandoAsa = true;
		} else if (!tocandoAsa) {
			return;
		}

		var toque = e.changedTouches[0];
		if (!toque) { return; }

		var tipo = e.type === 'touchstart' ? 'mousedown' : (e.type === 'touchmove' ? 'mousemove' : 'mouseup');

		// Se despacha sobre el propio destino del toque: jQuery UI escucha `mousemove`/`mouseup` en
		// `document`, así que el evento le llega por burbujeo.
		e.target.dispatchEvent(new MouseEvent(tipo, {
			bubbles: true, cancelable: true, view: window,
			clientX: toque.clientX, clientY: toque.clientY, button: 0,
		}));

		e.preventDefault();

		if (e.type !== 'touchmove') { tocandoAsa = e.type === 'touchstart'; }
	}

	document.addEventListener('touchstart', reenviarTactil, { passive: false });
	document.addEventListener('touchmove', reenviarTactil, { passive: false });
	document.addEventListener('touchend', reenviarTactil, { passive: false });
	document.addEventListener('touchcancel', reenviarTactil, { passive: false });

	/**
	 * API mínima para `pos-sala-plano-gestion.init.js` (panel de zonas/mesas): permite que el CRUD
	 * de zonas/mesas actualice el lienzo en memoria SIN pisar posiciones que el usuario ya arrastró
	 * y todavía no guardó (a diferencia de un `cargarZona(id, true)`, que sí las descarta).
	 */
	window.PosPlano = {
		estaEditando: function () { return editando; },
		zonaActiva: function () { return zonaId; },
		/** Añade una mesa recién creada (con posición asignada por el servidor) al lienzo si su zona está activa. */
		agregarMesa: function (zid, mesa) {
			if (!pendientePorZona[zid] || mesa.fila === null || mesa.columna === null) { return; }
			pendientePorZona[zid].push({
				id: mesa.id, nombre: mesa.nombre, fila: mesa.fila, columna: mesa.columna,
				ancho: mesa.ancho_celdas || 1, alto: mesa.alto_celdas || 1,
				forma: mesa.forma || 'cuadrada', estado: 'libre', olvidada: false,
			});
			if (zid === zonaId) { pintarCanvas(); }
		},
		/** Refleja un renombrado sin tocar la posición pendiente de esa mesa. */
		renombrarMesa: function (zid, id, nombre) {
			var lista = pendientePorZona[zid];
			var mesa = lista && lista.filter(function (m) { return String(m.id) === String(id); })[0];
			if (mesa) { mesa.nombre = nombre; }
			if (zid === zonaId) { pintarCanvas(); }
		},
		/** Quita una mesa eliminada (su celda ya quedó libre en servidor). */
		quitarMesa: function (zid, id) {
			if (pendientePorZona[zid]) {
				pendientePorZona[zid] = pendientePorZona[zid].filter(function (m) { return String(m.id) !== String(id); });
				// `.filter()` crea un array nuevo: si es la zona activa, `pendiente` (variable de
				// módulo que usa `pintarCanvas`) tiene que apuntar a ese array nuevo, si no queda
				// pintando la lista vieja con la mesa borrada todavía adentro.
				if (zid === zonaId) { pendiente = pendientePorZona[zid]; }
			}
			if (zid === zonaId) { pintarCanvas(); }
		},
	};
})();
