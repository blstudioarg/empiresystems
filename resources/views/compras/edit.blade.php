@extends('layouts.app')

@section('title', 'Editar compra')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header">
							<h4 class="card-title mb-0">Editar compra</h4>
						</div>
						<div class="card-body">
							<form id="compra-form" method="POST" action="{{ route('compras.update', $compra) }}">
								@csrf
								@method('PUT')
								<div class="row">
									<div class="col-md-4">
										<label for="proveedor_id" class="form-label">Proveedor</label>
										<select name="proveedor_id" id="proveedor_id" class="form-control">
											@foreach ($proveedores as $proveedor)
												<option value="{{ $proveedor->id }}" @selected($proveedor->id === $compra->proveedor_id)>{{ $proveedor->razon_social ?: $proveedor->nombre }}</option>
											@endforeach
										</select>
									</div>
									<div class="col-md-4">
										<label for="numero_documento" class="form-label">Nº de documento del proveedor</label>
										<input type="text" name="numero_documento" id="numero_documento" class="form-control" value="{{ $compra->numero_documento }}">
									</div>
									<div class="col-md-4">
										<label for="fecha" class="form-label">Fecha</label>
										<input type="date" name="fecha" id="fecha" class="form-control" value="{{ $compra->fecha->toDateString() }}">
									</div>
								</div>

								<hr>

								@include('compras._form_lineas')

								<hr>

								<div class="col-md-12">
									<label for="notas" class="form-label">Notas</label>
									<textarea name="notas" id="notas" rows="2" class="form-control">{{ $compra->notas }}</textarea>
								</div>

								<div class="mt-3 d-flex gap-2">
									<button type="submit" class="btn btn-primary">Guardar cambios</button>
									<a href="{{ route('compras.show', $compra) }}" class="btn btn-danger light">Cancelar</a>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Editar compra')
@section('ayuda')
	@include('ayuda.compras-editar')
@endsection

@push('scripts')
	<script>window.compraLineasIniciales = @json($lineasIniciales);</script>
	<script src="{{ asset('js/plugins-init/compras-form.init.js') }}"></script>
@endpush
