/*
 * Componente banco-select (lógica separada).
 *
 * Inicializa cualquier <select class="banco-select"> como un Select2 con botones
 * inline para agregar/editar/eliminar bancos del catálogo del tenant (tabla `bancos`).
 * Soporta múltiples instancias en la misma página compartiendo un único modal
 * (#bancoModal) y manteniendo todos los selects sincronizados con el catálogo.
 *
 * A diferencia de unidad-select, el VALOR del <option> es el id del banco (banco_id),
 * no su nombre, porque las cuentas bancarias guardan la FK banco_id.
 *
 * Config global esperada (la inyecta el componente Blade):
 *   window.bancoSelectConfig = { indexUrl, storeUrl, updateUrlTemplate, destroyUrlTemplate, csrf }
 *
 * API pública:
 *   window.BancoSelect.get(idOrEl) -> instancia { setValue(id), clear(), reload() }
 */
(function ($) {
	'use strict';

	var config = window.bancoSelectConfig || {};
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
	function reloadAll(selectIdActiva) {
		return $.when.apply($, instances.map(function (instance) {
			return instance.reload(instance === active ? selectIdActiva : undefined);
		}));
	}

	function BancoInstance($select) {
		this.$select = $select;
		this.$control = $select.closest('.banco-control');
		this.idToNombre = {};
		this.editingId = null;
	}

	BancoInstance.prototype.init = function () {
		var self = this;
		var $parentModal = this.$select.closest('.modal');

		this.$select.select2({
			placeholder: 'Selecciona un banco…',
			allowClear: true,
			width: '100%',
			dropdownParent: $parentModal.length ? $parentModal : $(document.body),
			language: {
				noResults: function () {
					return 'No se encontraron bancos';
				},
				searching: function () {
					return 'Buscando…';
				},
				removeAllItems: function () {
					return 'Quitar banco';
				},
			},
		});

		this.$control.on('click', '.btn-banco-add', function () {
			self.openModal('create');
		});
		this.$control.on('click', '.btn-banco-edit', function () {
			self.openModal('edit');
		});
		this.$control.on('click', '.btn-banco-delete', function () {
			self.eliminar();
		});

		return this.reload();
	};

	BancoInstance.prototype.currentId = function () {
		return this.$select.val();
	};

	BancoInstance.prototype.reload = function (selectId) {
		var self = this;

		return $.ajax({
			url: config.indexUrl,
			method: 'GET',
			dataType: 'json',
			headers: { Accept: 'application/json' },
		}).then(function (response) {
			var bancos = (response && response.data) || [];
			var previo = selectId !== undefined ? selectId : self.currentId();

			self.idToNombre = {};
			self.$select.empty().append('<option></option>');

			$.each(bancos, function (_, banco) {
				self.idToNombre[banco.id] = banco.nombre;
				self.$select.append(new Option(banco.nombre, banco.id));
			});

			self.setValue(previo);
		});
	};

	BancoInstance.prototype.setValue = function (id) {
		this.$select.val(id ? String(id) : '').trigger('change');
	};

	BancoInstance.prototype.clear = function () {
		this.setValue('');
	};

	BancoInstance.prototype.openModal = function (mode) {
		clearError();
		active = this;

		if (mode === 'edit') {
			var id = this.currentId();
			if (!id || !this.idToNombre.hasOwnProperty(id)) {
				toast('warning', 'Selecciona un banco del catálogo para renombrarlo.');
				return;
			}
			this.editingId = id;
			$input.val(this.idToNombre[id]);
			$label.text('Editar banco');
		} else {
			this.editingId = null;
			$input.val('');
			$label.text('Nuevo banco');
		}

		modalObj.show();
	};

	BancoInstance.prototype.eliminar = function () {
		var self = this;
		var id = this.currentId();

		if (!id || !this.idToNombre.hasOwnProperty(id)) {
			toast('warning', 'Selecciona un banco del catálogo para eliminarlo.');
			return;
		}

		active = this;
		var nombre = this.idToNombre[id];

		window.confirmDelete('¿Eliminar el banco "' + nombre + '" del catálogo?', function () {
			return $.ajax({
				url: buildUrl(config.destroyUrlTemplate, id),
				method: 'POST',
				data: { _method: 'DELETE', _token: config.csrf },
				dataType: 'json',
				headers: { Accept: 'application/json' },
			})
				.done(function (response) {
					reloadAll('').then(function () {
						toast('success', response.message || 'Banco eliminado.');
					});
				})
				.fail(function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar el banco. Inténtalo de nuevo.';
					toast('danger', msg);
				});
		});
	};

	// Guardado compartido del modal: opera sobre la instancia activa.
	function save() {
		clearError();

		var nombre = $.trim($input.val());

		if (!nombre) {
			showError('Escribe un nombre para el banco.');
			return;
		}

		var esEdicion = active && active.editingId != null;
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
				var nuevoId = response.banco ? response.banco.id : (active ? active.editingId : undefined);
				reloadAll(nuevoId).then(function () {
					toast('success', response.message || 'Banco guardado.');
				});
			})
			.fail(function (xhr) {
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					var errores = xhr.responseJSON.errors.nombre || ['Datos no válidos.'];
					showError(errores[0]);
				} else {
					toast('danger', 'No se pudo guardar el banco. Inténtalo de nuevo.');
				}
			});
	}

	$(function () {
		$modal = $('#bancoModal');
		var $selects = $('.banco-select');

		if (!$modal.length || !$selects.length) {
			return;
		}

		modalObj = bootstrap.Modal.getOrCreateInstance($modal[0]);
		$form = $('#banco-form');
		$input = $('#banco_nombre');
		$error = $form.find('[data-error-for="banco_nombre"]');
		$label = $('#bancoModalLabel');

		$form.on('submit', function (event) {
			event.preventDefault();
			save();
		});

		$modal.on('shown.bs.modal', function () {
			$input.trigger('focus');
		});

		// Con modales apilados, al cerrar el de banco Bootstrap quita modal-open del
		// body; lo restauramos si el modal padre sigue abierto.
		$modal.on('hidden.bs.modal', function () {
			if ($('.modal.show').length) {
				document.body.classList.add('modal-open');
			}
		});

		$selects.each(function () {
			var instance = new BancoInstance($(this));
			instances.push(instance);
			instance.init();
		});
	});

	window.BancoSelect = {
		get: function (idOrEl) {
			var el = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
			return instances.filter(function (instance) {
				return instance.$select[0] === el;
			})[0] || null;
		},
		instances: instances,
	};
})(jQuery);
