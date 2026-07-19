(function ($) {
	'use strict';

	function csrfToken() {
		var meta = document.querySelector('meta[name="csrf-token"]');

		return meta ? meta.getAttribute('content') : '';
	}

	/**
	 * Inicializa el modal de importación (excel/_importar_modal.blade.php): subir → previsualizar
	 * → confirmar, todo por AJAX, sin navegar nunca a otra página.
	 *
	 * @param {Object} opciones
	 * @param {string} opciones.previsualizarUrl Endpoint POST .../importar/{modulo}/previsualizar.
	 * @param {string} opciones.confirmarUrl Endpoint POST .../importar/{modulo}/confirmar.
	 * @param {string} opciones.tabla Selector de la tabla del listado a recargar tras confirmar.
	 */
	window.initImportacionModal = function (opciones) {
		var $modal = $('#importarModal');

		if (!$modal.length) {
			return;
		}

		var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
		var $form = $('#importar-form');
		var $fichero = $('#importar-fichero');
		var $btnPrevisualizar = $('#importar-btn-previsualizar');
		var $btnConfirmar = $('#importar-btn-confirmar');

		var $pasoSubir = $('#importar-paso-subir');
		var $pasoPreview = $('#importar-paso-previsualizacion');
		var $pasoResultado = $('#importar-paso-resultado');

		var tokenActual = null;

		function irAPaso(paso) {
			$pasoSubir.toggleClass('d-none', paso !== 'subir');
			$pasoPreview.toggleClass('d-none', paso !== 'preview');
			$pasoResultado.toggleClass('d-none', paso !== 'resultado');
			$btnPrevisualizar.toggleClass('d-none', paso !== 'subir');
			$btnConfirmar.toggleClass('d-none', paso !== 'preview');
		}

		function limpiarErrores() {
			$fichero.removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		function resetModal() {
			tokenActual = null;
			$form[0].reset();
			limpiarErrores();
			irAPaso('subir');
		}

		function renderPrevisualizacion(datos) {
			tokenActual = datos.token;

			$pasoPreview.find('[data-campo="total_filas"]').text(datos.total_filas);
			$pasoPreview.find('[data-campo="validas"]').text(datos.validas);
			$pasoPreview.find('[data-campo="rechazadas-count"]').text(datos.rechazadas.length);

			var $seccionRechazadas = $pasoPreview.find('[data-seccion="rechazadas"]');
			var $listaRechazadas = $pasoPreview.find('[data-lista="rechazadas"]').empty();

			if (datos.rechazadas.length > 0) {
				$.each(datos.rechazadas, function (_, rechazo) {
					$listaRechazadas.append($('<li>', { class: 'list-group-item px-0' }).text('Fila ' + rechazo.fila + ': ' + rechazo.motivo));
				});
				$seccionRechazadas.removeClass('d-none');
			} else {
				$seccionRechazadas.addClass('d-none');
			}

			var $seccionMuestra = $pasoPreview.find('[data-seccion="muestra"]');
			var $tabla = $pasoPreview.find('[data-tabla="muestra"]');
			var $cabecera = $tabla.find('thead tr').empty();
			var $cuerpo = $tabla.find('tbody').empty();

			if (datos.muestra.length > 0) {
				var claves = Object.keys(datos.muestra[0]);

				$.each(claves, function (_, clave) {
					$cabecera.append($('<th>').text(clave));
				});

				$.each(datos.muestra, function (_, fila) {
					var $tr = $('<tr>');

					$.each(claves, function (_, clave) {
						var valor = fila[clave];

						if (typeof valor === 'boolean') {
							valor = valor ? 'Sí' : 'No';
						}

						$tr.append($('<td>').text(valor === null || valor === undefined ? '' : valor));
					});

					$cuerpo.append($tr);
				});

				$seccionMuestra.removeClass('d-none');
			} else {
				$seccionMuestra.addClass('d-none');
			}

			$btnConfirmar.prop('disabled', datos.validas === 0);

			irAPaso('preview');
		}

		function renderResultado(datos) {
			$pasoResultado.find('[data-campo="importados"]').text(datos.importados);

			var $seccion = $pasoResultado.find('[data-seccion="resultado-rechazadas"]');
			var $sinRechazos = $pasoResultado.find('[data-seccion="resultado-sin-rechazos"]');
			var $lista = $pasoResultado.find('[data-lista="rechazadas-final"]').empty();

			if (datos.rechazadas.length > 0) {
				$pasoResultado.find('[data-campo="rechazadas-final-count"]').text(datos.rechazadas.length);

				$.each(datos.rechazadas, function (_, rechazo) {
					$lista.append($('<li>', { class: 'list-group-item px-0' }).text('Fila ' + rechazo.fila + ': ' + rechazo.motivo));
				});

				$pasoResultado.find('[data-link="rechazos-descarga"]').attr('href', datos.rechazos_url);
				$seccion.removeClass('d-none');
				$sinRechazos.addClass('d-none');
			} else {
				$seccion.addClass('d-none');
				$sinRechazos.removeClass('d-none');
			}

			irAPaso('resultado');
		}

		function refreshListado() {
			var $tabla = $(opciones.tabla);

			if ($.fn.DataTable.isDataTable($tabla)) {
				$tabla.DataTable().ajax.reload(null, false);
			}
		}

		$modal.on('show.bs.modal', function () {
			resetModal();
		});

		$btnPrevisualizar.on('click', function () {
			limpiarErrores();

			if (!$fichero[0].files.length) {
				$fichero.addClass('is-invalid');
				$form.find('[data-error-for="fichero"]').text('Selecciona un fichero.');

				return;
			}

			var formData = new FormData();
			formData.append('fichero', $fichero[0].files[0]);

			window
				.withButtonLoading($btnPrevisualizar, function () {
					return $.ajax({
						url: opciones.previsualizarUrl,
						method: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						dataType: 'json',
						headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
					});
				})
				.done(function (respuesta) {
					renderPrevisualizacion(respuesta);
				})
				.fail(function (xhr) {
					var mensaje = 'No se pudo analizar el fichero. Inténtalo de nuevo.';

					if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.fichero) {
						mensaje = xhr.responseJSON.errors.fichero[0];
					} else if (xhr.responseJSON && xhr.responseJSON.message) {
						mensaje = xhr.responseJSON.message;
					}

					$fichero.addClass('is-invalid');
					$form.find('[data-error-for="fichero"]').text(mensaje);
				});
		});

		$btnConfirmar.on('click', function () {
			if (!tokenActual) {
				return;
			}

			window
				.withButtonLoading($btnConfirmar, function () {
					return $.ajax({
						url: opciones.confirmarUrl,
						method: 'POST',
						data: { token: tokenActual },
						dataType: 'json',
						headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
					});
				})
				.done(function (respuesta) {
					renderResultado(respuesta);
					window.showToast('success', respuesta.importados + ' registros importados correctamente.');
					refreshListado();
				})
				.fail(function (xhr) {
					var mensaje = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo confirmar la importación.';

					window.showToast('danger', mensaje);

					if (xhr.status === 422) {
						resetModal();
					}
				});
		});
	};
})(jQuery);
