(function ($) {
	'use strict';

	/**
	 * Picker de mapa reutilizable: el modal de miembro se abre muchas veces (alta y edición de
	 * distintas filas) sobre el MISMO contenedor Leaflet, así que el mapa se crea una única vez
	 * al cargar la página y luego se reposiciona (marcador/círculo) en cada apertura, en vez de
	 * recrear el mapa (Leaflet no permite reinicializar un mismo <div> sin destruirlo antes).
	 */
	function crearPicker(mapId, radioGetter) {
		var mapaEl = document.getElementById(mapId);

		if (!mapaEl || typeof L === 'undefined') {
			return null;
		}

		var map = L.map(mapId).setView([40.4168, -3.7038], 5);

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
			maxZoom: 19,
		}).addTo(map);

		var marcador = null;
		var circulo = null;

		var picker = {
			onChange: null,
		};

		function fijarPunto(lat, lon, disparaOnChange) {
			if (!marcador) {
				marcador = L.marker([lat, lon], { draggable: true }).addTo(map);
				marcador.on('dragend', function () {
					var pos = marcador.getLatLng();
					fijarPunto(pos.lat, pos.lng, true);
				});
			} else {
				marcador.setLatLng([lat, lon]);
			}

			if (radioGetter) {
				if (!circulo) {
					circulo = L.circle([lat, lon], { radius: radioGetter(), color: '#1D69D6', fillOpacity: 0.1 }).addTo(map);
				} else {
					circulo.setLatLng([lat, lon]);
					circulo.setRadius(radioGetter());
				}
			}

			map.panTo([lat, lon]);

			if (disparaOnChange && picker.onChange) {
				picker.onChange(lat, lon);
			}
		}

		map.on('click', function (e) {
			fijarPunto(e.latlng.lat, e.latlng.lng, true);
		});

		/**
		 * Centra/hace zoom sobre un punto ya elegido desde el autocompletado, no solo pan
		 * (una dirección concreta amerita más zoom que el 5 inicial de "toda España").
		 */
		picker.fijarPuntoZoom = function (lat, lon, disparaOnChange) {
			fijarPunto(lat, lon, disparaOnChange);
			map.setView([lat, lon], 17);
		};

		picker.fijarPunto = fijarPunto;

		picker.limpiar = function () {
			if (marcador) {
				map.removeLayer(marcador);
				marcador = null;
			}
			if (circulo) {
				map.removeLayer(circulo);
				circulo = null;
			}
			map.setView([40.4168, -3.7038], 5);
		};

		picker.actualizarRadio = function () {
			if (circulo && radioGetter) {
				circulo.setRadius(radioGetter());
			}
		};

		picker.invalidateSize = function () {
			map.invalidateSize();
		};

		return picker;
	}

	/**
	 * Geocoding con Nominatim (OpenStreetMap): mismo ecosistema que los tiles de Leaflet, gratis y
	 * sin API key (Decisión 8, docs/01-arquitectura.md). Enviamos la dirección tecleada a
	 * `nominatim.openstreetmap.org` — es una llamada a host externo, como los tiles.
	 *
	 * Política de uso de Nominatim: máx. 1 req/s. Por eso el autocompletado va con debounce de
	 * 500 ms, mínimo 4 caracteres, y aborta la petición anterior antes de lanzar la nueva.
	 */
	var NOMINATIM = 'https://nominatim.openstreetmap.org';

	function attachAutocomplete(opts) {
		var $input = $(opts.input);

		if (!$input.length || !opts.picker) {
			return;
		}

		var picker = opts.picker;
		var $lat = $(opts.lat);
		var $lon = $(opts.lon);
		var $info = opts.info ? $(opts.info) : null;

		// Envuelve el input para posicionar el dropdown de sugerencias debajo.
		$input.wrap('<div class="geocoder-wrap"></div>');
		var $sug = $('<ul class="geocoder-suggestions list-group"></ul>').hide();
		$input.after($sug);

		var timer = null;
		var xhr = null;

		function cerrar() {
			$sug.hide().empty();
		}

		function setInfo(texto) {
			if ($info) { $info.text(texto); }
		}

		function elegir(item) {
			var lat = parseFloat(item.lat);
			var lon = parseFloat(item.lon);

			$input.val(item.display_name);
			$lat.val(lat);
			$lon.val(lon);
			picker.fijarPuntoZoom(lat, lon, false);
			setInfo('Lat: ' + lat.toFixed(6) + ', Lon: ' + lon.toFixed(6));
			cerrar();
		}

		function buscar(q) {
			if (xhr) { xhr.abort(); }

			xhr = $.ajax({
				url: NOMINATIM + '/search',
				data: {
					format: 'json',
					addressdetails: 0,
					limit: 6,
					// España (mercado del producto) + Argentina (donde también hay miembros de
					// equipo/tenants probando el sistema). Lista corta a propósito: Nominatim
					// ignora resultados de cualquier país no listado acá, así que sumar un país
					// nuevo es solo agregarlo a esta lista separada por comas.
					countrycodes: 'es,ar',
					'accept-language': 'es',
					q: q,
				},
				dataType: 'json',
			}).done(function (resultados) {
				$sug.empty();

				if (!resultados || !resultados.length) {
					$sug.append('<li class="list-group-item disabled small text-muted">Sin resultados</li>').show();
					return;
				}

				resultados.forEach(function (item) {
					$('<li class="list-group-item list-group-item-action geocoder-item small"></li>')
						.text(item.display_name)
						.on('click', function () { elegir(item); })
						.appendTo($sug);
				});

				$sug.show();
			});
		}

		// El campo llega precargado con la dirección resuelta por reverse-geocoding (o la elegida
		// la vez anterior). Sin esto, un clic para buscar una dirección nueva posiciona el cursor
		// donde se hizo clic y lo tecleado se inserta en el medio del texto viejo en vez de
		// reemplazarlo (rAF, no directo: el propio clic que dispara el foco fija la posición del
		// cursor después del evento focus, así que seleccionar de forma síncrona no sobrevive).
		$input.on('focus', function () {
			var el = this;
			requestAnimationFrame(function () { el.select(); });
		});

		$input.on('input', function () {
			var q = $.trim($input.val());

			clearTimeout(timer);

			if (q.length < 4) {
				cerrar();
				return;
			}

			timer = setTimeout(function () { buscar(q); }, 500);
		});

		$input.on('keydown', function (e) {
			if (e.key === 'Escape') { cerrar(); }
		});

		// Cierra el dropdown al hacer clic fuera del input/sugerencias.
		$(document).on('click', function (e) {
			if (!$(e.target).closest('.geocoder-wrap').length) {
				cerrar();
			}
		});

		opts.cerrar = cerrar;

		return opts;
	}

	/**
	 * Reverse geocoding: al fijar un punto en el mapa (clic o arrastre) rellenamos el texto de la
	 * dirección con lo que Nominatim resuelve para esas coordenadas, para que el usuario no tenga
	 * que escribirla a mano tampoco cuando elige por mapa.
	 */
	function reverse(lat, lon, cb) {
		$.ajax({
			url: NOMINATIM + '/reverse',
			data: {
				format: 'json',
				lat: lat,
				lon: lon,
				'accept-language': 'es',
			},
			dataType: 'json',
		}).done(function (res) {
			if (res && res.display_name) { cb(res.display_name); }
		});
	}

	$(function () {
		if (!document.getElementById('mapa-trabajo-modal')) {
			return;
		}

		var pickerTrabajo = crearPicker('mapa-trabajo-modal', function () {
			return parseFloat($('#miembro_distancia_max_metros').val()) || 0;
		});
		pickerTrabajo.onChange = function (lat, lon) {
			$('#miembro_trabajo_latitud').val(lat);
			$('#miembro_trabajo_longitud').val(lon);
			$('#trabajo-coords-info-modal').text('Lat: ' + lat.toFixed(6) + ', Lon: ' + lon.toFixed(6));
			reverse(lat, lon, function (direccion) { $('#miembro_trabajo_direccion').val(direccion); });
		};

		var pickerCasa = crearPicker('mapa-casa-modal', null);
		pickerCasa.onChange = function (lat, lon) {
			$('#miembro_casa_latitud').val(lat);
			$('#miembro_casa_longitud').val(lon);
			$('#casa-coords-info-modal').text('Lat: ' + lat.toFixed(6) + ', Lon: ' + lon.toFixed(6));
			reverse(lat, lon, function (direccion) { $('#miembro_casa_direccion').val(direccion); });
		};

		window.miembroMapaPickers = { trabajo: pickerTrabajo, casa: pickerCasa };

		var acTrabajo = attachAutocomplete({
			input: '#miembro_trabajo_direccion',
			picker: pickerTrabajo,
			lat: '#miembro_trabajo_latitud',
			lon: '#miembro_trabajo_longitud',
			info: '#trabajo-coords-info-modal',
		});

		var acCasa = attachAutocomplete({
			input: '#miembro_casa_direccion',
			picker: pickerCasa,
			lat: '#miembro_casa_latitud',
			lon: '#miembro_casa_longitud',
			info: '#casa-coords-info-modal',
		});

		// Cierra los dropdowns de sugerencias al cerrar el modal.
		window.miembroGeocoderCerrar = function () {
			if (acTrabajo && acTrabajo.cerrar) { acTrabajo.cerrar(); }
			if (acCasa && acCasa.cerrar) { acCasa.cerrar(); }
		};

		$('#miembro_distancia_max_metros').on('input', function () {
			pickerTrabajo.actualizarRadio();
		});

		$('#miembroEquipoModal').on('shown.bs.modal', function () {
			pickerTrabajo.invalidateSize();
			pickerCasa.invalidateSize();
		});

		$('#miembroEquipoModal').on('hidden.bs.modal', function () {
			if (window.miembroGeocoderCerrar) { window.miembroGeocoderCerrar(); }
		});
	});
})(jQuery);
