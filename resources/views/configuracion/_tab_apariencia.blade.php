@push('styles')
	<link href="{{ asset('vendor/jquery-asColorPicker/css/asColorPicker.min.css') }}" rel="stylesheet">
@endpush

<form id="apariencia-form" method="POST" action="{{ route('configuracion.apariencia.update') }}" enctype="multipart/form-data">
	@csrf
	@method('PUT')

	<p class="text-muted small mb-3">Los cambios se guardan automáticamente.</p>

	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="color_primario">Color primario</label>
			<input type="text" class="form-control as_colorpicker" id="color_primario" name="color_primario"
				value="{{ old('color_primario', $colores['color_primario']) }}">
			@error('color_primario')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="color_secundario">Color secundario</label>
			<input type="text" class="form-control as_colorpicker" id="color_secundario" name="color_secundario"
				value="{{ old('color_secundario', $colores['color_secundario']) }}">
			@error('color_secundario')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="color_topbar">Color de fondo de la topbar</label>
			<input type="text" class="form-control as_colorpicker" id="color_topbar" name="color_topbar"
				value="{{ old('color_topbar', $colores['color_topbar']) }}">
			@error('color_topbar')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="logo">Logo (menú expandido)</label>
			<img id="logo-preview" src="{{ $logoPath ? asset('storage/'.$logoPath) : '' }}"
				alt="Logo del tenant (expandido)" class="d-block mb-2" style="max-height: 80px; {{ $logoPath ? '' : 'display:none;' }}">
			<input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
			<small class="form-text text-muted">PNG, JPG o WEBP, máximo 1 MB. Se sube al seleccionarlo.</small>
			@error('logo')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
		<div class="col-md-6 mb-3">
			<label class="form-label" for="logo_mini">Logo (menú comprimido)</label>
			<img id="logo-mini-preview" src="{{ $logoMiniPath ? asset('storage/'.$logoMiniPath) : '' }}"
				alt="Logo del tenant (comprimido)" class="d-block mb-2" style="max-height: 80px; {{ $logoMiniPath ? '' : 'display:none;' }}">
			<input type="file" class="form-control" id="logo_mini" name="logo_mini" accept="image/png,image/jpeg,image/webp">
			<small class="form-text text-muted">PNG, JPG o WEBP, máximo 1 MB. Se sube al seleccionarlo.</small>
			@error('logo_mini')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<div class="d-flex gap-2">
		<button type="submit" name="restablecer" value="1" class="btn btn-outline-danger">Restablecer</button>
	</div>
</form>

@push('scripts')
	{{-- asColorPicker depende de asColor y asGradient (variables globales AsColor/AsGradient);
	     deben cargarse antes o el picker recibe undefined y lanza un TypeError. --}}
	<script src="{{ asset('vendor/jquery-asColor/jquery-asColor.min.js') }}"></script>
	<script src="{{ asset('vendor/jquery-asGradient/jquery-asGradient.min.js') }}"></script>
	<script src="{{ asset('vendor/jquery-asColorPicker/js/jquery-asColorPicker.min.js') }}"></script>
	<script src="{{ asset('js/plugins-init/jquery-asColorPicker.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/configuracion-apariencia.init.js') }}"></script>
@endpush
