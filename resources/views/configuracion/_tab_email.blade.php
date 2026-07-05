<p class="text-muted small mb-3">
	Cada tenant envía sus facturas desde su propia cuenta de correo (SMTP). Configura los datos de tu
	proveedor de correo (host, puerto, cifrado, usuario y contraseña) y verifica que funcionan con el
	botón "Enviar email de prueba" antes de enviar una factura real.
</p>

<form id="email-form" method="POST" action="{{ route('configuracion.email.update') }}">
	@csrf
	@method('PUT')

	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="smtp_host">Servidor SMTP</label>
			<input type="text" class="form-control" id="smtp_host" name="smtp_host" maxlength="255"
				value="{{ old('smtp_host', $email['smtp_host']) }}" placeholder="smtp.midominio.com" required>
			@error('smtp_host')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-3 mb-3">
			<label class="form-label" for="smtp_port">Puerto</label>
			<input type="number" class="form-control" id="smtp_port" name="smtp_port" min="1" max="65535"
				value="{{ old('smtp_port', $email['smtp_port']) }}" required>
			@error('smtp_port')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-3 mb-3">
			<label class="form-label" for="smtp_encryption">Cifrado</label>
			<select class="form-control" id="smtp_encryption" name="smtp_encryption" required>
				<option value="ssl" {{ old('smtp_encryption', $email['smtp_encryption']) === 'ssl' ? 'selected' : '' }}>SSL</option>
				<option value="tls" {{ old('smtp_encryption', $email['smtp_encryption']) === 'tls' ? 'selected' : '' }}>TLS</option>
			</select>
			@error('smtp_encryption')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="smtp_usuario">Usuario</label>
			<input type="text" class="form-control" id="smtp_usuario" name="smtp_usuario" maxlength="255"
				value="{{ old('smtp_usuario', $email['smtp_usuario']) }}" required>
			@error('smtp_usuario')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-6 mb-3">
			<label class="form-label" for="smtp_password">Contraseña</label>
			<input type="password" class="form-control" id="smtp_password" name="smtp_password"
				placeholder="{{ $emailTienePassword ? '••••••••' : '' }}" autocomplete="new-password">
			<small class="form-text text-muted">
				@if ($emailTienePassword)
					Déjala en blanco para conservar la contraseña ya guardada.
				@else
					Aún no hay ninguna contraseña guardada.
				@endif
			</small>
			@error('smtp_password')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="remitente">Email remitente</label>
			<input type="email" class="form-control" id="remitente" name="remitente" maxlength="255"
				value="{{ old('remitente', $email['remitente']) }}" required>
			@error('remitente')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="remitente_nombre">Nombre del remitente</label>
			<input type="text" class="form-control" id="remitente_nombre" name="remitente_nombre" maxlength="255"
				value="{{ old('remitente_nombre', $email['remitente_nombre']) }}">
			@error('remitente_nombre')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="responder_a">Responder a (opcional)</label>
			<input type="email" class="form-control" id="responder_a" name="responder_a" maxlength="255"
				value="{{ old('responder_a', $email['responder_a']) }}">
			@error('responder_a')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<div class="d-flex gap-2">
		<button type="submit" class="btn btn-primary">Guardar</button>
		<button type="button" class="btn btn-outline-primary" id="btn-enviar-prueba" data-loading-text="Enviando...">Enviar email de prueba</button>
	</div>
</form>

@push('scripts')
	<script>
		// Submit normal de página completa (sin AJAX): solo feedback visual + evita doble
		// submit, no hace falta restaurar el botón porque la página recarga.
		document.getElementById('email-form').addEventListener('submit', function () {
			window.setButtonLoading(this.querySelector('button[type="submit"]'), true);
		});

		document.getElementById('btn-enviar-prueba').addEventListener('click', function () {
			window.withButtonLoading(this, function () {
				return fetch(@json(route('configuracion.email.prueba')), {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				})
					.then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
					.then(function (res) {
						window.showToast(res.ok ? 'success' : 'error', res.data.message || 'No se pudo enviar el email de prueba.');
					})
					.catch(function () { window.showToast('error', 'No se pudo enviar el email de prueba.'); });
			});
		});
	</script>
@endpush
