/**
 * Dibujo del plano de sala (feature 041, D1): geometría de la rejilla y generación del HTML de una
 * mesa, compartidas por el **editor** (`pos-sala-plano.init.js`) y la **vista de servicio**
 * (`pos-sala-plano-servicio.init.js`).
 *
 * Antes esto vivía dentro del editor, detrás de su guard `if (!state.puedeEditar) return;`: el
 * camarero sin permiso de configuración se quedaba literalmente sin código de dibujo. Este archivo
 * va **fuera** de ese guard a propósito — es justo el caso que la feature 041 viene a servir.
 *
 * Todo lo de aquí son funciones **puras**: no tocan el DOM (solo producen cadenas de HTML), no
 * guardan estado y no saben si están dibujando para editar o para servir. Lo único que cambia entre
 * los dos modos es el contenido interior de la mesa y sus controles; el **contorno** (posición,
 * tamaño, forma, sillas y color de estado) es idéntico. Ese es el invariante del módulo: si el
 * contorno se calculara por separado en cada modo, el plano que ve el camarero podría dejar de
 * coincidir con el que colocó el encargado.
 */
(function () {
	'use strict';

	var CELL = 96;
	var GAP = 12;
	var STEP = CELL + GAP;

	// Lienzo por defecto (feature 042). NO son "la" rejilla: son el valor con el que nace una zona
	// y el que se usa si el payload todavía no trae geometría. Toda función de aquí recibe la
	// geometría de la zona que está dibujando; ninguna la supone.
	var COLS_DEFECTO = 8;
	var FILAS_DEFECTO = 6;
	var MIN = 4;
	var MAX = 24;

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}

	function formatoImporte(valor) {
		return parseFloat(valor || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	/**
	 * Ancho/alto en px de una mesa de `ancho`×`alto` celdas. Aritmética del encaje: con
	 * `grid: [STEP, STEP]` el widget solo emite múltiplos del paso, y `n * STEP - GAP` es
	 * exactamente el tamaño que ocupa la mesa dejando el hueco de separación entre celdas.
	 *
	 * La forma NO decide el tamaño (feature 040): decide solo el aspecto (border-radius) y el
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

	/** Caja en px (lo que emite el widget de redimensionado) → rectángulo en celdas. */
	function aCeldas(pos, size) {
		return {
			fila: Math.round(pos.top / STEP),
			columna: Math.round(pos.left / STEP),
			ancho: Math.max(1, Math.round((size.width + GAP) / STEP)),
			alto: Math.max(1, Math.round((size.height + GAP) / STEP)),
		};
	}

	/**
	 * Puntos de silla alrededor del perímetro, en porcentaje del tamaño del elemento: dos plazas por
	 * celda de ancho (una arriba y otra abajo) y dos por celda de alto (una a cada lado), de modo
	 * que el número de sillas comunica el tamaño real de la mesa.
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

	/**
	 * Estado visual de una mesa: `libre` / `ocupada` / `olvidada`. **Una sola** implementación de la
	 * regla, consumida por la tarjeta, el plano de servicio y el editor (feature 041). Estaba
	 * escrita dos veces y duplicarla es exactamente lo que permitiría que dos vistas de la misma
	 * mesa se contradigan.
	 *
	 * `olvidada` viene decidido por el servidor con el umbral del tenant: aquí no se hace ninguna
	 * aritmética de fechas (dependería del reloj de cada tablet).
	 */
	function claseEstado(mesa) {
		if (!mesa || mesa.estado === 'libre') { return 'libre'; }
		return mesa.olvidada ? 'olvidada' : 'ocupada';
	}

	/** Tamaño en celdas, aceptando tanto el objeto del editor (`ancho`) como el del payload (`ancho_celdas`). */
	function celdas(mesa) {
		return {
			ancho: mesa.ancho != null ? mesa.ancho : (mesa.ancho_celdas || 1),
			alto: mesa.alto != null ? mesa.alto : (mesa.alto_celdas || 1),
		};
	}

	/**
	 * HTML de una mesa dentro del lienzo.
	 *
	 * `opciones.modo`:
	 *  - `'edicion'`  → añade el asa circular de arrastre.
	 *  - `'servicio'` → añade el bloque de texto (importe y tiempo si está ocupada) y **nunca** asas.
	 *
	 * El contorno es el mismo en ambos: misma caja, misma forma, mismas sillas, misma clase de
	 * estado.
	 */
	function mesaHtml(mesa, opciones) {
		var modo = (opciones && opciones.modo) || 'edicion';
		var size = celdas(mesa);
		var caja = aPx({ fila: mesa.fila, columna: mesa.columna, ancho: size.ancho, alto: size.alto });
		var clase = claseEstado(mesa);

		var sillasHtml = sillasParaMesa(mesa.forma, size.ancho, size.alto).map(function (p) {
			return '<span class="silla" style="left:' + p[0] + '%;top:' + p[1] + '%;transform:translate(-50%,-50%);"></span>';
		}).join('');

		// `estirada` sustituye a la antigua forma `rectangular`: el borde se redondea menos en cuanto
		// la mesa deja de ser un cuadrado, sin que el usuario tenga que elegir nada.
		var estirada = size.ancho !== size.alto ? ' estirada' : '';

		var interior = '<span class="plano-mesa-nombre">' + escapeHtml(mesa.nombre) + '</span>';

		if (modo === 'servicio') {
			// Formato compacto (D4): en una mesa de una sola celda "Hace 12 min" no cabe y "12′" sí,
			// sin perder información. El importe se pinta tal cual lo entrega el servidor.
			if (mesa.estado !== 'libre') {
				interior += '<span class="plano-mesa-importe">' + formatoImporte(mesa.pendiente) + ' €</span>';
				if (mesa.abierta_hace_min != null) {
					interior += '<span class="plano-mesa-tiempo">' + mesa.abierta_hace_min + '′</span>';
				}
			}
		} else {
			interior += '<span class="plano-mesa-handle" title="Arrastrar"><i class="fas fa-arrows-up-down-left-right"></i></span>';
		}

		return '<div class="plano-mesa ' + clase + ' forma-' + mesa.forma + estirada +
			(modo === 'servicio' ? ' plano-mesa-servicio' : '') + '" data-mesa-id="' + mesa.id + '"' +
			' style="left:' + caja.left + 'px;top:' + caja.top + 'px;width:' + caja.width + 'px;height:' + caja.height + 'px;">' +
			sillasHtml +
			interior +
			'</div>';
	}

	/**
	 * Geometría normalizada de una zona del payload (feature 042). Una zona que todavía no trae
	 * lienzo cae al de por defecto, que es exactamente la rejilla fija anterior: así ninguna vista
	 * tiene que defenderse de un campo ausente.
	 */
	function geometriaDeZona(zona) {
		zona = zona || {};

		var columnas = parseInt(zona.columnas, 10);
		var filas = parseInt(zona.filas, 10);

		return {
			columnas: acotar(isNaN(columnas) ? COLS_DEFECTO : columnas),
			filas: acotar(isNaN(filas) ? FILAS_DEFECTO : filas),
			inactivas: (zona.celdas_inactivas || []).slice(),
		};
	}

	function acotar(n) {
		return Math.max(MIN, Math.min(MAX, n));
	}

	/** Ancho/alto en px del lienzo completo de una geometría. */
	function tamanoLienzo(geometria) {
		return {
			width: geometria.columnas * STEP - GAP,
			height: geometria.filas * STEP - GAP,
		};
	}

	/**
	 * Escribe las medidas del lienzo **en el propio elemento del lienzo** (D4), no en la raíz del
	 * documento: dos lienzos de zonas distintas pueden coexistir en la misma página (el del editor
	 * y el de servicio) y cada uno tiene que quedarse con las suyas.
	 */
	function aplicarGeometria(el, geometria) {
		if (!el) { return; }
		el.style.setProperty('--plano-cols', geometria.columnas);
		el.style.setProperty('--plano-rows', geometria.filas);
	}

	/**
	 * HTML de la capa de celdas del lienzo (feature 042, D5). Función pura como el resto del
	 * módulo: produce una cadena y no toca el DOM.
	 *
	 * `opciones.modo`:
	 *  - `'edicion'`  → **todas** las celdas, porque en modo recorte cada una es un blanco de toque.
	 *  - `'servicio'` → **solo las inactivas**, que es lo único que el camarero necesita ver: el
	 *    hueco que no es sala. Pintar 576 divs de suelo que nadie va a tocar sería puro coste.
	 */
	function celdasHtml(geometria, opciones) {
		var soloInactivas = ((opciones && opciones.modo) || 'edicion') === 'servicio';
		var inactivas = {};
		var html = [];
		var i;

		for (i = 0; i < geometria.inactivas.length; i++) {
			inactivas[geometria.inactivas[i]] = true;
		}

		for (var f = 0; f < geometria.filas; f++) {
			for (var c = 0; c < geometria.columnas; c++) {
				var clave = f + '-' + c;
				var inactiva = inactivas[clave] === true;

				if (soloInactivas && !inactiva) { continue; }

				html.push('<div class="plano-celda' + (inactiva ? ' plano-celda-inactiva' : '') +
					'" data-celda="' + clave + '" data-fila="' + f + '" data-columna="' + c + '"' +
					' style="left:' + (c * STEP) + 'px;top:' + (f * STEP) + 'px;' +
					'width:' + CELL + 'px;height:' + CELL + 'px;"></div>');
			}
		}

		return html.join('');
	}

	window.PosPlanoDibujo = {
		CELL: CELL,
		GAP: GAP,
		STEP: STEP,
		COLS_DEFECTO: COLS_DEFECTO,
		FILAS_DEFECTO: FILAS_DEFECTO,
		MIN: MIN,
		MAX: MAX,
		geometriaDeZona: geometriaDeZona,
		tamanoLienzo: tamanoLienzo,
		aplicarGeometria: aplicarGeometria,
		celdasHtml: celdasHtml,
		escapeHtml: escapeHtml,
		formatoImporte: formatoImporte,
		dimensiones: dimensiones,
		aPx: aPx,
		aCeldas: aCeldas,
		sillasParaMesa: sillasParaMesa,
		claseEstado: claseEstado,
		mesaHtml: mesaHtml,
	};

	// El TAMAÑO de la celda es constante del sistema de diseño y sí vive en `documentElement`.
	// Las MEDIDAS del lienzo ya no (D4 de la feature 042): son de cada zona, y escribirlas en la
	// raíz haría que la última zona dibujada le impusiera su tamaño a las demás —incluso al plano
	// de servicio, que puede estar mostrando otra—. Las escribe `aplicarGeometria()` en el propio
	// elemento del lienzo.
	document.documentElement.style.setProperty('--plano-cell', CELL + 'px');
	document.documentElement.style.setProperty('--plano-gap', GAP + 'px');
})();
