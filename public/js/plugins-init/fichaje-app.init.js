(function ($) {
	'use strict';

	$(function () {
		var state = window.fichajeState;

		if (!state) {
			return;
		}

		// ---------- Mapa (Leaflet) ----------
		var mapaEl = document.getElementById('fichaje-mapa');
		var map = null;
		var marcadorPosicion = null;

		if (mapaEl && typeof L !== 'undefined') {
			var centroTrabajo = state.tieneUbicacionTrabajo ? [state.trabajoLatitud, state.trabajoLongitud] : null;
			map = L.map('fichaje-mapa').setView(centroTrabajo || [40.4168, -3.7038], centroTrabajo ? 17 : 5);

			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
				maxZoom: 19,
			}).addTo(map);

			if (centroTrabajo) {
				L.marker(centroTrabajo).addTo(map).bindPopup('Centro de trabajo');
				L.circle(centroTrabajo, {
					radius: state.distanciaMaxMetros,
					color: '#1D69D6',
					fillColor: '#1D69D6',
					fillOpacity: 0.1,
				}).addTo(map);
			}
		}

		// ---------- Geolocalización: una sola suscripción, compartida por el mapa y el fichaje ----------
		var posicionActual = null;
		var $precisionInfo = $('#fichaje-precision-info');

		// Umbral de precisión "utilizable": un GPS real al aire libre da ~5-20m; por encima de
		// ~50m suele ser Wi-Fi/IP (habitual en interiores o sin buena señal) y el resultado
		// dentro/fuera del perímetro puede no ser confiable todavía — avisar en vez de dejarlo en
		// el mismo gris neutro de siempre.
		var PRECISION_IMPRECISA_METROS = 50;

		function actualizarPrecisionInfo(precisionMetros) {
			var imprecisa = precisionMetros > PRECISION_IMPRECISA_METROS;

			$precisionInfo
				.toggleClass('fichaje-hint--imprecisa', imprecisa)
				.text(
					imprecisa
						? 'Ubicación aproximada (±' + Math.round(precisionMetros) + ' m): esperá unos segundos o buscá mejor señal antes de fichar.'
						: 'Precisión de tu ubicación: ±' + Math.round(precisionMetros) + ' m'
				);
		}

		if (navigator.geolocation) {
			navigator.geolocation.watchPosition(
				function (position) {
					posicionActual = {
						lat: position.coords.latitude,
						lon: position.coords.longitude,
						precision: position.coords.accuracy,
					};

					if (map) {
						if (!marcadorPosicion) {
							marcadorPosicion = L.marker([posicionActual.lat, posicionActual.lon]).addTo(map).bindPopup('Tu posición');
						} else {
							marcadorPosicion.setLatLng([posicionActual.lat, posicionActual.lon]);
						}
					}

					actualizarPrecisionInfo(posicionActual.precision);
				},
				function () {
					$precisionInfo
						.addClass('fichaje-hint--imprecisa')
						.text('Sin acceso a tu ubicación: el fichaje se registrará como "sin ubicación".');
				},
				{ enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
			);
		}

		// ---------- Reloj en vivo (decorativo: confirma que la pantalla está "viva") ----------
		var $reloj = $('#fichaje-reloj');

		function pad(n) {
			return n < 10 ? '0' + n : '' + n;
		}

		function tickReloj() {
			var ahora = new Date();
			$reloj.text(pad(ahora.getHours()) + ':' + pad(ahora.getMinutes()) + ':' + pad(ahora.getSeconds()));
		}

		if ($reloj.length) {
			tickReloj();
			setInterval(tickReloj, 1000);
		}

		// ---------- Indicador "dentro/fuera de tu horario" (turno de hoy, hora local del navegador) ----------
		var $horarioBadge = $('#fichaje-horario-badge');

		function minutosDesdeMedianoche(horaTexto) {
			var partes = horaTexto.split(':');
			return parseInt(partes[0], 10) * 60 + parseInt(partes[1], 10);
		}

		var tramosHoy = (state.turnoHoy || []).map(function (tramo) {
			return { inicio: minutosDesdeMedianoche(tramo.hora_inicio), fin: minutosDesdeMedianoche(tramo.hora_fin) };
		});

		function tickHorario() {
			var minutosAhora = new Date().getHours() * 60 + new Date().getMinutes();
			var dentro = tramosHoy.some(function (tramo) {
				return minutosAhora >= tramo.inicio && minutosAhora < tramo.fin;
			});

			$horarioBadge
				.toggleClass('fichaje-horario-badge--dentro', dentro)
				.toggleClass('fichaje-horario-badge--fuera', !dentro)
				.text(dentro ? 'Dentro de tu horario' : 'Fuera de tu horario');
		}

		if ($horarioBadge.length) {
			tickHorario();
			setInterval(tickHorario, 1000);
		}

		// ---------- Horas trabajadas hoy (en vivo mientras la jornada está abierta y no en pausa) ----------
		var $horasHoy = $('#fichaje-horas-hoy');
		var resumen = state.resumenHoy || { segundos_base: 0, contando_desde: null };
		var segundosBase = resumen.segundos_base || 0;
		var contandoDesdeMs = resumen.contando_desde ? new Date(resumen.contando_desde).getTime() : null;

		function formatoDuracion(totalSegundos) {
			totalSegundos = Math.max(0, Math.round(totalSegundos));
			var h = Math.floor(totalSegundos / 3600);
			var m = Math.floor((totalSegundos % 3600) / 60);
			var s = totalSegundos % 60;
			return pad(h) + ':' + pad(m) + ':' + pad(s);
		}

		function tickHorasHoy() {
			var segundos = segundosBase;

			if (contandoDesdeMs !== null) {
				segundos += (Date.now() - contandoDesdeMs) / 1000;
			}

			$horasHoy.text(formatoDuracion(segundos));
		}

		if ($horasHoy.length) {
			tickHorasHoy();
			setInterval(tickHorasHoy, 1000);
		}

		// ---------- Timeline: prepende el fichaje recién registrado ----------
		function prependTimeline(tipoLabel, ubicacionLabel, hora) {
			$('#fichaje-timeline-vacio').remove();

			$('#fichaje-timeline').prepend(
				$('<li>').append(
					$('<span class="tipo-ubicacion">').append(
						$('<span class="tipo">').text(tipoLabel),
						$('<span class="ubicacion">').text(ubicacionLabel)
					),
					$('<span class="hora">').text(hora)
				)
			);
		}

		// ---------- Estado de jornada: qué botones y qué badge corresponden a cada estado ----------
		var etiquetasEstado = {
			cerrada: 'Sin jornada abierta',
			abierta: 'Jornada abierta',
			en_pausa: 'En pausa',
		};

		var clasesBadge = {
			cerrada: 'bg-secondary',
			abierta: 'bg-primary',
			en_pausa: 'bg-warning text-dark',
		};

		var botonesPorEstado = {
			cerrada: ['entrada'],
			abierta: ['salida', 'inicio_pausa'],
			en_pausa: ['fin_pausa', 'salida'],
		};

		// ---------- Aplicar la respuesta de un fichaje ya confirmado por el servidor (comparte
		// lógica entre el envío normal y el "Deshacer" de una salida) ----------
		function aplicarRespuestaFichaje(response) {
			// Congela el tramo que estaba corriendo antes de aplicar el nuevo estado, para
			// que el contador de "horas trabajadas hoy" no pierda el tiempo ya transcurrido.
			if (contandoDesdeMs !== null) {
				segundosBase += (Date.now() - contandoDesdeMs) / 1000;
			}
			contandoDesdeMs = response.tipo === 'entrada' || response.tipo === 'fin_pausa' ? Date.now() : null;

			aplicarEstado(response.estado);
			prependTimeline(response.tipo_label, response.resultado_ubicacion_label, response.hora);
			tickHorasHoy();
		}

		function enviarFichaje(tipo) {
			var datos = { tipo: tipo, _token: state.csrf };

			if (posicionActual) {
				datos.latitud = posicionActual.lat;
				datos.longitud = posicionActual.lon;
				datos.precision = Math.round(posicionActual.precision);
			}

			return $.ajax({
				url: state.storeUrl,
				method: 'POST',
				data: datos,
				dataType: 'json',
				headers: { Accept: 'application/json' },
			});
		}

		// ---------- "Deshacer" de una salida: nunca borra el fichaje ya registrado (el ledger es
		// append-only, ver RegistroFichajes), simplemente vuelve a fichar entrada — un evento
		// nuevo y auditable, igual que si el empleado lo hubiera tocado a mano. Ventana corta
		// (botón visible ~6s en el toast) porque es para el toque accidental, no una forma
		// alternativa de cerrar/reabrir la jornada. ----------
		function mostrarToastDeshacerSalida() {
			var $toast = typeof toastr !== 'undefined'
				? toastr.success(
					'Fichaje de salida registrado. <button type="button" class="btn btn-sm btn-light ms-2 fichaje-deshacer-salida">Deshacer</button>',
					null,
					{ timeOut: 6000, tapToDismiss: false }
				)
				: null;

			if (!$toast || !$toast.on) {
				window.showToast('success', 'Fichaje registrado correctamente.');
				return;
			}

			$toast.on('click', '.fichaje-deshacer-salida', function (e) {
				e.stopPropagation();
				var $btn = $(this);

				window.withButtonLoading($btn, function () {
					return enviarFichaje('entrada');
				})
					.done(function (response) {
						window.showToast('success', 'Fichaje de salida deshecho: tu jornada sigue abierta.');
						aplicarRespuestaFichaje(response);
					})
					.fail(function (xhr) {
						var mensaje = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo deshacer el fichaje.';
						window.showToast('danger', mensaje);
					})
					.always(function () {
						toastr.clear($toast);
					});
			});
		}

		var $botones = $('#fichaje-botones');
		var registrarPausas = $botones.data('registrar-pausas') === 1 || $botones.data('registrar-pausas') === '1';
		var $hero = $('.fichaje-hero');
		var $badge = $('#fichaje-estado-badge');

		// Bottom nav (mobile): mismas acciones que el stack de la card, pero como acá los 3 slots
		// son siempre visibles (nunca desaparecen), la máquina de estados se expresa deshabilitando
		// el botón en vez de ocultarlo, y el botón central alterna ícono/label/tipo entre pausa y
		// reanudar en vez de ser dos botones separados.
		var $navEntrada = $('#fichaje-bottom-nav [data-rol="entrada"]');
		var $navPausa = $('#fichaje-bottom-nav [data-rol="pausa"]');
		var $navSalida = $('#fichaje-bottom-nav [data-rol="salida"]');

		function aplicarEstado(estado) {
			$hero.attr('data-estado', estado);
			$badge
				.attr('data-estado', estado)
				.removeClass('bg-secondary bg-primary bg-warning text-dark')
				.addClass(clasesBadge[estado])
				.text(etiquetasEstado[estado]);

			var visibles = botonesPorEstado[estado] || [];

			$botones.find('button[data-tipo]').each(function () {
				var $btn = $(this);
				var tipo = $btn.data('tipo');
				var esPausaOpcional = tipo === 'inicio_pausa' && !registrarPausas;

				$btn.toggleClass('d-none', visibles.indexOf(tipo) === -1 || esPausaOpcional);
			});

			var enPausa = estado === 'en_pausa';

			$navEntrada.prop('disabled', estado !== 'cerrada');
			$navSalida.prop('disabled', estado === 'cerrada');
			$navPausa
				.attr('data-tipo', enPausa ? 'fin_pausa' : 'inicio_pausa')
				.prop('disabled', !registrarPausas || estado === 'cerrada')
				.find('.fichaje-nav-label-pausa')
				.text(enPausa ? 'Reanudar' : 'Pausa');
			$navPausa.find('.fichaje-nav-icono-pausa').toggleClass('d-none', enPausa);
			$navPausa.find('.fichaje-nav-icono-reanudar').toggleClass('d-none', !enPausa);
			$navPausa.find('.fichaje-bottom-nav-fab').toggleClass('fichaje-bottom-nav-fab--en-pausa', enPausa);
		}

		aplicarEstado(state.estado);

		// ---------- Envío del fichaje (delegado en la card Y en el bottom nav) ----------
		$botones.add('#fichaje-bottom-nav').on('click', 'button[data-tipo]:not(:disabled)', function () {
			var $btn = $(this);
			// attr, no .data(): el botón central de pausa cambia su data-tipo en caliente vía
			// .attr() en aplicarEstado(), y jQuery cachea .data() en la primera lectura — con
			// .data() acá, el segundo clic seguiría viendo el tipo del primer clic.
			var tipo = $btn.attr('data-tipo');

			window.withButtonLoading($btn, function () {
				return enviarFichaje(tipo);
			})
				.done(function (response) {
					if (response.tipo === 'salida') {
						// La acción de mayor consecuencia del día (cierra la jornada): en vez del
						// toast normal, uno con "Deshacer" por unos segundos para el toque
						// accidental (bottom nav mobile, "Salida" queda a un dedo del FAB de pausa).
						mostrarToastDeshacerSalida();
					} else {
						window.showToast('success', response.message || 'Fichaje registrado correctamente.');
					}

					aplicarRespuestaFichaje(response);
				})
				.fail(function (xhr) {
					var mensaje = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo registrar el fichaje.';
					window.showToast('danger', mensaje);
				});
		});
	});
})(jQuery);
