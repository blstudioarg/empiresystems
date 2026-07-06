(function ($) {
	'use strict';

	$(function () {
		var $modal = $('#horarioModal');
		var $form = $('#horario-form');

		if (!$modal.length || !$form.length) {
			return;
		}

		var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
		var storeUrl = $form.attr('action');
		var tramoTemplateEl = document.getElementById('horario-tramo-pill-template');

		function clearErrors() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
			$form.find('[data-error-for-dia]').text('');
		}

		/**
		 * Mapea el índice `tramos.N` del error del backend al día que corresponde,
		 * reconstruyendo el mismo orden con el que `recolectarTramos()` arma el payload.
		 */
		function diaDelIndiceTramo(indice) {
			var dias = [];

			$form.find('[data-dia-tramos]').each(function () {
				var dia = $(this).data('dia-tramos');
				$(this).find('.horario-tramo-pill').each(function () {
					dias.push(dia);
				});
			});

			return dias[indice];
		}

		function showErrors(errors) {
			clearErrors();

			$.each(errors, function (field, messages) {
				if (field === 'nombre') {
					$form.find('[name="nombre"]').addClass('is-invalid');
					$form.find('[data-error-for="nombre"]').text(messages[0]);
					return;
				}

				var match = field.match(/^tramos\.(\d+)\./);
				if (match) {
					var dia = diaDelIndiceTramo(Number(match[1]));
					if (dia !== undefined) {
						$form.find('[data-error-for-dia="' + dia + '"]').text(messages[0]);
					}
				}
			});
		}

		function agregarTramo(dia, horaInicio, horaFin) {
			var fragment = tramoTemplateEl.content.cloneNode(true);
			var $pill = $(fragment).find('.horario-tramo-pill');

			$pill.find('.tramo-hora-inicio').val(horaInicio || '09:00');
			$pill.find('.tramo-hora-fin').val(horaFin || '17:00');

			$form.find('[data-dia-tramos="' + dia + '"]').append($pill);
		}

		function limpiarTramos() {
			$form.find('.horario-dia-tramos').empty();
		}

		function resetForm() {
			clearErrors();
			$form[0].reset();
			$form.find('#horario_method').val('POST');
			$form.attr('action', storeUrl);
			$('#horarioModalLabel').text('Nuevo horario');
			limpiarTramos();
		}

		function fillForm(data) {
			clearErrors();
			$form.find('#horario_method').val('PUT');
			$form.attr('action', data.update_url);
			$('#horarioModalLabel').text('Editar horario');

			$form.find('#horario_nombre').val(data.nombre);
			$form.find('#horario_activo').prop('checked', !!data.activo);

			limpiarTramos();
			(data.tramos || []).forEach(function (tramo) {
				agregarTramo(tramo.dia_semana, tramo.hora_inicio, tramo.hora_fin);
			});
		}

		$(document).on('click', '.btn-add-horario', function () {
			resetForm();
		});

		$(document).on('click', '.btn-edit-horario', function () {
			var data = $(this).data('horario');
			fillForm(data);
			modal.show();
		});

		$form.on('click', '.btn-add-tramo', function () {
			agregarTramo($(this).data('dia'));
		});

		$form.on('click', '.btn-remove-tramo', function () {
			$(this).closest('.horario-tramo-pill').remove();
		});

		/**
		 * Copia los tramos del día de origen a los otros 6 días (sobreescribe lo que tuvieran),
		 * para no tener que recargar la misma franja horaria día por día.
		 */
		$form.on('click', '.btn-copiar-semana', function () {
			var diaOrigen = $(this).data('dia');
			var tramosOrigen = [];

			$form.find('[data-dia-tramos="' + diaOrigen + '"] .horario-tramo-pill').each(function () {
				tramosOrigen.push({
					horaInicio: $(this).find('.tramo-hora-inicio').val(),
					horaFin: $(this).find('.tramo-hora-fin').val(),
				});
			});

			if (!tramosOrigen.length) {
				window.showToast('warning', 'Agregá al menos un tramo en este día antes de copiarlo a la semana.');
				return;
			}

			for (var dia = 1; dia <= 7; dia++) {
				if (dia === diaOrigen) {
					continue;
				}

				var $destino = $form.find('[data-dia-tramos="' + dia + '"]');
				$destino.empty();

				tramosOrigen.forEach(function (tramo) {
					agregarTramo(dia, tramo.horaInicio, tramo.horaFin);
				});

				// Destaca brevemente los tramos recién copiados (feedback de que la copia ocurrió).
				var $pillsCopiadas = $destino.find('.horario-tramo-pill').addClass('horario-tramo-pill-copiado');
				setTimeout(function () {
					$pillsCopiadas.removeClass('horario-tramo-pill-copiado');
				}, 500);
			}

			window.showToast('success', 'Tramos copiados a los demás días de la semana.');
		});

		function recolectarTramos() {
			var tramos = [];

			$form.find('[data-dia-tramos]').each(function () {
				var dia = $(this).data('dia-tramos');

				$(this).find('.horario-tramo-pill').each(function () {
					tramos.push({
						dia_semana: dia,
						hora_inicio: $(this).find('.tramo-hora-inicio').val(),
						hora_fin: $(this).find('.tramo-hora-fin').val(),
					});
				});
			});

			return tramos;
		}

		$form.on('submit', function (event) {
			event.preventDefault();

			var $submitBtn = $form.find('button[type="submit"]');
			var payload = {
				_token: $form.find('input[name="_token"]').val(),
				_method: $form.find('#horario_method').val(),
				nombre: $form.find('#horario_nombre').val(),
				activo: $form.find('#horario_activo').is(':checked') ? 1 : 0,
				tramos: recolectarTramos(),
			};

			window.withButtonLoading($submitBtn, function () {
				return $.ajax({
					url: $form.attr('action'),
					method: 'POST',
					data: payload,
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (response) {
					modal.hide();
					clearErrors();
					window.showToast('success', response.message || 'Operación realizada correctamente.');

					if (window.horariosTable) {
						window.horariosTable.ajax.reload(null, false);
					}
				})
				.fail(function (xhr) {
					if (xhr.status === 422) {
						showErrors((xhr.responseJSON && xhr.responseJSON.errors) || {});
					} else {
						window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'Ocurrió un error inesperado.');
					}
				});
		});
	});
})(jQuery);
