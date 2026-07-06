<p class="text-muted small mb-3">
	Sube el certificado digital (.p12/.pfx) con el que se firmarán tus facturas en formato Facturae
	(XAdES-EPES). La contraseña se guarda cifrada y nunca se muestra de nuevo.
</p>

@if ($certificado)
	<div class="alert {{ $certificado['caducado'] ? 'alert-danger' : 'alert-info' }} d-flex justify-content-between align-items-center">
		<div>
			<strong>Titular:</strong> {{ $certificado['titular'] }}
			@if ($certificado['caduca_at'])
				&nbsp;·&nbsp; <strong>Caduca:</strong> {{ $certificado['caduca_at'] }}
			@endif
		</div>
		@if ($certificado['caducado'])
			<span class="badge badge-danger">Caducado</span>
		@else
			<span class="badge badge-success">Vigente</span>
		@endif
	</div>
@else
	<div class="alert alert-warning">
		Aún no has configurado ningún certificado. No podrás generar Facturae hasta subir uno válido.
	</div>
@endif

<form id="certificado-form" method="POST" action="{{ route('configuracion.certificado.update') }}" enctype="multipart/form-data">
	@csrf
	@method('PATCH')

	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="certificado">Certificado (.p12/.pfx)</label>
			<input type="file" class="form-control" id="certificado" name="certificado" accept=".p12,.pfx" required>
			@error('certificado')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-6 mb-3">
			<label class="form-label" for="certificado_password">Contraseña</label>
			<input type="password" class="form-control" id="certificado_password" name="password" autocomplete="new-password" required>
			@error('password')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<button type="submit" class="btn btn-primary">Guardar certificado</button>
</form>

<hr class="my-4">

<h6>Verificar NIF-IVA intracomunitario (VIES)</h6>
<p class="text-muted small mb-3">
	Para entregas intracomunitarias exentas (E5) verifica el NIF-IVA del cliente contra el
	censo VIES de la Comisión Europea antes de emitir el Facturae.
</p>

<form id="vies-form" class="row g-2 align-items-end">
	<div class="col-md-3">
		<label class="form-label" for="vies_pais">País</label>
		<input type="text" class="form-control" id="vies_pais" maxlength="2" placeholder="FR" required>
	</div>
	<div class="col-md-5">
		<label class="form-label" for="vies_nif_iva">NIF-IVA</label>
		<input type="text" class="form-control" id="vies_nif_iva" required>
	</div>
	<div class="col-md-4">
		<button type="submit" class="btn btn-outline-primary" id="vies-submit" data-loading-text="Verificando...">Verificar</button>
	</div>
</form>
<div id="vies-resultado" class="mt-2"></div>

@push('scripts')
	<script>
		document.getElementById('certificado-form').addEventListener('submit', function () {
			window.setButtonLoading(this.querySelector('button[type="submit"]'), true);
		});

		document.getElementById('vies-form').addEventListener('submit', function (e) {
			e.preventDefault();

			var $resultado = document.getElementById('vies-resultado');
			var $submit = document.getElementById('vies-submit');

			window.withButtonLoading($submit, function () {
				return fetch(@json(route('configuracion.certificado.verificar-vies')), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					},
					body: JSON.stringify({
						nif_iva: document.getElementById('vies_nif_iva').value,
						pais: document.getElementById('vies_pais').value,
					}),
				}).then(function (r) { return r.json(); });
			}).then(function (data) {
				if (!data.verificado) {
					$resultado.innerHTML = '<span class="badge bg-warning">VIES no disponible ahora mismo; no se ha podido verificar.</span>';
					return;
				}

				if (data.valido) {
					$resultado.innerHTML = '<span class="badge bg-success">NIF-IVA válido' + (data.nombre ? ' — ' + data.nombre : '') + '</span>';
				} else {
					$resultado.innerHTML = '<span class="badge bg-danger">NIF-IVA no válido</span>';
				}
			}).catch(function () {
				$resultado.innerHTML = '<span class="badge bg-warning">No se pudo verificar el NIF-IVA.</span>';
			});
		});
	</script>
@endpush
