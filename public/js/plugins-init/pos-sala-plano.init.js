/**
 * Plano de sala arrastrable (feature 039). Se apoya en `window.posSalaData` (cargado por
 * `pos-sala.init.js`) para las mesas/zonas y añade un "modo edición" propio: el lienzo de la zona
 * activa (medidas y celdas recortadas propias, feature 042; 8×6 por defecto), arrastre con
 * jQuery UI `draggable` (asa obligatoria, sin dependencia nueva —
 * docs/04-front-guidelines.md), reacomodo por colisión en cliente (previsualización, D3 de
 * research.md) y guardado explícito por botón (D4), nunca por evento de arrastre.
 *
 * El arrastre solo toca el estado en memoria (`pendiente`); nada se persiste hasta "Guardar
 * plano". Cambiar de zona o recargar sin guardar descarta los cambios (FR-009).
 */
(function () {
	'use strict';

	// Geometría y dibujo viven en `pos-plano-dibujo.js` (feature 041): la vista de servicio dibuja
	// exactamente lo mismo que el editor porque comparten esta implementación, no una copia.
	var D = window.PosPlanoDibujo;
	var CELL = D.CELL;
	var GAP = D.GAP;
	var STEP = D.STEP;

	var FORMAS = [
		{ valor: 'redonda', etiqueta: 'Redonda' },
		{ valor: 'cuadrada', etiqueta: 'Cuadrada' },
		{ valor: 'barra', etiqueta: 'Barra' },
	];

	var state = window.posPlanoState || {};
	if (!state.puedeEditar) { return; }

	var $toggle = document.getElementById('pos-plano-toggle');
	var $columnas = document.getElementById('pos-plano-columnas');
	var $filas = document.getElementById('pos-plano-filas');
	var $recorte = document.getElementById('pos-plano-recorte');
	var $hint = document.getElementById('pos-plano-hint');
	var $guardar = document.getElementById('pos-plano-guardar');
	var $wrap = document.getElementById('pos-plano-wrap');
	var $canvas = document.getElementById('pos-plano-canvas');
	var $fueraRejilla = document.getElementById('pos-plano-fuera-rejilla');
	var $popover = document.getElementById('plano-popover');
	var $popoverFormas = document.getElementById('plano-popover-formas');
	var $mesasLectura = document.getElementById('pos-sala-mesas');
	var $planoServicio = document.getElementById('pos-plano-servicio');
	var $zonasTabs = document.getElementById('pos-sala-zonas');

	if (!$toggle || !$wrap || !$canvas) { return; }

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

	// Lienzo de la zona activa (feature 042). Se guarda por zona igual que las mesas: cambiar de
	// pestaña sin guardar tampoco descarta un ajuste de medidas o un recorte a medio hacer.
	// `inactivas` es un OBJETO-conjunto (clave "fila-columna" → true) y se muta en sitio, nunca se
	// reasigna, para que el estado compartido de la zona siga apuntando al mismo objeto
	// (docs/04-front-guidelines.md, feature 038).
	var geometria = { columnas: D.COLS_DEFECTO, filas: D.FILAS_DEFECTO, inactivas: {} };
	var geometriaPorZona = {};
	var recortando = false;
	var $celdas = null;

	/** ¿Es una celda recortada (no es sala)? */
	function esInactiva(fila, columna) {
		return geometria.inactivas[fila + '-' + columna] === true;
	}

	/** Número de celdas de suelo que quedarían con una geometría dada (G5 en cliente). */
	function celdasDeSuelo(columnas, filas, inactivas) {
		var fuera = 0;
		Object.keys(inactivas).forEach(function (clave) {
			var partes = clave.split('-');
			if (parseInt(partes[0], 10) < filas && parseInt(partes[1], 10) < columnas) { fuera++; }
		});
		return columnas * filas - fuera;
	}

	/** La geometría de la zona activa, en la forma que espera `PosPlanoDibujo`. */
	function geometriaDibujo() {
		return {
			columnas: geometria.columnas,
			filas: geometria.filas,
			inactivas: Object.keys(geometria.inactivas),
		};
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

	/** ¿Toca el rectángulo alguna celda recortada? (G4 en cliente, FR-006.) */
	function pisaRecorte(fila, columna, ancho, alto) {
		for (var f = fila; f < fila + alto; f++) {
			for (var c = columna; c < columna + ancho; c++) {
				if (esInactiva(f, c)) { return true; }
			}
		}
		return false;
	}

	/**
	 * ¿Cabe el rectángulo en el lienzo DE ESTA ZONA, sin pisar a nadie y sin salirse de la sala?
	 * (G2 + G3 + G4.) Una celda recortada se comporta exactamente igual que una celda ocupada:
	 * misma ruta de colisión, mismo bloqueo, mismo feedback (FR-006).
	 */
	function rectanguloValido(r, ignorarId) {
		if (r.fila < 0 || r.columna < 0 || r.ancho < 1 || r.alto < 1) { return false; }
		if (r.columna + r.ancho > geometria.columnas || r.fila + r.alto > geometria.filas) { return false; }
		if (pisaRecorte(r.fila, r.columna, r.ancho, r.alto)) { return false; }
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

		for (var f = 0; f <= geometria.filas - alto; f++) {
			for (var c = 0; c <= geometria.columnas - ancho; c++) {
				if (pisaRecorte(f, c, ancho, alto)) { continue; }
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

	// Geometría y HTML de una mesa: implementación única en `pos-plano-dibujo.js` (feature 041).
	// Aquí solo se alias-an para no reescribir el resto del editor.
	var aPx = D.aPx;
	var aCeldas = D.aCeldas;

	function posicionPx(fila, columna) {
		return { left: columna * STEP, top: fila * STEP };
	}

	function renderMesaHtml(mesa) {
		return D.mesaHtml(mesa, { modo: 'edicion' });
	}

	function pintarCanvas() {
		// Las medidas del lienzo se escriben en el PROPIO lienzo (D4), no en `documentElement`.
		D.aplicarGeometria($canvas, geometria);

		var celdasHtml = '';
		if (recortando) {
			// En modo recorte la capa lleva TODAS las celdas: cada una es un blanco de toque.
			celdasHtml = '<div class="pos-plano-celdas recortando" id="pos-plano-celdas">' +
				D.celdasHtml(geometriaDibujo(), { modo: 'edicion' }) + '</div>';
		} else {
			// Fuera del modo recorte solo se dibujan las celdas que NO son sala: son contorno, y
			// el encargado tiene que verlas mientras coloca mesas.
			celdasHtml = '<div class="pos-plano-celdas">' +
				D.celdasHtml(geometriaDibujo(), { modo: 'servicio' }) + '</div>';
		}

		$canvas.innerHTML = celdasHtml + pendiente.map(renderMesaHtml).join('');
		$celdas = $canvas.querySelector('.pos-plano-celdas');

		// D6: pintar sobre un lienzo que ya tiene arrastre exige un modo de gesto explícito. Con el
		// recorte activo las mesas dejan de ser arrastrables y redimensionables — si no, el mismo
		// dedo sobre el mismo pixel querría decir dos cosas distintas.
		if (recortando) { return; }

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
	 * Igual que `marcarBloqueo`, pero por id de mesa: se usa cuando el rechazo ocurre DESPUÉS de un
	 * repintado, momento en el que el elemento que el usuario estaba arrastrando ya no existe.
	 */
	function marcarBloqueoMesa(id) {
		var el = $canvas.querySelector('.plano-mesa[data-mesa-id="' + id + '"]');
		if (el) { marcarBloqueo(el); }
	}

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
			// NO poner aquí el tamaño de una celda, por tentador que sea. El plugin `grid` de
			// jQuery UI, cuando el tamaño resultante queda por debajo del mínimo, **le suma un
			// paso de rejilla entero** en vez de recortarlo al mínimo (`y && (f += u)` en
			// jquery-ui.min.js). Con `minHeight: CELL` bastaba un subpíxel —el escalado de
			// pantalla de Windows, el zoom del navegador— para que el alto de una celda saliera
			// 95.99, se considerara "por debajo del mínimo" y rebotara al tamaño anterior: era
			// imposible bajar de 2 celdas a 1, mientras que de 3 a 2 funcionaba, porque esa rama
			// solo puede dispararse al acercarse al mínimo.
			// El mínimo real de una celda lo garantizamos nosotros: `aCeldas()` nunca devuelve
			// menos de 1 y `rectanguloValido()` rechaza cualquier rectángulo menor, y como en cada
			// `resize` reescribimos `ui.size` con el rectángulo elegido, el DOM tampoco baja de ahí.
			minWidth: 1,
			minHeight: 1,
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
		columna = Math.max(0, Math.min(geometria.columnas - mesa.ancho, columna));
		fila = Math.max(0, Math.min(geometria.filas - mesa.alto, fila));

		// Instantánea para poder cancelar el movimiento entero si algún desplazado no cabe.
		var original = pendiente.map(function (m) {
			return { mesa: m, fila: m.fila, columna: m.columna };
		});

		function revertir() {
			original.forEach(function (o) { o.mesa.fila = o.fila; o.mesa.columna = o.columna; });
		}

		// Soltar sobre una parte que no es sala se rechaza como cualquier otra colisión (FR-006):
		// el sistema NO le busca sitio, porque dónde va una mesa es decisión del encargado.
		if (pisaRecorte(fila, columna, mesa.ancho, mesa.alto)) {
			revertir();
			pintarCanvas();
			marcarBloqueoMesa(id);
			return;
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
			delete geometriaPorZona[id];
		}

		if (pendientePorZona[id]) {
			pendiente = pendientePorZona[id];
			versionZona = versionPorZona[id];
			geometria = geometriaPorZona[id];
		} else {
			var zona = datos.zonas.filter(function (z) { return String(z.id) === String(id); })[0];
			versionZona = zona ? zona.version : 1;
			versionPorZona[id] = versionZona;

			// El lienzo se lee del payload de la zona (contrato de la feature 042): el editor no
			// lo infiere de las mesas ni de ninguna constante propia.
			var geo = D.geometriaDeZona(zona);
			geometria = { columnas: geo.columnas, filas: geo.filas, inactivas: {} };
			geo.inactivas.forEach(function (clave) { geometria.inactivas[clave] = true; });
			geometriaPorZona[id] = geometria;

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

		sincronizarControles();

		var fuera = mesasZona.length - pendiente.length;
		$fueraRejilla.textContent = fuera > 0
			? fuera + ' mesa(s) de esta zona no tienen posición en la rejilla (creadas cuando ya estaba llena) y no aparecen en el plano.'
			: '';

		pintarCanvas();
	}

	/** Refleja en los controles el lienzo de la zona activa. */
	function sincronizarControles() {
		if ($columnas) { $columnas.value = geometria.columnas; }
		if ($filas) { $filas.value = geometria.filas; }
	}

	/**
	 * Aplica unas medidas nuevas al estado en memoria y redibuja **en el acto**, sin una sola
	 * peticion al servidor (FR-013): el cambio de lienzo viaja en el guardado explicito por boton,
	 * igual que las posiciones de las mesas.
	 *
	 * Devuelve `false` si el cambio se rechaza, dejando la geometria intacta.
	 */
	function aplicarMedidas(columnas, filas) {
		columnas = Math.max(D.MIN, Math.min(D.MAX, parseInt(columnas, 10) || geometria.columnas));
		filas = Math.max(D.MIN, Math.min(D.MAX, parseInt(filas, 10) || geometria.filas));

		if (columnas === geometria.columnas && filas === geometria.filas) {
			sincronizarControles();
			return true;
		}

		// FR-009/D9: nada se mueve solo. Si al encoger alguna mesa quedaria fuera del lienzo, el
		// cambio no se aplica y se dice CUANTAS mesas lo impiden -- el conteo, no la lista: con
		// doce mesas fuera, un toast con doce nombres no se lee.
		var estorban = pendiente.filter(function (m) {
			return m.columna + m.ancho > columnas || m.fila + m.alto > filas;
		}).length;

		if (estorban > 0) {
			sincronizarControles();
			window.showToast('warning', estorban === 1
				? 'Hay 1 mesa fuera de esas medidas. Muevela antes de reducir la zona.'
				: 'Hay ' + estorban + ' mesas fuera de esas medidas. Muevelas antes de reducir la zona.');
			return false;
		}

		// G5 en cliente: encoger no puede dejar la zona sin una sola celda de sala (FR-011).
		if (celdasDeSuelo(columnas, filas, geometria.inactivas) < 1) {
			sincronizarControles();
			window.showToast('warning', 'La zona debe conservar al menos una celda de sala.');
			return false;
		}

		geometria.columnas = columnas;
		geometria.filas = filas;

		// El recorte de las celdas que dejan de existir se descarta, no se recuerda: si la zona
		// vuelve a crecer, esas celdas vuelven como suelo. Misma regla que el servidor al
		// normalizar la mascara, para que cliente y servidor persistan lo mismo.
		Object.keys(geometria.inactivas).forEach(function (clave) {
			var partes = clave.split('-');
			if (parseInt(partes[0], 10) >= filas || parseInt(partes[1], 10) >= columnas) {
				delete geometria.inactivas[clave];
			}
		});

		sincronizarControles();
		pintarCanvas();
		return true;
	}

	if ($columnas && $filas) {
		[$columnas, $filas].forEach(function (input) {
			input.addEventListener('change', function () {
				aplicarMedidas($columnas.value, $filas.value);
			});
		});
	}

	if ($recorte) {
		$recorte.addEventListener('click', function () {
			recortando = !recortando;
			$recorte.setAttribute('aria-pressed', recortando ? 'true' : 'false');
			if ($hint) {
				$hint.textContent = recortando
					? 'Arrastra sobre el plano para marcar que celdas no son sala. Vuelve a tocar "Recortar sala" para colocar mesas.'
					: 'Arrastra las mesas por el asa para reordenarlas. Toca una mesa para cambiar su forma y tamano.';
			}
			cerrarPopover();
			pintarCanvas();
		});
	}

	// -- Pintado del recorte con Pointer Events (FR-004) --------------------------------------
	//
	// `pointerdown` + arrastre con `setPointerCapture` es lo que permite marcar (o desmarcar) una
	// tira entera de celdas en UN gesto continuo, con raton o con el dedo, sin que el navegador se
	// quede el arrastre como scroll. El sentido del gesto lo decide la PRIMERA celda tocada: si era
	// sala, todo el arrastre recorta; si no lo era, todo el arrastre devuelve suelo. Asi el gesto
	// nunca alterna solo por volver a pasar por una celda ya pintada.

	var pintando = null;   // true = recortar, false = devolver a sala
	var rechazadas = 0;    // celdas que el gesto no pudo cambiar (se avisa UNA vez al soltar)

	function mesaSobreCelda(fila, columna) {
		return pendiente.filter(function (m) {
			return fila >= m.fila && fila < m.fila + m.alto &&
				columna >= m.columna && columna < m.columna + m.ancho;
		})[0] || null;
	}

	function pintarCelda(el) {
		var fila = parseInt(el.getAttribute('data-fila'), 10);
		var columna = parseInt(el.getAttribute('data-columna'), 10);
		var clave = fila + '-' + columna;
		var yaInactiva = geometria.inactivas[clave] === true;

		if (yaInactiva === pintando) { return; }

		if (pintando) {
			// FR-010: recortar una celda ocupada NO mueve la mesa. La celda no cambia y la mesa que
			// lo impide da el feedback de bloqueo (sombra + micro-desplazamiento), nunca un cambio
			// de color de borde: ese esta reservado al estado libre/ocupada/olvidada (D7).
			var mesa = mesaSobreCelda(fila, columna);
			if (mesa) {
				rechazadas++;
				marcarBloqueoMesa(mesa.id);
				return;
			}

			// FR-011: el recorte no puede dejar la zona sin una sola celda de sala.
			if (celdasDeSuelo(geometria.columnas, geometria.filas, geometria.inactivas) <= 1) {
				rechazadas++;
				return;
			}

			geometria.inactivas[clave] = true;
			el.classList.add('plano-celda-inactiva');
		} else {
			delete geometria.inactivas[clave];
			el.classList.remove('plano-celda-inactiva');
		}
	}

	$canvas.addEventListener('pointerdown', function (e) {
		if (!recortando) { return; }

		var celda = e.target.closest ? e.target.closest('.plano-celda') : null;
		if (!celda) { return; }

		rechazadas = 0;
		pintando = !celda.classList.contains('plano-celda-inactiva');
		// La captura mantiene el gesto vivo aunque el dedo salga del lienzo: sin ella, soltar fuera
		// dejaria el pintado colgado. Se captura en el LIENZO, no en la celda, porque el destino de
		// cada `pointermove` se resuelve por posicion y no por el elemento capturado.
		if ($canvas.setPointerCapture) { $canvas.setPointerCapture(e.pointerId); }
		pintarCelda(celda);
		e.preventDefault();
	});

	// Con el puntero capturado por el lienzo, `pointerenter` de cada celda ya no llega: el destino
	// se resuelve por coordenadas, que ademas es lo unico fiable con el dedo (el toque no emite
	// eventos de hover sobre los elementos por los que pasa).
	$canvas.addEventListener('pointermove', function (e) {
		if (!recortando || pintando === null) { return; }

		var el = document.elementFromPoint(e.clientX, e.clientY);
		var celda = el && el.closest ? el.closest('.plano-celda') : null;
		if (celda && $canvas.contains(celda)) { pintarCelda(celda); }
		e.preventDefault();
	});

	function terminarPintado() {
		if (pintando === null) { return; }
		pintando = null;

		// UN solo toast al soltar, con el conteo agregado (FR-010): uno por celda recorrida
		// dispararia decenas en un solo arrastre y taparia la pantalla entera.
		if (rechazadas > 0) {
			window.showToast('warning', rechazadas === 1
				? 'Una celda no se pudo recortar: hay una mesa encima o es la ultima celda de sala.'
				: rechazadas + ' celdas no se pudieron recortar: hay mesas encima o dejarian la zona sin sala.');
		}
		rechazadas = 0;
	}

	document.addEventListener('pointerup', terminarPintado);
	document.addEventListener('pointercancel', terminarPintado);

	function activarEdicion() {
		if (editando) { return; }

		// Sesión de edición nueva: el caché de zonas pendientes es de la sesión anterior (ya
		// guardada o descartada al salir), no debe arrastrarse a esta.
		pendientePorZona = {};
		versionPorZona = {};
		geometriaPorZona = {};
		salirDeRecorte();

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
		// La vista de servicio (feature 041) es un tercer contenedor: entrar a editar la oculta,
		// igual que a la rejilla de tarjetas.
		if ($planoServicio) { $planoServicio.classList.add('d-none'); }
		$wrap.classList.add('activo');
		cargarZona(zid);
	}

	/** Devuelve el editor al modo de colocar mesas (no toca la mascara ya pintada). */
	function salirDeRecorte() {
		if (!recortando) { return; }
		recortando = false;
		if ($recorte) { $recorte.setAttribute('aria-pressed', 'false'); }
		if ($hint) {
			$hint.textContent = 'Arrastra las mesas por el asa para reordenarlas. Toca una mesa para cambiar su forma y tamano.';
		}
	}

	function desactivarEdicion() {
		editando = false;
		cerrarPopover();
		salirDeRecorte();
		// Salir del modo edicion descarta lo pendiente, y el lienzo va en el mismo saco: al volver
		// a entrar, `cargarZona` lo vuelve a derivar del payload, que es lo ultimo guardado.
		pendientePorZona = {};
		versionPorZona = {};
		geometriaPorZona = {};
		$toggle.classList.remove('d-none');
		$guardar.classList.add('d-none');
		// Devolver la Sala a la vista elegida por el usuario (tarjetas o plano de servicio), no
		// asumir tarjetas: quien edita el plano lo normal es que estuviera viendo el plano.
		$mesasLectura.classList.remove('d-none-plano', 'd-none');
		if (window.posSalaAplicarVista) { window.posSalaAplicarVista(); }
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
			// El lienzo va en el MISMO guardado que las mesas (feature 042): el servidor los
			// valida juntos y los persiste en una sola transaccion, asi que no puede quedar una
			// mesa fuera de su propio plano ni por un instante.
			columnas: geometria.columnas,
			filas: geometria.filas,
			celdas_inactivas: Object.keys(geometria.inactivas),
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
