@extends('layouts.app')

@section('title', 'Compras')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#compras-table_wrapper .dataTables_paginate .paginate_button.previous,
		#compras-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div id="compras-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de compras</h6>
									<h4 class="mb-0" data-metric="total">0</h4>
								</div>
								<div>
									<x-lordicon icon="invoice" size="38" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Confirmadas</h6>
									<h4 class="mb-0" data-metric="confirmadas">0</h4>
								</div>
								<div>
									<x-lordicon icon="box" size="38" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Importe confirmado</h6>
									<h4 class="mb-0" data-metric="importe_total">0</h4>
								</div>
								<div>
									<x-lordicon icon="euro" size="38" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header border-0 flex-wrap">
							<h4 class="card-title mb-0">Compras</h4>
							<div class="d-flex gap-2">
								<select id="filtro-estado-b2b" class="form-control" style="width: auto;">
									<option value="">Todos los estados B2B</option>
									@foreach (\App\Enums\EstadoB2b::cases() as $estado)
										<option value="{{ $estado->value }}">{{ $estado->label() }}</option>
									@endforeach
								</select>
								<button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importarFacturaeModal">
									Importar Facturae
								</button>
								<button type="button" class="btn btn-outline-primary"
									@if ($iaConfigurada) data-bs-toggle="modal" data-bs-target="#importarDocumentoModal"
									@else disabled title="Configurá el asistente IA en Configuración › Asistente IA para poder importar documentos." @endif>
									Importar documento
								</button>
								<a href="{{ route('compras.create') }}" class="btn btn-primary">+ Nueva compra</a>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="compras-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Proveedor</th>
											<th>Nº documento</th>
											<th>Fecha</th>
											<th>Estado</th>
											<th>Estado B2B</th>
											<th>Total</th>
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
		</div>

		<div class="modal fade" id="importarFacturaeModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<form id="importarFacturaeForm" method="POST" action="{{ route('compras.facturae.importar') }}"
					class="modal-content" enctype="multipart/form-data">
					@csrf
					<div class="modal-header">
						<h5 class="modal-title">Importar Facturae de proveedor</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<p class="text-muted small">
							Sube el XML Facturae recibido del proveedor. Se creará una compra con los datos,
							líneas e importes del documento; el proveedor se asocia (o crea) por NIF.
						</p>
						<div class="mb-3">
							<label class="form-label" for="importarFacturaeArchivo">Archivo XML</label>
							<input type="file" class="form-control" id="importarFacturaeArchivo" name="archivo" accept=".xml,.xsig" required>
							<div class="invalid-feedback d-block" data-error-for="archivo"></div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary" id="importarFacturaeSubmit" data-loading-text="Importando...">Importar</button>
					</div>
				</form>
			</div>
		</div>

		@include('compras._importar_documento_modal')
	</div>
@endsection

@section('ayuda-titulo', 'Compras')
@section('ayuda')
	@include('ayuda.compras')
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	{{-- @assetv, no asset(): el hosting cachea los estáticos una semana (guía, "Assets propios"). --}}
	<script src="@assetv('js/plugins-init/compras-datatable.init.js')"></script>
	<script>
		$(function () {
			$('#importarFacturaeForm').on('submit', function (e) {
				e.preventDefault();

				var $form = $(this);
				var $submit = $('#importarFacturaeSubmit');
				$form.find('[data-error-for]').text('');

				window.withButtonLoading($submit, function () {
					return $.ajax({
						url: $form.attr('action'),
						type: 'POST',
						dataType: 'json',
						processData: false,
						contentType: false,
						headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
						data: new FormData($form[0]),
					});
				})
					.done(function (response) {
						window.showToast('success', response.message);
						bootstrap.Modal.getInstance(document.getElementById('importarFacturaeModal')).hide();
						$('#compras-table').DataTable().ajax.reload(null, false);
					})
					.fail(function (xhr) {
						if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.archivo) {
							$form.find('[data-error-for="archivo"]').text(xhr.responseJSON.errors.archivo[0]);
							return;
						}

						var tipo = xhr.status === 409 ? 'warning' : 'error';
						window.showToast(tipo, xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'No se pudo importar el Facturae.');

						if (xhr.status === 409) {
							bootstrap.Modal.getInstance(document.getElementById('importarFacturaeModal')).hide();
						}
					});
			});
		});
	</script>

	@if ($iaConfigurada)
		{{-- @assetv, no asset(): el hosting cachea los estáticos una semana (guía, "Assets propios"). --}}
		<script src="@assetv('js/plugins-init/compras-importar-documento.init.js')"></script>
		<script>
			$(function () {
				window.initImportacionDocumentosCompra({
					urls: {
						subir: @json(route('compras.documentos.subir')),
						interpretar: @json(route('compras.documentos.interpretar', ['token' => '__TOKEN__'])),
						crear: @json(route('compras.documentos.crear', ['token' => '__TOKEN__'])),
						descartar: @json(route('compras.documentos.descartar', ['token' => '__TOKEN__'])),
					},
					proveedores: @json($proveedoresImportacion),
					articulos: @json($articulosImportacion),
					tabla: '#compras-table',
				});
			});
		</script>
	@endif
@endpush
