(function ($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	window.oportunidadesCache = {};

	window.cargarPipelineOportunidades = function () {
		if (!window.oportunidadesIndexUrl) {
			return $.Deferred().reject().promise();
		}

		return $.ajax({ url: window.oportunidadesIndexUrl, headers: { Accept: 'application/json' } })
			.done(function (response) {
				var porEtapa = { nueva: [], en_negociacion: [], ganada: [], perdida: [] };

				window.oportunidadesCache = {};

				(response.data || []).forEach(function (oportunidad) {
					window.oportunidadesCache[oportunidad.id] = oportunidad;

					if (porEtapa[oportunidad.etapa]) {
						porEtapa[oportunidad.etapa].push(oportunidad);
					}
				});

				Object.keys(porEtapa).forEach(function (etapa) {
					var $lista = $('[data-etapa-lista="' + etapa + '"]');
					var html = '';

					porEtapa[etapa].forEach(function (oportunidad) {
						html += (
							'<div class="card pipeline-card mb-2" data-id="' + oportunidad.id + '">' +
								'<div class="card-body py-2 px-3 d-flex justify-content-between align-items-start">' +
									'<a class="text-decoration-none text-body flex-grow-1" href="/oportunidades/' + oportunidad.id + '">' +
										'<div class="fw-bold">' + escapeHtml(oportunidad.titulo) + '</div>' +
										'<div class="small text-muted">' + escapeHtml(oportunidad.receptor || '') + '</div>' +
										(oportunidad.importe_estimado ? '<div class="small pipeline-importe">' + Number(oportunidad.importe_estimado).toLocaleString('es-ES', { minimumFractionDigits: 2 }) + ' €</div>' : '') +
									'</a>' +
									(oportunidad.editable
										? '<button type="button" class="btn btn-sm btn-link p-0 ms-2 btn-edit-oportunidad" data-id="' + oportunidad.id + '" title="Editar"><i class="fas fa-pen"></i></button>'
										: '') +
								'</div>' +
							'</div>'
						);
					});

					$lista.html(html || '<p class="text-muted small mb-0">Sin oportunidades.</p>');
				});
			});
	};

	$(function () {
		window.cargarPipelineOportunidades();
	});
})(jQuery);
