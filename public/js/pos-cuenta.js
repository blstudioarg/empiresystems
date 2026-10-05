/**
 * POS (TPV) — contexto de mesa/cuenta abierta (feature 038, US2).
 *
 * Solo se activa si la vista trae `window.posState.cuenta` (módulo de hostelería encendido): con
 * el módulo apagado este archivo no hace nada, para no tocar el camino de venta directa
 * (SC-012). Guarda, recupera, anula, transfiere y une cuentas; el 409 de bloqueo optimista
 * (FR-024) siempre avisa y recarga — **nunca reintenta en silencio**, porque eso sería justo
 * pisar los cambios del otro camarero.
 */
window.PosApp.registrar('cuenta', function (PosApp) {
	'use strict';

	var estado = PosApp.state.cuenta; // null si no hay módulo/hostelería activa o es venta directa
	var mesaPreseleccionada = PosApp.state.mesaPreseleccionada || null;

	if (!estado && !mesaPreseleccionada) {
		return {}; // Venta directa pura: nada que inicializar.
	}

	var $chip = document.getElementById('pos-mesa-chip');
	var $chipLabel = document.getElementById('pos-mesa-chip-label');
	var $chipPendiente = document.getElementById('pos-mesa-chip-pendiente');
	var $guardarBtn = document.getElementById('pos-guardar-cuenta');
	var $anularBtn = document.getElementById('pos-anular-cuenta');
	var $aparcadasBtn = document.getElementById('pos-aparcadas-btn');
	var $moverBtn = document.getElementById('pos-mesa-chip-mover');
	var $precuentaBtn = document.getElementById('pos-precuenta-btn');
	var $precuentaEstado = document.getElementById('pos-precuenta-estado');
	var $suplementoZona = document.getElementById('pos-suplemento-zona');
	var $cobroMesaCtx = document.getElementById('pos-cobro-mesa-ctx');
	var $cobroModalEl = document.getElementById('posCobroModal');
	var $moverModalEl = document.getElementById('posMoverModal');
	var moverModal = $moverModalEl ? bootstrap.Modal.getOrCreateInstance($moverModalEl) : null;
	var $moverLista = document.getElementById('pos-mover-lista');

	var cuenta = estado; // { id, version, mesa_id, mesa_nombre, zona_nombre, lineas: [...] } | null
	var mesaId = estado ? estado.mesa_id : (mesaPreseleccionada ? mesaPreseleccionada.id : null);
	var mesaNombre = estado ? estado.mesa_nombre : (mesaPreseleccionada ? mesaPreseleccionada.nombre : null);

	function csrf() {
		return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
	}

	function peticion(url, method, body) {
		return fetch(url, {
			method: method,
			headers: {
				'X-CSRF-TOKEN': csrf(),
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: body ? JSON.stringify(body) : undefined,
		}).then(function (r) {
			return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
		});
	}

	function renderChip() {
		if (!$chip) { return; }

		if (!mesaId) {
			$chip.classList.add('d-none');
			return;
		}

		$chip.classList.remove('d-none');
		if ($chipLabel) { $chipLabel.textContent = mesaNombre || __t('Mesa'); }
		if ($chipPendiente) {
			$chipPendiente.textContent = cuenta ? (PosApp.format(parseFloat(cuenta.pendiente || 0)) + ' €') : '';
			$chipPendiente.classList.toggle('d-none', !cuenta);
		}
		if ($anularBtn) { $anularBtn.classList.toggle('d-none', !cuenta); }
		if ($moverBtn) { $moverBtn.classList.toggle('d-none', !cuenta); }
		renderPrecuenta();

		if ($suplementoZona) {
			var suplemento = cuenta ? parseFloat(cuenta.zona_suplemento || 0) : 0;
			if (suplemento > 0) {
				$suplementoZona.textContent = '· ' + __t('+:porcentaje% zona', { porcentaje: PosApp.format(suplemento) });
				$suplementoZona.classList.remove('d-none');
			} else {
				$suplementoZona.classList.add('d-none');
			}
		}
	}

	/**
	 * Precuenta (feature 049). El botón está siempre que haya mesa —también con una mesa recién
	 * tocada, cuya cuenta se crea al guardar—, deshabilitado con el ticket vacío. El caso "todo
	 * cobrado" lo rechaza el servidor (422). El estado lo decide SIEMPRE el servidor (huella), aquí
	 * solo se pinta.
	 */
	function renderPrecuenta() {
		if ($precuentaBtn) {
			$precuentaBtn.classList.toggle('d-none', !mesaId);
			if (!window.jQuery || !window.jQuery($precuentaBtn).data('loading-busy')) {
				$precuentaBtn.disabled = PosApp.lineas.length === 0;
			}
		}

		if (!$precuentaEstado) { return; }

		var estadoPrecuenta = cuenta && cuenta.precuenta ? cuenta.precuenta.estado : 'ninguna';

		if (!mesaId || estadoPrecuenta === 'ninguna') {
			$precuentaEstado.classList.add('d-none');
			return;
		}

		var desactualizada = estadoPrecuenta === 'desactualizada';
		var texto = desactualizada ? __t('Precuenta desactualizada') : __t('Precuenta dada');
		$precuentaEstado.innerHTML = desactualizada
			? '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i> ' + PosApp.escapeHtml(__t('Desactualizada'))
			: '<i class="fas fa-check" aria-hidden="true"></i>';
		$precuentaEstado.setAttribute('title', texto);
		$precuentaEstado.setAttribute('aria-label', texto);
		$precuentaEstado.classList.toggle('desactualizada', desactualizada);
		$precuentaEstado.classList.remove('d-none');
	}

	// ── Transferir / unir (US6) ──────────────────────────────────────────
	function abrirMover() {
		if (!cuenta || !$moverLista) { return; }

		$moverLista.innerHTML = '<p class="text-muted small mb-0">' + __t('Cargando mesas…') + '</p>';
		moverModal.show();

		fetch('/pos/sala', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				var otras = json.mesas.filter(function (m) { return String(m.id) !== String(mesaId); });

				if (!otras.length) {
					$moverLista.innerHTML = '<p class="text-muted small mb-0">' + __t('No hay otras mesas en la sala.') + '</p>';
					return;
				}

				$moverLista.innerHTML = '';
				otras.forEach(function (mesa) {
					var $item = document.createElement('button');
					$item.type = 'button';
					$item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
					$item.innerHTML = '<span>' + PosApp.escapeHtml(mesa.nombre) + '</span>' +
						(mesa.estado === 'ocupada'
							? '<span class="badge badge-warning light">' + __t('Ocupada · unir') + '</span>'
							: '<span class="badge badge-secondary light">' + __t('Libre · transferir') + '</span>');
					$item.addEventListener('click', function () {
						mesa.estado === 'ocupada' ? unir(mesa) : transferir(mesa);
					});
					$moverLista.appendChild($item);
				});
			})
			.catch(function () {
				$moverLista.innerHTML = '<p class="text-danger small mb-0">' + __t('No se pudo cargar la sala.') + '</p>';
			});
	}

	function transferir(mesa) {
		window.confirmDelete(
			__t('¿Transferir la cuenta a «:mesa»?', { mesa: mesa.nombre }),
			function () {
				return peticion('/pos/cuentas/' + cuenta.id + '/transferir', 'POST', { mesa_id: mesa.id }).then(function (res) {
					if (!res.ok) {
						window.showToast('error', res.data.message || __t('No se pudo transferir la cuenta.'));
						return;
					}
					window.showToast('success', __t('Cuenta transferida.'));
					window.location.href = res.data.cuenta.mesa_id
						? '/pos/crear?cuenta=' + cuenta.id
						: (PosApp.state.salaUrl || '/pos/sala');
				});
			},
			{ confirmLabel: __t('Transferir'), confirmClass: 'btn-primary', icon: 'wired-outline-1846-employee-working-hover-working' },
		);
		if (moverModal) { moverModal.hide(); }
	}

	function unir(mesa) {
		window.confirmDelete(
			__t('¿Unir esta cuenta con la de «:mesa»? Se sumará todo el consumo pendiente.', { mesa: mesa.nombre }),
			function () {
				return peticion('/pos/cuentas/' + cuenta.id + '/unir', 'POST', { cuenta_destino_id: mesa.cuenta_id }).then(function (res) {
					if (!res.ok) {
						window.showToast('error', res.data.message || __t('No se pudieron unir las cuentas.'));
						return;
					}
					window.showToast('success', __t('Cuentas unidas.'));
					window.location.href = '/pos/crear?cuenta=' + mesa.cuenta_id;
				});
			},
			{ confirmLabel: __t('Unir'), confirmClass: 'btn-primary', icon: 'wired-outline-1846-employee-working-hover-working' },
		);
		if (moverModal) { moverModal.hide(); }
	}

	/** Payload de líneas en el formato que espera PUT /pos/cuentas/{cuenta}. */
	function lineasPayload() {
		return PosApp.lineas.map(function (l) {
			return {
				id: l.cuenta_linea_id || undefined,
				articulo_id: l.articulo_id,
				concepto: l.concepto,
				unidad: l.unidad,
				cantidad: l.cantidad,
				tipo_impositivo: l.tipo,
				opciones: (l.opciones || []).map(function (o) { return { opcion_id: o.opcion_id }; }),
			};
		});
	}

	function aplicarCuenta(data) {
		cuenta = data;
		mesaId = data.mesa_id;
		mesaNombre = data.mesa_nombre;
		// `PosApp.state.cuenta` es el único puente que otros módulos (pos-cobro.js) tienen hacia la
		// cuenta actual: sin sincronizarlo aquí, quedaría congelado en lo que había al cargar la
		// página (a menudo `null`, si se venía de una mesa libre) y cualquier lectura posterior
		// vería una cuenta inexistente aunque ya se hubiera creado/actualizado en servidor.
		PosApp.state.cuenta = data;
		renderChip();
	}

	/**
	 * @param {{silencioso?: boolean}} [opciones] `silencioso` omite el toast de "Cuenta guardada"
	 *   cuando el guardado es un paso previo de otra acción que ya avisa por su cuenta (precuenta).
	 *   Los errores y el aviso de precuenta desactualizada se muestran siempre.
	 */
	function guardar(opciones) {
		var silencioso = !!(opciones && opciones.silencioso);

		if (!PosApp.lineas.length) {
			window.showToast('error', __t('No hay nada que guardar todavía.'));
			return null;
		}

		var promesa = cuenta
			? peticion('/pos/cuentas/' + cuenta.id, 'PUT', {
				version: cuenta.version,
				mesa_id: mesaId,
				lineas: lineasPayload(),
			})
			: peticion('/pos/cuentas', 'POST', { mesa_id: mesaId }).then(function (res) {
				if (!res.ok) { return res; }
				cuenta = res.data;
				return peticion('/pos/cuentas/' + cuenta.id, 'PUT', {
					version: cuenta.version,
					mesa_id: mesaId,
					lineas: lineasPayload(),
				});
			});

		// FR-022: si este guardado deja desactualizada una precuenta que estaba vigente, se avisa.
		var estadoPrevio = cuenta && cuenta.precuenta ? cuenta.precuenta.estado : 'ninguna';

		return promesa.then(function (res) {
			if (res.status === 409) {
				window.showToast('error', res.data.message || __t('Otro dispositivo modificó esta cuenta.'));
				aplicarCuenta(res.data.cuenta);
				return res;
			}
			if (!res.ok) {
				window.showToast('error', res.data.message || __t('No se pudo guardar la cuenta.'));
				return res;
			}
			aplicarCuenta(res.data);
			if (estadoPrevio === 'vigente' && res.data.precuenta && res.data.precuenta.estado === 'desactualizada') {
				window.showToast('warning', __t('La precuenta impresa ya no coincide con la cuenta. Reimprímela antes de cobrar.'));
			} else if (!silencioso) {
				window.showToast('success', __t('Cuenta guardada.'));
			}
			return res;
		});
	}

	function anular() {
		if (!cuenta) { return; }

		window.confirmDelete(
			__t('¿Anular esta cuenta? Se perderá todo lo que no se haya cobrado todavía.'),
			function () {
				return peticion('/pos/cuentas/' + cuenta.id + '/anular', 'POST').then(function (res) {
					if (!res.ok) {
						window.showToast('error', res.data.message || __t('No se pudo anular la cuenta.'));
						return;
					}
					window.showToast('success', res.data.message || __t('Cuenta anulada.'));
					window.location.href = PosApp.state.salaUrl || '/pos/sala';
				});
			},
			{ confirmLabel: __t('Anular'), icon: 'wired-outline-185-trash-bin-hover-empty' },
		);
	}

	function actualizarContextoCobro() {
		if (!$cobroMesaCtx) { return; }

		if (!mesaId) {
			$cobroMesaCtx.classList.add('d-none');
			return;
		}

		$cobroMesaCtx.textContent = mesaNombre || __t('Mesa');
		$cobroMesaCtx.classList.remove('d-none');
	}

	function init() {
		renderChip();

		document.addEventListener('pos:ticket-render', renderPrecuenta);

		// FR-064: al abrir el modal de cobro, refrescar el contexto de mesa (puede haber cambiado
		// desde que se cargó la vista, p. ej. tras transferir).
		if ($cobroModalEl) {
			$cobroModalEl.addEventListener('show.bs.modal', actualizarContextoCobro);
		}

		if ($guardarBtn) {
			$guardarBtn.addEventListener('click', function () {
				window.withButtonLoading($guardarBtn, function () {
					var promesa = guardar();

					// `guardar()` devuelve null si no habia nada que guardar, y ya aviso por su
					// cuenta: no hay nada que vaciar ni que encadenar.
					if (!promesa) { return Promise.resolve(); }

					return promesa.then(function (res) {
						// Solo se vacia si el servidor confirmo. Un 409 (otro dispositivo toco la
						// cuenta) recarga lo que hay en servidor y se queda en pantalla, que es
						// justo cuando el usuario necesita ver con que se topo.
						if (res && res.ok) { vaciarPantalla(); }
						return res;
					});
				});
			});
		}

		if ($anularBtn) {
			$anularBtn.addEventListener('click', anular);
		}

		if ($moverBtn) {
			$moverBtn.addEventListener('click', abrirMover);
		}

		if ($aparcadasBtn) {
			$aparcadasBtn.addEventListener('click', function () {
				window.location.href = PosApp.state.salaUrl || '/pos/sala';
			});
		}
	}

	/**
	 * Deja la pantalla como recien abierta despues de guardar: sin lineas, sin receptor y sin
	 * cuenta ni mesa asociadas.
	 *
	 * Ojo con lo que NO hace: la cuenta no se cierra ni se anula, sigue viva en el servidor con
	 * todo lo guardado. Lo que se cierra es el ticket EN PANTALLA.
	 *
	 * Guardar significa "ya esta, a la siguiente": se manda la comanda y se pasa a otra mesa. Si
	 * la cuenta anterior se quedara cargada, las lineas siguientes se irian a ella sin que nadie
	 * lo notara, que es el error caro de esta pantalla. Por eso guardar y cobrar terminan igual:
	 * en cero.
	 */
	function vaciarPantalla() {
		var cobro = PosApp.modulos.cobro;

		limpiarTrasCierre();

		// `nuevoTicket()` es el mismo vaciado que corre al cerrar el modal de exito tras cobrar:
		// se reutiliza para que guardar y cobrar no puedan divergir en que consideran "a cero".
		if (cobro && cobro.nuevoTicket) {
			cobro.nuevoTicket();
			return;
		}

		PosApp.modulos.ticket.limpiar();
		PosApp.modulos.ticket.render();
	}

	// Tras cobrar el total, la cuenta se cierra y la mesa se libera en servidor: el chip deja de
	// tener sentido en esta pantalla hasta que se abra una cuenta nueva.
	function limpiarTrasCierre() {
		cuenta = null;
		mesaId = null;
		mesaNombre = null;
		PosApp.state.cuenta = null;
		renderChip();
	}

	return {
		init: init,
		guardar: guardar,
		aplicarCuenta: aplicarCuenta,
		vaciarPantalla: vaciarPantalla,
		mesaId: function () { return mesaId; },
		limpiarTrasCierre: limpiarTrasCierre,
	};
});
