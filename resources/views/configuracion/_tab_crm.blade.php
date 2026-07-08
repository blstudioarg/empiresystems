<p class="text-muted small mb-3">Reglas de asignación de leads y valores por defecto de presupuestos.</p>

<form id="crm-form" method="POST" action="{{ route('configuracion.crm.update') }}">
	@csrf
	@method('PUT')

	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="asignacion_estrategia">Estrategia de asignación de leads</label>
			<select class="form-select" id="asignacion_estrategia" name="asignacion_estrategia">
				<option value="manual" {{ $crmConfig['asignacion_estrategia'] === 'manual' ? 'selected' : '' }}>Manual</option>
				<option value="round_robin" {{ $crmConfig['asignacion_estrategia'] === 'round_robin' ? 'selected' : '' }}>Reparto equitativo (round-robin)</option>
			</select>
			<small class="form-text text-muted">Manual: el comercial se elige en cada alta. Round-robin: se reparte automáticamente entre los comerciales seleccionados.</small>
		</div>
		<div class="col-md-8 mb-3">
			<label class="form-label" for="asignacion_comerciales">Comerciales del reparto round-robin</label>
			<select class="form-select" id="asignacion_comerciales" name="asignacion_comerciales[]" multiple size="4">
				@foreach ($comercialesDisponibles as $comercial)
					<option value="{{ $comercial->id }}" {{ in_array($comercial->id, $crmConfig['asignacion_comerciales'], true) ? 'selected' : '' }}>
						{{ $comercial->name }}
					</option>
				@endforeach
			</select>
			<small class="form-text text-muted">Solo se usa con la estrategia round-robin. Si queda vacío, los leads nuevos quedan sin asignar.</small>
		</div>
	</div>

	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="retencion_dias">Retención de leads descartados (días)</label>
			<input type="number" class="form-control" id="retencion_dias" name="retencion_dias" min="1" value="{{ $crmConfig['retencion_dias'] }}">
			<small class="form-text text-muted">Pasado este plazo, los leads descartados o no convertidos se eliminan definitivamente (RGPD).</small>
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="presupuesto_dias_validez">Validez por defecto de un presupuesto (días)</label>
			<input type="number" class="form-control" id="presupuesto_dias_validez" name="presupuesto_dias_validez" min="1" value="{{ $crmConfig['presupuesto_dias_validez'] }}">
		</div>
	</div>

	<button type="submit" class="btn btn-primary">Guardar</button>
</form>

@push('scripts')
	<script>
		document.getElementById('crm-form').addEventListener('submit', function (e) {
			e.preventDefault();

			var form = e.target;
			var comerciales = Array.from(document.getElementById('asignacion_comerciales').selectedOptions).map(function (o) { return o.value; });

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
					asignacion_estrategia: document.getElementById('asignacion_estrategia').value,
					asignacion_comerciales: comerciales,
					retencion_dias: document.getElementById('retencion_dias').value,
					presupuesto_dias_validez: document.getElementById('presupuesto_dias_validez').value,
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
