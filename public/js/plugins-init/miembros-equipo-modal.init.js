(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#miembroEquipoModal');
		var $form = $('#miembro-equipo-form');

		if (!$modal.length || !$form.length) {
			return;
		}

		var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
		var storeUrl = $form.attr('action');

		function clearErrors() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		function showErrors(errors) {
			clearErrors();

			$.each(errors, function (field, messages) {
				$form.find('[name="' + field + '"]').addClass('is-invalid');
				$form.find('[data-error-for="' + field + '"]').text(messages[0]);
			});
		}

		function pickers() {
			return window.miembroMapaPickers || {};
		}

		/**
		 * Reconstruye el <select> de usuario en cada apertura: solo ofrece usuarios sin miembro
		 * asociado, salvo el que ya está asignado a ESTE miembro cuando se edita (si no,
		 * desaparecería de la lista al abrir su propia edición).
		 */
		function llenarSelectUsuarios(userIdActual) {
			var estado = window.miembrosEquipoState || { usuarios: [], asignados: [] };
			var asignados = estado.asignados || [];

			var $select = $form.find('#miembro_user_id');
			$select.empty().append('<option value="">— Selecciona un usuario —</option>');

			estado.usuarios.forEach(function (usuario) {
				var yaAsignado = asignados.indexOf(usuario.id) !== -1;

				if (yaAsignado && usuario.id !== userIdActual) {
					return;
				}

				$select.append(
					$('<option>', { value: usuario.id, text: usuario.name + ' (' + usuario.email + ')' })
				);
			});

			if (userIdActual) {
				$select.val(userIdActual);
			}
		}

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$form.find('#miembro_method').val('POST');
			$form.attr('action', storeUrl);
			$('#miembroEquipoModalLabel').text('Nuevo miembro');
			$('#miembro_distancia_max_metros').val(100);

			llenarSelectUsuarios(null);

			if (pickers().trabajo) { pickers().trabajo.limpiar(); }
			if (pickers().casa) { pickers().casa.limpiar(); }
			$('#trabajo-coords-info-modal, #casa-coords-info-modal').text('');

			$(document).trigger('miembroEquipoModal:abierto', [null]);
		}

		function fillForm(data) {
			clearErrors();
			$form.find('#miembro_method').val('PUT');
			$form.attr('action', data.updateUrl);
			$('#miembroEquipoModalLabel').text('Editar miembro');

			$form.find('#miembro_puesto').val(data.puesto);
			$form.find('#miembro_trabajo_direccion').val(data.trabajoDireccion);
			$form.find('#miembro_distancia_max_metros').val(data.distanciaMaxMetros);
			$form.find('#miembro_casa_direccion').val(data.casaDireccion);

			llenarSelectUsuarios(data.userId);

			var trabajo = pickers().trabajo;
			if (trabajo) {
				trabajo.limpiar();
				if (data.trabajoLatitud !== null && data.trabajoLongitud !== null) {
					trabajo.fijarPunto(data.trabajoLatitud, data.trabajoLongitud, false);
					$('#miembro_trabajo_latitud').val(data.trabajoLatitud);
					$('#miembro_trabajo_longitud').val(data.trabajoLongitud);
				}
			}

			var casa = pickers().casa;
			if (casa) {
				casa.limpiar();
				if (data.casaLatitud !== null && data.casaLongitud !== null) {
					casa.fijarPunto(data.casaLatitud, data.casaLongitud, false);
					$('#miembro_casa_latitud').val(data.casaLatitud);
					$('#miembro_casa_longitud').val(data.casaLongitud);
				}
			}
		}

		$(document).on('click', '.btn-add-miembro-equipo', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-miembro-equipo', function () {
			var $btn = $(this);

			fillForm({
				updateUrl: $btn.data('update-url'),
				userId: $btn.data('user-id') ? Number($btn.data('user-id')) : null,
				puesto: $btn.data('puesto'),
				trabajoDireccion: $btn.data('trabajo-direccion'),
				trabajoLatitud: $btn.data('trabajo-latitud') !== '' ? Number($btn.data('trabajo-latitud')) : null,
				trabajoLongitud: $btn.data('trabajo-longitud') !== '' ? Number($btn.data('trabajo-longitud')) : null,
				distanciaMaxMetros: $btn.data('distancia-max-metros'),
				casaDireccion: $btn.data('casa-direccion'),
				casaLatitud: $btn.data('casa-latitud') !== '' ? Number($btn.data('casa-latitud')) : null,
				casaLongitud: $btn.data('casa-longitud') !== '' ? Number($btn.data('casa-longitud')) : null,
			});

			modal.show();

			$(document).trigger('miembroEquipoModal:abierto', [$btn.data('id')]);
		});

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
					clearErrors();
					window.showToast('success', response.message || 'Operación realizada correctamente.');

					if (window.miembrosEquipoTable) {
						window.miembrosEquipoTable.ajax.reload(null, false);
					}
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						showErrors(xhr.responseJSON.errors || {});
					} else {
						window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'Ocurrió un error inesperado.');
					}
				});
		});
	});
})(jQuery);
