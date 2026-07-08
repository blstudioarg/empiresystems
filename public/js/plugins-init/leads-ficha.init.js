(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	$(function () {
		var $fichaModal = $('#leadFichaModal');
		var $convertirModal = $('#convertirModal');
		var $convertirForm = $('#convertir-form');

		if (!$fichaModal.length) {
			return;
		}

		var fichaModal = bootstrap.Modal.getOrCreateInstance($fichaModal[0]);
		var convertirModal = bootstrap.Modal.getOrCreateInstance($convertirModal[0]);

		function renderOportunidades(oportunidades) {
			var $wrap = $('#ficha-oportunidades-wrap');
			var $lista = $('#ficha-oportunidades-list').empty();

			if (!oportunidades || !oportunidades.length) {
				$wrap.addClass('d-none');
				return;
			}

			oportunidades.forEach(function (oportunidad) {
				$lista.append(
					'<li class="mb-1">' + escapeHtml(oportunidad.titulo) +
						' <span class="badge bg-secondary-light">' + escapeHtml(oportunidad.etapa_label) + '</span>' +
					'</li>'
				);
			});

			$wrap.removeClass('d-none');
		}

		function renderNotas(notas) {
			var $lista = $('#ficha-notas-list').empty();

			if (!notas || !notas.length) {
				$lista.append('<li class="list-group-item px-0 text-muted" id="ficha-notas-vacio">Todavía no hay actividad registrada.</li>');
				return;
			}

			notas.forEach(function (nota) {
				$lista.append(
					'<li class="list-group-item px-0">' +
						'<div class="d-flex justify-content-between">' +
							'<strong>' + escapeHtml(nota.tipo_label) + '</strong>' +
							'<small class="text-muted">' + escapeHtml(nota.autor) + ' · ' + escapeHtml(nota.fecha) + '</small>' +
						'</div>' +
						'<p class="mb-0">' + escapeHtml(nota.contenido) + '</p>' +
					'</li>'
				);
			});
		}

		function pintarFicha(lead) {
			$fichaModal.data('lead', lead);

			$('#ficha-nombre').text(lead.nombre);
			$('#ficha-estado').text(lead.estado_label);
			$('#ficha-empresa').text(lead.empresa || '—');
			$('#ficha-origen').text(lead.origen_label);
			$('#ficha-email').text(lead.email || '—');
			$('#ficha-telefono').text(lead.telefono || '—');
			$('#ficha-asignado').text(lead.asignado_nombre || 'Sin asignar');

			if (lead.motivo_descarte) {
				$('#ficha-motivo').text(lead.motivo_descarte);
				$('#ficha-motivo-wrap').removeClass('d-none');
			} else {
				$('#ficha-motivo-wrap').addClass('d-none');
			}

			renderOportunidades(lead.oportunidades);
			renderNotas(lead.notas);

			$('#ficha-nueva-oportunidad').attr('href', lead.nueva_oportunidad_url);
			$('#ficha-nota-form').attr('data-notas-url', lead.notas_url);
			$convertirForm.attr('action', lead.convertir_url);

			if (lead.convertido) {
				$('#ficha-btn-convertir, #ficha-nueva-oportunidad').addClass('d-none');
				$('#ficha-link-cliente').removeClass('d-none');
			} else {
				$('#ficha-btn-convertir, #ficha-nueva-oportunidad').removeClass('d-none');
				$('#ficha-link-cliente').addClass('d-none');
			}
		}

		$(document).on('click', '.btn-ver-ficha-lead', function () {
			var leadId = $(this).data('id');
			var url = window.leadFormState.indexUrl + '/' + leadId;

			$.ajax({ url: url, headers: { Accept: 'application/json' } })
				.done(function (lead) {
					pintarFicha(lead);
					fichaModal.show();
				})
				.fail(function () {
					window.showToast('danger', 'No se pudo cargar la ficha del lead.');
				});
		});

		$('#ficha-nota-form').on('submit', function (event) {
			event.preventDefault();

			var $form = $(this);
			var notasUrl = $form.attr('data-notas-url');
			var $contenido = $('#ficha-nota-contenido');
			var $submitBtn = $form.find('button[type="submit"]');

			if (!$.trim($contenido.val())) {
				$contenido.addClass('is-invalid').trigger('focus');
				return;
			}
			$contenido.removeClass('is-invalid');

			window.withButtonLoading($submitBtn, function () {
				return $.ajax({
					url: notasUrl,
					method: 'POST',
					dataType: 'json',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
					data: {
						tipo: $('#ficha-nota-tipo').val(),
						contenido: $contenido.val(),
					},
				});
			})
				.done(function (response) {
					$('#ficha-notas-vacio').remove();

					var nota = response.nota;
					$('#ficha-notas-list').prepend(
						'<li class="list-group-item px-0">' +
							'<div class="d-flex justify-content-between">' +
								'<strong>' + escapeHtml(nota.tipo_label) + '</strong>' +
								'<small class="text-muted">' + escapeHtml(nota.autor) + ' · ' + escapeHtml(nota.fecha) + '</small>' +
							'</div>' +
							'<p class="mb-0">' + escapeHtml(nota.contenido) + '</p>' +
						'</li>'
					);

					$contenido.val('');
					window.showToast('success', response.message || 'Nota añadida correctamente.');
				})
				.fail(function () {
					window.showToast('danger', 'No se pudo añadir la nota.');
				});
		});

		// Convertir: form nativo (no AJAX) — navega a clientes.index, un recurso distinto.
		$convertirForm.on('submit', function () {
			window.setButtonLoading($(this).find('button[type="submit"]'), true);
		});
	});
})(jQuery);
