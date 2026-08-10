/**
 * Conteo animado de las cards informativas de métricas.
 *
 * Se engancha a cualquier elemento [data-metric] sin que las vistas ni los
 * *.init.js tengan que hacer nada: un MutationObserver detecta cuando el
 * valor cambia (los datatables lo escriben con $(...).text(n) tras el ajax de
 * totales) y anima del valor anterior al nuevo. Los valores renderizados en
 * servidor animan desde 0 al cargar la página.
 *
 * Solo se animan enteros (con o sin separador de miles). Importes, porcentajes
 * y cualquier otro formato se escriben directo — animarlos daría cifras
 * intermedias sin sentido.
 */
(function () {
	'use strict';

	var DURACION = 600;
	var animando = new WeakSet();

	function reducirMovimiento() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	/** Devuelve {valor, agrupado} si el texto es un entero animable, o null. */
	function parsearEntero(texto) {
		var limpio = String(texto).trim();

		if (/^\d+$/.test(limpio)) {
			return { valor: parseInt(limpio, 10), agrupado: false };
		}

		if (/^\d{1,3}(\.\d{3})+$/.test(limpio)) {
			return { valor: parseInt(limpio.replace(/\./g, ''), 10), agrupado: true };
		}

		return null;
	}

	function formatear(valor, agrupado) {
		return agrupado ? valor.toLocaleString('es-ES') : String(valor);
	}

	function escribir(el, texto) {
		animando.add(el);
		el.textContent = texto;
		// El observer entrega los callbacks en microtask: liberamos después.
		Promise.resolve().then(function () {
			animando.delete(el);
		});
	}

	function animar(el, desde, hasta, agrupado) {
		if (desde === hasta || reducirMovimiento()) {
			escribir(el, formatear(hasta, agrupado));
			el._metricValor = hasta;
			return;
		}

		if (el._metricRaf) {
			cancelAnimationFrame(el._metricRaf);
		}

		var inicio = null;

		function paso(ahora) {
			if (inicio === null) {
				inicio = ahora;
			}

			var t = Math.min((ahora - inicio) / DURACION, 1);
			// easeOutExpo: arranca rápido, frena al final.
			var eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
			var actual = Math.round(desde + (hasta - desde) * eased);

			escribir(el, formatear(actual, agrupado));

			if (t < 1) {
				el._metricRaf = requestAnimationFrame(paso);
			} else {
				el._metricRaf = null;
				el._metricValor = hasta;
			}
		}

		el._metricRaf = requestAnimationFrame(paso);
	}

	function alCambiar(el) {
		if (animando.has(el)) {
			return;
		}

		var parseado = parsearEntero(el.textContent);

		if (!parseado) {
			el._metricValor = null;
			return;
		}

		var desde = typeof el._metricValor === 'number' ? el._metricValor : 0;
		animar(el, desde, parseado.valor, parseado.agrupado);
	}

	function observar(el) {
		if (el._metricObservado) {
			return;
		}

		el._metricObservado = true;

		new MutationObserver(function () {
			alCambiar(el);
		}).observe(el, { childList: true, characterData: true, subtree: true });

		var inicial = parsearEntero(el.textContent);

		if (inicial) {
			animar(el, 0, inicial.valor, inicial.agrupado);
		}
	}

	function iniciar() {
		document.querySelectorAll('[data-metric]').forEach(observar);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', iniciar);
	} else {
		iniciar();
	}
})();
