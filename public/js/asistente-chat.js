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
	const historial = document.getElementById('asistente-historial');
	const historialLista = document.getElementById('asistente-historial-lista');
	const historialAbrir = document.getElementById('asistente-historial-abrir');
	const historialCerrar = document.getElementById('asistente-historial-cerrar');

	const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
	const urlMensaje = root.dataset.urlMensaje;
	const urlConversaciones = root.dataset.urlConversaciones;
	const urlAccionBase = root.dataset.urlConfirmar; // .../asistente/accion

	let enviando = false;
	let conversacionActivaId = null;

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
	let maquina = null;

	// Frases que rotan mientras se espera. Tono sobrio a propósito: quien usa el asistente muchas
	// veces al día se cansa antes de las simpáticas, y ninguna promete nada que no sepamos ("casi
	// listo" sería mentira: no sabemos cuánto falta).
	const FRASES_ESPERA = [
		'Pensando…',
		'Leyendo tu mensaje…',
		'Ordenando ideas…',
		'Preparando la respuesta…',
	];

	const TECLEO_MS = 45;      // escribir
	const BORRADO_MS = 25;     // borrar más rápido que escribir se siente natural
	const PAUSA_LEIDA_MS = 1200; // tiempo que la frase queda completa antes de borrarse

	const sinMovimiento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

			// Lo que se teclea queda oculto a lectores de pantalla; la etiqueta estable de al lado es
			// la que se anuncia, y solo cambia cuando cambia el estado de verdad.
			const etiqueta = document.createElement('span');
			etiqueta.className = 'asistente-chat__estado-texto';
			etiqueta.setAttribute('aria-hidden', 'true');

			const paraLectores = document.createElement('span');
			paraLectores.className = 'asistente-chat__estado-sr';

			estadoEl.appendChild(puntos);
			estadoEl.appendChild(etiqueta);
			estadoEl.appendChild(paraLectores);
		}

		estadoEl.querySelector('.asistente-chat__estado-sr').textContent = texto;

		// El estado concreto ("Consultando clientes…") encabeza la rotación, y detrás van las frases
		// genéricas.
		const frases = [texto].concat(FRASES_ESPERA.filter(function (f) { return f !== texto; }));
		arrancarMaquina(estadoEl.querySelector('.asistente-chat__estado-texto'), frases);

		mensajes.appendChild(estadoEl);
		scrollAbajo();
	}

	// Escribe una frase letra a letra, la deja leerse, la borra y pasa a la siguiente.
	function arrancarMaquina(el, frases) {
		pararMaquina();

		if (sinMovimiento) {
			el.textContent = frases[0];
			return;
		}

		const estado = { i: 0, pos: 0, borrando: false, timer: null };
		maquina = estado;

		function paso() {
			const frase = frases[estado.i];

			if (!estado.borrando) {
				estado.pos++;
				el.textContent = frase.slice(0, estado.pos);

				if (estado.pos >= frase.length) {
					estado.borrando = true;
					estado.timer = setTimeout(paso, PAUSA_LEIDA_MS);

					return;
				}

				estado.timer = setTimeout(paso, TECLEO_MS);

				return;
			}

			estado.pos--;
			el.textContent = frase.slice(0, estado.pos);

			if (estado.pos <= 0) {
				estado.borrando = false;
				estado.i = (estado.i + 1) % frases.length;
			}

			estado.timer = setTimeout(paso, BORRADO_MS);
		}

		paso();
	}

	function pararMaquina() {
		if (maquina && maquina.timer) clearTimeout(maquina.timer);
		maquina = null;
	}

	function ocultarEstado() {
		pararMaquina();
		if (estadoEl) estadoEl.remove();
		estadoEl = null;
	}

	nueva.addEventListener('click', function () {
		// Ya no descarta el hilo: lo deja guardado en el historial y abre uno vacío (FR-007).
		fetch(urlConversaciones, { method: 'POST', headers: cabeceras() }).finally(function () {
			vaciarPanel('Conversación nueva. ¿En qué te ayudo?');
		});
	});

	function vaciarPanel(texto) {
		// Parar el tecleo antes de vaciar: si no, el temporizador sigue escribiendo sobre un elemento
		// que ya no está en el DOM.
		pararMaquina();
		estadoEl = null; // el innerHTML de abajo lo saca del DOM
		mensajes.innerHTML = '<div class="asistente-chat__bienvenida"></div>';
		mensajes.firstChild.textContent = texto;
	}

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
				if (resp.status === 404) {
					// El hilo se borró desde otra pestaña: no se escribe en una conversación que ya no existe.
					vaciarPanel('Esa conversación ya no existe. Empezá una nueva.');
					if (window.showToast) window.showToast('warning', 'La conversación fue eliminada.');
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
					} else if (evento === 'compactando') {
						// La conversación cruzó el umbral: se está resumiendo la parte vieja (FR-011).
						mostrarEstado('Compactando la conversación…');
					} else if (evento === 'conversacion') {
						// Nació un hilo con este mensaje: el historial ya tiene algo que listar.
						conversacionActivaId = payload.id;
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
		const resumenes = accion.resumenes || [accion.resumen];

		const resumen = document.createElement('div');
		resumen.className = 'asistente-accion__resumen';

		if (resumenes.length === 1) {
			resumen.textContent = resumenes[0];
		} else {
			// Con varias acciones el usuario tiene que poder revisarlas antes de confirmar: si no,
			// "confirmar 10 cosas" es un cheque en blanco.
			const titulo = document.createElement('strong');
			titulo.textContent = resumenes.length + ' acciones a confirmar:';
			const lista = document.createElement('ul');
			lista.className = 'asistente-accion__lista';
			resumenes.forEach(function (r) {
				const li = document.createElement('li');
				li.textContent = r;
				lista.appendChild(li);
			});
			resumen.appendChild(titulo);
			resumen.appendChild(lista);
		}

		const botones = document.createElement('div');
		botones.className = 'asistente-accion__botones';

		const confirmar = document.createElement('button');
		confirmar.className = 'btn btn-primary btn-sm';
		confirmar.textContent = resumenes.length === 1 ? 'Confirmar' : 'Confirmar las ' + resumenes.length;
		const cancelar = document.createElement('button');
		cancelar.className = 'btn btn-outline-secondary btn-sm';
		cancelar.textContent = 'Cancelar';

		function bloquear() { confirmar.disabled = true; cancelar.disabled = true; }

		confirmar.addEventListener('click', function () {
			bloquear();
			fetch(urlAccionBase + '/' + accion.id + '/confirmar', { method: 'POST', headers: cabeceras() })
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
				.then(function (res) {
					pintarResultado(resumen, botones, res);
					if (window.showToast) {
						window.showToast(res.ok && res.d.ok ? 'success' : 'error', res.d.mensaje || 'Acción procesada.');
					}
				})
				.catch(function () { if (window.showToast) window.showToast('error', 'No se pudo completar la acción.'); });
		});

		cancelar.addEventListener('click', function () {
			bloquear();
			fetch(urlAccionBase + '/' + accion.id + '/cancelar', { method: 'POST', headers: cabeceras() });
			resumen.textContent = 'Propuesta cancelada.';
			botones.innerHTML = '';
		});

		botones.appendChild(confirmar);
		botones.appendChild(cancelar);
		cont.appendChild(resumen);
		cont.appendChild(botones);
		scrollAbajo();
	}

	// Tras confirmar, el usuario tiene que ver QUÉ se hizo y qué no: un lote parcialmente aplicado
	// que solo dijera "hecho" sería engañoso.
	function pintarResultado(resumen, botones, res) {
		resumen.textContent = '';
		botones.innerHTML = '';

		const cabecera = document.createElement('div');
		cabecera.textContent = (res.ok && res.d.ok ? '✓ ' : '✗ ') + (res.d.mensaje || '');
		resumen.appendChild(cabecera);

		const rechazadas = res.d.rechazadas || [];
		if (rechazadas.length) {
			const lista = document.createElement('ul');
			lista.className = 'asistente-accion__lista asistente-accion__lista--error';
			rechazadas.forEach(function (r) {
				const li = document.createElement('li');
				li.textContent = r;
				lista.appendChild(li);
			});
			resumen.appendChild(lista);
		}

		if (res.d.url) {
			const a = document.createElement('a');
			a.href = res.d.url;
			a.className = 'btn btn-link btn-sm p-0';
			a.textContent = 'Ver';
			botones.appendChild(a);
		}
	}

	// --- Historial de conversaciones (feature 045) ---------------------------
	// Vista deslizante dentro del propio panel: no cabe una segunda columna en 440px (research D10).

	function abrirHistorial() {
		historial.hidden = false;
		cargarHistorial();
	}

	function cerrarHistorial() { historial.hidden = true; }

	if (historialAbrir) historialAbrir.addEventListener('click', abrirHistorial);
	if (historialCerrar) historialCerrar.addEventListener('click', cerrarHistorial);

	function cargarHistorial() {
		historialLista.textContent = '';
		fetch(urlConversaciones, { headers: cabeceras() })
			.then(function (r) { return r.json(); })
			.then(function (d) { pintarHistorial(d.conversaciones || []); })
			.catch(function () {
				if (window.showToast) window.showToast('error', 'No se pudo cargar el historial.');
			});
	}

	function pintarHistorial(lista) {
		historialLista.textContent = '';

		if (!lista.length) {
			const vacio = document.createElement('div');
			vacio.className = 'asistente-chat__historial-vacio';
			vacio.textContent = 'Todavía no tenés conversaciones guardadas.';
			historialLista.appendChild(vacio);
			return;
		}

		lista.forEach(function (c) { historialLista.appendChild(filaHistorial(c)); });
	}

	function filaHistorial(c) {
		const fila = document.createElement('div');
		fila.className = 'asistente-chat__hilo' + (c.activa ? ' is-activo' : '');

		const datos = document.createElement('button');
		datos.type = 'button';
		datos.className = 'asistente-chat__hilo-datos';
		datos.style.background = 'transparent';
		datos.style.border = 'none';
		datos.style.padding = '0';
		datos.style.textAlign = 'left';
		datos.style.cursor = 'pointer';

		const titulo = document.createElement('span');
		titulo.className = 'asistente-chat__hilo-titulo';
		titulo.textContent = c.titulo;

		const fecha = document.createElement('span');
		fecha.className = 'asistente-chat__hilo-fecha';
		fecha.textContent = formatearFecha(c.ultima_actividad_en);

		datos.appendChild(titulo);
		datos.appendChild(fecha);
		datos.addEventListener('click', function () { abrirConversacion(c.id); });

		const borrar = document.createElement('button');
		borrar.type = 'button';
		borrar.className = 'asistente-chat__hilo-borrar';
		borrar.title = 'Borrar conversación';
		borrar.setAttribute('aria-label', 'Borrar conversación');
		borrar.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
		borrar.addEventListener('click', function (e) {
			e.stopPropagation();
			confirmarBorrado(c);
		});

		fila.appendChild(datos);
		fila.appendChild(borrar);
		return fila;
	}

	function formatearFecha(iso) {
		if (!iso) return '';
		try {
			return new Date(iso).toLocaleString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
		} catch (_) { return ''; }
	}

	function abrirConversacion(id) {
		fetch(urlConversaciones + '/' + id, { headers: cabeceras() })
			.then(function (r) {
				if (!r.ok) throw new Error('no_disponible');
				return r.json();
			})
			.then(function (d) {
				pintarConversacion(d);
				cerrarHistorial();
			})
			.catch(function () {
				if (window.showToast) window.showToast('error', 'Esa conversación ya no está disponible.');
				cargarHistorial();
			});
	}

	function pintarConversacion(d) {
		pararMaquina();
		estadoEl = null;
		mensajes.textContent = '';

		if (d.resumida) {
			const marca = document.createElement('div');
			marca.className = 'asistente-chat__resumido';
			marca.textContent = 'Parte anterior resumida';
			mensajes.appendChild(marca);
		}

		(d.mensajes || []).forEach(function (m) {
			if (m.rol === 'actividad') {
				nuevoMensajeEl('asistente-msg--actividad', 'Consultando ' + String(m.contenido).replace(/_/g, ' ') + '…');
			} else if (m.rol === 'user') {
				nuevoMensajeEl('asistente-msg asistente-msg--user', m.contenido);
			} else {
				nuevoMensajeEl('asistente-msg asistente-msg--bot', m.contenido);
			}
		});

		if (!(d.mensajes || []).length) vaciarPanel('Conversación vacía. ¿En qué te ayudo?');
		scrollAbajo();
	}

	function confirmarBorrado(c) {
		const borrar = function () {
			return fetch(urlConversaciones + '/' + c.id, { method: 'DELETE', headers: cabeceras() })
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok && d.ok, d: d }; }); })
				.then(function (res) {
					if (!res.ok) throw new Error('no_borrado');
					if (window.showToast) window.showToast('success', res.d.mensaje || 'Conversación eliminada.');
					if (c.activa) vaciarPanel('Conversación nueva. ¿En qué te ayudo?');
					cargarHistorial();
				})
				.catch(function () {
					if (window.showToast) window.showToast('error', 'No se pudo borrar la conversación.');
				});
		};

		// confirmDelete es el mecanismo estándar del proyecto y trae su propio estado de carga.
		if (window.confirmDelete) {
			window.confirmDelete({
				title: 'Borrar conversación',
				text: 'Se eliminará «' + c.titulo + '» y sus mensajes. No se puede deshacer.',
				onConfirm: borrar,
			});
		} else {
			borrar();
		}
	}

	// Al abrir el panel por primera vez, recuperar la conversación con actividad más reciente (FR-009).
	let historialCargadoAlAbrir = false;
	if (toggle) {
		toggle.addEventListener('click', function () {
			if (historialCargadoAlAbrir || !estaAbierto()) return;
			historialCargadoAlAbrir = true;
			fetch(urlConversaciones, { headers: cabeceras() })
				.then(function (r) { return r.json(); })
				.then(function (d) {
					const activa = (d.conversaciones || []).find(function (c) { return c.activa; }) || (d.conversaciones || [])[0];
					if (activa) abrirConversacion(activa.id);
				})
				.catch(function () { /* sin historial disponible: se queda el panel de bienvenida */ });
		});
	}
})();
