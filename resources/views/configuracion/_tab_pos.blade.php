@push('styles')
	<style>
		/* Las secciones dependientes solo tienen sentido con el módulo encendido; se atenúan (no se
		   ocultan) para que se vea que existen y qué se gana al activarlo. */
		.pos-config-dependiente.apagado { opacity: .45; pointer-events: none; }

		/* Traducciones del POS (feature 050): override de paginación de toda DataTable
		   (docs/04-front-guidelines.md, "DataTable: botones Anterior/Siguiente") y textos largos
		   que parten en varias líneas en vez de estirar la tabla. */
		#pos-traducciones-table_wrapper .dataTables_paginate .paginate_button.previous,
		#pos-traducciones-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
		#pos-traducciones-table td.pos-traduccion-texto { white-space: normal; min-width: 14rem; max-width: 26rem; }
		.pos-traduccion-variables code { font-size: .8rem; }
	</style>
@endpush

{{-- Idioma del POS (feature 050): bloque propio, fuera de los ajustes del módulo de hostelería
     (FR-002: aplica también al POS sin hostelería), con su propio guardado. Esta pestaña sigue
     en español aunque el POS esté en chino (FR-008). --}}
<form id="pos-idioma-form" method="POST" action="{{ route('configuracion.pos.update') }}" class="mb-4">
	@csrf
	@method('PUT')
	<h5 class="mb-1">Idioma del POS</h5>
	<p class="text-muted small mb-3">
		Idioma en el que todo el personal ve el POS: crear ticket, sala, caja, listados, avisos y
		ayuda. El resto de la aplicación sigue en español. Los nombres de artículos, mesas y zonas
		se muestran tal como se cargaron. Con el POS en chino, el ticket y la precuenta se imprimen
		en español y en chino.
	</p>
	<div class="row align-items-end">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="pos_idioma">Idioma</label>
			<select class="form-select form-control" id="pos_idioma" name="idioma">
				@foreach (config('traduccion.idiomas') as $codigo => $nombre)
					<option value="{{ $codigo }}" @selected($posConfig['idioma'] === $codigo)>{{ $nombre }}</option>
				@endforeach
			</select>
		</div>
		<div class="col-md-4 mb-3">
			<button type="submit" class="btn btn-primary" id="pos-idioma-guardar">Guardar idioma</button>
		</div>
	</div>
	<div class="alert alert-info small py-2 d-none" id="pos-idioma-pendientes" role="status"></div>
</form>

{{-- Traducciones del POS (feature 050, US4): solo con el POS en un idioma distinto del español.
     DataTable client-side (§ "Listados: SIEMPRE DataTable") y edición en modal (§ "CRUD simple"). --}}
<div id="pos-traducciones" class="mb-4 {{ $posConfig['idioma'] === config('traduccion.idioma_origen') ? 'd-none' : '' }}">
	<h5 class="mb-1">Traducciones del POS</h5>
	<p class="text-muted small mb-3">
		Los textos del POS se traducen solos. Si alguno quedó raro, corrígelo aquí: la corrección
		se ve al momento en todo el POS, solo en tu empresa, y ninguna actualización la vuelve a
		cambiar. «Restaurar automática» descarta tu corrección.
	</p>
	<div class="table-responsive">
		<table id="pos-traducciones-table" class="display responsive nowrap w-100">
			<thead>
				<tr>
					<th>Texto en español</th>
					<th>Traducción</th>
					<th>Origen</th>
					<th>Acciones</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="posTraduccionModal" tabindex="-1" aria-labelledby="posTraduccionModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<form id="pos-traduccion-form">
				<div class="modal-header">
					<h5 class="modal-title" id="posTraduccionModalLabel">Corregir traducción</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<span class="form-label d-block">Texto en español</span>
						<p class="mb-0 small" id="pos-traduccion-original"></p>
					</div>
					<div class="mb-3" id="pos-traduccion-automatica-wrap">
						<span class="form-label d-block">Traducción automática</span>
						<p class="mb-0 small text-muted" id="pos-traduccion-automatica"></p>
					</div>
					<div class="mb-2">
						<label class="form-label" for="pos_traduccion_texto">Tu traducción</label>
						<textarea class="form-control" id="pos_traduccion_texto" name="traduccion" rows="3" maxlength="5000"></textarea>
						<div class="invalid-feedback" data-error-for="traduccion"></div>
					</div>
					<small class="form-text text-muted d-none pos-traduccion-variables" id="pos-traduccion-variables"></small>
					<small class="form-text text-muted d-none" id="pos-traduccion-html">
						Puedes usar <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code> y <code>&lt;br&gt;</code>; cualquier otra etiqueta se elimina al guardar.
					</small>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" id="pos-traduccion-guardar">Guardar</button>
				</div>
			</form>
		</div>
	</div>
</div>

<hr class="my-4">
<h5 class="mb-1">Módulo de hostelería</h5>

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

	{{-- Caja (feature 048): aplica a todo POS, con o sin hostelería, por eso va fuera del bloque
	     dependiente. --}}
	<hr class="my-4">
	<h5 class="mb-1">Caja</h5>
	<p class="text-muted small mb-3">Apertura, arqueo y cierre del efectivo del día desde POS → Caja.</p>
	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="pos_caja_umbral_descuadre">Diferencia máxima sin explicación (€)</label>
			<input type="number" class="form-control" id="pos_caja_umbral_descuadre" min="0" max="9999.99" step="0.01"
				value="{{ number_format($posConfig['caja_umbral_descuadre'], 2, '.', '') }}">
			<small class="form-text text-muted">Si al cerrar la caja el efectivo contado se aparta más que esto de lo esperado, hay que escribir qué pasó.</small>
		</div>
	</div>

	<button type="submit" class="btn btn-primary" id="pos-config-guardar">Guardar</button>
</form>

<p class="text-muted small mb-0">
	Las zonas y mesas de la sala se crean y editan directamente desde
	<a href="{{ route('pos.sala') }}">POS → Sala</a> (botón «Editar plano»).
</p>

@push('scripts')
	<script>
		window.posConfigState = {
			updateUrl: @json(route('configuracion.pos.update')),
			idioma: @json($posConfig['idioma']),
			traduccionesUrl: @json(route('configuracion.pos.traducciones.index')),
			traduccionUrlTemplate: @json(route('configuracion.pos.traducciones.update', ['hash' => str_repeat('0', 64)])),
		};
	</script>
	<script src="@assetv('js/plugins-init/configuracion-pos.init.js')"></script>
@endpush
