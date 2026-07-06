@extends('layouts.app')

@section('title', 'Jornada')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header">
							<h4 class="card-title">Registro de jornada</h4>
						</div>
						<div class="card-body">
							<form id="jornada-filtro-form" method="GET" action="{{ route('jornada.index') }}" class="row g-2 align-items-end mb-3">
								<input type="hidden" name="preset" value="personalizado">
								<div class="col-md-4">
									<label class="form-label" for="miembro_id">Miembro</label>
									<select name="miembro_id" id="miembro_id" class="form-select">
										<option value="">— Selecciona un miembro —</option>
										@foreach ($miembros as $m)
											<option value="{{ $m->id }}" {{ $miembroSeleccionado?->id === $m->id ? 'selected' : '' }}>
												{{ $m->user->name }}
											</option>
										@endforeach
									</select>
								</div>
								<div class="col-md-3">
									<label class="form-label" for="desde">Desde</label>
									<input type="date" name="desde" id="desde" class="form-control" value="{{ $rango->desde->toDateString() }}">
								</div>
								<div class="col-md-3">
									<label class="form-label" for="hasta">Hasta</label>
									<input type="date" name="hasta" id="hasta" class="form-control" value="{{ $rango->hasta->toDateString() }}">
								</div>
								<div class="col-md-2">
									<button type="submit" class="btn btn-primary w-100">Consultar</button>
								</div>
							</form>

							<div id="jornada-resultado">
								@include('partials.jornada-resultado')
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="corregirFichajeModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<form id="corregirFichajeForm" method="POST" action="">
					@csrf
					<div class="modal-header">
						<h5 class="modal-title">Corregir fichaje</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div class="mb-2">
							<label class="form-label" for="corregir-tipo">Tipo</label>
							<select name="tipo" id="corregir-tipo" class="form-select">
								<option value="entrada">Entrada</option>
								<option value="salida">Salida</option>
								<option value="inicio_pausa">Inicio de pausa</option>
								<option value="fin_pausa">Fin de pausa</option>
							</select>
						</div>
						<div class="mb-2">
							<label class="form-label" for="corregir-ocurrido-at">Fecha y hora correcta</label>
							<input type="datetime-local" name="ocurrido_at" id="corregir-ocurrido-at" class="form-control" required>
						</div>
						<div class="mb-2">
							<label class="form-label" for="corregir-motivo">Motivo</label>
							<textarea name="motivo" id="corregir-motivo" class="form-control" required></textarea>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Guardar corrección</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="jornadaPdfModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Vista previa del informe de jornada</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body p-0" style="height: 80vh;">
					<iframe id="jornadaPdfFrame" src="" style="width: 100%; height: 100%; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('js/plugins-init/jornada-filtro.init.js') }}"></script>
@endpush
