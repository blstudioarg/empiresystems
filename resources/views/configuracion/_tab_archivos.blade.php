<p class="text-muted small mb-3">Ajustes del gestor documental del tenant.</p>

<div class="row">
	<div class="col-md-4 mb-3">
		<label class="form-label" for="archivos_limite_mb">Límite de tamaño por archivo (MB)</label>
		<input type="number" class="form-control" id="archivos_limite_mb" min="1" max="1024" value="{{ $archivosLimiteMb }}">
		<small class="form-text text-muted">Los archivos subidos al gestor documental que superen este tamaño se rechazan. Se guarda al cambiar el valor.</small>
	</div>
</div>

@push('scripts')
	<script>
		document.getElementById('archivos_limite_mb').addEventListener('change', function () {
			var limite = this.value;

			fetch(@json(route('configuracion.archivos.update')), {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'X-HTTP-Method-Override': 'PUT',
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify({ limite_mb: limite }),
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
