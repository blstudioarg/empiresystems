@php
	$verifactuActivo = $verifactuConfig['activo'] ?? false;
	$verifactuEntorno = $verifactuConfig['entorno'] ?? 'pruebas';
	$verifactuCadenaIniciada = $verifactuConfig['cadena_iniciada'] ?? false;
@endphp

<p class="text-muted small mb-3">Los cambios se guardan automáticamente.</p>

<div class="mb-4">
	<h5 class="mb-1">Registro y remisión Verifactu</h5>
	<p class="text-muted small mb-3">
		Con el flag activo, cada factura emitida queda sellada (huella encadenada + QR de cotejo)
		y se remite en tiempo real a la AEAT. Con el flag apagado, la emisión funciona exactamente
		igual que hoy, sin ningún dato Verifactu.
	</p>
	<div class="form-check form-switch">
		<input class="form-check-input" type="checkbox" role="switch" id="verifactu_activo" {{ $verifactuActivo ? 'checked' : '' }}>
		<label class="form-check-label" for="verifactu_activo">Verifactu activo para este tenant</label>
	</div>
</div>

<div class="mb-3">
	<label class="form-label" for="verifactu_entorno">Entorno</label>
	<select class="form-control" id="verifactu_entorno" style="max-width: 320px;" {{ $verifactuCadenaIniciada ? 'disabled' : '' }}>
		<option value="pruebas" {{ $verifactuEntorno === 'pruebas' ? 'selected' : '' }}>Pruebas (preproducción AEAT)</option>
		<option value="produccion" {{ $verifactuEntorno === 'produccion' ? 'selected' : '' }}>Producción</option>
	</select>
	@if ($verifactuCadenaIniciada)
		<small class="form-text text-warning">
			Este tenant ya tiene una cadena Verifactu iniciada: el entorno no puede cambiarse (mezclaría
			registros de pruebas con la cadena fiscal real).
		</small>
	@else
		<small class="form-text text-muted">No se puede cambiar una vez emitida la primera factura con el flag activo.</small>
	@endif
</div>

@push('scripts')
	<script>
		(function () {
			function guardarVerifactu() {
				fetch(@json(route('configuracion.verifactu.update')), {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'X-HTTP-Method-Override': 'PUT',
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({
						activo: document.getElementById('verifactu_activo').checked ? 1 : 0,
						entorno: document.getElementById('verifactu_entorno').value,
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
			}

			document.getElementById('verifactu_activo').addEventListener('change', guardarVerifactu);
			document.getElementById('verifactu_entorno').addEventListener('change', guardarVerifactu);
		})();
	</script>
@endpush
