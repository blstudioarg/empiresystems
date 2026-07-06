<p class="text-muted small mb-3">Control horario y fichajes: retención de datos personales (RGPD) y comportamiento del geofencing.</p>

<form id="fichajes-form" method="POST" action="{{ route('configuracion.fichajes.update') }}">
	@csrf
	@method('PUT')

	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="retencion_geo_dias">Retención de geo del fichaje (días)</label>
			<input type="number" class="form-control" id="retencion_geo_dias" name="retencion_geo_dias" min="1" value="{{ $fichajesConfig['retencion_geo_dias'] }}">
			<small class="form-text text-muted">Pasado este plazo se nulifica el veredicto dentro/fuera, la distancia y la precisión del fichaje; la fila de jornada se conserva 4 años.</small>
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="retencion_casa_dias">Retención de datos de casa tras baja (días)</label>
			<input type="number" class="form-control" id="retencion_casa_dias" name="retencion_casa_dias" min="1" value="{{ $fichajesConfig['retencion_casa_dias'] }}">
			<small class="form-text text-muted">Pasado este plazo desde la baja de un miembro, se nulifica su dirección y coordenadas de casa.</small>
		</div>
	</div>

	<div class="mb-3">
		<div class="form-check form-switch">
			<input class="form-check-input" type="checkbox" role="switch" id="geofencing_bloqueante" name="geofencing_bloqueante"
				{{ $fichajesConfig['geofencing_bloqueante'] ? 'checked' : '' }}>
			<label class="form-check-label" for="geofencing_bloqueante">
				Geofencing bloqueante (impedir fichar fuera del perímetro)
			</label>
		</div>
		<small class="form-text text-muted">Por defecto es informativo: el fichaje fuera del perímetro se registra igual y genera una alerta.</small>
	</div>

	<div class="mb-3">
		<div class="form-check form-switch">
			<input class="form-check-input" type="checkbox" role="switch" id="registrar_pausas" name="registrar_pausas"
				{{ $fichajesConfig['registrar_pausas'] ? 'checked' : '' }}>
			<label class="form-check-label" for="registrar_pausas">
				Registrar pausas (inicio/fin de pausa)
			</label>
		</div>
	</div>

	<hr>
	<p class="text-muted small mb-3">Umbrales del informe de cumplimiento de horarios (previsto vs. real).</p>

	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="tolerancia_retraso_min">Tolerancia de retraso (min)</label>
			<input type="number" class="form-control" id="tolerancia_retraso_min" name="tolerancia_retraso_min" min="0" value="{{ $fichajesConfig['tolerancia_retraso_min'] }}">
			<small class="form-text text-muted">Minutos de gracia antes de marcar un día como retraso.</small>
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="tolerancia_exceso_min">Tolerancia de exceso (min)</label>
			<input type="number" class="form-control" id="tolerancia_exceso_min" name="tolerancia_exceso_min" min="0" value="{{ $fichajesConfig['tolerancia_exceso_min'] }}">
			<small class="form-text text-muted">Minutos por encima (o por debajo) de lo previsto antes de marcar exceso o cumplimiento parcial.</small>
		</div>
	</div>

	<button type="submit" class="btn btn-primary">Guardar</button>
</form>

@push('scripts')
	<script>
		document.getElementById('fichajes-form').addEventListener('submit', function (e) {
			e.preventDefault();

			var form = e.target;

			fetch(form.action, {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'X-HTTP-Method-Override': 'PUT',
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify({
					retencion_geo_dias: document.getElementById('retencion_geo_dias').value,
					retencion_casa_dias: document.getElementById('retencion_casa_dias').value,
					geofencing_bloqueante: document.getElementById('geofencing_bloqueante').checked ? 1 : 0,
					registrar_pausas: document.getElementById('registrar_pausas').checked ? 1 : 0,
					tolerancia_retraso_min: document.getElementById('tolerancia_retraso_min').value,
					tolerancia_exceso_min: document.getElementById('tolerancia_exceso_min').value,
				}),
			})
				.then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
				.then(function (res) {
					if (!res.ok) {
						window.showToast('error', res.data.message || 'No se pudo guardar.');
						return;
					}
					window.showToast('success', res.data.message);
				})
				.catch(function () { window.showToast('error', 'No se pudo guardar la configuración.'); });
		});
	</script>
@endpush
