(function ($) {
	'use strict';

	var state = window.campanaFormState || {};
	var TAMANO = state.tamanoTanda || 8;

	function csrf() {
		return $('meta[name="csrf-token"]').attr('content');
	}

	function enviarTandaUrl(id) {
		return (state.enviarTandaUrlTemplate || '').replace('__ID__', id);
	}

	// Trocea los ids en tandas y las envía secuencialmente. `onTanda(campana)` se llama
	// tras cada respuesta con el agregado de la campaña; devuelve una promesa jQuery.
	function enviarTandas(ids, onTanda) {
		var tandas = [];
		for (var i = 0; i < ids.length; i += TAMANO) {
			tandas.push(ids.slice(i, i + TAMANO));
		}

		var dfd = $.Deferred();

		function siguiente(indice) {
			if (indice >= tandas.length) {
				dfd.resolve();
				return;
			}

			$.ajax({
				url: enviarTandaUrl(state.campanaId),
				method: 'POST',
				data: { _token: csrf(), destinatario_ids: tandas[indice] },
				dataType: 'json',
				headers: { Accept: 'application/json' },
			})
				.done(function (response) {
					if (typeof onTanda === 'function' && response && response.campana) {
						onTanda(response.campana);
					}
					siguiente(indice + 1);
				})
				.fail(function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo enviar una de las tandas.';
					window.showToast('error', msg);
					dfd.reject(xhr);
				});
		}

		siguiente(0);

		return dfd.promise();
	}

	function actualizarProgreso(campana) {
		var procesados = campana.enviados + campana.fallidos;
		var pct = campana.total > 0 ? Math.round((procesados / campana.total) * 100) : 100;

		$('#progreso-enviados').text(campana.enviados);
		$('#progreso-fallidos').text(campana.fallidos);
		$('#progreso-procesados').text(procesados);
		$('#progreso-total').text(campana.total);
		$('#campanaEnvioModal .progress-bar').css('width', pct + '%').attr('aria-valuenow', pct);
	}

	// ── Página de creación ────────────────────────────────────────────────
	function initCreate() {
		var $form = $('#campana-form');
		if (!$form.length) {
			return;
		}

		var $plantilla = $('#plantilla_email_id');
		var $asunto = $('#asunto');
		var $cuerpo = $('#cuerpo');

		$plantilla.on('change', function () {
			var $opt = $plantilla.find('option:selected');
			var asunto = $opt.data('asunto');
			var cuerpo = $opt.data('cuerpo');
			if (asunto !== undefined && asunto !== '') {
				$asunto.val(asunto);
			}
			if (cuerpo !== undefined && cuerpo !== '') {
				$cuerpo.val(cuerpo);
			}
		});

		var $checks = function () { return $('.destinatario-check'); };

		function actualizarContador() {
			$('#destinatarios-contador').text($('.destinatario-check:checked').length);
		}

		$('#destinatarios-todos').on('change', function () {
			var marcado = $(this).is(':checked');
			$('.destinatario-check').each(function () {
				if ($(this).closest('tr').is(':visible')) {
					$(this).prop('checked', marcado);
				}
			});
			actualizarContador();
		});

		$(document).on('change', '.destinatario-check', actualizarContador);

		$('#destinatarios-buscar').on('input', function () {
			var q = $(this).val().toLowerCase();
			$('#destinatarios-tabla tbody tr').each(function () {
				var $tr = $(this);
				var nombre = String($tr.data('nombre') || '');
				var email = String($tr.data('email') || '');
				$tr.toggle(nombre.indexOf(q) !== -1 || email.indexOf(q) !== -1);
			});
		});

		function clearErrors() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		function showErrors(errors) {
			clearErrors();
			$.each(errors, function (field, messages) {
				var base = field.split('.')[0];
				$form.find('[data-error-for="' + base + '"]').text(messages[0]);
			});
		}

		$form.on('submit', function (event) {
			event.preventDefault();
			clearErrors();

			var clienteIds = $('.destinatario-check:checked').map(function () {
				return $(this).val();
			}).get();

			if (!clienteIds.length) {
				$form.find('[data-error-for="cliente_ids"]').text('Selecciona al menos un cliente destinatario.');
				return;
			}

			var $btn = $('#campana-enviar-btn');

			var $envioModalEl = $('#campanaEnvioModal');
			var envioModal = $envioModalEl.length
				? bootstrap.Modal.getOrCreateInstance($envioModalEl[0])
				: null;

			function finalizarEnvio(titulo) {
				$('#progreso-titulo').text(titulo);
				$('#progreso-spinner').hide();
				$('#campanaEnvioModal .progress-bar').removeClass('progress-bar-animated');
				$('#progreso-ver-detalle, #progreso-volver').show();
			}

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: state.storeUrl,
					method: 'POST',
					data: {
						_token: csrf(),
						asunto: $asunto.val(),
						cuerpo: $cuerpo.val(),
						plantilla_email_id: $plantilla.val() || '',
						cliente_ids: clienteIds,
					},
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (response) {
					state.campanaId = response.campana_id;

					// Reiniciar estado del modal y abrirlo.
					$('#progreso-titulo').text('Enviando campaña…');
					$('#progreso-spinner').show();
					$('#campanaEnvioModal .progress-bar').addClass('progress-bar-animated');
					$('#progreso-ver-detalle, #progreso-volver').hide();
					$('#progreso-ver-detalle').attr('href', response.show_url);
					actualizarProgreso(response.campana);
					if (envioModal) {
						envioModal.show();
					}

					// Deshabilitar edición mientras se envía.
					$btn.prop('disabled', true);

					var ids = response.destinatario_ids || [];

					if (!ids.length) {
						finalizarEnvio('Campaña creada');
						window.showToast('info', 'No hay destinatarios con email para enviar.');
						return;
					}

					enviarTandas(ids, actualizarProgreso)
						.done(function () {
							finalizarEnvio('Campaña enviada');
							window.showToast('success', 'Campaña enviada.');
						})
						.fail(function () {
							finalizarEnvio('Envío incompleto');
						});
				})
				.fail(function (xhr) {
					if (xhr.status === 422 && xhr.responseJSON) {
						if (xhr.responseJSON.errors) {
							showErrors(xhr.responseJSON.errors);
						} else if (xhr.responseJSON.message) {
							window.showToast('error', xhr.responseJSON.message);
						}
					} else {
						window.showToast('error', 'No se pudo crear la campaña. Inténtalo de nuevo.');
					}
				});
		});
	}

	// ── Página de detalle: reintentar fallidos ───────────────────────────
	function initShow() {
		var $btn = $('#campana-reintentar-btn');
		if (!$btn.length) {
			return;
		}

		state.campanaId = $btn.data('campana-id');

		$btn.on('click', function () {
			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: $btn.data('reintentar-url'),
					method: 'POST',
					data: { _token: csrf() },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (response) {
					var ids = response.destinatario_ids || [];
					if (!ids.length) {
						window.showToast('info', 'No hay destinatarios fallidos con email para reintentar.');
						return;
					}

					$btn.prop('disabled', true);

					enviarTandas(ids)
						.done(function () {
							window.showToast('success', 'Reintento completado.');
							window.location.reload();
						})
						.fail(function () {
							window.location.reload();
						});
				})
				.fail(function () {
					window.showToast('error', 'No se pudo iniciar el reintento.');
				});
		});
	}

	$(function () {
		initCreate();
		initShow();
	});
})(jQuery);
