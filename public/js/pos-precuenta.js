/**
 * POS (TPV) — precuenta de la cuenta abierta (feature 049).
 *
 * La precuenta es un documento NO fiscal: no emite ticket, no consume numeración, no cobra. Este
 * módulo solo orquesta: guarda la cuenta (FR-003, mismo bloqueo de versión que el guardado), pide
 * al servidor que emita la precuenta —que calcula todo; el navegador no manda importes— y muestra
 * el PDF en su modal. Al cerrar el modal el TPV queda en cero, como tras Guardar (FR-011).
 *
 * Con el módulo de hostelería apagado este archivo ni se carga (FR-028).
 */
window.PosApp.registrar('precuenta', function (PosApp) {
	'use strict';

	var $btn = document.getElementById('pos-precuenta-btn');
	var $modalEl = document.getElementById('posPrecuentaModal');
	var $frame = document.getElementById('pos-precuenta-frame');
	var $imprimir = document.getElementById('pos-precuenta-imprimir');

	if (!$btn || !$modalEl) {
		return {};
	}

	var modal = bootstrap.Modal.getOrCreateInstance($modalEl);

	function csrf() {
		return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
	}

	function emitir(cuenta) {
		return fetch('/pos/cuentas/' + cuenta.id + '/precuentas', {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': csrf(),
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: JSON.stringify({ version: cuenta.version }),
		}).then(function (r) {
			return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
		});
	}

	function generar() {
		var cuentaModulo = PosApp.modulos.cuenta;
		if (!cuentaModulo || !cuentaModulo.guardar) { return Promise.resolve(); }

		var guardado = cuentaModulo.guardar({ silencioso: true });

		// `guardar()` devuelve null (y ya avisó) si el ticket está vacío.
		if (!guardado) { return Promise.resolve(); }

		return guardado.then(function (resGuardar) {
			var cuenta = PosApp.state.cuenta;

			// Guardado fallido (409/422/red): ya avisó; nunca generar sobre un estado no guardado.
			if (!resGuardar || !resGuardar.ok || !cuenta) { return; }

			return emitir(cuenta).then(function (res) {
				if (res.status === 409) {
					window.showToast('error', res.data.message || 'Otro dispositivo modificó esta cuenta.');
					if (res.data.cuenta) { cuentaModulo.aplicarCuenta(res.data.cuenta); }
					return;
				}
				if (!res.ok) {
					window.showToast('error', res.data.message || 'No se pudo generar la precuenta.');
					return;
				}

				cuentaModulo.aplicarCuenta(res.data.cuenta);
				window.showToast('success', res.data.precuenta.reimpresion ? 'Precuenta reimpresa.' : 'Precuenta generada.');

				$frame.setAttribute('src', res.data.precuenta.pdf_url);
				modal.show();
			});
		}).catch(function () {
			window.showToast('error', 'No se pudo generar la precuenta.');
		});
	}

	function init() {
		$btn.addEventListener('click', function () {
			window.withButtonLoading($btn, generar);
		});

		if ($imprimir) {
			$imprimir.addEventListener('click', function () {
				if ($frame && $frame.contentWindow) {
					$frame.contentWindow.focus();
					$frame.contentWindow.print();
				}
			});
		}

		// Cualquier cierre (Listo, X, Esc): liberar el PDF y dejar el TPV en cero. La cuenta sigue
		// abierta en su mesa con todo lo guardado.
		$modalEl.addEventListener('hidden.bs.modal', function () {
			$frame.setAttribute('src', '');
			var cuentaModulo = PosApp.modulos.cuenta;
			if (cuentaModulo && cuentaModulo.vaciarPantalla) { cuentaModulo.vaciarPantalla(); }
		});
	}

	return { init: init };
});
