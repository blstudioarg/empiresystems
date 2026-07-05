(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#articuloModal');
		var $form = $('#articulo-form');
		var state = window.articuloFormState || {};

		if (!$modal.length || !$form.length) {
			return;
		}

		var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);

		function clearErrors() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		function showErrors(errors) {
			clearErrors();

			$.each(errors, function (field, messages) {
				var $field = $form.find('[name="' + field + '"]');
				var $feedback = $form.find('[data-error-for="' + field + '"]');
				$field.addClass('is-invalid');
				$feedback.text(messages[0]);
			});
		}

		function showAlert(type, message) {
			window.showToast(type, message);
		}

		function refreshListado() {
			var $table = $('#articulos-table');

			if ($.fn.DataTable.isDataTable($table)) {
				$table.DataTable().ajax.reload(null, false);
			}
		}

		function toggleCamposPorTipo() {
			var esProducto = $form.find('#tipo').val() === 'producto';
			$form.find('.campos-producto').prop('hidden', !esProducto);

			if (!esProducto) {
				$form.find('#gestion_stock').prop('checked', false);
			}

			toggleCamposStock();
		}

		function toggleCamposStock() {
			var esProducto = $form.find('#tipo').val() === 'producto';
			var gestionaStock = esProducto && $form.find('#gestion_stock').is(':checked');
			$form.find('.campos-stock').prop('hidden', !gestionaStock);
		}

		function suggestSku() {
			var tipo = $form.find('#tipo').val();
			var prefix = tipo === 'producto' ? 'PROD-' : 'SERV-';

			$.ajax({
				url: state.indexUrl,
				method: 'GET',
				dataType: 'json',
				headers: { Accept: 'application/json' },
			})
				.done(function (response) {
					var articulos = response.data || [];
					var matching = articulos.filter(function (a) { return a.sku && a.sku.indexOf(prefix) === 0; });
					var lastNum = matching.length > 0
						? Math.max.apply(null, matching.map(function (a) { return parseInt(a.sku.slice(prefix.length), 10) || 0; }))
						: 0;
					var next = prefix + String(lastNum + 1).padStart(3, '0');
					$form.find('#sku').val(next);
				});
		}

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$form.find('#articulo_method').val('POST');
			$form.attr('action', state.storeUrl);
			$('#articuloModalLabel').text('Agregar artículo');
			var unidad = window.UnidadSelect && window.UnidadSelect.get('unidad');
			if (unidad) {
				unidad.clear();
			}
			toggleCamposPorTipo();
			suggestSku();
		}

		function fillForm(data) {
			clearErrors();
			$form.find('#articulo_method').val('PUT');
			$form.attr('action', data.updateUrl);
			$form.find('#tipo').val(data.tipo);
			$form.find('#sku').val(data.sku);
			$form.find('#nombre').val(data.nombre);
			$form.find('#descripcion').val(data.descripcion);
			var unidad = window.UnidadSelect && window.UnidadSelect.get('unidad');
			if (unidad) {
				unidad.setValue(data.unidad);
			} else {
				$form.find('#unidad').val(data.unidad);
			}
			$form.find('#precio').val(data.precio);
			// El data-attribute llega como "21.00" (cast decimal:2) pero las <option> del
			// select usan valores cortos ("21"): normalizamos a número antes de asignar,
			// si no el .val() no encuentra ninguna opción que matchee.
			var $tipoImpositivo = $form.find('#tipo_impositivo');
			if ($tipoImpositivo.is('select')) {
				$tipoImpositivo.val(parseFloat(data.tipoImpositivo));
			} else {
				$tipoImpositivo.val(data.tipoImpositivo);
			}
			$form.find('#gestion_stock').prop('checked', data.gestionStock === '1');
			$form.find('#stock_actual').val(data.stockActual);
			$form.find('#stock_minimo').val(data.stockMinimo);
			$form.find('#aplica_recargo_equivalencia').prop('checked', data.recargo === '1');
			$('#articuloModalLabel').text('Editar artículo');
			toggleCamposPorTipo();
		}

		$(document).on('click', '.btn-add-articulo', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-articulo', function () {
			var $btn = $(this);

			fillForm({
				updateUrl: $btn.data('update-url'),
				tipo: $btn.data('tipo'),
				sku: $btn.data('sku'),
				nombre: $btn.data('nombre'),
				descripcion: $btn.data('descripcion'),
				unidad: $btn.data('unidad'),
				precio: $btn.data('precio'),
				tipoImpositivo: $btn.data('tipo-impositivo'),
				gestionStock: String($btn.data('gestion-stock')),
				stockActual: $btn.data('stock-actual'),
				stockMinimo: $btn.data('stock-minimo'),
				recargo: String($btn.data('recargo')),
			});
		});

		$form.on('change', '#tipo', function () {
			toggleCamposPorTipo();
			// Al cambiar tipo, sugerir nuevo SKU basado en el nuevo tipo
			if ($form.find('#articulo_method').val() === 'POST') {
				suggestSku();
			}
		});
		$form.on('change', '#gestion_stock', toggleCamposStock);

		$form.on('submit', function (event) {
			event.preventDefault();

			var $submitBtn = $form.find('button[type="submit"]');

			window.withButtonLoading($submitBtn, function () {
				return $.ajax({
					url: $form.attr('action'),
					method: 'POST',
					data: $form.serialize(),
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (response) {
					modal.hide();
					showAlert('success', response.message || 'Operación realizada correctamente.');
					refreshListado();
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						showErrors(xhr.responseJSON.errors || {});
					} else {
						showAlert('danger', 'Ocurrió un error inesperado. Inténtalo de nuevo.');
					}
				});
		});

		$(document).on('click', '.btn-delete-articulo', function () {
			var url = $(this).data('delete-url');

			window.confirmDelete('¿Eliminar este artículo? Esta acción no se puede deshacer desde la pantalla.', function () {
				return $.ajax({
					url: url,
					method: 'POST',
					data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') || $form.find('input[name="_token"]').val() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				})
					.done(function (response) {
						showAlert('success', response.message || 'Artículo eliminado correctamente.');
						refreshListado();
					})
					.fail(function () {
						showAlert('danger', 'No se pudo eliminar el artículo. Inténtalo de nuevo.');
					});
			});
		});

		toggleCamposPorTipo();
	});
})(jQuery);
