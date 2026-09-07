/**
 * Panel lateral del asistente IA (feature 030). Se abre desde el botón de la topbar
 * (id `asistente-toggle`, en partials/header.blade.php). Consume el stream SSE del endpoint
 * POST /asistente/mensaje con fetch + ReadableStream (no EventSource, porque es POST con CSRF)
 * y renderiza el progreso.
 */
(function () {
	'use strict';

	const root = document.getElementById('asistente-chat');
	if (!root) return;

	const toggle = document.getElementById('asistente-toggle');
	const panel = document.getElementById('asistente-panel');
	const cerrar = document.getElementById('asistente-cerrar');
	const backdrop = document.getElementById('asistente-backdrop');
	const nueva = document.getElementById('asistente-nueva');
	const form = document.getElementById('asistente-form');
	const input = document.getElementById('asistente-input');
	const mensajes = document.getElementById('asistente-mensajes');
	const botonEnviar = document.getElementById('asistente-enviar');

	const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
	const urlMensaje = root.dataset.urlMensaje;
	const urlReiniciar = root.dataset.urlReiniciar;
	const urlAccionBase = root.dataset.urlConfirmar; // .../asistente/accion

	let enviando = false;

	// --- Abrir / cerrar el panel (el estado vive en la raíz, así el backdrop lo comparte) ---
	function estaAbierto() { return root.classList.contains('is-open'); }
	function abrir() {
		root.classList.add('is-open');
		if (input) setTimeout(() => input.focus(), 120); // tras la transición de entrada
	}
	function cerrarPanel() { root.classList.remove('is-open'); }

	if (toggle) toggle.addEventListener('click', () => (estaAbierto() ? cerrarPanel() : abrir()));
	if (cerrar) cerrar.addEventListener('click', cerrarPanel);
	if (backdrop) backdrop.addEventListener('click', cerrarPanel);
	// Cerrar con Escape cuando el panel está abierto.
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && estaAbierto()) cerrarPanel();
	});

	if (!form) return; // modo "activar" (sin clave): nada más que hacer.

	function nuevoMensajeEl(clase, texto) {
		const bienvenida = mensajes.querySelector('.asistente-chat__bienvenida');
		if (bienvenida) bienvenida.remove();
		const el = document.createElement('div');
		el.className = clase;
		if (texto !== undefined) el.textContent = texto;
		mensajes.appendChild(el);
		mensajes.scrollTop = mensajes.scrollHeight;
		return el;
	}

	function scrollAbajo() { mensajes.scrollTop = mensajes.scrollHeight; }

	// --- Indicador de progreso ------------------------------------------------
	// Un único elemento efímero que va contando en qué anda el asistente ("Enviando…",
	// "Pensando…", "Consultando clientes…"). Sin esto el usuario manda el mensaje y no ve
	// absolutamente nada hasta que llega el primer fragmento de texto, que con tool use puede
	// tardar varios segundos. Nunca queda en el historial: se borra al terminar el turno.
	let estadoEl = null;

	function mostrarEstado(texto) {
		if (!estadoEl) {
			const bienvenida = mensajes.querySelector('.asistente-chat__bienvenida');
			if (bienvenida) bienvenida.remove();
			estadoEl = document.createElement('div');
			estadoEl.className = 'asistente-chat__estado';
			estadoEl.setAttribute('role', 'status');
			const puntos = document.createElement('span');
			puntos.className = 'asistente-chat__puntos';
			puntos.setAttribute('aria-hidden', 'true');
			puntos.innerHTML = '<span></span><span></span><span></span>';
			const etiqueta = document.createElement('span');
			etiqueta.className = 'asistente-chat__estado-texto';
			estadoEl.appendChild(puntos);
			estadoEl.appendChild(etiqueta);
		}
		estadoEl.querySelector('.asistente-chat__estado-texto').textContent = texto;
		// Reubicar al final: los mensajes nuevos se agregan por debajo.
		mensajes.appendChild(estadoEl);
		scrollAbajo();
	}

	function ocultarEstado() {
		if (estadoEl) estadoEl.remove();
		estadoEl = null;
	}

	nueva.addEventListener('click', function () {
		fetch(urlReiniciar, { method: 'POST', headers: cabeceras() }).finally(function () {
			estadoEl = null; // el innerHTML de abajo lo saca del DOM
			mensajes.innerHTML = '<div class="asistente-chat__bienvenida">Conversación nueva. ¿En qué te ayudo?</div>';
		});
	});

	function cabeceras() {
		return {
			'X-CSRF-TOKEN': csrf,
			'X-Requested-With': 'XMLHttpRequest',
			'Content-Type': 'application/json',
			'Accept': 'text/event-stream',
		};
	}

	// Auto-resize del textarea.
	input.addEventListener('input', function () {
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 120) + 'px';
	});
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (enviando) return;
		const texto = input.value.trim();
		if (!texto) return;

		nuevoMensajeEl('asistente-msg asistente-msg--user', texto);
		input.value = '';
		input.style.height = 'auto';
		enviarMensaje(texto);
	});

	function enviarMensaje(texto) {
		enviando = true;
		if (botonEnviar) botonEnviar.disabled = true;
		mostrarEstado('Enviando…');
		let botEl = null;
		let acumulado = '';

		fetch(urlMensaje, {
			method: 'POST',
			headers: cabeceras(),
			body: JSON.stringify({ mensaje: texto }),
		})
			.then(function (resp) {
				if (resp.status === 409) {
					nuevoMensajeEl('asistente-msg asistente-msg--bot', 'El asistente no está configurado. Avisá a un administrador.');
					throw new Error('no_configurado');
				}
				if (!resp.ok || !resp.body) throw new Error('http');

				mostrarEstado('Pensando…');

				const reader = resp.body.getReader();
				const decoder = new TextDecoder();
				let buffer = '';

				function procesarEvento(bloque) {
					const lineas = bloque.split('\n');
					let evento = 'message';
					let data = '';
					lineas.forEach(function (l) {
						if (l.indexOf('event:') === 0) evento = l.slice(6).trim();
						else if (l.indexOf('data:') === 0) data += l.slice(5).trim();
					});
					if (!data) return;
					let payload;
					try { payload = JSON.parse(data); } catch (_) { return; }
					manejarEvento(evento, payload);
				}

				function manejarEvento(evento, payload) {
					if (evento === 'texto') {
						ocultarEstado();
						if (!botEl) botEl = nuevoMensajeEl('asistente-msg asistente-msg--bot', '');
						acumulado += payload.delta || '';
						botEl.textContent = acumulado;
						scrollAbajo();
					} else if (evento === 'actividad') {
						nuevoMensajeEl('asistente-msg--actividad', 'Consultando ' + payload.tool.replace(/_/g, ' ') + '…');
						mostrarEstado('Pensando…');
						botEl = null; acumulado = '';
					} else if (evento === 'accion_pendiente') {
						ocultarEstado();
						botEl = null; acumulado = '';
						renderAccionPendiente(payload);
					} else if (evento === 'error') {
						ocultarEstado();
						const msg = payload.mensaje + (payload.detalle ? ' (' + payload.detalle + ')' : '');
						nuevoMensajeEl('asistente-msg asistente-msg--bot', msg);
						botEl = null; acumulado = '';
					}
				}

				function leer() {
					return reader.read().then(function (res) {
						if (res.done) { procesarBuffer(true); return; }
						buffer += decoder.decode(res.value, { stream: true });
						procesarBuffer(false);
						return leer();
					});
				}

				function procesarBuffer(fin) {
					let idx;
					while ((idx = buffer.indexOf('\n\n')) !== -1) {
						const bloque = buffer.slice(0, idx);
						buffer = buffer.slice(idx + 2);
						if (bloque.trim()) procesarEvento(bloque);
					}
					if (fin && buffer.trim()) procesarEvento(buffer);
				}

				return leer();
			})
			.catch(function (err) {
				if (err.message !== 'no_configurado' && err.message !== 'http') {
					nuevoMensajeEl('asistente-msg asistente-msg--bot', 'Hubo un problema al procesar tu mensaje.');
				} else if (err.message === 'http') {
					nuevoMensajeEl('asistente-msg asistente-msg--bot', 'Hubo un problema al procesar tu mensaje.');
				}
			})
			.finally(function () {
				ocultarEstado();
				enviando = false;
				if (botonEnviar) botonEnviar.disabled = false;
			});
	}

	function renderAccionPendiente(accion) {
		const cont = nuevoMensajeEl('asistente-accion');
		const resumen = document.createElement('div');
		resumen.className = 'asistente-accion__resumen';
		resumen.textContent = accion.resumen;
		const botones = document.createElement('div');
		botones.className = 'asistente-accion__botones';

		const confirmar = document.createElement('button');
		confirmar.className = 'btn btn-primary btn-sm';
		confirmar.textContent = 'Confirmar';
		const cancelar = document.createElement('button');
		cancelar.className = 'btn btn-outline-secondary btn-sm';
		cancelar.textContent = 'Cancelar';

		function bloquear() { confirmar.disabled = true; cancelar.disabled = true; }

		confirmar.addEventListener('click', function () {
			bloquear();
			fetch(urlAccionBase + '/' + accion.id + '/confirmar', { method: 'POST', headers: cabeceras() })
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
				.then(function (res) {
					if (res.ok && res.d.ok) {
						resumen.textContent = '✓ ' + (res.d.mensaje || 'Hecho.');
						botones.innerHTML = '';
						if (res.d.url) {
							const a = document.createElement('a');
							a.href = res.d.url; a.className = 'btn btn-link btn-sm p-0'; a.textContent = 'Ver';
							botones.appendChild(a);
						}
						if (window.showToast) window.showToast('success', res.d.mensaje || 'Acción realizada.');
					} else {
						resumen.textContent = '✗ ' + (res.d.mensaje || 'No se pudo completar.');
						if (window.showToast) window.showToast('error', res.d.mensaje || 'No se pudo completar la acción.');
					}
				})
				.catch(function () { if (window.showToast) window.showToast('error', 'No se pudo completar la acción.'); });
		});

		cancelar.addEventListener('click', function () {
			bloquear();
			fetch(urlAccionBase + '/' + accion.id + '/cancelar', { method: 'POST', headers: cabeceras() });
			resumen.textContent = 'Acción cancelada.';
			botones.innerHTML = '';
		});

		botones.appendChild(confirmar);
		botones.appendChild(cancelar);
		cont.appendChild(resumen);
		cont.appendChild(botones);
		scrollAbajo();
	}
})();
