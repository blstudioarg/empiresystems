/*
 * Componente canal-captacion-select (clonado de banco-select.js, mismo patrón id-based).
 *
 * Inicializa cualquier <select class="canal-captacion-select"> como un Select2 con botones
 * inline para agregar/editar/eliminar canales de captación del catálogo del tenant (tabla
 * `canales_captacion`). El VALOR del <option> es el id del canal (canal_captacion_id).
 *
 * Config global esperada (la inyecta el componente Blade):
 *   window.canalCaptacionSelectConfig = { indexUrl, storeUrl, updateUrlTemplate, destroyUrlTemplate, csrf }
 *
 * API pública:
 *   window.CanalCaptacionSelect.get(idOrEl) -> instancia { setValue(id), clear(), reload() }
 */
(function ($) {
	'use strict';

	var config = window.canalCaptacionSelectConfig || {};
	var instances = [];
	var active = null;

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

	function reloadAll(selectIdActiva) {
		return $.when.apply($, instances.map(function (instance) {
			return instance.reload(instance === active ? selectIdActiva : undefined);
		}));
	}

	function CanalCaptacionInstance($select) {
		this.$select = $select;
		this.$control = $select.closest('.canal-captacion-control');
		this.idToNombre = {};
		this.editingId = null;
	}

	CanalCaptacionInstance.prototype.init = function () {
		var self = this;
		var $parentModal = this.$select.closest('.modal');

		this.$select.select2({
			placeholder: 'Selecciona un canal…',
			allowClear: true,
			width: '100%',
			dropdownParent: $parentModal.length ? $parentModal : $(document.body),
			language: {
				noResults: function () {
					return 'No se encontraron canales';
				},
				searching: function () {
					return 'Buscando…';
				},
				removeAllItems: function () {
					return 'Quitar canal';
				},
			},
		});

		this.$control.on('click', '.btn-canal-captacion-add', function () {
			self.openModal('create');
		});
		this.$control.on('click', '.btn-canal-captacion-edit', function () {
			self.openModal('edit');
		});
		this.$control.on('click', '.btn-canal-captacion-delete', function () {
			self.eliminar();
		});

		return this.reload();
	};

	CanalCaptacionInstance.prototype.currentId = function () {
		return this.$select.val();
	};

	CanalCaptacionInstance.prototype.reload = function (selectId) {
		var self = this;

		return $.ajax({
			url: config.indexUrl,
			method: 'GET',
			dataType: 'json',
			headers: { Accept: 'application/json' },
		}).then(function (response) {
			var canales = (response && response.data) || [];
			var previo = selectId !== undefined ? selectId : self.currentId();

			self.idToNombre = {};
			self.$select.empty().append('<option></option>');

			$.each(canales, function (_, canal) {
				self.idToNombre[canal.id] = canal.nombre;
				self.$select.append(new Option(canal.nombre, canal.id));
			});

			self.setValue(previo);
		});
	};

	CanalCaptacionInstance.prototype.setValue = function (id) {
		this.$select.val(id ? String(id) : '').trigger('change');
	};

	CanalCaptacionInstance.prototype.clear = function () {
		this.setValue('');
	};

	CanalCaptacionInstance.prototype.openModal = function (mode) {
		clearError();
		active = this;

		if (mode === 'edit') {
			var id = this.currentId();
			if (!id || !this.idToNombre.hasOwnProperty(id)) {
				toast('warning', 'Selecciona un canal del catálogo para renombrarlo.');
				return;
			}
			this.editingId = id;
			$input.val(this.idToNombre[id]);
			$label.text('Editar canal');
		} else {
			this.editingId = null;
			$input.val('');
			$label.text('Nuevo canal');
		}

		modalObj.show();
	};

	CanalCaptacionInstance.prototype.eliminar = function () {
		var self = this;
		var id = this.currentId();

		if (!id || !this.idToNombre.hasOwnProperty(id)) {
			toast('warning', 'Selecciona un canal del catálogo para eliminarlo.');
			return;
		}

		active = this;
		var nombre = this.idToNombre[id];

		window.confirmDelete('¿Eliminar el canal "' + nombre + '" del catálogo? Si tiene leads asociados, se desactivará en su lugar.', function () {
			return $.ajax({
				url: buildUrl(config.destroyUrlTemplate, id),
				method: 'POST',
				data: { _method: 'DELETE', _token: config.csrf },
				dataType: 'json',
				headers: { Accept: 'application/json' },
			})
				.done(function (response) {
					reloadAll('').then(function () {
						toast('success', response.message || 'Canal eliminado.');
					});
				})
				.fail(function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar el canal. Inténtalo de nuevo.';
					toast('danger', msg);
				});
		});
	};

	function save() {
		clearError();

		var nombre = $.trim($input.val());

		if (!nombre) {
			showError('Escribe un nombre para el canal.');
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
				var nuevoId = response.canal ? response.canal.id : (active ? active.editingId : undefined);
				reloadAll(nuevoId).then(function () {
					toast('success', response.message || 'Canal guardado.');
				});
			})
			.fail(function (xhr) {
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					var errores = xhr.responseJSON.errors.nombre || ['Datos no válidos.'];
					showError(errores[0]);
				} else {
					toast('danger', 'No se pudo guardar el canal. Inténtalo de nuevo.');
				}
			});
	}

	$(function () {
		$modal = $('#canalCaptacionModal');
		var $selects = $('.canal-captacion-select');

		if (!$modal.length || !$selects.length) {
			return;
		}

		modalObj = bootstrap.Modal.getOrCreateInstance($modal[0]);
		$form = $('#canal-captacion-form');
		$input = $('#canal_captacion_nombre');
		$error = $form.find('[data-error-for="canal_captacion_nombre"]');
		$label = $('#canalCaptacionModalLabel');

		$form.on('submit', function (event) {
			event.preventDefault();
			save();
		});

		$modal.on('shown.bs.modal', function () {
			$input.trigger('focus');
		});

		$modal.on('hidden.bs.modal', function () {
			if ($('.modal.show').length) {
				document.body.classList.add('modal-open');
			}
		});

		$selects.each(function () {
			var instance = new CanalCaptacionInstance($(this));
			instances.push(instance);
			instance.init();
		});
	});

	window.CanalCaptacionSelect = {
		get: function (idOrEl) {
			var el = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
			return instances.filter(function (instance) {
				return instance.$select[0] === el;
			})[0] || null;
		},
		instances: instances,
	};
})(jQuery);
