/**
 * POS (TPV) — cobro: receptor, métodos de pago, teclado numérico, emisión y modales de resultado.
 *
 * Extraído de `pos-form.js` (feature 038, research D8). El desglose de pagos es interno de caja
 * (no viaja al PDF); el importe siempre lo recalcula el servidor (Principio III).
 */
window.PosApp.registrar('cobro', function (PosApp) {
	'use strict';

	var lineas = PosApp.lineas;

	// Cobro (pago simple o dividido), táctil tablet-first: tarjetas de método + teclado numérico.
	var $cobroModalEl = document.getElementById('posCobroModal');
	var cobroModal = $cobroModalEl ? bootstrap.Modal.getOrCreateInstance($cobroModalEl) : null;
	var $cobroTotal = document.getElementById('pos-cobro-total');
	var $cobroRestante = document.getElementById('pos-cobro-restante');
	var $cobroRestanteLbl = document.getElementById('pos-cobro-restante-lbl');
	var $cobroRestanteVal = document.getElementById('pos-cobro-restante-val');
	var $cobroTenders = document.getElementById('pos-cobro-tenders');
	var $cobroEleccion = document.getElementById('pos-cobro-eleccion');
	var $cobroHint = document.getElementById('pos-cobro-hint');
	var $cobroMetodos = $cobroEleccion ? $cobroEleccion.querySelectorAll('.pos-metodo') : [];
	var $cobroKeypad = document.getElementById('pos-cobro-keypad');
	var $keypadIcon = document.getElementById('pos-keypad-icon');
	var $keypadMetodoLbl = document.getElementById('pos-keypad-metodo-lbl');
	var $keypadMonto = document.getElementById('pos-keypad-monto');
	var $keypadCancelar = document.getElementById('pos-keypad-cancelar');
	var $keypadAnadir = document.getElementById('pos-keypad-anadir');
	var $cobroEmitir = document.getElementById('pos-cobro-emitir');

	// Entregado/Devolver (FR-063): ayuda de caja en efectivo, no altera lo que se cobra.
	var $keypadCambio = document.getElementById('pos-keypad-cambio');
	var $keypadEntregado = document.getElementById('pos-keypad-entregado');
	var $keypadDevolverVal = document.getElementById('pos-keypad-devolver-val');

	var metodosMeta = {};
	try { metodosMeta = JSON.parse((document.getElementById('pos-metodos-data') || {}).textContent || '{}'); } catch (e) { metodosMeta = {}; }

	var tenders = [];        // [{ metodo, importe }]
	var keypadMetodo = null; // método en edición
	var keypadStr = '';      // importe tecleado (decimal con coma)
	var keypadPrellenado = false;

	// Modal de éxito al emitir (OK + mensaje + acciones; sin PDF embebido).
	var $exitoModalEl = document.getElementById('posExitoModal');
	var exitoModal = $exitoModalEl ? bootstrap.Modal.getOrCreateInstance($exitoModalEl) : null;
	var $exitoNumero = document.getElementById('pos-exito-numero');
	var $exitoVer = document.getElementById('pos-exito-ver');
	var $exitoImprimir = document.getElementById('pos-exito-imprimir');
	var $exitoSeguir = document.getElementById('pos-exito-seguir');
	var ultimoTicketPdfUrl = null;

	// Ver ticket (modal aparte con el PDF) + iframe oculto para imprimir.
	var $verModalEl = document.getElementById('posVerTicketModal');
	var verModal = $verModalEl ? bootstrap.Modal.getOrCreateInstance($verModalEl) : null;
	var $verFrame = document.getElementById('pos-ver-frame');
	var $printFrame = document.getElementById('pos-print-frame');

	// Receptor (modal): botón de la botonera + campos.
	var $clienteBtn = document.getElementById('pos-cliente-btn');
	var $clienteBtnLabel = document.getElementById('pos-cliente-btn-label');
	var $receptorModal = document.getElementById('posReceptorModal');
	var $receptorQuitar = document.getElementById('pos-receptor-quitar');
	var $cliente = document.getElementById('pos-cliente');
	var $nif = document.getElementById('pos-nif');
	var $nombre = document.getElementById('pos-nombre');
	var $direccion = document.getElementById('pos-direccion');

	// ── Receptor (factura simplificada cualificada, opcional) ──
	function receptorTieneDatos() {
		return $nif && $nif.value.trim().length > 0;
	}

	function actualizarBotonCliente() {
		if (!$clienteBtn) { return; }
		var tiene = receptorTieneDatos();
		$clienteBtn.classList.toggle('active', tiene);
		if ($clienteBtnLabel) {
			$clienteBtnLabel.textContent = tiene
				? (($nombre && $nombre.value.trim()) || $nif.value.trim())
				: 'Cliente';
		}
	}

	function limpiarReceptor() {
		if ($cliente) { $cliente.value = ''; }
		if ($nif) { $nif.value = ''; }
		if ($nombre) { $nombre.value = ''; }
		if ($direccion) { $direccion.value = ''; }
		actualizarBotonCliente();
	}

	function payload() {
		var data = {
			lineas: lineas.map(function (l) {
				return {
					articulo_id: l.articulo_id,
					concepto: l.concepto,
					unidad: l.unidad,
					cantidad: l.cantidad,
					precio_unitario: l.precio,
					tipo_impositivo: l.tipo,
				};
			}),
			pagos: leerPagos(),
		};

		if (receptorTieneDatos()) {
			data.receptor = {
				cliente_id: ($cliente && $cliente.value) || null,
				cliente_nif: $nif.value.trim(),
				cliente_nombre: ($nombre && $nombre.value.trim()) || null,
				cliente_razon_social: ($nombre && $nombre.value.trim()) || null,
				cliente_direccion: ($direccion && $direccion.value.trim()) || null,
			};
		}

		return data;
	}

	// ── Cobro: reparto del total en uno o varios métodos de pago (tender-by-tender) ──
	function totalCobro() {
		return Math.round(PosApp.totalBruto() * 100) / 100;
	}

	function sumaAsignada() {
		return tenders.reduce(function (acc, t) { return acc + t.importe; }, 0);
	}

	function restanteCobro() {
		return Math.round((totalCobro() - sumaAsignada()) * 100) / 100;
	}

	function metodoLabel(metodo) {
		return (metodosMeta[metodo] && metodosMeta[metodo].label) || metodo;
	}

	function metodoIcon(metodo) {
		return (metodosMeta[metodo] && metodosMeta[metodo].icon) || 'fa-money-bill-wave';
	}

	// Importe tecleado (coma decimal es-ES) → número.
	function montoKeypad() {
		var val = parseFloat((keypadStr || '0').replace(',', '.'));
		return isNaN(val) ? 0 : Math.round(val * 100) / 100;
	}

	function renderTenders() {
		if (!$cobroTenders) { return; }
		if (!tenders.length) {
			$cobroTenders.classList.add('d-none');
			$cobroTenders.innerHTML = '';
			return;
		}
		$cobroTenders.classList.remove('d-none');
		$cobroTenders.innerHTML = '';
		tenders.forEach(function (t, i) {
			var row = document.createElement('div');
			row.className = 'pos-cobro-tender';
			row.innerHTML =
				'<span class="ic"><i class="fas ' + metodoIcon(t.metodo) + '"></i></span>' +
				'<span class="nom">' + PosApp.escapeHtml(metodoLabel(t.metodo)) + '</span>' +
				'<span class="imp">' + PosApp.format(t.importe) + ' €</span>' +
				'<button type="button" class="quitar" data-i="' + i + '" aria-label="Quitar pago">×</button>';
			$cobroTenders.appendChild(row);
		});
	}

	function renderRestante() {
		var restante = restanteCobro();
		var completo = PosApp.centimos(restante) === 0 && tenders.length > 0;

		var tecleando = keypadMetodo !== null;
		if ($cobroRestante) {
			$cobroRestante.classList.toggle('completo', completo);
			// Tocable sólo mientras se teclea un importe y aún queda algo por asignar.
			$cobroRestante.classList.toggle('tappable', tecleando && restante > 0);
		}
		if ($cobroRestanteLbl) { $cobroRestanteLbl.textContent = completo ? 'Cobrado' : 'Restante'; }
		if ($cobroRestanteVal) { $cobroRestanteVal.textContent = PosApp.format(completo ? totalCobro() : Math.max(0, restante)) + ' €'; }

		// Elección de método visible sólo mientras falte por asignar (y no se esté tecleando).
		if ($cobroEleccion) { $cobroEleccion.classList.toggle('d-none', tecleando || restante <= 0); }
		if ($cobroHint) {
			$cobroHint.textContent = tenders.length
				? 'Añadí otro método para dividir el pago.'
				: 'Tocá el método con el que cobrás.';
		}

		if ($cobroEmitir) { $cobroEmitir.disabled = !completo || !lineas.length; }
	}

	function renderCobro() {
		if ($cobroTotal) { $cobroTotal.textContent = PosApp.format(totalCobro()) + ' €'; }
		renderTenders();
		renderRestante();
	}

	function abrirKeypad(metodo) {
		keypadMetodo = metodo;
		keypadStr = restanteCobro().toFixed(2).replace('.', ','); // prellena con lo que falta
		keypadPrellenado = true;
		if ($keypadIcon) { $keypadIcon.className = 'fas ' + metodoIcon(metodo); }
		if ($keypadMetodoLbl) { $keypadMetodoLbl.textContent = metodoLabel(metodo); }
		if ($cobroKeypad) { $cobroKeypad.classList.remove('d-none'); }
		if ($cobroEleccion) { $cobroEleccion.classList.add('d-none'); }
		if ($keypadEntregado) { $keypadEntregado.value = ''; }
		renderKeypad();
	}

	function cerrarKeypad() {
		keypadMetodo = null;
		keypadStr = '';
		if ($cobroKeypad) { $cobroKeypad.classList.add('d-none'); }
		renderRestante();
	}

	function renderKeypad() {
		var monto = montoKeypad();
		var restante = restanteCobro();
		if ($keypadMonto) { $keypadMonto.textContent = PosApp.format(monto) + ' €'; }
		// Sólo se puede añadir un importe > 0 que no supere lo que falta (comparación en céntimos).
		if ($keypadAnadir) { $keypadAnadir.disabled = PosApp.centimos(monto) <= 0 || PosApp.centimos(monto) > PosApp.centimos(restante); }
		renderCambio(monto);
	}

	// Entregado/Devolver (FR-063): ayuda de caja, solo visible en efectivo. No altera `monto`
	// (lo que realmente se registra como tender): solo calcula el vuelto para el cajero.
	function renderCambio(monto) {
		if (!$keypadCambio) { return; }

		var esEfectivo = keypadMetodo === 'efectivo';
		$keypadCambio.classList.toggle('d-none', !esEfectivo);

		if (!esEfectivo) { return; }

		var entregado = parseFloat((($keypadEntregado && $keypadEntregado.value) || '0').replace(',', '.'));
		if (isNaN(entregado)) { entregado = 0; }

		var devuelve = Math.max(0, Math.round((entregado - monto) * 100) / 100);
		if ($keypadDevolverVal) { $keypadDevolverVal.textContent = PosApp.format(devuelve) + ' €'; }
	}

	function pulsarTecla(key) {
		if (key === 'del') {
			keypadStr = keypadPrellenado ? '' : keypadStr.slice(0, -1);
			keypadPrellenado = false;
			renderKeypad();
			return;
		}
		// La primera pulsación tras prellenar arranca de cero (el cajero teclea su importe).
		if (keypadPrellenado) { keypadStr = ''; keypadPrellenado = false; }
		if (key === ',') {
			if (keypadStr.indexOf(',') === -1) { keypadStr = (keypadStr || '0') + ','; }
		} else {
			// Máximo 2 decimales.
			var partes = keypadStr.split(',');
			if (partes[1] && partes[1].length >= 2) { return; }
			keypadStr += key;
		}
		renderKeypad();
	}

	function anadirTender() {
		var monto = montoKeypad();
		var restante = restanteCobro();
		if (PosApp.centimos(monto) <= 0 || PosApp.centimos(monto) > PosApp.centimos(restante)) { return; }
		tenders.push({ metodo: keypadMetodo, importe: monto });
		cerrarKeypad();
		renderCobro();
	}

	function resetCobro() {
		tenders = [];
		cerrarKeypad();
		renderCobro();
	}

	// pagos[] para el backend. El desglose siempre cuadra (el UI no deja emitir hasta restante 0).
	function leerPagos() {
		return tenders.map(function (t) { return { metodo: t.metodo, importe: t.importe }; });
	}

	// ── Éxito al emitir: OK + PDF (formato ticket, igual que "Ver ticket" de la tabla) ──
	function pdfUrlPara(id) {
		return (PosApp.state.pdfUrlTemplate || '').replace('__ID__', id);
	}

	function mostrarExito(data) {
		ultimoTicketPdfUrl = pdfUrlPara(data.id);
		// Precargar el PDF en el iframe oculto para que "Imprimir" responda al instante.
		if ($printFrame) { $printFrame.setAttribute('src', ultimoTicketPdfUrl); }
		if ($exitoNumero) { $exitoNumero.textContent = data.numero_completo ? ('Nº ' + data.numero_completo) : ''; }
		if (exitoModal) { exitoModal.show(); }
	}

	function nuevoTicket() {
		PosApp.modulos.ticket.limpiar();
		limpiarReceptor();
		PosApp.modulos.ticket.render();
	}

	function autocompletarRestante() {
		// Tocar "Restante" (con el teclado abierto) autocompleta el importe con lo que falta.
		if (keypadMetodo === null) { return; }
		var restante = restanteCobro();
		if (restante <= 0) { return; }
		keypadStr = restante.toFixed(2).replace('.', ',');
		keypadPrellenado = true;
		renderKeypad();
	}

	function init() {
		if ($cliente) {
			$cliente.addEventListener('change', function () {
				var opt = this.options[this.selectedIndex];
				$nif.value = opt.getAttribute('data-nif') || '';
				$nombre.value = opt.getAttribute('data-nombre') || '';
				$direccion.value = opt.getAttribute('data-direccion') || '';
			});
		}

		if ($receptorQuitar) {
			$receptorQuitar.addEventListener('click', limpiarReceptor);
		}

		// Al cerrar el modal, reflejar en el botón si quedó receptor cargado o no.
		if ($receptorModal) {
			$receptorModal.addEventListener('hidden.bs.modal', actualizarBotonCliente);
		}

		if ($cobroModalEl) {
			$cobroModalEl.addEventListener('show.bs.modal', resetCobro);
		}

		Array.prototype.forEach.call($cobroMetodos, function (btn) {
			btn.addEventListener('click', function () { abrirKeypad(btn.getAttribute('data-metodo')); });
		});

		if ($keypadEntregado) {
			$keypadEntregado.addEventListener('input', function () { renderCambio(montoKeypad()); });
		}

		if ($cobroKeypad) {
			$cobroKeypad.addEventListener('click', function (e) {
				var tecla = e.target.closest('.pos-key');
				if (tecla) { pulsarTecla(tecla.getAttribute('data-key')); }
			});
		}
		if ($keypadCancelar) { $keypadCancelar.addEventListener('click', cerrarKeypad); }
		if ($keypadAnadir) { $keypadAnadir.addEventListener('click', anadirTender); }

		if ($cobroRestante) {
			$cobroRestante.addEventListener('click', autocompletarRestante);
			$cobroRestante.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); autocompletarRestante(); }
			});
		}

		if ($cobroTenders) {
			$cobroTenders.addEventListener('click', function (e) {
				var btn = e.target.closest('.quitar');
				if (!btn) { return; }
				tenders.splice(parseInt(btn.getAttribute('data-i'), 10), 1);
				renderCobro();
			});
		}

		// "Ver ticket": recién acá se carga y muestra el PDF, en su propio modal.
		if ($exitoVer) {
			$exitoVer.addEventListener('click', function () {
				if (!ultimoTicketPdfUrl) { return; }
				if ($verFrame) { $verFrame.setAttribute('src', ultimoTicketPdfUrl); }
				if (verModal) { verModal.show(); }
			});
		}

		if ($exitoImprimir) {
			$exitoImprimir.addEventListener('click', function () {
				if ($printFrame && $printFrame.contentWindow) {
					$printFrame.contentWindow.focus();
					$printFrame.contentWindow.print();
				}
			});
		}

		if ($exitoSeguir) {
			$exitoSeguir.addEventListener('click', function () {
				if (exitoModal) { exitoModal.hide(); }
			});
		}

		// Cualquier cierre del modal de éxito (Seguir creando, X, Esc) vacía todo para un ticket nuevo.
		if ($exitoModalEl) {
			$exitoModalEl.addEventListener('hidden.bs.modal', function () {
				nuevoTicket();
				if ($printFrame) { $printFrame.setAttribute('src', ''); }
			});
		}

		// Al cerrar el modal del PDF, liberar el iframe.
		if ($verModalEl) {
			$verModalEl.addEventListener('hidden.bs.modal', function () {
				if ($verFrame) { $verFrame.setAttribute('src', ''); }
			});
		}

		if ($cobroEmitir) {
			$cobroEmitir.addEventListener('click', function () {
				window.withButtonLoading($cobroEmitir, function () {
					// Con una cuenta de mesa de por medio (feature 038), el cobro NO pasa por el
					// endpoint de venta directa: tiene que ir por `CobradorCuenta`
					// (`/pos/cuentas/{id}/cobrar`), que es quien marca `cantidad_saldada`, crea
					// `pos_cobros` y cierra la cuenta/libera la mesa. Emitir por el camino de venta
					// directa produce una factura válida pero deja la cuenta "abierta" para
					// siempre, con riesgo real de cobrar el mismo consumo dos veces.
					// Ojo: hay contexto de mesa aunque `PosApp.state.cuenta` todavía sea `null` — pasa
					// al venir de una mesa LIBRE recién tocada en la Sala, donde la cuenta se crea
					// recién al guardar la primera vez. Por eso la condición mira `mesaId()` (el
					// módulo sabe si hay mesa de por medio) y no la existencia de `cuenta`;
					// `cobrarCuenta()` guarda primero, lo que crea la cuenta si hiciera falta.
					var cuentaModulo = PosApp.modulos.cuenta;

					if (cuentaModulo && cuentaModulo.mesaId()) {
						return cobrarCuenta(cuentaModulo);
					}

					return fetch(PosApp.state.storeUrl, {
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
						},
						body: JSON.stringify(payload()),
					})
						.then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
						.then(function (res) {
							if (!res.ok) {
								window.showToast('error', res.data.message || 'No se pudo emitir el ticket.');
								return;
							}
							if (cobroModal) { cobroModal.hide(); }
							mostrarExito(res.data);
						})
						.catch(function () {
							window.showToast('error', 'No se pudo emitir el ticket.');
						});
				}).always(function () {
					renderRestante();
				});
			});
		}

		function csrf() {
			return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
		}

		// Guarda primero las líneas actuales (crea la cuenta si todavía no existía, p. ej. al
		// cobrar de una tirada desde una mesa recién tocada) y recién entonces cobra la cuenta
		// entera contra su propio endpoint.
		function cobrarCuenta(cuentaModulo) {
			var guardado = cuentaModulo.guardar();

			if (!guardado) {
				// `guardar()` ya mostró su propio toast (p. ej. "No hay nada que guardar todavía").
				return Promise.resolve();
			}

			return guardado.then(function (resGuardar) {
				var cuenta = PosApp.state.cuenta;

				if (!resGuardar || !resGuardar.ok || !cuenta) {
					// El guardado falló (409/422/red) y ya avisó por su cuenta: no seguir a
					// cobrar con una cuenta inexistente o desactualizada.
					return;
				}

				return fetch('/pos/cuentas/' + cuenta.id + '/cobrar', {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': csrf(),
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({ version: cuenta.version, pagos: leerPagos() }),
				})
					.then(function (r) { return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; }); })
					.then(function (res) {
						if (res.status === 409) {
							window.showToast('error', res.data.message || 'Otro dispositivo modificó esta cuenta.');
							return;
						}
						if (!res.ok) {
							window.showToast('error', res.data.message || 'No se pudo cobrar la cuenta.');
							return;
						}
						if (res.data.cuenta_cerrada && cuentaModulo.limpiarTrasCierre) {
							cuentaModulo.limpiarTrasCierre();
						}
						if (cobroModal) { cobroModal.hide(); }
						mostrarExito(res.data);
					})
					.catch(function () {
						window.showToast('error', 'No se pudo cobrar la cuenta.');
					});
			});
		}
	}

	return {
		init: init,
		renderCobro: renderCobro,
		mostrarExito: mostrarExito,
		nuevoTicket: nuevoTicket,
		payload: payload,
		limpiarReceptor: limpiarReceptor,
	};
});
