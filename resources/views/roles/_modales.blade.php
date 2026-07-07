<div class="modal fade" id="rolModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" role="document">
		<div class="modal-content">
			<form id="rol-form" method="POST" action="{{ route('roles.store') }}">
				@csrf
				<input type="hidden" name="_method" id="rol_method" value="POST">
				<div class="modal-header">
					<h5 class="modal-title" id="rolModalLabel">Agregar rol</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label for="rol_name" class="form-label">Nombre del rol</label>
						<input type="text" name="name" id="rol_name" class="form-control">
						<div class="invalid-feedback" data-error-for="name"></div>
					</div>

					<label class="form-label d-block">Permisos</label>
					<div class="invalid-feedback d-block mb-2" data-error-for="permisos"></div>
					<div class="rol-permisos-grid">
						@foreach (\App\Support\CatalogoPermisos::porModulo() as $grupo)
							<div class="rol-modulo-group" data-modulo="{{ $grupo['modulo'] }}">
								<div class="rol-modulo-group__header">
									<span class="rol-modulo-group__nombre">{{ $grupo['modulo'] }}</span>
									@if (count($grupo['permisos']) > 1)
										<input type="checkbox" class="form-check-input rol-modulo-group__master" title="Marcar todo el módulo {{ $grupo['modulo'] }}">
									@endif
								</div>
								@foreach ($grupo['permisos'] as $permiso)
									<div class="form-check">
										<input type="checkbox" name="permisos[]" value="{{ $permiso['name'] }}" id="permiso_{{ $permiso['name'] }}" class="form-check-input rol-permiso-checkbox">
										<label class="form-check-label" for="permiso_{{ $permiso['name'] }}">{{ $permiso['label'] }}</label>
									</div>
								@endforeach
							</div>
						@endforeach
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
