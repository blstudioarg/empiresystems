/**
 * POS (TPV) — orquestador.
 *
 * Este archivo NO implementa pantalla: expone el estado compartido del ticket y cablea los
 * módulos que sí lo hacen (`pos-catalogo.js`, `pos-ticket.js`, `pos-cobro.js`). La partición se
 * hizo antes de añadir funcionalidad de hostelería (research D8 de la feature 038): sobre un
 * único archivo el POS quedaba en ~1.800 líneas.
 *
 * Contrato entre módulos:
 *   - `PosApp.lineas` es el array compartido del ticket. Se muta SIEMPRE en el sitio
 *     (`push`/`splice`/`length = 0`); reasignarlo rompería la referencia de los demás módulos.
 *   - `PosApp.registrar(nombre, factory)` registra un módulo. Al arrancar se crean todos primero
 *     y solo después se llama a su `init()`, para que un módulo pueda usar la API de otro sin
 *     depender del orden de los `<script>`.
 */
(function () {
	'use strict';

	var PosApp = window.PosApp = {
		state: window.posState || {},
		/** @type {Array<{articulo_id, concepto, unidad, precio, tipo, cantidad}>} */
		lineas: [],
		modulos: {},
	};

	PosApp.format = function (n) {
		return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	};

	PosApp.escapeHtml = function (s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	};

	PosApp.centimos = function (n) { return Math.round(n * 100); };

	PosApp.baseLinea = function (l) {
		return Math.round(l.precio * l.cantidad * 100) / 100;
	};

	PosApp.brutoLinea = function (l) {
		var base = PosApp.baseLinea(l);
		return base + Math.round(base * l.tipo / 100 * 100) / 100;
	};

	PosApp.totalBruto = function () {
		return PosApp.lineas.reduce(function (acc, l) { return acc + PosApp.brutoLinea(l); }, 0);
	};

	PosApp.subtotalBase = function () {
		return PosApp.lineas.reduce(function (acc, l) { return acc + PosApp.baseLinea(l); }, 0);
	};

	var pendientes = [];

	PosApp.registrar = function (nombre, factory) {
		pendientes.push({ nombre: nombre, factory: factory });
	};

	function boot() {
		// Dos pasadas: primero se construyen todos los módulos (queda su API disponible en
		// `PosApp.modulos`), después se inicializan. Sin esto, `pos-catalogo` no podría llamar a
		// `pos-ticket` salvo que se garantizara el orden de carga en cada vista.
		pendientes.forEach(function (m) {
			PosApp.modulos[m.nombre] = m.factory(PosApp) || {};
		});

		pendientes.forEach(function (m) {
			var modulo = PosApp.modulos[m.nombre];
			if (modulo && typeof modulo.init === 'function') { modulo.init(); }
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
