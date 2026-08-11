@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		/* Override obligatorio en CADA tabla nueva: el template estiliza `.paginate_button.previous/.next`
		   con ancho fijo de 24px y, como el idioma usa texto, "Anterior"/"Siguiente" se parte en
		   vertical letra por letra. No hay regla global que lo cubra. */
		#pos-zonas-table_wrapper .dataTables_paginate .paginate_button.previous,
		#pos-zonas-table_wrapper .dataTables_paginate .paginate_button.next,
		#pos-mesas-table_wrapper .dataTables_paginate .paginate_button.previous,
		#pos-mesas-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		/* Las secciones de sala solo tienen sentido con el módulo encendido; se atenúan (no se
		   ocultan) para que se vea que existen y qué se gana al activarlo. */
		.pos-config-dependiente.apagado { opacity: .45; pointer-events: none; }
	</style>
@endpush

<p class="text-muted small mb-3">
	Convierte el POS en un TPV de hostelería: mesas, cuentas abiertas y opciones de artículo.
	Está <strong>desactivado por defecto</strong>; mientras lo esté, el POS funciona exactamente
	igual que hasta ahora.
</p>

<form id="pos-config-form" method="POST" action="{{ route('configuracion.pos.update') }}">
	@csrf
	@method('PUT')

	<div class="mb-3">
		<div class="form-check form-switch">
			<input class="form-check-input" type="checkbox" role="switch" id="pos_hosteleria_activo"
				{{ $posConfig['hosteleria_activo'] ? 'checked' : '' }}>
			<label class="form-check-label" for="pos_hosteleria_activo">
				<strong>Activar el módulo de hostelería</strong>
			</label>
		</div>
		<small class="form-text text-muted">
			Interruptor maestro. No se puede desactivar mientras haya cuentas abiertas: primero
			hay que cobrarlas o anularlas.
		</small>
	</div>

	<div class="pos-config-dependiente {{ $posConfig['hosteleria_activo'] ? '' : 'apagado' }}" id="pos-config-dependiente">
		<div class="mb-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" role="switch" id="pos_opciones_activo"
					{{ $posConfig['opciones_activo'] ? 'checked' : '' }}>
				<label class="form-check-label" for="pos_opciones_activo">Opciones de artículo (modificadores)</label>
			</div>
			<small class="form-text text-muted">Punto de cocción, guarniciones, extras… con precio propio por artículo.</small>
		</div>

		<div class="mb-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" role="switch" id="pos_cobro_dividido_activo"
					{{ $posConfig['cobro_dividido_activo'] ? 'checked' : '' }}>
				<label class="form-check-label" for="pos_cobro_dividido_activo">Cobro por selección de líneas</label>
			</div>
			<small class="form-text text-muted">Permite cobrar parte de la cuenta y dejar la mesa ocupada con lo que falta.</small>
		</div>

		<div class="mb-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" role="switch" id="pos_suplemento_zona_activo"
					{{ $posConfig['suplemento_zona_activo'] ? 'checked' : '' }}>
				<label class="form-check-label" for="pos_suplemento_zona_activo">Suplemento por zona</label>
			</div>
			<small class="form-text text-muted">Un porcentaje configurable por zona que se aplica al cobrar (p. ej. terraza).</small>
		</div>

		<div class="row">
			<div class="col-md-4 mb-3">
				<label class="form-label" for="pos_mesa_olvidada_min">Aviso de mesa olvidada (min)</label>
				<input type="number" class="form-control" id="pos_mesa_olvidada_min" min="1" max="1440"
					value="{{ $posConfig['mesa_olvidada_min'] }}">
				<small class="form-text text-muted">Minutos desde que se abre la cuenta tras los que la mesa se destaca en la sala.</small>
			</div>
		</div>
	</div>

	<button type="submit" class="btn btn-primary" id="pos-config-guardar">Guardar</button>
</form>

<hr class="my-4">

<div class="pos-config-dependiente {{ $posConfig['hosteleria_activo'] ? '' : 'apagado' }}" id="pos-sala-config">
	<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
		<div>
			<h5 class="mb-1">Zonas</h5>
			<p class="text-muted small mb-0">
				Agrupan las mesas de la sala. El nombre es libre y no significa nada para el sistema:
				«Terraza» es solo un ejemplo.
			</p>
		</div>
		<button type="button" class="btn btn-primary btn-add-pos-zona" data-bs-toggle="modal" data-bs-target="#posZonaModal">
			+ Añadir zona
		</button>
	</div>

	<div class="table-responsive">
		<table id="pos-zonas-table" class="display responsive nowrap w-100">
			<thead>
				<tr>
					<th>Zona</th>
					<th>Mesas</th>
					<th>Suplemento</th>
					<th>Orden</th>
					<th>Acciones</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>

	<div class="d-flex justify-content-between align-items-center flex-wrap mb-3 mt-4">
		<div>
			<h5 class="mb-1">Mesas</h5>
			<p class="text-muted small mb-0">Cada mesa pertenece a una zona. Una mesa con cuenta abierta no se puede eliminar.</p>
		</div>
		<button type="button" class="btn btn-primary btn-add-pos-mesa" data-bs-toggle="modal" data-bs-target="#posMesaModal">
			+ Añadir mesa
		</button>
	</div>

	<div class="table-responsive">
		<table id="pos-mesas-table" class="display responsive nowrap w-100">
			<thead>
				<tr>
					<th>Mesa</th>
					<th>Zona</th>
					<th>Estado</th>
					<th>Orden</th>
					<th>Acciones</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>

{{-- Un único modal reutilizado para alta y edición (patrón por defecto de CRUD simple). --}}
<div class="modal fade" id="posZonaModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<form id="pos-zona-form" method="POST" action="{{ route('configuracion.pos.zonas.store') }}">
				@csrf
				<input type="hidden" name="_method" id="pos_zona_method" value="POST">
				<div class="modal-header">
					<h5 class="modal-title" id="posZonaModalLabel">Añadir zona</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label" for="pos_zona_nombre">Nombre</label>
						<input type="text" class="form-control" id="pos_zona_nombre" name="nombre" maxlength="60" placeholder="Ej. Comedor">
						<div class="invalid-feedback" data-error-for="nombre"></div>
					</div>
					<div class="mb-3" id="pos-zona-suplemento-wrap">
						<label class="form-label" for="pos_zona_suplemento">Suplemento (%)</label>
						<input type="number" step="0.01" min="0" max="100" class="form-control" id="pos_zona_suplemento" name="suplemento_porcentaje" placeholder="0.00">
						<small class="form-text text-muted">Se aplica al cobrar, sobre el precio de cada línea.</small>
						<div class="invalid-feedback" data-error-for="suplemento_porcentaje"></div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="pos_zona_orden">Orden</label>
						<input type="number" min="0" class="form-control" id="pos_zona_orden" name="orden" placeholder="0">
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

<div class="modal fade" id="posMesaModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<form id="pos-mesa-form" method="POST" action="{{ route('configuracion.pos.mesas.store') }}">
				@csrf
				<input type="hidden" name="_method" id="pos_mesa_method" value="POST">
				<div class="modal-header">
					<h5 class="modal-title" id="posMesaModalLabel">Añadir mesa</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label" for="pos_mesa_zona">Zona</label>
						<select class="form-control" id="pos_mesa_zona" name="zona_id"></select>
						<div class="invalid-feedback" data-error-for="zona_id"></div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="pos_mesa_nombre">Nombre</label>
						<input type="text" class="form-control" id="pos_mesa_nombre" name="nombre" maxlength="40" placeholder="Ej. Mesa 4">
						<div class="invalid-feedback" data-error-for="nombre"></div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="pos_mesa_orden">Orden</label>
						<input type="number" min="0" class="form-control" id="pos_mesa_orden" name="orden" placeholder="0">
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

@push('scripts')
	<script>
		window.posConfigState = {
			updateUrl: @json(route('configuracion.pos.update')),
			zonasIndexUrl: @json(route('configuracion.pos.zonas.index')),
			zonasStoreUrl: @json(route('configuracion.pos.zonas.store')),
			mesasIndexUrl: @json(route('configuracion.pos.mesas.index')),
			mesasStoreUrl: @json(route('configuracion.pos.mesas.store')),
			suplementoZonaActivo: @json((bool) $posConfig['suplemento_zona_activo']),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/configuracion-pos.init.js') }}"></script>
@endpush
