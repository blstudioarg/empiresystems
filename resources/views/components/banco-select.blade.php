@props([
	'name' => 'banco_id',
	'id' => null,
])
@php
	$fieldId = $id ?? $name;
@endphp

<div class="input-group banco-control">
	<select name="{{ $name }}" id="{{ $fieldId }}" class="form-control banco-select" {{ $attributes }}></select>
	<button type="button" class="btn btn-success btn-banco-add" title="Nuevo banco"><i class="fas fa-plus"></i></button>
	<button type="button" class="btn btn-primary btn-banco-edit" title="Renombrar banco seleccionado"><i class="fas fa-pen"></i></button>
	<button type="button" class="btn btn-danger btn-banco-delete" title="Eliminar banco seleccionado"><i class="fas fa-trash"></i></button>
</div>
<div class="invalid-feedback d-block" data-error-for="{{ $name }}"></div>

{{-- Assets + modal compartidos: se emiten una sola vez por página aunque haya
     varias instancias del componente. --}}
@once
	@push('styles')
		<link href="{{ asset('vendor/select2/css/select2.min.css') }}" rel="stylesheet">
		<link href="{{ asset('css/banco-select.css') }}" rel="stylesheet">
	@endpush

	@push('scripts')
		<div class="modal fade" id="bancoModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
					<form id="banco-form">
						<div class="modal-header">
							<h5 class="modal-title" id="bancoModalLabel">Nuevo banco</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<label for="banco_nombre" class="form-label">Nombre del banco</label>
							<input type="text" id="banco_nombre" class="form-control" maxlength="255" placeholder="BBVA, CaixaBank...">
							<div class="invalid-feedback d-block" data-error-for="banco_nombre"></div>
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
			window.bancoSelectConfig = {
				indexUrl: @json(route('bancos.index')),
				storeUrl: @json(route('bancos.store')),
				updateUrlTemplate: @json(route('bancos.update', '__ID__')),
				destroyUrlTemplate: @json(route('bancos.destroy', '__ID__')),
				csrf: @json(csrf_token()),
			};
		</script>
		<script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>
		<script src="{{ asset('js/components/banco-select.js') }}"></script>
	@endpush
@endonce
