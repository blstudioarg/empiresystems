/**
 * Panel "Abrir caja" (feature 048). Controla `resources/views/pos/_caja-apertura.blade.php`.
 *
 * Lo comparten la pantalla de caja y el modal del TPV, así que abrir la caja se ve y se comporta
 * igual se haga donde se haga:
 *
 *   PosCajaApertura.crear(el, { url, csrf, onAbierta(respuesta), onYaAbierta(respuesta), onCancelar() })
 *
 * El botón de confirmar repite el importe ("Abrir caja con 150,00 €"): la acción dice exactamente
 * lo que va a pasar. El fondo lo decide el servidor (si se contó, lo suma él desde el conteo).
 */
window.PosCajaApertura = (function () {
	'use strict';

	function crear(root, opciones) {
		var T = window.PosTeclado;
		var $confirmar = root.querySelector('[data-apertura-confirmar]');
		var $texto = root.querySelector('[data-apertura-texto]');
		var $cancelar = root.querySelector('[data-apertura-cancelar]');

		var bandeja = window.PosCajaBandeja.crear(root.querySelector('[data-bandeja]'), {
			onCambio: function (total) {
				if ($texto) { $texto.textContent = __t('Abrir caja con :importe', { importe: T.formatear(total) + ' €' }); }
			},
		});

		function enviar() {
			var datos = bandeja.payload();
			var cuerpo = datos.conteo !== undefined ? { conteo: datos.conteo } : { fondo_inicial: datos.importe };

			return window.withButtonLoading($confirmar, function () {
				return $.ajax({
					url: opciones.url,
					method: 'POST',
					dataType: 'json',
					contentType: 'application/json',
					data: JSON.stringify(cuerpo),
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': opciones.csrf },
				});
			})
				.done(function (res) {
					window.showToast('success', res.message || __t('Caja abierta.'));
					bandeja.limpiar();
					if (typeof opciones.onAbierta === 'function') { opciones.onAbierta(res); }
				})
				.fail(function (xhr) {
					var res = xhr.responseJSON || {};
					if (xhr.status === 409 && res.codigo === 'caja_ya_abierta') {
						// Otra tablet se adelantó: no es un error para quien quería vender, la caja ya
						// está abierta. Se informa y se sigue.
						window.showToast('info', res.sesion && res.sesion.abierta_por
							? __t('La caja ya estaba abierta (la abrió :usuario).', { usuario: res.sesion.abierta_por })
							: __t('La caja ya estaba abierta.'));
						if (typeof opciones.onYaAbierta === 'function') { opciones.onYaAbierta(res); }
						return;
					}
					var msg = res.message;
					if (xhr.status === 422 && res.errors) {
						var primero = Object.keys(res.errors)[0];
						msg = res.errors[primero][0];
					}
					window.showToast('error', msg || __t('No se pudo abrir la caja.'));
				});
		}

		if ($confirmar) { $confirmar.addEventListener('click', enviar); }
		if ($cancelar) {
			$cancelar.addEventListener('click', function () {
				bandeja.limpiar();
				if (typeof opciones.onCancelar === 'function') { opciones.onCancelar(); }
			});
		}

		return { bandeja: bandeja, enviar: enviar };
	}

	return { crear: crear };
})();
