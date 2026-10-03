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

	const clip = document.getElementById('asistente-clip');
	const inputFichero = document.getElementById('asistente-fichero');
	const adjunto = document.getElementById('asistente-adjunto');
	const adjuntoNombre = document.getElementById('asistente-adjunto-nombre');
	const adjuntoQuitar = document.getElementById('asistente-adjunto-quitar');
	const sugerencias = document.getElementById('asistente-sugerencias');
	const categoriasEl = document.getElementById('asistente-categorias');
	const listaSugerenciasEl = document.getElementById('asistente-lista-sugerencias');

	const urlMaterialBase = root.dataset.urlMaterialBase;
	const urlMaterial = form ? form.dataset.urlMaterial : null;
	const urlSugerencias = form ? form.dataset.urlSugerencias : null;

	let enviando = false;
	let conversacionActivaId = null;

	// --- Importación conversacional (feature 046) ---------------------------
	// El material vive en el servidor bajo un token; acá solo se guarda el token y el módulo del
	// que va la importación. El fichero nunca viaja dentro del mensaje: se sube aparte (research D5).
	let materialToken = null;
	let moduloImportacion = null;
	let materialPorAnunciar = false;

	// --- Gestos de entrada de material (feature 047) ------------------------
	// Pegar o soltar son gestos que la persona hace ANTES de explicar de qué va la importación,
	// que es el orden inverso al que exige `subirMaterial()` (research D2). Para no descartar el
	// fichero en silencio —el peor fallo posible: no se distingue de "está roto"— se retiene aquí
	// y se pide el módulo; en cuanto se resuelve, sube solo.
	let materialRetenido = null;

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

	// --- Material para importar ---------------------------------------------

	// Módulos que se pueden importar y cómo los nombra la gente al escribir. El clip solo aparece
	// cuando se sabe de qué va la importación: adjuntar ficheros para cualquier otra cosa está
	// fuera de alcance a propósito (research D5).
	const MODULOS = [
		{ modulo: 'clientes', patron: /\bclientes?\b/i },
		{ modulo: 'articulos', patron: /\bart[ií]culos?\b|\bproductos?\b|\bcat[áa]logo\b/i },
		{ modulo: 'proveedores', patron: /\bproveedor(es)?\b/i },
	];

	const INTENCION_IMPORTAR = /\bimport(ar|o|ación|acion)\b|\bsubir\b|\bcargar\b|\badjunt|\bexcel\b|\bcsv\b|\bpdf\b|\bfichero\b|\barchivo\b|\bplanilla\b|\bhoja de c[áa]lculo\b/i;

	/**
	 * Enciende el clip cuando el texto deja claro que se está hablando de importar y de qué. Se
	 * ejecuta sobre lo que escribe la persona: es el único momento en que sabemos el contexto sin
	 * inventarlo.
	 *
	 * Devuelve true si, al resolverse el módulo, se liberó material retenido y con eso ya se envió
	 * un turno: quien llama no debe mandar otro encima.
	 */
	function detectarContextoImportacion(texto) {
		if (!texto) return false;

		const encontrado = MODULOS.find(function (m) { return m.patron.test(texto); });

		if (!encontrado) return false;

		// Con material retenido ya preguntamos "¿de clientes, artículos o proveedores?", así que la
		// respuesta esperada es el nombre del módulo a secas. Exigirle además un verbo de importación
		// dejaba el fichero retenido para siempre ante respuestas naturales como "son cuatro clientes
		// que quiero que me agregues": el turno salía sin la imagen y el asistente pedía los datos a
		// mano, como si nunca hubiera recibido nada.
		if (!materialRetenido && !INTENCION_IMPORTAR.test(texto) && !moduloImportacion) return false;

		moduloImportacion = encontrado.modulo;
		if (clip) clip.hidden = false;

		// Ya sabemos el módulo: lo que llegó antes por un gesto puede subir (contrato G3).
		return liberarMaterialRetenido(texto);
	}

	function mostrarAdjunto(nombre) {
		if (!adjunto) return;
		adjuntoNombre.textContent = nombre;
		adjunto.hidden = false;
	}

	function olvidarMaterial() {
		materialToken = null;
		if (adjunto) adjunto.hidden = true;
	}

	if (clip && inputFichero) {
		clip.addEventListener('click', function () { inputFichero.click(); });

		inputFichero.addEventListener('change', function () {
			const fichero = inputFichero.files && inputFichero.files[0];
			inputFichero.value = ''; // permite volver a elegir el mismo fichero
			if (fichero) subirMaterial(fichero);
		});
	}

	// --- Gestos de entrada: pegar y arrastrar (feature 047) -----------------
	// Los tres gestos (clip, pegar, soltar) convergen en `subirMaterial()`, que es el único punto
	// de subida del cliente: tipo, tamaño, permisos y mensajes de rechazo salen de ahí por
	// construcción, no por disciplina (contrato G5, invariante 1). No crear un segundo camino.

	/**
	 * Avisa cuando un gesto aportó varios ficheros. Se toma el primero, como ya hace el clip, pero
	 * los sobrantes nunca se descartan en silencio (FR-011, contrato G4).
	 */
	function avisarSobrantes(cantidad) {
		if (cantidad <= 1) return;
		if (window.showToast) {
			window.showToast('warning', 'Se adjuntó solo el primer fichero. Añadí el resto de a uno.');
		}
	}

	/**
	 * Una captura del portapapeles llega sin nombre propio (o con un `image.png` genérico), así que
	 * dos seguidas serían indistinguibles en la conversación. Se le pone un sello temporal
	 * (FR-005). La extensión es obligatoria: el servidor valida el tipo por
	 * `getClientOriginalExtension()`, y sin ella el fichero se rechazaría (research D4).
	 */
	function nombrarCaptura(fichero) {
		if (fichero.name && /\.[a-z0-9]{2,5}$/i.test(fichero.name) && !/^image\.[a-z]+$/i.test(fichero.name)) {
			return fichero;
		}

		const tipo = fichero.type || '';
		const ext = tipo.indexOf('/') !== -1 ? tipo.split('/')[1].split('+')[0].toLowerCase() : '';
		if (!ext) return fichero;

		const d = new Date();
		function dd(n) { return String(n).padStart(2, '0'); }
		const sello = String(d.getFullYear()) + dd(d.getMonth() + 1) + dd(d.getDate())
			+ '-' + dd(d.getHours()) + dd(d.getMinutes()) + dd(d.getSeconds());

		try {
			return new File([fichero], 'captura-' + sello + '.' + (ext === 'jpeg' ? 'jpg' : ext), {
				type: fichero.type,
				lastModified: fichero.lastModified,
			});
		} catch (e) {
			return fichero; // navegador sin constructor de File: mejor el nombre feo que nada
		}
	}

	// G1 — Pegar. El listener va sobre el campo de escribir, nunca sobre `document`: un listener
	// global capturaría pegados de cualquier formulario de la aplicación (FR-004, research D3).
	if (input) {
		input.addEventListener('paste', function (e) {
			const datos = e.clipboardData;
			if (!datos) return;

			const ficheros = [];
			const items = datos.items || [];
			for (let i = 0; i < items.length; i++) {
				if (items[i].kind === 'file') {
					const f = items[i].getAsFile();
					if (f) ficheros.push(f);
				}
			}

			// Sin ficheros el evento no se toca: el texto se inserta como siempre (FR-003, SC-004).
			if (!ficheros.length) return;

			// Contenido mixto (imagen + texto, típico al copiar de un documento): se adjunta la
			// imagen y NO se cancela el evento, para que el texto acompañante entre por el camino
			// normal. Nada se pierde en silencio.
			const hayTexto = (datos.getData('text/plain') || '').length > 0;
			if (!hayTexto) e.preventDefault();

			avisarSobrantes(ficheros.length);
			subirMaterial(nombrarCaptura(ficheros[0]));
		});
	}

	// G2 — Arrastrar y soltar sobre el panel.
	if (panel) {
		// `dragleave` también dispara al pasar sobre elementos hijos del panel, lo que haría
		// parpadear la marca. El contador de entradas/salidas la mantiene estable (research D5).
		let arrastres = 0;

		function marcarZona(activa) {
			panel.classList.toggle('is-dropzone', activa);
		}

		function tieneFicheros(e) {
			const t = e.dataTransfer;
			if (!t) return false;
			if (t.types) {
				for (let i = 0; i < t.types.length; i++) {
					if (t.types[i] === 'Files') return true;
				}
				return false;
			}
			return true;
		}

		panel.addEventListener('dragenter', function (e) {
			if (!tieneFicheros(e)) return;
			e.preventDefault();
			arrastres++;
			marcarZona(true);
		});

		// Sin `preventDefault` en `dragover` el navegador abre el fichero y descarta la página,
		// llevándose lo que la persona tuviera escrito sin enviar (FR-008, SC-005).
		panel.addEventListener('dragover', function (e) {
			if (!tieneFicheros(e)) return;
			e.preventDefault();
			if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
		});

		panel.addEventListener('dragleave', function () {
			arrastres = Math.max(0, arrastres - 1);
			if (arrastres === 0) marcarZona(false);
		});

		panel.addEventListener('drop', function (e) {
			e.preventDefault();
			arrastres = 0;
			marcarZona(false);

			const ficheros = (e.dataTransfer && e.dataTransfer.files) || [];
			// Arrastrar texto seleccionado o un enlace no adjunta nada, y la marca ya se apagó.
			if (!ficheros.length) return;

			avisarSobrantes(ficheros.length);
			subirMaterial(ficheros[0]);
		});

		// Un arrastre cancelado (Escape, soltar fuera) no siempre emite `dragleave` sobre el panel.
		document.addEventListener('dragend', function () { arrastres = 0; marcarZona(false); });
		document.addEventListener('drop', function () { arrastres = 0; marcarZona(false); });
	}

	if (adjuntoQuitar) {
		adjuntoQuitar.addEventListener('click', function () {
			// Descartar el adjunto también se lleva lo retenido: no sobrevive a un descarte (G3).
			if (!materialToken) {
				const habiaRetenido = materialRetenido !== null;
				olvidarRetenido();
				olvidarMaterial();
				if (habiaRetenido && window.showToast) window.showToast('info', 'Material descartado.');
				return;
			}

			fetch(urlMaterialBase + '/' + materialToken, { method: 'DELETE', headers: cabeceras() })
				.finally(function () {
					olvidarMaterial();
					if (window.showToast) window.showToast('info', 'Material descartado.');
				});
		});
	}

	/**
	 * Guarda el fichero a la espera de saber a qué módulo va la importación y lo pide en una sola
	 * frase (contrato G3). No se infiere el módulo del contenido: eso gastaría una llamada al
	 * proveedor antes de validar permisos y contradice la decisión de la feature 046.
	 */
	function retenerMaterial(fichero) {
		materialRetenido = fichero;
		mostrarAdjunto(fichero.name);
		nuevoMensajeEl(
			'asistente-msg asistente-msg--bot',
			'Recibí «' + fichero.name + '». ¿Esto es de clientes, artículos o proveedores?'
		);
	}

	function olvidarRetenido() {
		materialRetenido = null;
	}

	/**
	 * El fichero sigue esperando módulo. Se insiste nombrando las opciones, porque quedarse callado
	 * con el indicador de adjunto encendido es indistinguible de estar roto.
	 */
	function repreguntarModulo() {
		nuevoMensajeEl(
			'asistente-msg asistente-msg--bot',
			'Sigo con «' + materialRetenido.name + '» esperando. Decime si es de clientes, artículos '
			+ 'o proveedores, o quitá el adjunto con la ✕ si preferís seguir con otra cosa.'
		);
	}

	/**
	 * Sube el material retenido en cuanto se conoce el módulo. Se llama desde
	 * `detectarContextoImportacion()`, que es el único punto donde `moduloImportacion` se asigna.
	 */
	function liberarMaterialRetenido(textoUsuario) {
		if (!materialRetenido) return false;

		const fichero = materialRetenido;
		materialRetenido = null;
		// La frase con la que la persona resolvió el módulo ES su instrucción ("creame esos cuatro
		// clientes de la tabla..."). Viaja como el turno de la subida: si se descartara a favor del
		// texto genérico de la 046, el modelo nunca la vería.
		subirMaterial(fichero, textoUsuario);

		// Devuelve true para que quien la llamó NO envíe además su propio turno: `subirMaterial()`
		// ya manda uno, y dos turnos a la vez se pisarían (`enviando` es un único estado compartido
		// y el segundo se perdería en silencio).
		return true;
	}

	function subirMaterial(fichero, instruccion) {
		if (!urlMaterial) return;

		// Sin módulo no se puede subir todavía, pero tampoco se descarta: se retiene y se pregunta.
		if (!moduloImportacion) { retenerMaterial(fichero); return; }

		const datos = new FormData();
		datos.append('fichero', fichero);
		datos.append('modulo', moduloImportacion);
		// Con token, el material se acumula en la importación en curso en vez de empezar otra.
		if (materialToken) datos.append('token', materialToken);

		mostrarEstado('Subiendo «' + fichero.name + '»…');

		fetch(urlMaterial, {
			method: 'POST',
			headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
			body: datos,
		})
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); })
			.then(function (res) {
				ocultarEstado();

				if (!res.ok) {
					// Todos los rechazos del contrato traen explicación en `mensaje`: tipo no
					// admitido, más de 5 MB, módulo no importable o token que ya no vale.
					const mensaje = (res.d && res.d.mensaje)
						|| (res.d && res.d.errors && res.d.errors.fichero && res.d.errors.fichero[0])
						|| 'No se pudo subir el material.';
					if (window.showToast) window.showToast('error', mensaje);

					return;
				}

				materialToken = res.d.token;
				materialPorAnunciar = true;
				mostrarAdjunto(res.d.nombre);

				// Subir no analiza: quien analiza es el asistente, para que el progreso se vea en la
				// conversación y no en una barra de carga.
				//
				// Si la subida viene de material retenido, la persona ya escribió su instrucción y
				// esa frase ya se pintó en el hilo: se manda tal cual, sin repetirla en pantalla ni
				// nombrar el fichero. Nombrarlo hacía que el modelo lo tomara por un SEGUNDO adjunto
				// distinto del token, lo buscara y pidiera volver a adjuntarlo.
				if (instruccion) {
					enviarMensaje(instruccion);

					return;
				}

				nuevoMensajeEl('asistente-msg asistente-msg--user', 'Te paso «' + res.d.nombre + '».');
				enviarMensaje('Te paso «' + res.d.nombre + '» para importar ' + res.d.modulo + '.');
			})
			.catch(function () {
				ocultarEstado();
				if (window.showToast) window.showToast('error', 'No se pudo subir el material.');
			});
	}

	// --- Sugerencias del estado vacío (US4) ---------------------------------

	function cargarSugerencias() {
		if (!urlSugerencias || !sugerencias) return;

		fetch(urlSugerencias, { headers: cabeceras() })
			.then(function (r) { return r.json(); })
			.then(function (d) { pintarSugerencias(d.categorias || []); })
			.catch(function () { /* sin sugerencias el panel sigue funcionando igual */ });
	}

	function pintarSugerencias(categorias) {
		if (!categorias.length) return;

		categoriasEl.textContent = '';
		listaSugerenciasEl.textContent = '';

		categorias.forEach(function (categoria, i) {
			const chip = document.createElement('button');
			chip.type = 'button';
			chip.className = 'asistente-chat__categoria' + (i === 0 ? ' is-activa' : '');
			chip.textContent = categoria.etiqueta;
			chip.addEventListener('click', function () {
				Array.prototype.forEach.call(categoriasEl.children, function (c) { c.classList.remove('is-activa'); });
				chip.classList.add('is-activa');
				pintarListaSugerencias(categoria.sugerencias);
			});
			categoriasEl.appendChild(chip);
		});

		pintarListaSugerencias(categorias[0].sugerencias);
		sugerencias.hidden = false;
	}

	function pintarListaSugerencias(textos) {
		listaSugerenciasEl.textContent = '';

		textos.forEach(function (texto) {
			const boton = document.createElement('button');
			boton.type = 'button';
			boton.className = 'asistente-chat__sugerencia';
			boton.textContent = texto;
			// Pulsar una sugerencia la envía como si la hubiera escrito la persona (FR-026), y con
			// eso las sugerencias desaparecen: ya hay conversación.
			boton.addEventListener('click', function () {
				const absorbido = detectarContextoImportacion(texto);
				nuevoMensajeEl('asistente-msg asistente-msg--user', texto);
				if (!absorbido) enviarMensaje(texto);
			});
			listaSugerenciasEl.appendChild(boton);
		});
	}

	function vaciarPanel(texto) {
		// Parar el tecleo antes de vaciar: si no, el temporizador sigue escribiendo sobre un elemento
		// que ya no está en el DOM.
		pararMaquina();
		estadoEl = null; // el innerHTML de abajo lo saca del DOM
		mensajes.innerHTML = '<div class="asistente-chat__bienvenida"></div>';
		mensajes.firstChild.textContent = texto;

		// Cambiar de hilo deja fuera el material en curso, igual que la propuesta pendiente: el
		// servidor tampoco lo acepta desde otra conversación, así que el panel no puede sugerir
		// que sigue ahí.
		olvidarMaterial();
		olvidarRetenido();
		moduloImportacion = null;
		if (clip) clip.hidden = true;

		// El estado vacío vuelve a ofrecer sugerencias (FR-025).
		if (sugerencias) {
			mensajes.firstChild.appendChild(sugerencias);
			cargarSugerencias();
		}
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

		const absorbido = detectarContextoImportacion(texto);
		nuevoMensajeEl('asistente-msg asistente-msg--user', texto);
		input.value = '';
		input.style.height = 'auto';
		if (absorbido) return;

		// Sigue habiendo material retenido y esta frase tampoco nombró un módulo. Repreguntar en vez
		// de mandar el turno: el modelo no sabe que hay un fichero esperando (el aviso de "Recibí…"
		// se pinta solo en el DOM), así que respondería como si nunca hubiera llegado nada.
		if (materialRetenido) { repreguntarModulo(); return; }

		enviarMensaje(texto);
	});

	function enviarMensaje(texto) {
		enviando = true;
		if (botonEnviar) botonEnviar.disabled = true;
		mostrarEstado('Enviando…');
		let botEl = null;
		let acumulado = '';

		const cuerpo = { mensaje: texto };

		// El token solo se anuncia en el turno siguiente a la subida: el modelo ya lo tiene en el
		// contexto de la conversación, y repetirlo en cada mensaje sería ruido.
		if (materialPorAnunciar && materialToken) {
			cuerpo.material_token = materialToken;
			materialPorAnunciar = false;
		}

		fetch(urlMensaje, {
			method: 'POST',
			headers: cabeceras(),
			body: JSON.stringify(cuerpo),
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
			const fila = document.createElement('span');
			fila.className = 'asistente-accion__fila';
			const texto = document.createElement('span');
			texto.textContent = resumenes[0];
			const ojo = document.createElement('button');
			ojo.type = 'button';
			ojo.className = 'asistente-accion__ojo';
			ojo.title = 'Ver todos los campos';
			ojo.setAttribute('aria-label', 'Ver todos los campos');
			ojo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
			ojo.addEventListener('click', function () { abrirDetalle(accion, 0); });
			fila.appendChild(texto);
			fila.appendChild(ojo);
			resumen.appendChild(fila);
		} else {
			// Con varias acciones el usuario tiene que poder revisarlas antes de confirmar: si no,
			// "confirmar 10 cosas" es un cheque en blanco.
			const titulo = document.createElement('strong');
			titulo.textContent = resumenes.length + ' acciones a confirmar:';
			const lista = document.createElement('ul');
			lista.className = 'asistente-accion__lista';
			resumenes.forEach(function (r, i) {
				const li = document.createElement('li');
				const fila = document.createElement('span');
				fila.className = 'asistente-accion__fila';

				const texto = document.createElement('span');
				texto.textContent = r;

				// El resumen de una línea no alcanza para verificar: el ojo abre la tabla completa.
				const ojo = document.createElement('button');
				ojo.type = 'button';
				ojo.className = 'asistente-accion__ojo';
				ojo.title = 'Ver todos los campos';
				ojo.setAttribute('aria-label', 'Ver todos los campos de: ' + r);
				ojo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
				ojo.addEventListener('click', function () { abrirDetalle(accion, i); });

				fila.appendChild(texto);
				fila.appendChild(ojo);
				li.appendChild(fila);
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

	// --- Detalle de una propuesta -------------------------------------------
	// Tabla con TODOS los campos de cada acción: el resumen de una línea esconde lo que no cabe en
	// él, y confirmar diez altas sin poder mirar los campos es firmar en blanco.

	function abrirDetalle(accion, indiceDestacado) {
		const modalEl = document.getElementById('asistente-detalle-modal');
		if (!modalEl || !window.bootstrap) return;

		const detalle = accion.detalle || [];
		if (!detalle.length) return;

		pintarTablaDetalle(detalle, indiceDestacado);
		pintarEstadoAnalisis(accion.analisis);

		window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
	}

	function pintarTablaDetalle(detalle, indiceDestacado) {
		const tabla = document.getElementById('asistente-detalle-tabla');
		const cabecera = tabla.querySelector('thead tr');
		const cuerpo = tabla.querySelector('tbody');

		cabecera.textContent = '';
		cuerpo.textContent = '';

		// Las columnas son la unión de los campos de todas las acciones: si una trae un dato que
		// otra no, tiene que verse igual (y verse vacío donde falta).
		const columnas = [];
		detalle.forEach(function (d) {
			(d.campos || []).forEach(function (c) {
				if (columnas.indexOf(c.etiqueta) === -1) columnas.push(c.etiqueta);
			});
		});

		const thNum = document.createElement('th');
		thNum.textContent = '#';
		thNum.style.width = '1%';
		cabecera.appendChild(thNum);

		columnas.forEach(function (col) {
			const th = document.createElement('th');
			th.textContent = col;
			cabecera.appendChild(th);
		});

		detalle.forEach(function (d, i) {
			const tr = document.createElement('tr');
			if (i === indiceDestacado) tr.className = 'asistente-detalle-destacada';

			const tdNum = document.createElement('td');
			tdNum.textContent = String(i + 1);
			tr.appendChild(tdNum);

			const porEtiqueta = {};
			(d.campos || []).forEach(function (c) { porEtiqueta[c.etiqueta] = c.valor; });

			columnas.forEach(function (col) {
				const td = document.createElement('td');
				const valor = porEtiqueta[col];
				td.textContent = (valor === undefined || valor === '—') ? '—' : valor;
				// Un hueco se marca, no se disimula: que falte el NIF es justo lo que hay que ver.
				if (td.textContent === '—') td.className = 'text-muted';
				tr.appendChild(td);
			});

			cuerpo.appendChild(tr);
		});
	}

	// Estado del análisis cuando la propuesta salió de documentos interpretados. Mientras no exista
	// esa vía, `analisis` no llega y el aviso queda oculto.
	function pintarEstadoAnalisis(analisis) {
		const caja = document.getElementById('asistente-detalle-analisis');
		if (!caja) return;

		if (!analisis) {
			caja.hidden = true;
			caja.textContent = '';
			return;
		}

		caja.textContent = analisis;
		caja.hidden = false;
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
					else cargarSugerencias(); // estado vacío: es cuando las sugerencias sirven (FR-025)
				})
				.catch(function () { /* sin historial disponible: se queda el panel de bienvenida */ });
		});
	}
})();
