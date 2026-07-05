/*
 * Componente unidad-select (lógica separada).
 *
 * Inicializa cualquier <select class="unidad-select"> como un Select2 con botones
 * inline para agregar/editar/eliminar unidades del catálogo (tabla `unidades`).
 * Soporta múltiples instancias en la misma página compartiendo un único modal
 * (#unidadModal) y manteniendo todos los selects sincronizados con el catálogo.
 *
 * Config global esperada (la inyecta el componente Blade):
 *   window.unidadSelectConfig = { indexUrl, storeUrl, updateUrlTemplate, destroyUrlTemplate, csrf }
 *
 * API pública:
 *   window.UnidadSelect.get(idOrEl) -> instancia { setValue(nombre), clear() }
 */
(function ($) {
	'use strict';

	var config = window.unidadSelectConfig || {};
	var instances = [];
	var active = null;

	// Referencias al modal compartido (se cachean en el arranque).
	var $modal, modalObj, $form, $input, $error, $label;

	function toast(type, message) {
		if (window.showToast) {
			window.showToast(type, message);
		}
	}

	function buildUrl(template, id) {
		return template.replace('__ID__', id);
	}

	function clearError() {
		$input.removeClass('is-invalid');
		$error.text('');
	}

	function showError(message) {
		$input.addClass('is-invalid');
		$error.text(message);
	}

	// Repuebla todos los selects tras un alta/edición/borrado. Solo la instancia
	// activa fuerza una selección concreta; el resto conserva su valor actual.
	function reloadAll(selectNombreActiva) {
		return $.when.apply($, instances.map(function (instance) {
			return instance.reload(instance === active ? selectNombreActiva : undefined);
		}));
	}

	function UnidadInstance($select) {
		this.$select = $select;
		this.$control = $select.closest('.unidad-control');
		this.nombreToId = {};
		this.editingId = null;
	}

	UnidadInstance.prototype.init = function () {
		var self = this;
		var $parentModal = this.$select.closest('.modal');

		this.$select.select2({
			placeholder: 'Selecciona una unidad',
			allowClear: true,
			width: '100%',
			dropdownParent: $parentModal.length ? $parentModal : $(document.body),
			language: {
				noResults: function () {
					return 'No se encontraron unidades';
				},
				searching: function () {
					return 'Buscando…';
				},
				removeAllItems: function () {
					return 'Quitar unidad';
				},
			},
		});

		this.$control.on('click', '.btn-unidad-add', function () {
			self.openModal('create');
		});
		this.$control.on('click', '.btn-unidad-edit', function () {
			self.openModal('edit');
		});
		this.$control.on('click', '.btn-unidad-delete', function () {
			self.eliminar();
		});

		this.reload();
	};

	UnidadInstance.prototype.currentNombre = function () {
		return this.$select.val();
	};

	UnidadInstance.prototype.reload = function (selectNombre) {
		var self = this;

		return $.ajax({
			url: config.indexUrl,
			method: 'GET',
			dataType: 'json',
			headers: { Accept: 'application/json' },
		}).then(function (unidades) {
			var previo = selectNombre !== undefined ? selectNombre : self.currentNombre();

			self.nombreToId = {};
			self.$select.empty().append('<option></option>');

			$.each(unidades, function (_, unidad) {
				self.nombreToId[unidad.nombre] = unidad.id;
				self.$select.append(new Option(unidad.nombre, unidad.nombre));
			});

			self.setValue(previo);
		});
	};

	// Selecciona una unidad por nombre; si no está en el catálogo (dato heredado
	// de texto libre) añade una opción temporal para no perder el valor.
	UnidadInstance.prototype.setValue = function (nombre) {
		if (!nombre) {
			this.$select.val('').trigger('change');
			return;
		}

		if (!this.nombreToId.hasOwnProperty(nombre) && !this.$select.find('option[value="' + nombre.replace(/"/g, '\\"') + '"]').length) {
			this.$select.append(new Option(nombre, nombre, true, true));
		}

		this.$select.val(nombre).trigger('change');
	};

	UnidadInstance.prototype.clear = function () {
		this.setValue('');
	};

	UnidadInstance.prototype.openModal = function (mode) {
		clearError();
		active = this;

		if (mode === 'edit') {
			var nombre = this.currentNombre();
			if (!nombre || !this.nombreToId.hasOwnProperty(nombre)) {
				toast('warning', 'Selecciona una unidad del catálogo para renombrarla.');
				return;
			}
			this.editingId = this.nombreToId[nombre];
			$input.val(nombre);
			$label.text('Editar unidad');
		} else {
			this.editingId = null;
			$input.val('');
			$label.text('Nueva unidad');
		}

		modalObj.show();
	};

	UnidadInstance.prototype.eliminar = function () {
		var self = this;
		var nombre = this.currentNombre();

		if (!nombre || !this.nombreToId.hasOwnProperty(nombre)) {
			toast('warning', 'Selecciona una unidad del catálogo para eliminarla.');
			return;
		}

		active = this;
		var id = this.nombreToId[nombre];

		window.confirmDelete('¿Eliminar la unidad "' + nombre + '" del catálogo? Los artículos que ya la usen conservan su texto.', function () {
			return $.ajax({
				url: buildUrl(config.destroyUrlTemplate, id),
				method: 'POST',
				data: { _method: 'DELETE', _token: config.csrf },
				dataType: 'json',
				headers: { Accept: 'application/json' },
			})
				.done(function (response) {
					reloadAll('').then(function () {
						toast('success', response.message || 'Unidad eliminada.');
					});
				})
				.fail(function () {
					toast('danger', 'No se pudo eliminar la unidad. Inténtalo de nuevo.');
				});
		});
	};

	// Guardado compartido del modal: opera sobre la instancia activa.
	function save() {
		clearError();

		var nombre = $.trim($input.val());

		if (!nombre) {
			showError('Escribe un nombre para la unidad.');
			return;
		}

		var esEdicion = active && active.editingId !== null;
		var url = esEdicion ? buildUrl(config.updateUrlTemplate, active.editingId) : config.storeUrl;
		var data = { nombre: nombre, _token: config.csrf };

		if (esEdicion) {
			data._method = 'PUT';
		}

		window.withButtonLoading($form.find('button[type="submit"]'), function () {
			return $.ajax({
				url: url,
				method: 'POST',
				data: data,
				dataType: 'json',
				headers: { Accept: 'application/json' },
			});
		})
			.done(function (response) {
				modalObj.hide();
				reloadAll(response.unidad ? response.unidad.nombre : nombre).then(function () {
					toast('success', response.message || 'Unidad guardada.');
				});
			})
			.fail(function (xhr) {
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					var errores = xhr.responseJSON.errors.nombre || ['Datos no válidos.'];
					showError(errores[0]);
				} else {
					toast('danger', 'No se pudo guardar la unidad. Inténtalo de nuevo.');
				}
			});
	}

	$(function () {
		$modal = $('#unidadModal');
		var $selects = $('.unidad-select');

		if (!$modal.length || !$selects.length) {
			return;
		}

		modalObj = bootstrap.Modal.getOrCreateInstance($modal[0]);
		$form = $('#unidad-form');
		$input = $('#unidad_nombre');
		$error = $form.find('[data-error-for="unidad_nombre"]');
		$label = $('#unidadModalLabel');

		$form.on('submit', function (event) {
			event.preventDefault();
			save();
		});

		$modal.on('shown.bs.modal', function () {
			$input.trigger('focus');
		});

		// Con modales apilados, al cerrar el de unidad Bootstrap quita modal-open del
		// body; lo restauramos si el modal padre sigue abierto.
		$modal.on('hidden.bs.modal', function () {
			if ($('.modal.show').length) {
				document.body.classList.add('modal-open');
			}
		});

		$selects.each(function () {
			var instance = new UnidadInstance($(this));
			instances.push(instance);
			instance.init();
		});
	});

	window.UnidadSelect = {
		get: function (idOrEl) {
			var el = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
			return instances.filter(function (instance) {
				return instance.$select[0] === el;
			})[0] || null;
		},
		instances: instances,
	};
})(jQuery);
