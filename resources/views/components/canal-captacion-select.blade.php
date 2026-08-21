@props([
	'name' => 'canal_captacion_id',
	'id' => null,
])
@php
	$fieldId = $id ?? $name;
@endphp

<div class="input-group canal-captacion-control">
	<select name="{{ $name }}" id="{{ $fieldId }}" class="form-control canal-captacion-select" {{ $attributes }}></select>
	<button type="button" class="btn btn-success btn-canal-captacion-add" title="Nuevo canal"><i class="fas fa-plus"></i></button>
	<button type="button" class="btn btn-primary btn-canal-captacion-edit" title="Renombrar canal seleccionado"><i class="fas fa-pen"></i></button>
	<button type="button" class="btn btn-danger btn-canal-captacion-delete" title="Eliminar canal seleccionado"><i class="fas fa-trash"></i></button>
</div>
<div class="invalid-feedback d-block" data-error-for="{{ $name }}"></div>

{{-- Assets + modal compartidos: se emiten una sola vez por página aunque haya
     varias instancias del componente. --}}
@once
	@push('styles')
		<link href="{{ asset('vendor/select2/css/select2.min.css') }}" rel="stylesheet">
		<link href="@assetv('css/canal-captacion-select.css')" rel="stylesheet">
	@endpush

	@push('scripts')
		<div class="modal fade" id="canalCaptacionModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
					<form id="canal-captacion-form">
						<div class="modal-header">
							<h5 class="modal-title" id="canalCaptacionModalLabel">Nuevo canal</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<label for="canal_captacion_nombre" class="form-label">Nombre del canal</label>
							<input type="text" id="canal_captacion_nombre" class="form-control" maxlength="80" placeholder="Web, Feria, Recomendación...">
							<div class="invalid-feedback d-block" data-error-for="canal_captacion_nombre"></div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
							<button type="submit" class="btn btn-primary">Guardar</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<script>
			window.canalCaptacionSelectConfig = {
				indexUrl: @json(route('canales-captacion.index', ['solo_activos' => 1])),
				storeUrl: @json(route('canales-captacion.store')),
				updateUrlTemplate: @json(route('canales-captacion.update', '__ID__')),
				destroyUrlTemplate: @json(route('canales-captacion.destroy', '__ID__')),
				csrf: @json(csrf_token()),
			};
		</script>
		<script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>
		<script src="@assetv('js/components/canal-captacion-select.js')"></script>
	@endpush
@endonce
