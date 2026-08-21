@push('styles')
	<style>
		/* Las secciones dependientes solo tienen sentido con el módulo encendido; se atenúan (no se
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

<p class="text-muted small mb-0">
	Las zonas y mesas de la sala se crean y editan directamente desde
	<a href="{{ route('pos.sala') }}">POS → Sala</a> (botón «Editar plano»).
</p>

@push('scripts')
	<script>
		window.posConfigState = {
			updateUrl: @json(route('configuracion.pos.update')),
		};
	</script>
	<script src="@assetv('js/plugins-init/configuracion-pos.init.js')"></script>
@endpush
