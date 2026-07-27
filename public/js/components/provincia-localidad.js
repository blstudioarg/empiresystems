/*
 * Select encadenado Provincia → Localidad.
 *
 * Módulo multi-instancia: inicializa automáticamente cada
 * `<select data-provincia-select data-localidad-target="ID_DEL_SELECT_CIUDAD">`
 * que haya en la página y mantiene sincronizado su select de ciudad.
 *
 * Los valores que se persisten son los NOMBRES (no los ids): así lo guardan
 * `clientes.provincia/ciudad`, `proveedores.*`, `tenants.*` y los campos
 * congelados `facturas.cliente_provincia/cliente_ciudad`.
 *
 * API pública:
 *   window.ProvinciaLocalidad.get('cliente_provincia')   // por id de cualquiera de los dos selects
 *   instancia.setValues('Madrid', 'Alcalá de Henares')   // carga localidades y selecciona
 *   instancia.clear()
 */
(function ($) {
	'use strict';

	var instances = [];

	function textoOpcion(texto) {
		return $('<option>').text(texto).prop('outerHTML');
	}

	function ProvinciaLocalidad($provincia, $ciudad) {
		this.$provincia = $provincia;
		this.$ciudad = $ciudad;
		this.url = $provincia.data('localidades-url');
		this.placeholder = $ciudad.data('placeholder') || 'Selecciona una provincia primero';
	}

	ProvinciaLocalidad.prototype.init = function () {
		var self = this;

		this.$provincia.on('change', function () {
			self.cargarLocalidades(self.provinciaIdActual(), null);
		});

		// Valor inicial (edición / `old()`): la provincia ya viene marcada desde Blade,
		// solo falta poblar las localidades y reseleccionar la ciudad guardada.
		var ciudadInicial = this.$ciudad.data('valor-inicial');

		if (this.provinciaIdActual()) {
			this.cargarLocalidades(this.provinciaIdActual(), ciudadInicial || null);
		} else if (ciudadInicial) {
			// Ciudad heredada sin provincia en el catálogo: no se pierde el dato.
			this.mostrarHeredada(ciudadInicial);
		} else {
			this.resetCiudad();
		}
	};

	ProvinciaLocalidad.prototype.provinciaIdActual = function () {
		return this.$provincia.find('option:selected').data('provincia-id') || null;
	};

	ProvinciaLocalidad.prototype.resetCiudad = function () {
		this.$ciudad.html('<option value="">' + this.placeholder + '</option>');
	};

	ProvinciaLocalidad.prototype.mostrarHeredada = function (ciudad) {
		this.$ciudad
			.html('<option value="">' + this.placeholder + '</option>' + textoOpcion(ciudad))
			.val(ciudad);
	};

	ProvinciaLocalidad.prototype.cargarLocalidades = function (provinciaId, seleccionada) {
		var self = this;

		if (!provinciaId) {
			if (seleccionada) {
				this.mostrarHeredada(seleccionada);
			} else {
				this.resetCiudad();
			}

			return $.Deferred().resolve().promise();
		}

		this.$ciudad.prop('disabled', true).html('<option value="">Cargando...</option>');

		return $.ajax({
			url: this.url,
			method: 'GET',
			data: { provincia_id: provinciaId },
			dataType: 'json',
		})
			.done(function (localidades) {
				var options = '<option value="">Selecciona una localidad</option>';
				var existe = false;

				$.each(localidades, function (_, localidad) {
					options += textoOpcion(localidad.nombre);

					if (seleccionada && localidad.nombre === seleccionada) {
						existe = true;
					}
				});

				// Dato heredado que no está en el catálogo (importaciones, facturas viejas):
				// se añade como opción para no borrarlo silenciosamente al guardar.
				if (seleccionada && !existe) {
					options += textoOpcion(seleccionada);
				}

				self.$ciudad.html(options);

				if (seleccionada) {
					self.$ciudad.val(seleccionada);
				}
			})
			.fail(function () {
				self.$ciudad.html('<option value="">No se pudieron cargar las localidades</option>');
			})
			.always(function () {
				self.$ciudad.prop('disabled', false);
			});
	};

	/**
	 * Selecciona provincia y ciudad por nombre (p. ej. al precargar los datos de un cliente).
	 * Si la provincia no está en el catálogo, se añade como opción para conservar el dato.
	 */
	ProvinciaLocalidad.prototype.setValues = function (provincia, ciudad) {
		provincia = provincia || '';
		ciudad = ciudad || '';

		if (provincia && !this.$provincia.find('option').filter(function () {
			return this.value === provincia;
		}).length) {
			this.$provincia.append(textoOpcion(provincia));
		}

		this.$provincia.val(provincia);

		return this.cargarLocalidades(this.provinciaIdActual(), ciudad || null);
	};

	ProvinciaLocalidad.prototype.clear = function () {
		this.$provincia.val('');
		this.resetCiudad();
	};

	$(function () {
		$('select[data-provincia-select]').each(function () {
			var $provincia = $(this);
			var $ciudad = $('#' + $provincia.data('localidad-target'));

			if (!$ciudad.length) {
				return;
			}

			var instancia = new ProvinciaLocalidad($provincia, $ciudad);
			instances.push(instancia);
			instancia.init();
		});
	});

	window.ProvinciaLocalidad = {
		get: function (idOrEl) {
			var el = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;

			if (!el) {
				return null;
			}

			for (var i = 0; i < instances.length; i++) {
				if (instances[i].$provincia[0] === el || instances[i].$ciudad[0] === el) {
					return instances[i];
				}
			}

			return null;
		},
		instances: instances,
	};
})(jQuery);
