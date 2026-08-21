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
	var COLS = 8;
	var ROWS = 6;

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

	window.PosPlanoDibujo = {
		CELL: CELL,
		GAP: GAP,
		STEP: STEP,
		COLS: COLS,
		ROWS: ROWS,
		escapeHtml: escapeHtml,
		formatoImporte: formatoImporte,
		dimensiones: dimensiones,
		aPx: aPx,
		aCeldas: aCeldas,
		sillasParaMesa: sillasParaMesa,
		claseEstado: claseEstado,
		mesaHtml: mesaHtml,
	};

	// Las variables de la rejilla las consume el CSS del lienzo (ambos modos), así que se fijan
	// aquí y no en el init del editor: la vista de servicio también las necesita.
	document.documentElement.style.setProperty('--plano-cols', COLS);
	document.documentElement.style.setProperty('--plano-rows', ROWS);
	document.documentElement.style.setProperty('--plano-cell', CELL + 'px');
	document.documentElement.style.setProperty('--plano-gap', GAP + 'px');
})();
