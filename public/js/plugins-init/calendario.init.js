(function ($) {
	'use strict';

	var config = window.calendarioConfig || {};
	var calendarEl = document.getElementById('calendar');

	if (!calendarEl) {
		return;
	}

	var $miembroSelect = $('#calendario-miembro');
	var diaModal = new bootstrap.Modal(document.getElementById('calendarioDiaModal'));
	var equipoModal = new bootstrap.Modal(document.getElementById('calendarioEquipoModal'));
	var corregirModal = new bootstrap.Modal(document.getElementById('corregirFichajeModal'));
	var asignarModal = new bootstrap.Modal(document.getElementById('asignarHorarioModal'));

	function miembroSeleccionado() {
		return $miembroSelect.val() || '';
	}

	var calendar = new FullCalendar.Calendar(calendarEl, {
		locale: 'es',
		initialView: 'dayGridMonth',
		height: 'auto',
		firstDay: 1,
		headerToolbar: {
			left: 'prev,next today',
			center: 'title',
			right: 'dayGridMonth,timeGridWeek,timeGridDay',
		},
		// El feed calcula todo en backend; aquí solo se pinta (FR-007).
		events: function (fetchInfo, success, failure) {
			var params = { start: fetchInfo.startStr, end: fetchInfo.endStr };
			if (miembroSeleccionado()) {
				params.miembro_equipo_id = miembroSeleccionado();
			}
			$.getJSON(config.eventosUrl, params)
				.done(success)
				.fail(function (xhr) {
					window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo cargar el calendario.');
					failure(new Error('feed'));
				});
		},
		// Los eventos de fondo (veredicto/previsto) no pintan texto por sí solos: se inyecta la
		// etiqueta accesible dentro del propio elemento del evento (color + texto, nunca solo color).
		eventDidMount: function (info) {
			var props = info.event.extendedProps;

			if (props.tipo === 'veredicto_dia' && info.view.type === 'dayGridMonth') {
				var etiqueta = document.createElement('span');
				etiqueta.className = 'cal-etiqueta';
				etiqueta.textContent = props.veredicto_label + (props.incidencia ? ' · Incidencia' : '');
				info.el.appendChild(etiqueta);
			}

			if (props.tipo === 'previsto' && info.view.type !== 'dayGridMonth') {
				var marca = document.createElement('span');
				marca.className = 'cal-previsto-etiqueta';
				marca.textContent = 'Previsto';
				info.el.appendChild(marca);
			}
		},
		eventContent: function (arg) {
			var props = arg.event.extendedProps;

			if (props.tipo === 'resumen_equipo') {
				var partes = [];
				if (props.ausencias) partes.push('<span class="cal-resumen-badge cal-veredicto-ausencia">' + props.ausencias + ' aus.</span>');
				if (props.retrasos) partes.push('<span class="cal-resumen-badge cal-veredicto-retraso">' + props.retrasos + ' retr.</span>');
				if (props.incidencias) partes.push('<span class="cal-resumen-badge cal-veredicto-incidencia">' + props.incidencias + ' incid.</span>');
				return { html: '<div class="cal-resumen">' + partes.join('') + '</div>' };
			}

			if (props.tipo === 'real') {
				return { html: '<div class="cal-real-contenido">Trabajado</div>' };
			}

			return true;
		},
		dateClick: function (info) {
			if (miembroSeleccionado()) {
				abrirDetalleDia(info.dateStr);
			}
		},
		eventClick: function (info) {
			var props = info.event.extendedProps;
			var fecha = info.event.startStr.substring(0, 10);

			if (props.tipo === 'resumen_equipo') {
				abrirDetalleEquipo(fecha, props);
			} else if (miembroSeleccionado()) {
				abrirDetalleDia(fecha);
			}
		},
		// El panel de métricas se recalcula cada vez que cambia el rango visible (mes/semana/día).
		datesSet: function (info) {
			rangoVisible = { start: info.startStr, end: info.endStr };
			refrescarMetricas();
		},
	});

	calendar.render();

	$miembroSelect.on('change', function () {
		calendar.refetchEvents();
		refrescarMetricas();
	});

	// ---------------------------------------------------------------- cards de métricas (feature 026)

	var rangoVisible = null;

	function refrescarMetricas() {
		var panel = document.getElementById('cal-metricas');
		if (!panel || !rangoVisible) {
			return;
		}

		var params = { start: rangoVisible.start, end: rangoVisible.end };
		if (miembroSeleccionado()) {
			params.miembro_equipo_id = miembroSeleccionado();
		}

		$.getJSON(config.resumenUrl, params)
			.done(pintarMetricas)
			.fail(function (xhr) {
				window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudieron cargar las métricas.');
			});
	}

	function texto(sel, valor) {
		var el = document.querySelector('#cal-metricas [data-kpi="' + sel + '"]');
		if (el) el.textContent = valor;
	}

	function pintarMetricas(data) {
		var k = data.kpis;

		texto('cumplimiento', k.cumplimiento_pct == null ? '—' : k.cumplimiento_pct + '%');
		texto('cumplimiento-detalle', k.dias_laborables > 0
			? k.dias_cumplidos + ' de ' + k.dias_laborables + ' días laborables'
			: 'Sin días laborables');

		texto('horas-trabajadas', formatoHoras(k.horas_trabajadas));
		texto('horas-previstas', formatoHoras(k.horas_previstas));
		var $dif = $('#cal-metricas [data-kpi="horas-diferencia"]');
		if (k.diferencia_horas === 0) {
			$dif.attr('class', 'text-muted').text('Ajustado al horario previsto');
		} else {
			var positivo = k.diferencia_horas > 0;
			$dif.attr('class', positivo ? 'text-info' : 'text-danger')
				.html('<i class="fas fa-arrow-' + (positivo ? 'up' : 'down') + '"></i> '
					+ (positivo ? '+' : '−') + formatoHoras(Math.abs(k.diferencia_horas)) + ' h vs. previsto');
		}

		texto('retrasos', k.dias_retraso);
		texto('retrasos-detalle', k.minutos_retraso > 0 ? k.minutos_retraso + ' min acumulados' : 'Sin minutos de retraso');

		texto('ausencias', k.ausencias);
		texto('ausencias-detalle', (k.incidencias === 1 ? '1 incidencia' : k.incidencias + ' incidencias') + ' de fichaje');
	}

	function formatoHoras(n) {
		return Number(n).toLocaleString('es-ES', { maximumFractionDigits: 1 });
	}

	// ---------------------------------------------------------------- detalle de día (US4)

	function eventoVeredictoDe(fecha) {
		return calendar.getEvents().find(function (evento) {
			return evento.extendedProps.tipo === 'veredicto_dia' && evento.startStr.substring(0, 10) === fecha;
		}) || null;
	}

	function abrirDetalleDia(fecha) {
		var evento = eventoVeredictoDe(fecha);
		var props = evento ? evento.extendedProps : null;
		var fichajes = (props && props.fichajes) || [];

		$('#calendario-dia-titulo').text('Detalle del ' + fecha.split('-').reverse().join('/'));

		var resumen = '';
		if (props) {
			resumen = '<span class="cal-leyenda-item cal-veredicto-' + props.veredicto + '">' + props.veredicto_label + '</span>'
				+ (props.incidencia ? ' <span class="cal-leyenda-item cal-veredicto-incidencia">Incidencia</span>' : '')
				+ '<div class="text-muted mt-2">Previstas: ' + props.horas_previstas + ' h · Trabajadas: ' + props.horas_trabajadas + ' h'
				+ (props.minutos_retraso > 0 ? ' · Retraso: ' + props.minutos_retraso + ' min' : '') + '</div>';
		} else {
			resumen = '<div class="text-muted">Día sin veredicto (hoy o futuro): solo se muestra el horario previsto.</div>';
		}
		$('#calendario-dia-resumen').html(resumen);

		var $tbody = $('#calendario-dia-fichajes').empty();
		if (!fichajes.length) {
			$tbody.append('<tr><td colspan="5" class="text-muted">Sin fichajes este día.</td></tr>');
		}
		fichajes.forEach(function (f) {
			var $tr = $('<tr>').toggleClass('table-warning', f.es_correccion);
			$tr.append($('<td>').text(f.hora));
			$tr.append($('<td>').text(f.tipo_label));
			$tr.append($('<td>').text(f.resultado_ubicacion || '—'));
			$tr.append($('<td>').text(f.es_correccion ? 'Corrige fichaje #' + f.corrige_fichaje_id + ': ' + (f.motivo || '') : '—'));
			var $acciones = $('<td class="text-end">');
			if (f.corregir_url) {
				$acciones.append(
					$('<button type="button" class="btn btn-outline-secondary btn-sm">Corregir</button>').on('click', function () {
						abrirCorreccion(f);
					})
				);
			}
			$tr.append($acciones);
			$tbody.append($tr);
		});

		diaModal.show();
	}

	// ---------------------------------------------------------------- vista de equipo (US3)

	function abrirDetalleEquipo(fecha, props) {
		$('#calendario-equipo-titulo').text('Incumplimientos del ' + fecha.split('-').reverse().join('/'));
		var $lista = $('#calendario-equipo-miembros').empty();

		(props.miembros || []).forEach(function (miembro) {
			var $item = $('<li class="list-group-item d-flex justify-content-between align-items-center px-0">');
			$item.append($('<span>').text(miembro.nombre));
			var $derecha = $('<span class="d-flex align-items-center gap-2">');
			$derecha.append($('<span class="cal-leyenda-item cal-veredicto-' + miembro.veredicto + '">').text(miembro.veredicto_label || miembro.veredicto));
			$derecha.append(
				$('<button type="button" class="btn btn-outline-primary btn-sm">Ver calendario</button>').on('click', function () {
					equipoModal.hide();
					$miembroSelect.val(String(miembro.id)).trigger('change');
				})
			);
			$item.append($derecha);
			$lista.append($item);
		});

		equipoModal.show();
	}

	// ---------------------------------------------------------------- corrección (US4/FR-012)

	function abrirCorreccion(fichaje) {
		var $form = $('#corregirFichajeForm');
		$form.attr('action', fichaje.corregir_url);
		$('#corregir-tipo').val(fichaje.tipo);
		$('#corregir-ocurrido-at').val(fichaje.ocurrido_at);
		$('#corregir-motivo').val('');
		diaModal.hide();
		corregirModal.show();
	}

	$('#corregirFichajeForm').on('submit', function (e) {
		e.preventDefault();
		var $form = $(this);

		window.withButtonLoading($form.find('button[type="submit"]'), function () {
			return $.ajax({
				url: $form.attr('action'),
				method: 'POST',
				data: $form.serialize(),
				headers: { Accept: 'application/json' },
			});
		})
			.done(function (response) {
				corregirModal.hide();
				window.showToast('success', (response && response.message) || 'Corrección registrada correctamente.');
				calendar.refetchEvents();
			})
			.fail(function (xhr) {
				window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo registrar la corrección.');
			});
	});

	// ---------------------------------------------------------------- asignación de horario (US4/FR-013)

	$('#calendario-asignar-horario-btn').on('click', function () {
		if (!miembroSeleccionado()) {
			return;
		}
		abrirAsignacion(miembroSeleccionado());
	});

	function abrirAsignacion(miembroId) {
		var $select = $('#asignar-horario-id').empty();
		(config.horarios || []).forEach(function (horario) {
			$select.append($('<option>').val(horario.id).text(horario.nombre));
		});
		$('#asignar-vigente-desde').val('');
		$('#asignarHorarioForm').attr('action', config.asignarUrlTemplate.replace('__ID__', miembroId));

		var $historico = $('#asignar-historico').html('<li class="list-group-item text-muted px-0">Cargando…</li>');
		$.getJSON(config.horariosUrlTemplate.replace('__ID__', miembroId))
			.done(function (response) {
				$historico.empty();
				var asignaciones = (response && response.data) || [];
				if (!asignaciones.length) {
					$historico.append('<li class="list-group-item text-muted px-0">Sin asignaciones previas.</li>');
				}
				asignaciones.forEach(function (a) {
					var periodo = a.vigente_desde.split('-').reverse().join('/') + ' → ' + (a.vigente_hasta ? a.vigente_hasta.split('-').reverse().join('/') : 'sin fin');
					var $item = $('<li class="list-group-item d-flex justify-content-between px-0">');
					$item.append($('<span>').text(a.horario.nombre + (a.es_vigente ? ' (vigente)' : '')));
					$item.append($('<span class="text-muted">').text(periodo));
					$historico.append($item);
				});
			})
			.fail(function () {
				$historico.html('<li class="list-group-item text-muted px-0">No se pudo cargar el histórico.</li>');
			});

		diaModal.hide();
		asignarModal.show();
	}

	$('#asignarHorarioForm').on('submit', function (e) {
		e.preventDefault();
		var $form = $(this);

		window.withButtonLoading($form.find('button[type="submit"]'), function () {
			return $.ajax({
				url: $form.attr('action'),
				method: 'POST',
				data: $form.serialize(),
				headers: { Accept: 'application/json' },
			});
		})
			.done(function (response) {
				asignarModal.hide();
				window.showToast('success', (response && response.message) || 'Horario asignado correctamente.');
				calendar.refetchEvents();
			})
			.fail(function (xhr) {
				window.showToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'No se pudo asignar el horario.');
			});
	});
})(jQuery);
