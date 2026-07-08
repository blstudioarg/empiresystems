@props([
	'name' => 'categoria_id',
	'id' => null,
])
@php
	$fieldId = $id ?? $name;
@endphp

<div class="input-group categoria-control">
	<select name="{{ $name }}" id="{{ $fieldId }}" class="form-control categoria-select" {{ $attributes }}></select>
	<button type="button" class="btn btn-success btn-categoria-add" title="Nueva categoría"><i class="fas fa-plus"></i></button>
	<button type="button" class="btn btn-primary btn-categoria-edit" title="Renombrar categoría seleccionada"><i class="fas fa-pen"></i></button>
	<button type="button" class="btn btn-danger btn-categoria-delete" title="Eliminar categoría seleccionada"><i class="fas fa-trash"></i></button>
</div>
<div class="invalid-feedback d-block" data-error-for="{{ $name }}"></div>

{{-- Assets + modal compartidos: se emiten una sola vez por página aunque haya
     varias instancias del componente. --}}
@once
	@push('styles')
		<link href="{{ asset('vendor/select2/css/select2.min.css') }}" rel="stylesheet">
		<link href="{{ asset('css/categoria-select.css') }}" rel="stylesheet">
	@endpush

	@push('scripts')
		<div class="modal fade" id="categoriaModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
					<form id="categoria-form">
						<div class="modal-header">
							<h5 class="modal-title" id="categoriaModalLabel">Nueva categoría</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<label for="categoria_nombre" class="form-label">Nombre de la categoría</label>
							<input type="text" id="categoria_nombre" class="form-control" maxlength="60" placeholder="Bebidas, Servicios, Material...">
							<div class="invalid-feedback d-block" data-error-for="categoria_nombre"></div>
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
			window.categoriaSelectConfig = {
				indexUrl: @json(route('categorias.index')),
				storeUrl: @json(route('categorias.store')),
				updateUrlTemplate: @json(route('categorias.update', '__ID__')),
				destroyUrlTemplate: @json(route('categorias.destroy', '__ID__')),
				csrf: @json(csrf_token()),
			};
		</script>
		<script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>
		<script src="{{ asset('js/components/categoria-select.js') }}"></script>
	@endpush
@endonce
