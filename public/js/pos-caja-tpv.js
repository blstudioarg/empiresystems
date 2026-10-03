/**
 * POS (TPV) — estado de la caja (feature 048, FR-020). Módulo del orquestador `pos-form.js`.
 *
 * Cobrar exige caja abierta. Con la caja cerrada, el botón Cobrar no abre el modal de cobro: abre
 * el panel "Abrir caja" (el mismo de la pantalla de caja) y, en cuanto se abre, sigue al cobro.
 * Armar el ticket, guardar cuentas y todo lo demás sigue igual sin caja.
 *
 * El servidor es quien manda: si otra tablet cierra la caja mientras tanto, el 409 `caja_cerrada`
 * de la emisión lo recoge `pos-cobro.js` y vuelve a pasar por aquí.
 */
window.PosApp.registrar('caja', function (PosApp) {
	'use strict';

	var estado = PosApp.state.caja || { abierta: true, puedeAbrir: false };
	var $chip = document.getElementById('pos-caja-chip');
	var $cobrar = document.getElementById('pos-cobrar');
	var modalEl = document.getElementById('posCajaAperturaModal');
	var modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
	var alAbrir = null;

	function pintarChip() {
		if (!$chip) { return; }
		$chip.classList.toggle('abierta', estado.abierta);
		$chip.classList.toggle('cerrada', !estado.abierta);
		$chip.querySelector('[data-caja-chip-texto]').textContent = estado.abierta ? 'Caja abierta' : 'Caja cerrada';
		$chip.setAttribute('aria-label', estado.abierta ? 'Caja abierta' : 'Caja cerrada. Toca para abrirla');
		if (estado.abierta) {
			$chip.setAttribute('tabindex', '-1');
			$chip.setAttribute('aria-disabled', 'true');
		} else {
			$chip.removeAttribute('tabindex');
			$chip.removeAttribute('aria-disabled');
		}
	}

	function pedirApertura(onAbierta) {
		alAbrir = typeof onAbierta === 'function' ? onAbierta : null;
		if (modal) { modal.show(); }
	}

	function marcarAbierta() {
		estado.abierta = true;
		pintarChip();
		if (modal) { modal.hide(); }
		var cb = alAbrir;
		alAbrir = null;
		// Esperar a que el modal termine de cerrarse antes de abrir el de cobro: dos modales de
		// Bootstrap a la vez pelean por el backdrop.
		if (cb && modalEl) {
			modalEl.addEventListener('hidden.bs.modal', function una() {
				modalEl.removeEventListener('hidden.bs.modal', una);
				cb();
			});
		}
	}

	function marcarCerrada() {
		estado.abierta = false;
		pintarChip();
	}

	function init() {
		pintarChip();

		var panel = document.getElementById('pos-caja-apertura');
		if (panel && estado.puedeAbrir && window.PosCajaApertura) {
			window.PosCajaApertura.crear(panel, {
				url: estado.abrirUrl,
				csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				onAbierta: marcarAbierta,
				onYaAbierta: marcarAbierta,
				onCancelar: function () { alAbrir = null; if (modal) { modal.hide(); } },
			});
		}

		if (modalEl) {
			modalEl.addEventListener('hidden.bs.modal', function () {
				// Cerrado sin abrir la caja (X, Esc, Cancelar): se olvida el "seguir al cobro".
				if (!estado.abierta) { alAbrir = null; }
			});
		}

		if ($chip) {
			$chip.addEventListener('click', function () { if (!estado.abierta) { pedirApertura(null); } });
		}

		// Interceptar Cobrar con la caja cerrada. Fase de captura: corta el data-api de Bootstrap
		// (delegado en document) antes de que abra el modal de cobro.
		if ($cobrar) {
			$cobrar.addEventListener('click', function (e) {
				if (estado.abierta) { return; }
				e.preventDefault();
				e.stopImmediatePropagation();
				pedirApertura(function () {
					var cobroEl = document.getElementById('posCobroModal');
					if (cobroEl) { bootstrap.Modal.getOrCreateInstance(cobroEl).show(); }
				});
			}, true);
		}
	}

	return {
		init: init,
		abierta: function () { return estado.abierta; },
		pedirApertura: pedirApertura,
		marcarCerrada: marcarCerrada,
	};
});
