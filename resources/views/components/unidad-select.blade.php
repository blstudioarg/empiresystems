@props([
	'name' => 'unidad',
	'id' => null,
])
@php
	$fieldId = $id ?? $name;
@endphp

<div class="input-group unidad-control">
	<select name="{{ $name }}" id="{{ $fieldId }}" class="form-control unidad-select" {{ $attributes }}></select>
	<button type="button" class="btn btn-success btn-unidad-add" title="Nueva unidad"><i class="fas fa-plus"></i></button>
	<button type="button" class="btn btn-primary btn-unidad-edit" title="Renombrar unidad seleccionada"><i class="fas fa-pen"></i></button>
	<button type="button" class="btn btn-danger btn-unidad-delete" title="Eliminar unidad seleccionada"><i class="fas fa-trash"></i></button>
</div>
<div class="invalid-feedback d-block" data-error-for="{{ $name }}"></div>

{{-- Assets + modal compartidos: se emiten una sola vez por página aunque haya
     varias instancias del componente. --}}
@once
	@push('styles')
		<link href="{{ asset('vendor/select2/css/select2.min.css') }}" rel="stylesheet">
		<link href="{{ asset('css/unidad-select.css') }}" rel="stylesheet">
	@endpush

	@push('scripts')
		<div class="modal fade" id="unidadModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<form id="unidad-form">
						<div class="modal-header">
							<h5 class="modal-title" id="unidadModalLabel">Nueva unidad</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<label for="unidad_nombre" class="form-label">Nombre de la unidad</label>
							<input type="text" id="unidad_nombre" class="form-control" maxlength="20" placeholder="ud, hora, kg...">
							<div class="invalid-feedback d-block" data-error-for="unidad_nombre"></div>
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
			window.unidadSelectConfig = {
				indexUrl: @json(route('unidades.index')),
				storeUrl: @json(route('unidades.store')),
				updateUrlTemplate: @json(route('unidades.update', '__ID__')),
				destroyUrlTemplate: @json(route('unidades.destroy', '__ID__')),
				csrf: @json(csrf_token()),
			};
		</script>
		<script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>
		<script src="{{ asset('js/components/unidad-select.js') }}"></script>
	@endpush
@endonce
