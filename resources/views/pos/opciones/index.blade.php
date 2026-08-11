@extends('layouts.app')

@section('title', 'POS · Opciones de artículo')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		/* Override obligatorio de paginación en cada tabla nueva (ver docs/04-front-guidelines.md):
		   sin esto "Anterior"/"Siguiente" se renderizan como columnas verticales de letras. */
		#pos-grupos-table_wrapper .dataTables_paginate .paginate_button.previous,
		#pos-grupos-table_wrapper .dataTables_paginate .paginate_button.next,
		#pos-opciones-table_wrapper .dataTables_paginate .paginate_button.previous,
		#pos-opciones-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<div>
						<h4 class="card-title mb-1">Grupos de opciones</h4>
						<p class="text-muted small mb-0">Agrupan opciones que se eligen juntas: punto de cocción, guarnición, extras…</p>
					</div>
					<button type="button" class="btn btn-primary btn-add-pos-grupo" data-bs-toggle="modal" data-bs-target="#posGrupoModal">
						+ Añadir grupo
					</button>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table id="pos-grupos-table" class="display responsive nowrap w-100">
							<thead>
								<tr>
									<th>Grupo</th>
									<th>Selección</th>
									<th>Opciones</th>
									<th>Artículos</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<div>
						<h4 class="card-title mb-1">Opciones</h4>
						<p class="text-muted small mb-0">
							El precio por defecto es solo el punto de partida: el precio que se cobra se
							fija en cada artículo.
						</p>
					</div>
					<button type="button" class="btn btn-primary btn-add-pos-opcion" data-bs-toggle="modal" data-bs-target="#posOpcionModal">
						+ Añadir opción
					</button>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table id="pos-opciones-table" class="display responsive nowrap w-100">
							<thead>
								<tr>
									<th>Opción</th>
									<th>Grupo</th>
									<th>Precio por defecto</th>
									<th>Artículo vinculado</th>
									<th>Artículos</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>

		</div>
	</div>

	<div class="modal fade" id="posGrupoModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content">
				<form id="pos-grupo-form" method="POST" action="{{ route('pos.opcion-grupos.store') }}">
					@csrf
					<input type="hidden" name="_method" id="pos_grupo_method" value="POST">
					<div class="modal-header">
						<h5 class="modal-title" id="posGrupoModalLabel">Añadir grupo</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="mb-3">
							<label class="form-label" for="pos_grupo_nombre">Nombre</label>
							<input type="text" class="form-control" id="pos_grupo_nombre" name="nombre" maxlength="60" placeholder="Ej. Punto de cocción">
							<div class="invalid-feedback" data-error-for="nombre"></div>
						</div>
						<div class="mb-3">
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" role="switch" id="pos_grupo_obligatorio" name="obligatorio" value="1">
								<label class="form-check-label" for="pos_grupo_obligatorio">Obligatorio</label>
							</div>
							<small class="form-text text-muted">Un grupo obligatorio necesita al menos una opción, o el artículo no se podría comandar.</small>
							<div class="invalid-feedback d-block" data-error-for="obligatorio"></div>
						</div>
						<div class="row">
							<div class="col-6 mb-3">
								<label class="form-label" for="pos_grupo_min">Mínimo</label>
								<input type="number" min="0" max="255" class="form-control" id="pos_grupo_min" name="min_selecciones" placeholder="0">
								<div class="invalid-feedback" data-error-for="min_selecciones"></div>
							</div>
							<div class="col-6 mb-3">
								<label class="form-label" for="pos_grupo_max">Máximo</label>
								<input type="number" min="1" max="255" class="form-control" id="pos_grupo_max" name="max_selecciones" placeholder="Sin límite">
								<div class="invalid-feedback" data-error-for="max_selecciones"></div>
							</div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="pos_grupo_orden">Orden</label>
							<input type="number" min="0" class="form-control" id="pos_grupo_orden" name="orden" placeholder="0">
							<div class="invalid-feedback" data-error-for="orden"></div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Guardar</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="posOpcionModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content">
				<form id="pos-opcion-form" method="POST" action="{{ route('pos.opciones.store') }}">
					@csrf
					<input type="hidden" name="_method" id="pos_opcion_method" value="POST">
					<div class="modal-header">
						<h5 class="modal-title" id="posOpcionModalLabel">Añadir opción</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="mb-3">
							<label class="form-label" for="pos_opcion_grupo">Grupo</label>
							<select class="form-control" id="pos_opcion_grupo" name="grupo_id">
								@foreach ($grupos as $grupo)
									<option value="{{ $grupo->id }}">{{ $grupo->nombre }}</option>
								@endforeach
							</select>
							<div class="invalid-feedback" data-error-for="grupo_id"></div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="pos_opcion_nombre">Nombre</label>
							<input type="text" class="form-control" id="pos_opcion_nombre" name="nombre" maxlength="60" placeholder="Ej. Al punto">
							<div class="invalid-feedback" data-error-for="nombre"></div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="pos_opcion_precio">Precio por defecto (€)</label>
							<input type="number" step="0.01" min="0" class="form-control" id="pos_opcion_precio" name="precio_defecto" placeholder="0.00">
							<div class="invalid-feedback" data-error-for="precio_defecto"></div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="pos_opcion_articulo">Artículo vinculado (opcional)</label>
							<select class="form-control" id="pos_opcion_articulo" name="articulo_vinculado_id">
								<option value="">— Ninguno —</option>
								@foreach ($articulos as $articulo)
									<option value="{{ $articulo->id }}">{{ $articulo->nombre }}</option>
								@endforeach
							</select>
							<small class="form-text text-muted">Descuenta stock de ese artículo al vender la opción, sin generar una línea propia en el ticket.</small>
							<div class="invalid-feedback" data-error-for="articulo_vinculado_id"></div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="pos_opcion_orden">Orden</label>
							<input type="number" min="0" class="form-control" id="pos_opcion_orden" name="orden" placeholder="0">
							<div class="invalid-feedback" data-error-for="orden"></div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Guardar</button>
					</div>
				</form>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Opciones de artículo (POS)')
@section('ayuda')
	@include('ayuda.pos-opciones')
@endsection

@push('scripts')
	<script>
		window.posOpcionesState = {
			gruposIndexUrl: @json(route('pos.opcion-grupos.index')),
			gruposStoreUrl: @json(route('pos.opcion-grupos.store')),
			opcionesIndexUrl: @json(route('pos.opciones.index')),
			opcionesStoreUrl: @json(route('pos.opciones.store')),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/pos-opciones-datatable.init.js') }}"></script>
@endpush
