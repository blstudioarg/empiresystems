/*
 * Componente categoria-select (lógica separada).
 *
 * Inicializa cualquier <select class="categoria-select"> como un Select2 con
 * botones inline para agregar/editar/eliminar categorías del catálogo (tabla
 * `categorias_articulo`). Soporta múltiples instancias en la misma página
 * compartiendo un único modal (#categoriaModal) y manteniendo todos los selects
 * sincronizados con el catálogo.
 *
 * A diferencia de unidad-select, el VALOR de cada <option> es el id de la
 * categoría (FK articulos.categoria_id), no el nombre.
 *
 * Config global esperada (la inyecta el componente Blade):
 *   window.categoriaSelectConfig = { indexUrl, storeUrl, updateUrlTemplate, destroyUrlTemplate, csrf }
 *
 * API pública:
 *   window.CategoriaSelect.get(idOrEl) -> instancia { setValue(id), clear() }
 */
(function ($) {
	'use strict';

	var config = window.categoriaSelectConfig || {};
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

	function CategoriaInstance($select) {
		this.$select = $select;
		this.$control = $select.closest('.categoria-control');
		this.idToNombre = {};
		this.editingId = null;
	}

	CategoriaInstance.prototype.init = function () {
		var self = this;
		var $parentModal = this.$select.closest('.modal');

		this.$select.select2({
			placeholder: 'Selecciona una categoría',
			allowClear: true,
			width: '100%',
			dropdownParent: $parentModal.length ? $parentModal : $(document.body),
			language: {
				noResults: function () {
					return 'No se encontraron categorías';
				},
				searching: function () {
					return 'Buscando…';
				},
				removeAllItems: function () {
					return 'Quitar categoría';
				},
			},
		});

		this.$control.on('click', '.btn-categoria-add', function () {
			self.openModal('create');
		});
		this.$control.on('click', '.btn-categoria-edit', function () {
			self.openModal('edit');
		});
		this.$control.on('click', '.btn-categoria-delete', function () {
			self.eliminar();
		});

		this.reload();
	};

	CategoriaInstance.prototype.currentId = function () {
		return this.$select.val();
	};

	CategoriaInstance.prototype.reload = function (selectId) {
		var self = this;

		return $.ajax({
			url: config.indexUrl,
			method: 'GET',
			dataType: 'json',
			headers: { Accept: 'application/json' },
		}).then(function (categorias) {
			var previo = selectId !== undefined ? selectId : self.currentId();

			self.idToNombre = {};
			self.$select.empty().append('<option></option>');

			$.each(categorias, function (_, categoria) {
				self.idToNombre[categoria.id] = categoria.nombre;
				self.$select.append(new Option(categoria.nombre, categoria.id));
			});

			self.setValue(previo);
		});
	};

	// Selecciona una categoría por id.
	CategoriaInstance.prototype.setValue = function (id) {
		if (!id) {
			this.$select.val('').trigger('change');
			return;
		}

		this.$select.val(String(id)).trigger('change');
	};

	CategoriaInstance.prototype.clear = function () {
		this.setValue('');
	};

	CategoriaInstance.prototype.openModal = function (mode) {
		clearError();
		active = this;

		if (mode === 'edit') {
			var id = this.currentId();
			if (!id || !this.idToNombre.hasOwnProperty(id)) {
				toast('warning', 'Selecciona una categoría del catálogo para renombrarla.');
				return;
			}
			this.editingId = id;
			$input.val(this.idToNombre[id]);
			$label.text('Editar categoría');
		} else {
			this.editingId = null;
			$input.val('');
			$label.text('Nueva categoría');
		}

		modalObj.show();
	};

	CategoriaInstance.prototype.eliminar = function () {
		var self = this;
		var id = this.currentId();

		if (!id || !this.idToNombre.hasOwnProperty(id)) {
			toast('warning', 'Selecciona una categoría del catálogo para eliminarla.');
			return;
		}

		active = this;
		var nombre = this.idToNombre[id];

		window.confirmDelete('¿Eliminar la categoría "' + nombre + '" del catálogo? Los artículos que la usen quedarán sin categoría.', function () {
			return $.ajax({
				url: buildUrl(config.destroyUrlTemplate, id),
				method: 'POST',
				data: { _method: 'DELETE', _token: config.csrf },
				dataType: 'json',
				headers: { Accept: 'application/json' },
			})
				.done(function (response) {
					reloadAll('').then(function () {
						toast('success', response.message || 'Categoría eliminada.');
					});
				})
				.fail(function () {
					toast('danger', 'No se pudo eliminar la categoría. Inténtalo de nuevo.');
				});
		});
	};

	// Guardado compartido del modal: opera sobre la instancia activa.
	function save() {
		clearError();

		var nombre = $.trim($input.val());

		if (!nombre) {
			showError('Escribe un nombre para la categoría.');
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
				reloadAll(response.categoria ? response.categoria.id : undefined).then(function () {
					toast('success', response.message || 'Categoría guardada.');
				});
			})
			.fail(function (xhr) {
				if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
					var errores = xhr.responseJSON.errors.nombre || ['Datos no válidos.'];
					showError(errores[0]);
				} else {
					toast('danger', 'No se pudo guardar la categoría. Inténtalo de nuevo.');
				}
			});
	}

	$(function () {
		$modal = $('#categoriaModal');
		var $selects = $('.categoria-select');

		if (!$modal.length || !$selects.length) {
			return;
		}

		modalObj = bootstrap.Modal.getOrCreateInstance($modal[0]);
		$form = $('#categoria-form');
		$input = $('#categoria_nombre');
		$error = $form.find('[data-error-for="categoria_nombre"]');
		$label = $('#categoriaModalLabel');

		$form.on('submit', function (event) {
			event.preventDefault();
			save();
		});

		$modal.on('shown.bs.modal', function () {
			$input.trigger('focus');
		});

		// Con modales apilados, al cerrar el de categoría Bootstrap quita modal-open del
		// body; lo restauramos si el modal padre sigue abierto.
		$modal.on('hidden.bs.modal', function () {
			if ($('.modal.show').length) {
				document.body.classList.add('modal-open');
			}
		});

		$selects.each(function () {
			var instance = new CategoriaInstance($(this));
			instances.push(instance);
			instance.init();
		});
	});

	window.CategoriaSelect = {
		get: function (idOrEl) {
			var el = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
			return instances.filter(function (instance) {
				return instance.$select[0] === el;
			})[0] || null;
		},
		instances: instances,
	};
})(jQuery);
