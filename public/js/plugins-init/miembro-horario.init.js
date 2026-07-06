(function ($) {
	'use strict';

	$(function () {
		var $seccionAsignar = $('#miembro-horario-asignar');
		var $vigenteInfo = $('#miembro-horario-vigente');
		var $historicoWrap = $('#miembro-horario-historico-wrap');
		var $historicoBody = $('#miembro-horario-historico');
		var $selectHorario = $('#miembro_horario_id');

		if (!$seccionAsignar.length) {
			return;
		}

		var horariosCache = null;

		function escapeHtml(value) {
			return $('<div>').text(value === null || value === undefined ? '' : value).html();
		}

		function cargarHorariosActivos() {
			if (horariosCache) {
				return $.Deferred().resolve(horariosCache).promise();
			}

			return $.ajax({
				url: '/horarios',
				headers: { Accept: 'application/json' },
			}).then(function (json) {
				horariosCache = (json.data || []).filter(function (h) { return h.activo; });
				return horariosCache;
			});
		}

		function llenarSelectHorarios() {
			cargarHorariosActivos().done(function (horarios) {
				$selectHorario.empty().append('<option value="">— Selecciona un horario —</option>');
				horarios.forEach(function (h) {
					$selectHorario.append($('<option>', { value: h.id, text: h.nombre }));
				});
			});
		}

		function renderHistorico(asignaciones) {
			$historicoBody.empty();

			if (!asignaciones.length) {
				$historicoWrap.hide();
				return;
			}

			$historicoWrap.show();

			asignaciones.forEach(function (asignacion) {
				var $tr = $('<tr>');
				$tr.append($('<td>').text(asignacion.horario.nombre));
				$tr.append($('<td>').text(asignacion.vigente_desde));
				$tr.append($('<td>').text(asignacion.vigente_hasta || (asignacion.es_vigente ? 'Vigente' : '—')));

				var $acciones = $('<td>');
				if (asignacion.es_vigente) {
					var $btn = $('<button>', { type: 'button', class: 'btn btn-sm btn-outline-danger btn-quitar-asignacion', text: 'Quitar' });
					$btn.data('delete-url', asignacion.delete_url);
					$acciones.append($btn);
				}
				$tr.append($acciones);

				$historicoBody.append($tr);
			});
		}

		function cargarHistorico(miembroId) {
			$.ajax({
				url: '/miembros-equipo/' + miembroId + '/horarios',
				headers: { Accept: 'application/json' },
			}).done(function (json) {
				var asignaciones = json.data || [];
				var vigente = asignaciones.find(function (a) { return a.es_vigente; });

				$vigenteInfo.text(vigente ? ('Horario vigente: ' + vigente.horario.nombre + ' (desde ' + vigente.vigente_desde + ')') : 'Sin horario asignado actualmente.');
				renderHistorico(asignaciones);
			});
		}

		$(document).on('miembroEquipoModal:abierto', function (event, miembroId) {
			if (!miembroId) {
				$seccionAsignar.hide();
				$historicoWrap.hide();
				$vigenteInfo.text('Guardá el miembro para poder asignarle un horario.');
				return;
			}

			$seccionAsignar.data('miembro-id', miembroId).show();
			$('#miembro_horario_vigente_desde').val('');
			$selectHorario.val('');
			llenarSelectHorarios();
			cargarHistorico(miembroId);
		});

		$(document).on('click', '#btn-asignar-horario', function () {
			var miembroId = $seccionAsignar.data('miembro-id');
			var horarioId = $selectHorario.val();
			var vigenteDesde = $('#miembro_horario_vigente_desde').val();
			var $btn = $(this);

			$form_error_clear();

			if (!horarioId || !vigenteDesde) {
				return;
			}

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: '/miembros-equipo/' + miembroId + '/horarios',
					method: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
					data: { horario_id: horarioId, vigente_desde: vigenteDesde },
				});
			})
				.done(function (response) {
					window.showToast('success', response.message || 'Horario asignado correctamente.');
					cargarHistorico(miembroId);
				})
				.fail(function (xhr) {
					$('#miembro-horario-asignar [data-error-for="horario_id"]').text((xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo asignar el horario.');
				});
		});

		function $form_error_clear() {
			$('#miembro-horario-asignar [data-error-for="horario_id"]').text('');
		}

		$(document).on('click', '.btn-quitar-asignacion', function () {
			var deleteUrl = $(this).data('delete-url');
			var miembroId = $seccionAsignar.data('miembro-id');

			window.confirmDelete('¿Quitar esta asignación de horario?', function () {
				return $.ajax({
					url: deleteUrl,
					type: 'DELETE',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
				})
					.done(function (response) {
						window.showToast('success', response.message || 'Asignación eliminada correctamente.');
						cargarHistorico(miembroId);
					})
					.fail(function (xhr) {
						window.showToast('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo eliminar la asignación.');
					});
			}, { confirmLabel: 'Quitar' });
		});
	});
})(jQuery);
