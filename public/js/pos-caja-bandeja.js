/**
 * Bandeja de conteo de efectivo (feature 048). Controla `resources/views/pos/_caja-bandeja.blade.php`.
 *
 *   var bandeja = PosCajaBandeja.crear(document.getElementById('x'), { onCambio: fn });
 *   bandeja.payload()  → { conteo: { "5000": 3, … } }  o  { importe: "112,10" } según el modo
 *   bandeja.total()    → número (solo para pintar: el servidor recalcula siempre, Principio III)
 *
 * El conteo nunca se envía como total de confianza: viaja el mapa céntimos → cantidad y el
 * servidor suma. La regla de tecleo es la compartida de `pos-teclado.js`.
 */
window.PosCajaBandeja = (function () {
	'use strict';

	function crear(root, opciones) {
		opciones = opciones || {};
		var T = window.PosTeclado;

		var fichas = Array.prototype.slice.call(root.querySelectorAll('.caja-ficha'));
		var $modoBtns = root.querySelectorAll('[data-bandeja-modo]');
		var $paneles = root.querySelectorAll('[data-bandeja-panel]');
		var $soloConteo = root.querySelectorAll('[data-bandeja-solo="conteo"]');
		var $sel = root.querySelector('[data-bandeja-sel]');
		var $display = root.querySelector('[data-bandeja-display]');
		var $total = root.querySelector('[data-bandeja-total]');
		var $importe = root.querySelector('[data-bandeja-importe]');
		var $coma = root.querySelector('[data-bandeja-coma]');

		var modo = root.getAttribute('data-modo-inicial') || 'conteo';
		var cantidades = {};      // céntimos → string tecleado
		var seleccion = null;     // ficha activa
		var prellenado = false;   // la próxima tecla reemplaza (tras seleccionar una ficha con valor)
		var importeStr = '';
		var importePrellenado = false;
		var ultimoToque = { ficha: null, t: 0 };

		function cantidadDe(c) { return parseInt(cantidades[c] || '0', 10) || 0; }

		function totalCentimos() {
			if (modo === 'importe') { return Math.round(T.aNumero(importeStr) * 100); }
			var total = 0;
			Object.keys(cantidades).forEach(function (c) { total += parseInt(c, 10) * cantidadDe(c); });
			return total;
		}

		function pintarFicha(ficha) {
			var c = ficha.getAttribute('data-centimos');
			var n = cantidadDe(c);
			ficha.classList.toggle('tiene', n > 0);
			ficha.querySelector('[data-cant]').textContent = '×' + n;
			ficha.querySelector('[data-sub]').textContent = T.formatear(n * parseInt(c, 10) / 100);
			var params = { valor: ficha.getAttribute('data-etiqueta'), n: n };
			ficha.setAttribute('aria-label', ficha.classList.contains('caja-billete')
				? (n === 1 ? __t('Billete de :valor, :n unidad', params) : __t('Billete de :valor, :n unidades', params))
				: (n === 1 ? __t('Moneda de :valor, :n unidad', params) : __t('Moneda de :valor, :n unidades', params)));
		}

		function pintarTeclado() {
			if (modo === 'importe') {
				if ($importe) { $importe.textContent = T.formatear(T.aNumero(importeStr)) + ' €'; }
			} else {
				var c = seleccion ? seleccion.getAttribute('data-centimos') : null;
				var valor = c ? (cantidades[c] || '') : '';
				if ($sel) { $sel.textContent = seleccion ? seleccion.getAttribute('data-etiqueta') : __t('Toca un billete o moneda'); }
				if ($display) {
					$display.textContent = valor === '' ? '0' : valor;
					$display.classList.toggle('vacio', valor === '');
				}
			}
			if ($total) { $total.textContent = T.formatear(totalCentimos() / 100) + ' €'; }
			if (typeof opciones.onCambio === 'function') { opciones.onCambio(totalCentimos() / 100); }
		}

		function seleccionar(ficha) {
			if (seleccion) { seleccion.classList.remove('sel'); seleccion.removeAttribute('aria-pressed'); }
			seleccion = ficha;
			if (ficha) {
				ficha.classList.add('sel');
				ficha.setAttribute('aria-pressed', 'true');
				prellenado = cantidadDe(ficha.getAttribute('data-centimos')) > 0;
			}
			pintarTeclado();
		}

		function aplicarModo(nuevo) {
			modo = nuevo === 'importe' ? 'importe' : 'conteo';
			Array.prototype.forEach.call($modoBtns, function (b) {
				var activo = b.getAttribute('data-bandeja-modo') === modo;
				b.classList.toggle('active', activo);
				b.setAttribute('aria-pressed', activo ? 'true' : 'false');
			});
			Array.prototype.forEach.call($paneles, function (p) { p.hidden = p.getAttribute('data-bandeja-panel') !== modo; });
			Array.prototype.forEach.call($soloConteo, function (el) { el.hidden = modo !== 'conteo'; });
			// En conteo la coma no tiene sentido: la tecla pasa a ser "C" (poner a cero).
			if ($coma) {
				$coma.textContent = modo === 'conteo' ? 'C' : ',';
				$coma.setAttribute('data-key', modo === 'conteo' ? 'c' : ',');
				$coma.setAttribute('aria-label', modo === 'conteo' ? __t('Poner a cero') : __t('Coma decimal'));
			}
			if (modo === 'conteo' && !seleccion && fichas.length) { seleccionar(fichas[0]); }
			pintarTeclado();
		}

		function pulsar(key) {
			if (modo === 'importe') {
				var sig = T.aplicarTecla(importeStr, key, importePrellenado);
				importePrellenado = false;
				if (sig === null) { return; }
				importeStr = sig;
			} else {
				if (!seleccion) { return; }
				var c = seleccion.getAttribute('data-centimos');
				var nuevo = T.aplicarTeclaEntera(cantidades[c] || '', key, prellenado);
				prellenado = false;
				if (nuevo === null) { return; }
				cantidades[c] = nuevo;
				pintarFicha(seleccion);
			}
			pintarTeclado();
		}

		// ── Eventos ──
		fichas.forEach(function (ficha) {
			ficha.addEventListener('click', function () {
				var ahora = Date.now();
				// Doble toque = una unidad más: contar billete a billete sin mirar el teclado.
				if (ultimoToque.ficha === ficha && ahora - ultimoToque.t < 380 && seleccion === ficha) {
					var c = ficha.getAttribute('data-centimos');
					var n = Math.min(cantidadDe(c) + 1, 9999);
					cantidades[c] = String(n);
					prellenado = true;
					pintarFicha(ficha);
					pintarTeclado();
					ultimoToque = { ficha: null, t: 0 };
					return;
				}
				ultimoToque = { ficha: ficha, t: ahora };
				seleccionar(ficha);
			});
		});

		Array.prototype.forEach.call($modoBtns, function (b) {
			b.addEventListener('click', function () { aplicarModo(b.getAttribute('data-bandeja-modo')); });
		});

		root.querySelectorAll('.caja-key').forEach(function (k) {
			k.addEventListener('click', function () { pulsar(k.getAttribute('data-key')); });
		});

		// Teclado físico (caja con teclado USB): dígitos, coma/punto, retroceso.
		root.addEventListener('keydown', function (e) {
			if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) { return; }
			var k = e.key;
			if (/^[0-9]$/.test(k)) { pulsar(k); e.preventDefault(); }
			else if (k === 'Backspace') { pulsar('del'); e.preventDefault(); }
			else if ((k === ',' || k === '.') && modo === 'importe') { pulsar(','); e.preventDefault(); }
		});

		// ── API ──
		var api = {
			modo: function () { return modo; },
			total: function () { return totalCentimos() / 100; },
			conteo: function () {
				var limpio = {};
				Object.keys(cantidades).forEach(function (c) { if (cantidadDe(c) > 0) { limpio[c] = cantidadDe(c); } });
				return limpio;
			},
			importeTexto: function () { return T.aNumero(importeStr).toFixed(2); },
			/** Lo que viaja al servidor. El total, si lo hay, lo recalcula él. */
			payload: function () {
				return modo === 'importe' ? { importe: api.importeTexto() } : { conteo: api.conteo() };
			},
			/** Restaura un estado guardado (recarga accidental de la tablet). */
			cargar: function (estado) {
				if (!estado) { return; }
				cantidades = {};
				Object.keys(estado.conteo || {}).forEach(function (c) { cantidades[c] = String(estado.conteo[c]); });
				importeStr = estado.importe ? String(estado.importe).replace('.', ',') : '';
				importePrellenado = importeStr !== '';
				fichas.forEach(pintarFicha);
				aplicarModo(estado.modo || modo);
			},
			estado: function () { return { modo: modo, conteo: api.conteo(), importe: importeStr ? api.importeTexto() : '' }; },
			limpiar: function () {
				cantidades = {};
				importeStr = '';
				fichas.forEach(pintarFicha);
				seleccion = null;
				fichas.forEach(function (f) { f.classList.remove('sel'); });
				aplicarModo(root.getAttribute('data-modo-inicial') || 'conteo');
			},
		};

		fichas.forEach(pintarFicha);
		aplicarModo(modo);

		return api;
	}

	return { crear: crear };
})();
