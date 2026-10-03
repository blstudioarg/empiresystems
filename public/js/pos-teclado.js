/**
 * Regla de tecleo compartida por todos los teclados táctiles propios del POS (feature 048).
 *
 * Extraída de `pos-cobro.js` (importe y "Entregado" del modal de cobro) para que la caja
 * (fondo, conteo por billetes, total contado, movimientos) teclee exactamente igual: dos teclados
 * que se comportan distinto al teclear son un error de bulto en una pantalla de servicio
 * (docs/04 "Entrada numérica en pantallas táctiles").
 *
 * Sin dependencias: se carga antes que cualquier script que lo use.
 */
window.PosTeclado = (function () {
	'use strict';

	/**
	 * Aplica una tecla a una cadena de importe con coma decimal ("12,5"). Devuelve `null` si la
	 * pulsación no cambia nada (tercer decimal, coma repetida), para que quien llama no repinte.
	 * La primera pulsación tras prellenar reemplaza el valor: el gesto normal es teclear el
	 * importe entero, no corregir dígito a dígito.
	 */
	function aplicarTecla(str, key, prellenado) {
		if (key === 'del') {
			return prellenado ? '' : str.slice(0, -1);
		}
		if (prellenado) { str = ''; }
		if (key === ',') {
			return str.indexOf(',') === -1 ? (str || '0') + ',' : null;
		}
		var partes = str.split(',');
		if (partes[1] && partes[1].length >= 2) { return null; }
		return str + key;
	}

	/**
	 * Variante para cantidades (unidades de un billete o moneda): solo enteros, la tecla `C` pone a
	 * cero y no hay coma. Mismo comportamiento de prellenado que la decimal.
	 */
	function aplicarTeclaEntera(str, key, prellenado) {
		if (key === 'c') { return ''; }
		if (key === 'del') {
			return prellenado ? '' : str.slice(0, -1);
		}
		if (key === ',') { return null; }
		if (prellenado) { str = ''; }
		if (str === '0') { str = ''; }
		if (str.length >= 4) { return null; } // hasta 9.999 unidades por denominación
		return str + key;
	}

	/** "12,5" → 12.5, redondeado al céntimo. */
	function aNumero(str) {
		var val = parseFloat((str || '0').replace(',', '.'));
		return isNaN(val) ? 0 : Math.round(val * 100) / 100;
	}

	/**
	 * 1307.5 → "1.307,50" (sin símbolo). `useGrouping: 'always'` porque la convención es-ES no
	 * separa los miles en números de 4 cifras ("1307,50"), y en caja ese es justo el rango típico:
	 * el PDF los separa y la pantalla tiene que decir lo mismo.
	 */
	function formatear(n) {
		var opciones = { minimumFractionDigits: 2, maximumFractionDigits: 2, useGrouping: 'always' };
		try {
			return (Number(n) || 0).toLocaleString('es-ES', opciones);
		} catch (e) {
			opciones.useGrouping = true;
			return (Number(n) || 0).toLocaleString('es-ES', opciones);
		}
	}

	/** Número → cadena editable para prellenar un teclado ("12,5"; "" si es 0). */
	function aCadena(n) {
		n = Number(n) || 0;
		if (n === 0) { return ''; }
		return String(Math.round(n * 100) / 100).replace('.', ',');
	}

	return {
		aplicarTecla: aplicarTecla,
		aplicarTeclaEntera: aplicarTeclaEntera,
		aNumero: aNumero,
		formatear: formatear,
		aCadena: aCadena,
	};
})();
