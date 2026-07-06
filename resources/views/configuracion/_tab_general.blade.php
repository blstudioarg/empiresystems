<p class="text-muted small mb-3">Ajustes generales del tenant que afectan a toda la aplicación.</p>

<form id="general-form" method="POST" action="{{ route('configuracion.general.update') }}">
	@csrf
	@method('PUT')

	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="zona_horaria">Zona horaria</label>
			<select class="form-select" id="zona_horaria" name="zona_horaria">
				@foreach ($zonasHorariasDisponibles as $valor => $etiqueta)
					<option value="{{ $valor }}" {{ $generalConfig['zona_horaria'] === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
				@endforeach
			</select>
			<small class="form-text text-muted">Las horas se guardan siempre en el servidor (UTC); esto controla en qué hora local se muestran en toda la aplicación (fichajes, registros de actividad, campañas, etc.).</small>
		</div>
	</div>

	<button type="submit" class="btn btn-primary">Guardar</button>
</form>

@push('scripts')
	<script>
		document.getElementById('general-form').addEventListener('submit', function (e) {
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
					zona_horaria: document.getElementById('zona_horaria').value,
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
