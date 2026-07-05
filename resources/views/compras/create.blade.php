@extends('layouts.app')

@section('title', 'Nueva compra')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header">
							<h4 class="card-title mb-0">Nueva compra</h4>
						</div>
						<div class="card-body">
							<form id="compra-form" method="POST" action="{{ route('compras.store') }}">
								@csrf
								<div class="row">
									<div class="col-md-4">
										<label for="proveedor_id" class="form-label">Proveedor</label>
										<select name="proveedor_id" id="proveedor_id" class="form-control">
											<option value="">Selecciona un proveedor</option>
											@foreach ($proveedores as $proveedor)
												<option value="{{ $proveedor->id }}">{{ $proveedor->razon_social ?: $proveedor->nombre }}</option>
											@endforeach
										</select>
									</div>
									<div class="col-md-4">
										<label for="numero_documento" class="form-label">Nº de documento del proveedor</label>
										<input type="text" name="numero_documento" id="numero_documento" class="form-control">
									</div>
									<div class="col-md-4">
										<label for="fecha" class="form-label">Fecha</label>
										<input type="date" name="fecha" id="fecha" class="form-control" value="{{ now()->toDateString() }}">
									</div>
								</div>

								<hr>

								@include('compras._form_lineas')

								<hr>

								<div class="col-md-12">
									<label for="notas" class="form-label">Notas</label>
									<textarea name="notas" id="notas" rows="2" class="form-control"></textarea>
								</div>

								<div class="mt-3 d-flex gap-2">
									<button type="submit" class="btn btn-primary">Guardar borrador</button>
									<a href="{{ route('compras.index') }}" class="btn btn-danger light">Cancelar</a>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script>window.compraLineasIniciales = @json($lineasIniciales);</script>
	<script src="{{ asset('js/plugins-init/compras-form.init.js') }}"></script>
@endpush
