<p class="text-muted small mb-3">
	El asistente IA responde dudas de funcionamiento y consulta tus datos usando la API de OpenAI.
	El coste del uso corre por cuenta de tu propia clave. Pegá tu API key para activarlo; se guarda
	cifrada y solo se muestra enmascarada.
</p>

<div class="alert alert-info small" role="alert">
	<strong>Privacidad:</strong> al activar el asistente, los mensajes que escriban los usuarios y los
	resultados de las consultas (nombres de clientes, importes, etc.) se envían a la API de OpenAI
	bajo tu clave y tu responsabilidad como responsable del tratamiento. Solo se envían los datos
	necesarios para responder cada consulta.
</div>

<form id="ia-form" method="POST" action="{{ route('configuracion.ia.update') }}">
	@csrf
	@method('PUT')

	<div class="row">
		<div class="col-md-8 mb-3">
			<label class="form-label" for="api_key">API key de OpenAI</label>
			<input type="password" class="form-control" id="api_key" name="api_key" maxlength="255" spellcheck="false"
				placeholder="{{ $iaConfigurada ? $iaClaveEnmascarada : 'sk-...' }}" autocomplete="off">
			<small class="form-text text-muted">
				@if ($iaConfigurada)
					Hay una clave guardada ({{ $iaClaveEnmascarada }}). Escribí una nueva para reemplazarla,
					o dejala en blanco para conservar la actual.
				@else
					Aún no hay ninguna clave configurada. Obtené la tuya en platform.openai.com.
				@endif
			</small>
			@error('api_key')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="retencion_dias">Conservar el historial de conversaciones</label>
			<div class="input-group">
				<input type="number" class="form-control" id="retencion_dias" name="retencion_dias"
					min="1" max="3650" value="{{ old('retencion_dias', $iaRetencionDias) }}">
				<span class="input-group-text">días</span>
			</div>
			<small class="text-muted">
				Las conversaciones sin actividad durante más tiempo se eliminan automáticamente, junto con
				sus mensajes. Es un requisito de protección de datos: no se conservan indefinidamente.
			</small>
			@error('retencion_dias')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>

	{{-- Quitar la clave es una acción explícita: sin esto, guardar solo el plazo de retención
	     borraría la clave, porque el campo es de tipo password y viaja vacío. --}}
	<input type="hidden" name="quitar_clave" id="quitar_clave" value="0">

	<div class="d-flex gap-2">
		<button type="submit" class="btn btn-primary">Guardar</button>
		@if ($iaConfigurada)
			<button type="button" class="btn btn-outline-primary" id="btn-probar-ia" data-loading-text="Probando...">Probar conexión</button>
			<button type="submit" class="btn btn-outline-danger" id="btn-quitar-ia">Quitar clave</button>
		@endif
	</div>
</form>

@push('scripts')
	<script>
		document.getElementById('ia-form').addEventListener('submit', function () {
			window.setButtonLoading(this.querySelector('button[type="submit"]'), true);
		});

		// "Quitar clave": manda el form con api_key vacía (el backend interpreta vacío = borrar).
		const btnQuitar = document.getElementById('btn-quitar-ia');
		if (btnQuitar) {
			btnQuitar.addEventListener('click', function () {
				document.getElementById('api_key').value = '';
				document.getElementById('quitar_clave').value = '1';
			});
		}

		const btnProbar = document.getElementById('btn-probar-ia');
		if (btnProbar) {
			btnProbar.addEventListener('click', function () {
				window.withButtonLoading(this, function () {
					return fetch(@json(route('configuracion.ia.probar')), {
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
							'Accept': 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
						},
					})
						.then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
						.then(function (res) {
							window.showToast(res.data.ok ? 'success' : 'error', res.data.mensaje || 'No se pudo probar la conexión.');
						})
						.catch(function () { window.showToast('error', 'No se pudo probar la conexión.'); });
				});
			});
		}
	</script>
@endpush
