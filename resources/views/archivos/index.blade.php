@extends('layouts.app')

@section('title', 'Archivos')

@push('styles')
	<style>
		#archivos-drop-zone {
			position: relative;
			min-height: 220px;
			border: 2px dashed transparent;
			border-radius: 0.75rem;
			transition: border-color .15s ease, background-color .15s ease;
		}

		#archivos-drop-zone.archivos-dragover {
			border-color: var(--bs-primary);
			background-color: color-mix(in srgb, var(--bs-primary) 6%, transparent);
		}

		#archivos-drop-zone.archivos-dragover::after {
			content: 'Suelta aquí para subir';
			position: absolute;
			inset: 0;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: 600;
			color: var(--bs-primary);
			background: color-mix(in srgb, var(--bs-primary) 4%, #fff 96%);
			border-radius: 0.65rem;
			pointer-events: none;
			z-index: 2;
		}

		/* La miniatura de imagen NO debe ser arrastrable de forma nativa: el navegador
		   reconstruye un File a partir de un <img> arrastrado y lo confunde con una subida real.
		   El .archivo-item (la tarjeta completa) sí es arrastrable vía draggable="true" en JS,
		   para mover el archivo soltándolo sobre una carpeta. */
		.archivo-item img {
			-webkit-user-drag: none;
			user-select: none;
		}

		.archivo-item {
			cursor: grab;
		}

		.archivo-arrastrando {
			opacity: 0.4;
		}

		.archivo-drop-target .archivo-card,
		#archivos-lista .archivo-drop-target,
		#archivos-breadcrumbs li.archivo-drop-target {
			outline: 2px dashed var(--bs-primary);
			outline-offset: 2px;
			background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent);
			border-radius: 0.5rem;
		}

		.archivo-card {
			border: 1px solid var(--bs-border-color);
			transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
		}

		.archivo-card:hover {
			transform: translateY(-2px);
			border-color: var(--bs-primary);
			box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
		}

		.archivo-acciones {
			opacity: 0;
			transition: opacity .12s ease;
		}

		.archivo-card:hover .archivo-acciones {
			opacity: 1;
		}

		.archivo-item[data-tipo="carpeta"] .archivo-card .card-body i.fa-folder {
			transition: transform .12s ease;
		}

		.archivo-item[data-tipo="carpeta"] .archivo-card:hover .card-body i.fa-folder {
			transform: scale(1.08);
		}

		#archivos-lista .archivo-fila {
			cursor: default;
		}

		#archivos-lista .archivo-fila:hover {
			background-color: var(--bs-tertiary-bg, #f7f8fa);
		}

		#archivos-lista .archivo-fila .archivo-acciones {
			opacity: 0;
			transition: opacity .12s ease;
		}

		#archivos-lista .archivo-fila:hover .archivo-acciones {
			opacity: 1;
		}

		.archivos-skeleton .placeholder {
			border-radius: 0.5rem;
		}

		#archivos-breadcrumbs .breadcrumb-item + .breadcrumb-item::before {
			content: '\f105'; /* fa-chevron-right */
			font-family: 'Font Awesome 5 Free';
			font-weight: 900;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div id="archivos-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de archivos</h6>
									<h3 class="mb-0" data-metric="archivos">{{ $totales['archivos'] }}</h3>
								</div>
								<div>
									<x-lordicon icon="system-regular-49-upload-file" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Carpetas</h6>
									<h3 class="mb-0" data-metric="carpetas">{{ $totales['carpetas'] }}</h3>
								</div>
								<div>
									<x-lordicon icon="box" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Espacio usado</h6>
									<h3 class="mb-0" data-metric="espacio">{{ number_format($totales['espacio_bytes'] / 1024 / 1024, 1) }} MB</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-1-cloud" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap">
					<h4 class="card-title mb-0">Archivos</h4>
					<div class="d-flex align-items-center">
						<div class="btn-group me-2" role="group" aria-label="Cambiar vista">
							<button type="button" class="btn btn-outline-secondary btn-vista active" data-vista="grid" title="Vista rejilla"><i class="fas fa-th-large"></i></button>
							<button type="button" class="btn btn-outline-secondary btn-vista" data-vista="lista" title="Vista lista"><i class="fas fa-list"></i></button>
						</div>
						<input type="file" id="archivo-input" class="d-none" multiple>
						<button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#nuevaCarpetaModal">+ Nueva carpeta</button>
						<button type="button" class="btn btn-primary" id="btn-subir-archivo">+ Subir archivo</button>
					</div>
				</div>
				<div class="card-body">
					<p class="text-muted small mb-2">
						Formatos permitidos: {{ collect($extensionesPermitidas)->map(fn ($ext) => strtoupper($ext))->join(', ') }}.
						Máximo {{ $limiteMb }} MB por archivo.
					</p>

					<div class="input-group mb-3" style="max-width: 360px;">
						<span class="input-group-text"><i class="fas fa-search"></i></span>
						<input type="search" class="form-control" id="archivos-buscador" placeholder="Buscar archivos y carpetas…" autocomplete="off">
						<button type="button" class="btn btn-outline-secondary d-none" id="btn-limpiar-busqueda" title="Limpiar búsqueda"><i class="fas fa-times"></i></button>
					</div>

					<nav aria-label="breadcrumb" id="archivos-breadcrumbs-nav">
						<ol class="breadcrumb" id="archivos-breadcrumbs">
							<li class="breadcrumb-item"><i class="fas fa-home me-1"></i>Raíz</li>
						</ol>
					</nav>
					<p class="text-muted small d-none" id="archivos-busqueda-info"></p>

					<div id="archivos-drop-zone">
						<div id="archivos-skeleton" class="archivos-skeleton row g-3 d-none">
							@for ($i = 0; $i < 6; $i++)
								<div class="col-xl-2 col-lg-3 col-sm-4 col-6">
									<div class="card same-card h-100">
										<div class="card-body text-center py-4">
											<span class="placeholder col-6 mb-2" style="height:2rem;display:block;margin:0 auto;"></span>
											<span class="placeholder col-8"></span>
										</div>
									</div>
								</div>
							@endfor
						</div>

						<div id="archivos-explorer" class="row g-3"></div>
						<div id="archivos-lista" class="list-group d-none"></div>

						<div id="archivos-vacio" class="text-center text-muted py-5 d-none">
							<i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
							<p class="mb-3">Esta carpeta está vacía todavía.</p>
							<button type="button" class="btn btn-primary btn-sm" id="btn-subir-desde-vacio">Subir tu primer archivo</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="nuevaCarpetaModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<form id="nueva-carpeta-form">
					<div class="modal-header">
						<h5 class="modal-title">Nueva carpeta</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<label class="form-label" for="nueva_carpeta_nombre">Nombre</label>
						<input type="text" class="form-control" id="nueva_carpeta_nombre" name="nombre" maxlength="255" required>
						<div class="invalid-feedback d-block" data-error-for="nombre"></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Crear</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="renombrarModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<form id="renombrar-form">
					<div class="modal-header">
						<h5 class="modal-title">Renombrar</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<label class="form-label" for="renombrar_nombre">Nombre</label>
						<input type="text" class="form-control" id="renombrar_nombre" name="nombre" maxlength="255" required>
						<div class="invalid-feedback d-block" data-error-for="nombre"></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Guardar</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="moverModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Mover archivo</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<nav aria-label="breadcrumb">
						<ol class="breadcrumb" id="mover-breadcrumbs"></ol>
					</nav>
					<div id="mover-lista" class="list-group"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-primary" id="btn-mover-aqui">Mover aquí</button>
				</div>
			</div>
		</div>
	</div>

	@include('archivos.partials._preview-modal')
@endsection

@section('ayuda-titulo', 'Archivos')
@section('ayuda')
	@include('ayuda.archivos')
@endsection

@push('scripts')
	<script>
		window.archivosState = {
			indexUrl: @json(route('archivos.index')),
			storeUrl: @json(route('archivos.store')),
			carpetasStoreUrl: @json(route('carpetas.store')),
			carpetaActual: @json($carpetaActual?->id),
		};
	</script>
	<script src="{{ asset('js/plugins-init/archivos-explorer.js') }}"></script>
@endpush
