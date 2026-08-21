<p class="text-muted small mb-3">Renombrá y reordená (arrastrando) las secciones de tu menú lateral. Los
	cambios se aplican recién al pulsar <strong>Guardar</strong>. Cada grupo arranca contraído —
	desplegalo con la flecha para reordenar sus entradas; para mover grupos entre sí no hace falta
	desplegarlos.</p>

<form id="menu-form" action="{{ route('configuracion.menu.update') }}">
	@csrf

	<ul id="menu-grupos" class="menu-editor-lista">
		@foreach ($menuEstructura as $grupo)
			<li class="menu-editor-grupo @if (count($grupo['hijos'])) colapsado @endif" data-clave="{{ $grupo['clave'] }}">
				<div class="menu-editor-fila">
					<span class="menu-editor-handle" title="Arrastrar para reordenar">
						<i class="fas fa-grip-vertical"></i>
					</span>
					@if (count($grupo['hijos']))
						<button type="button" class="menu-editor-toggle" aria-expanded="false" title="Mostrar/ocultar entradas">
							<i class="fas fa-chevron-right"></i>
						</button>
					@endif
					<div class="menu-editor-campo">
						<input type="text" class="form-control" name="etiquetas[{{ $grupo['clave'] }}]"
							value="{{ $grupo['etiqueta'] }}" maxlength="40">
						<small class="form-text text-muted">Por defecto: {{ $grupo['etiqueta_defecto'] }}</small>
						<div class="invalid-feedback" data-error-for="etiquetas.{{ $grupo['clave'] }}"></div>
					</div>
				</div>

				@if (count($grupo['hijos']))
					<ul class="menu-editor-hijos" data-clave-grupo="{{ $grupo['clave'] }}">
						@foreach ($grupo['hijos'] as $hijo)
							<li class="menu-editor-hijo" data-clave="{{ $hijo['clave'] }}">
								<div class="menu-editor-fila">
									<span class="menu-editor-handle" title="Arrastrar para reordenar">
										<i class="fas fa-grip-vertical"></i>
									</span>
									<div class="menu-editor-campo">
										<input type="text" class="form-control" name="etiquetas[{{ $hijo['clave'] }}]"
											value="{{ $hijo['etiqueta'] }}" maxlength="40">
										<small class="form-text text-muted">Por defecto: {{ $hijo['etiqueta_defecto'] }}</small>
										<div class="invalid-feedback" data-error-for="etiquetas.{{ $hijo['clave'] }}"></div>
									</div>
								</div>
							</li>
						@endforeach
					</ul>
				@endif
			</li>
		@endforeach
	</ul>

	<div class="d-flex gap-2 mt-3">
		<button type="submit" class="btn btn-primary" id="menu-guardar-btn" data-loading-text="Guardando...">Guardar</button>
		<button type="button" class="btn btn-outline-secondary" id="menu-restaurar-btn"
			data-restaurar-url="{{ route('configuracion.menu.restaurar') }}">Restaurar valores por defecto</button>
	</div>
</form>

@push('styles')
	<link rel="stylesheet" href="{{ asset('vendor/jqueryui/css/jquery-ui.min.css') }}">
	<link rel="stylesheet" href="@assetv('css/configuracion-menu.css')">
@endpush

@push('scripts')
	<script src="{{ asset('vendor/jqueryui/js/jquery-ui.min.js') }}"></script>
	<script src="@assetv('js/plugins-init/configuracion-menu.init.js')"></script>
@endpush
