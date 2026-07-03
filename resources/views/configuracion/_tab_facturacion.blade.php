<form id="facturacion-form" method="POST" action="{{ route('configuracion.apariencia.update') }}" enctype="multipart/form-data">
	@csrf
	@method('PUT')

	<p class="text-muted small mb-3">Los cambios se guardan automáticamente.</p>

	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="logo_facturacion">Logo de facturación (PDF)</label>
			<img id="logo-facturacion-preview" src="{{ $logoFacturacionPath ? asset('storage/'.$logoFacturacionPath) : '' }}"
				alt="Logo de facturación" class="d-block mb-2" style="max-height: 80px; {{ $logoFacturacionPath ? '' : 'display:none;' }}">
			<input type="file" class="form-control" id="logo_facturacion" name="logo_facturacion" accept="image/png,image/jpeg,image/webp">
			<small class="form-text text-muted">PNG, JPG o WEBP, máximo 1 MB. Si no se configura, se usa el logo por defecto del PDF de factura. Se sube al seleccionarlo.</small>
			@error('logo_facturacion')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>
</form>
