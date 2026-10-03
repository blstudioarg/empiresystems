/**
 * Pantalla de caja del POS (feature 048): estado cerrada / abierta / cierre.
 *
 * El servidor decide el estado inicial y es la única fuente de las cifras. Este archivo solo
 * alterna vistas, pinta lo que llega y envía lo que el usuario cuenta o teclea:
 *
 *  · Arqueo ciego (FR-010): el efectivo esperado no está en `cajaState` ni en ninguna respuesta
 *    hasta que se confirma el conteo; aquí no hay nada que "ocultar", simplemente no existe.
 *  · El conteo en curso se guarda en sessionStorage por sesión de caja: una recarga accidental de
 *    la tablet no obliga a recontar (preferencia de interfaz, no viaja al servidor).
 */
(function () {
	'use strict';

	var S = window.cajaState;
	if (!S) { return; }

	var T = window.PosTeclado;
	var $ = window.jQuery;

	var el = function (id) { return document.getElementById(id); };
	var vistas = { cerrada: el('caja-cerrada'), abierta: el('caja-abierta'), cierre: el('caja-cierre') };

	var sesion = S.estado.abierta ? S.estado.sesion : null;
	var enVivo = S.estado.abierta ? S.estado.en_vivo : null;
	var ultimoCierre = S.estado.abierta ? null : S.estado.ultimo_cierre;
	var cierreActual = null; // respuesta del cierre (informe y URLs)

	var reducirMovimiento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function euros(v) { return T.formatear(parseFloat(v) || 0) + ' €'; }
	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}
	function headers() { return { Accept: 'application/json', 'X-CSRF-TOKEN': S.csrf }; }
	function postJson(url, datos) {
		return $.ajax({ url: url, method: 'POST', dataType: 'json', contentType: 'application/json', data: JSON.stringify(datos), headers: headers() });
	}
	function mensajeError(xhr, porDefecto) {
		var r = xhr.responseJSON || {};
		if (xhr.status === 422 && r.errors) {
			var k = Object.keys(r.errors)[0];
			return r.errors[k][0];
		}
		return r.message || porDefecto;
	}

	function mostrar(nombre) {
		Object.keys(vistas).forEach(function (k) { vistas[k].hidden = k !== nombre; });
		window.scrollTo({ top: 0, behavior: reducirMovimiento ? 'auto' : 'smooth' });
	}

	// ── Estado A: cerrada ──────────────────────────────────────────────────────

	var $hero = el('caja-hero');
	var $aperturaWrap = el('caja-apertura-wrap');

	function pintarUltimoCierre() {
		var $u = el('caja-ultimo');
		if (!ultimoCierre) { $u.hidden = true; return; }
		$u.hidden = false;
		var estadoTxt = { cuadra: 'Cuadró', sobra: 'Sobraron ' + euros(Math.abs(ultimoCierre.descuadre)), falta: 'Faltaron ' + euros(Math.abs(ultimoCierre.descuadre)) }[ultimoCierre.estado] || '';
		$u.querySelector('[data-ultimo="titulo"]').textContent = euros(ultimoCierre.total_facturado) + ' facturado · ' + estadoTxt;
		$u.querySelector('[data-ultimo="detalle"]').textContent = ultimoCierre.cerrada_texto + (ultimoCierre.cerrada_por ? ' · ' + ultimoCierre.cerrada_por : '');
	}

	el('caja-abrir-btn').addEventListener('click', function () {
		$hero.hidden = true;
		$aperturaWrap.hidden = false;
	});

	window.PosCajaApertura.crear(el('caja-apertura'), {
		url: S.urls.abrir,
		csrf: S.csrf,
		onAbierta: function (res) { entrarAbierta(res.sesion, res.en_vivo); },
		onYaAbierta: function () { refrescar(); },
		onCancelar: function () { $aperturaWrap.hidden = true; $hero.hidden = false; },
	});

	el('caja-ultimo-ver').addEventListener('click', function () {
		if (ultimoCierre) { abrirInforme(ultimoCierre.informe_url_ticket); }
	});

	// ── Estado B: abierta ──────────────────────────────────────────────────────

	var $estado = el('caja-estado');

	function duracionDesde(iso) {
		var min = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
		var h = Math.floor(min / 60);
		var m = min % 60;
		return '(' + (h > 0 ? h + ' h ' : '') + m + ' min)';
	}

	function pintarSesion() {
		if (!sesion) { return; }
		$estado.querySelector('[data-sesion="hora"]').textContent = sesion.abierta_hora + (sesion.abierta_dia_anterior ? ' del ' + sesion.abierta_fecha : '');
		$estado.querySelector('[data-sesion="duracion"]').textContent = duracionDesde(sesion.abierta_at);
		$estado.querySelector('[data-sesion="usuario"]').textContent = sesion.abierta_por || '';
		$estado.classList.toggle('caja-estado-antigua', !!sesion.abierta_dia_anterior);
		document.querySelector('[data-sesion="fondo"]').textContent = euros(sesion.fondo_inicial);
	}

	function pintarEnVivo() {
		if (!enVivo) { return; }
		document.querySelector('[data-vivo="total_vendido"]').textContent = euros(enVivo.total_vendido);
		document.querySelector('[data-vivo="num_tickets"]').textContent = String(enVivo.num_tickets);
		document.querySelector('[data-vivo="ticket_medio"]').textContent = euros(enVivo.ticket_medio);

		var total = parseFloat(enVivo.total_vendido) || 0;
		var barra = el('caja-metodos-barra');
		var lista = el('caja-metodos-lista');
		barra.innerHTML = '';
		lista.innerHTML = '';
		enVivo.por_metodo.forEach(function (m) {
			var importe = parseFloat(m.importe) || 0;
			if (total > 0 && importe > 0) {
				var seg = document.createElement('span');
				seg.setAttribute('data-metodo', m.metodo);
				seg.style.width = (importe / total * 100).toFixed(2) + '%';
				seg.title = m.label + ': ' + euros(importe);
				barra.appendChild(seg);
			}
			var li = document.createElement('li');
			li.setAttribute('data-metodo', m.metodo);
			li.className = importe > 0 ? '' : 'cero';
			li.innerHTML = '<span class="caja-punto" aria-hidden="true"></span>'
				+ '<span class="nombre">' + esc(m.label) + '</span>'
				+ '<span class="tickets">' + (m.tickets ? m.tickets + (m.tickets === 1 ? ' ticket' : ' tickets') : '—') + '</span>'
				+ '<span class="importe">' + euros(importe) + '</span>';
			lista.appendChild(li);
		});

		var movs = enVivo.movimientos || [];
		var $lista = el('caja-mov-lista');
		$lista.innerHTML = movs.slice().reverse().map(function (m) {
			var hora = new Date(m.at);
			var hh = ('0' + hora.getHours()).slice(-2) + ':' + ('0' + hora.getMinutes()).slice(-2);
			return '<li><span class="hora">' + hh + '</span><span class="motivo" title="' + esc(m.motivo) + '">' + esc(m.motivo) + '</span>'
				+ '<span class="imp ' + m.tipo + '">' + (m.tipo === 'entrada' ? '+' : '−') + euros(m.importe) + '</span></li>';
		}).join('');
		el('caja-mov-vacio').hidden = movs.length > 0;
		document.querySelector('[data-mov="conteo"]').textContent = movs.length ? movs.length + (movs.length === 1 ? ' movimiento' : ' movimientos') : '';
	}

	function entrarAbierta(s, vivo) {
		sesion = s;
		enVivo = vivo;
		pintarSesion();
		pintarEnVivo();
		mostrar('abierta');
	}

	function entrarCerrada() {
		sesion = null;
		enVivo = null;
		$hero.hidden = false;
		$aperturaWrap.hidden = true;
		pintarUltimoCierre();
		mostrar('cerrada');
	}

	function refrescar(boton) {
		var peticion = function () { return $.ajax({ url: S.urls.estado, dataType: 'json', headers: { Accept: 'application/json' } }); };
		var p = boton ? window.withButtonLoading(boton, peticion) : peticion();
		return p.done(function (r) {
			// Mientras se cuenta, el estado no cambia de vista por debajo: el cierre lo decide el usuario.
			if (!vistas.cierre.hidden) { return; }
			if (r.abierta) { entrarAbierta(r.sesion, r.en_vivo); } else { ultimoCierre = r.ultimo_cierre; entrarCerrada(); }
		});
	}

	el('caja-actualizar').addEventListener('click', function () { refrescar(this); });

	// "¿Cómo vamos?" sin tocar nada: cada minuto, solo con la pestaña visible.
	setInterval(function () {
		if (document.visibilityState !== 'visible') { return; }
		if (sesion && !vistas.abierta.hidden) {
			pintarSesion();
			refrescar();
		}
	}, 60000);
	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'visible' && sesion && !vistas.abierta.hidden) { refrescar(); }
	});

	// ── Movimientos ────────────────────────────────────────────────────────────

	var movModalEl = el('cajaMovimientoModal');
	var movModal = bootstrap.Modal.getOrCreateInstance(movModalEl);
	var movTipo = 'salida';
	var movStr = '';
	var $movImporte = el('caja-mov-importe');
	var $movMotivo = el('caja-mov-motivo');
	var $movGuardar = el('caja-mov-guardar');
	var CHIPS = {
		salida: ['Pago a proveedor', 'Retirada a caja fuerte', 'Gasto menor', 'Otro'],
		entrada: ['Cambio', 'Reposición de fondo', 'Otro'],
	};

	function pintarMov() {
		$movImporte.textContent = euros(T.aNumero(movStr));
		$movImporte.classList.toggle('vacio', movStr === '');
		movModalEl.querySelectorAll('[data-mov-tipo]').forEach(function (b) {
			var activo = b.getAttribute('data-mov-tipo') === movTipo;
			b.classList.toggle('active', activo);
			b.setAttribute('aria-pressed', activo ? 'true' : 'false');
		});
		$movGuardar.textContent = movTipo === 'entrada' ? 'Registrar entrada' : 'Registrar salida';
		$movGuardar.disabled = T.aNumero(movStr) <= 0 || $movMotivo.value.trim() === '';
		el('caja-mov-chips').innerHTML = CHIPS[movTipo].map(function (c) {
			return '<button type="button" class="caja-chip" data-chip="' + esc(c) + '">' + esc(c) + '</button>';
		}).join('');
	}

	document.querySelectorAll('[data-movimiento]').forEach(function (b) {
		b.addEventListener('click', function () {
			movTipo = b.getAttribute('data-movimiento');
			movStr = '';
			$movMotivo.value = '';
			pintarMov();
			movModal.show();
		});
	});
	movModalEl.querySelectorAll('[data-mov-tipo]').forEach(function (b) {
		b.addEventListener('click', function () { movTipo = b.getAttribute('data-mov-tipo'); pintarMov(); });
	});
	el('caja-mov-teclado').addEventListener('click', function (e) {
		var k = e.target.closest('[data-key]');
		if (!k) { return; }
		var sig = T.aplicarTecla(movStr, k.getAttribute('data-key'), false);
		if (sig === null) { return; }
		movStr = sig;
		pintarMov();
	});
	el('caja-mov-chips').addEventListener('click', function (e) {
		var c = e.target.closest('[data-chip]');
		if (!c) { return; }
		// "Otro" no rellena nada: deja el campo para escribir. El resto acelera, pero sigue editable.
		var texto = c.getAttribute('data-chip');
		$movMotivo.value = texto === 'Otro' ? '' : texto;
		$movMotivo.focus();
		pintarMov();
	});
	$movMotivo.addEventListener('input', function () {
		$movGuardar.disabled = T.aNumero(movStr) <= 0 || $movMotivo.value.trim() === '';
	});
	$movGuardar.addEventListener('click', function () {
		window.withButtonLoading($movGuardar, function () {
			return postJson(S.urls.movimiento, { tipo: movTipo, importe: T.aNumero(movStr).toFixed(2), motivo: $movMotivo.value.trim() });
		})
			.done(function (r) {
				window.showToast('success', r.message);
				enVivo = r.en_vivo;
				pintarEnVivo();
				movModal.hide();
			})
			.fail(function (xhr) {
				window.showToast('error', mensajeError(xhr, 'No se pudo registrar el movimiento.'));
				if (xhr.status === 409) { movModal.hide(); refrescar(); }
			});
	});

	// ── Estado C: cierre ───────────────────────────────────────────────────────

	var claveConteo = function () { return 'caja-conteo:' + (sesion ? sesion.id : '0'); };
	var bandejaCierre = window.PosCajaBandeja.crear(el('caja-bandeja-cierre'), {
		onCambio: function () {
			try { sessionStorage.setItem(claveConteo(), JSON.stringify(bandejaCierre.estado())); } catch (e) { /* sin storage: no pasa nada */ }
		},
	});
	var $pasoContar = el('caja-paso-contar');
	var $pasoResultado = el('caja-paso-resultado');

	function marcarPaso(n) {
		document.querySelectorAll('.caja-paso').forEach(function (p) {
			var num = parseInt(p.getAttribute('data-paso'), 10);
			p.classList.toggle('activo', num === n);
			p.classList.toggle('hecho', num < n);
		});
		el('caja-ciego').hidden = n !== 1;
	}

	function entrarCierre() {
		bandejaCierre.limpiar();
		try {
			var guardado = JSON.parse(sessionStorage.getItem(claveConteo()) || 'null');
			if (guardado) { bandejaCierre.cargar(guardado); }
		} catch (e) { /* ignorar */ }
		$pasoContar.hidden = false;
		$pasoResultado.hidden = true;
		el('caja-resultado').classList.remove('caja-revelado');
		marcarPaso(1);
		mostrar('cierre');
	}

	el('caja-cerrar-btn').addEventListener('click', entrarCierre);
	el('caja-cierre-volver').addEventListener('click', function () { mostrar('abierta'); refrescar(); });

	function cuerpoCierre(observacion) {
		var datos = bandejaCierre.payload();
		var cuerpo = { sesion_id: sesion.id };
		if (datos.conteo !== undefined) { cuerpo.conteo = datos.conteo; } else { cuerpo.efectivo_contado = datos.importe; }
		if (observacion) { cuerpo.observacion = observacion; }
		return cuerpo;
	}

	var VEREDICTOS = {
		cuadra: { icono: 'fa-circle-check', titulo: function () { return 'Cuadra'; }, sub: 'El efectivo coincide al céntimo.' },
		sobra: { icono: 'fa-arrow-trend-up', titulo: function (d) { return 'Sobran ' + euros(Math.abs(d)); }, sub: 'Hay más efectivo del esperado.' },
		falta: { icono: 'fa-triangle-exclamation', titulo: function (d) { return 'Faltan ' + euros(Math.abs(d)); }, sub: 'Hay menos efectivo del esperado.' },
	};

	function revelar(resultado, provisional, umbral) {
		$pasoContar.hidden = true;
		$pasoResultado.hidden = false;
		marcarPaso(2);

		var $res = el('caja-resultado');
		$res.classList.remove('caja-revelado');
		$res.querySelector('[data-res="efectivo_esperado"]').textContent = euros(resultado.efectivo_esperado);
		$res.querySelector('[data-res="efectivo_contado"]').textContent = euros(resultado.efectivo_contado);

		var v = VEREDICTOS[resultado.estado] || VEREDICTOS.cuadra;
		var $v = el('caja-veredicto');
		$v.setAttribute('data-estado', resultado.estado);
		var $icono = $v.querySelector('[data-veredicto="icono"]');
		$icono.className = 'fa-solid ' + v.icono;
		$v.querySelector('[data-veredicto="titulo"]').textContent = v.titulo(parseFloat(resultado.descuadre));
		$v.querySelector('[data-veredicto="sub"]').textContent = provisional ? 'La caja todavía no está cerrada.' : v.sub;

		el('caja-observacion').hidden = !provisional;
		el('caja-acciones-provisional').hidden = !provisional;
		el('caja-acciones-final').hidden = provisional;
		el('caja-papel-wrap').hidden = provisional;
		if (provisional) {
			el('caja-observacion').querySelector('[data-observacion="ayuda"]').textContent =
				'Las diferencias de más de ' + euros(umbral) + ' necesitan una explicación para cerrar.';
		}

		// Dos frames: el estado inicial (opaco, 8px abajo) tiene que pintarse antes de la transición.
		requestAnimationFrame(function () { requestAnimationFrame(function () { $res.classList.add('caja-revelado'); }); });
		if (provisional) { setTimeout(function () { el('caja-observacion-txt').focus(); }, reducirMovimiento ? 0 : 320); }
	}

	function enviarCierre(boton, observacion) {
		return window.withButtonLoading(boton, function () { return postJson(S.urls.cerrar, cuerpoCierre(observacion)); })
			.done(function (r) {
				cierreActual = r;
				ultimoCierre = r.ultimo_cierre;
				try { sessionStorage.removeItem(claveConteo()); } catch (e) { /* ignorar */ }
				pintarPapel(r.informe);
				revelar(r.resultado, false);
				window.showToast('success', r.message || 'Caja cerrada.');
			})
			.fail(function (xhr) {
				var r = xhr.responseJSON || {};
				if (xhr.status === 422 && r.codigo === 'observacion_requerida') {
					revelar(r.resultado, true, r.umbral);
					return;
				}
				window.showToast('error', mensajeError(xhr, 'No se pudo cerrar la caja.'));
				if (xhr.status === 409) {
					try { sessionStorage.removeItem(claveConteo()); } catch (e) { /* ignorar */ }
					// Otra tablet cerró antes: se sale del conteo y se muestra el estado real de la caja
					// (con su informe, si lo hay) en vez de dejar al usuario contando una caja cerrada.
					vistas.cierre.hidden = true;
					refrescar();
					if (r.informe_url_ticket) { abrirInforme(r.informe_url_ticket); }
				}
			});
	}

	el('caja-confirmar-conteo').addEventListener('click', function () { enviarCierre(this, null); });
	el('caja-cerrar-con-diferencia').addEventListener('click', function () {
		var obs = el('caja-observacion-txt').value.trim();
		if (!obs) {
			window.showToast('warning', 'Escribe qué pasó para poder cerrar con esta diferencia.');
			el('caja-observacion-txt').focus();
			return;
		}
		enviarCierre(this, obs);
	});
	el('caja-recontar').addEventListener('click', function () {
		$pasoResultado.hidden = true;
		$pasoContar.hidden = false;
		el('caja-resultado').classList.remove('caja-revelado');
		marcarPaso(1);
	});

	// ── Informe Z ──────────────────────────────────────────────────────────────

	function fila(izq, der, clase) {
		return '<div class="f' + (clase ? ' ' + clase : '') + '"><span>' + esc(izq) + '</span><span>' + esc(der) + '</span></div>';
	}

	/** La tira en pantalla dice lo mismo que el PDF de 80 mm (mismas secciones, mismo orden). */
	function pintarPapel(inf) {
		var h = [];
		h.push('<div class="c b">' + esc(S.tenant || '') + '</div>');
		h.push('<div class="c t">CIERRE DE CAJA · Nº ' + esc(inf.numero) + '</div>');
		h.push('<div class="c m">Informe Z</div><div class="sep"></div>');
		h.push(fila('Apertura', inf.abierta_at), '<div class="m">' + esc(inf.abierta_por || '') + '</div>');
		h.push(fila('Cierre', inf.cerrada_at || ''), '<div class="m">' + esc(inf.cerrada_por || '') + '</div><div class="sep"></div>');
		h.push('<div class="sec">VENTAS</div>', fila('Tickets', String(inf.num_tickets)));
		if (inf.primer_ticket) { h.push('<div class="m">' + esc(inf.primer_ticket) + ' → ' + esc(inf.ultimo_ticket) + '</div>'); }
		h.push(fila('TOTAL', euros(inf.total_facturado), 'tot'), '<div class="sep"></div><div class="sec">POR MÉTODO</div>');
		inf.por_metodo.forEach(function (m) { h.push(fila(m.label + ' (' + m.tickets + ')', euros(m.importe))); });
		if (inf.por_impuesto.length) {
			h.push('<div class="sep"></div><div class="sec">IMPUESTOS</div>');
			inf.por_impuesto.forEach(function (i) {
				h.push(fila(i.tipo_impuesto.toUpperCase() + ' ' + T.formatear(i.porcentaje).replace(',00', '') + '%  base ' + T.formatear(i.base), euros(i.cuota)));
			});
		}
		if (inf.anulados.length) {
			h.push('<div class="sep"></div><div class="sec">ANULADOS (no suman)</div>');
			inf.anulados.forEach(function (a) { h.push(fila(a.numero || '—', euros(a.total))); });
		}
		if (inf.movimientos.length) {
			h.push('<div class="sep"></div><div class="sec">MOVIMIENTOS</div>');
			inf.movimientos.forEach(function (m) { h.push(fila(m.hora + ' ' + m.motivo, (m.tipo === 'entrada' ? '+' : '−') + euros(m.importe))); });
		}
		var signo = parseFloat(inf.descuadre) > 0 ? '+' : '';
		var veredicto = { cuadra: 'CUADRA', sobra: 'SOBRANTE', falta: 'FALTANTE' }[inf.estado] || '';
		h.push('<div class="sep"></div><div class="sec">ARQUEO</div>');
		h.push(fila('Fondo inicial', euros(inf.fondo_inicial)), fila('+ Ventas efectivo', euros(inf.efectivo_ventas)));
		h.push(fila('+ Entradas', euros(inf.entradas)), fila('− Salidas', euros(inf.salidas)));
		h.push(fila('Esperado', euros(inf.efectivo_esperado), 'b'), fila('Contado', euros(inf.efectivo_contado), 'b'));
		h.push(fila(veredicto, signo + euros(inf.descuadre), 'tot'));
		if (inf.observacion) { h.push('<div class="sep"></div><div class="sec">OBSERVACIÓN</div><div>' + esc(inf.observacion) + '</div>'); }
		el('caja-papel').innerHTML = h.join('');
	}

	var informeModalEl = el('cajaInformeModal');
	function abrirInforme(url) {
		el('cajaInformeFrame').setAttribute('src', url);
		bootstrap.Modal.getOrCreateInstance(informeModalEl).show();
	}
	// Vaciar el src al cerrar: si no, al reabrir se ve un instante el documento anterior.
	informeModalEl.addEventListener('hidden.bs.modal', function () { el('cajaInformeFrame').setAttribute('src', ''); });

	el('caja-imprimir-ticket').addEventListener('click', function () { if (cierreActual) { abrirInforme(cierreActual.informe_url_ticket); } });
	el('caja-ver-a4').addEventListener('click', function () { if (cierreActual) { abrirInforme(cierreActual.informe_url_a4); } });
	el('caja-volver-inicio').addEventListener('click', function () { cierreActual = null; entrarCerrada(); });

	// ── Arranque ───────────────────────────────────────────────────────────────

	if (sesion) {
		pintarSesion();
		pintarEnVivo();
	} else {
		pintarUltimoCierre();
	}
})();
