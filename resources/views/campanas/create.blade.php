@extends('layouts.app')

@section('title', 'Nueva campaña')

@push('styles')
	<style>
		.destinatarios-tabla-wrap {
			max-height: 340px;
			overflow-y: auto;
			border: 1px solid var(--bs-border-color);
			border-radius: 0.5rem;
		}

		.destinatarios-tabla-wrap table {
			margin-bottom: 0;
		}

		.destinatarios-tabla-wrap thead th {
			position: sticky;
			top: 0;
			background: var(--bs-card-bg, #fff);
			z-index: 1;
		}

		.destinatario-sin-email {
			color: var(--bs-danger);
			font-size: 0.8125rem;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<form id="campana-form">
				@csrf
				<div class="row">
					<div class="col-lg-7">
						<div class="card">
							<div class="card-header border-0">
								<h4 class="card-title mb-0">Composición</h4>
							</div>
							<div class="card-body">
								@if (! $emailConfigurado)
									<div class="alert alert-warning" role="alert">
										Aún no has configurado tu servidor de correo.
										<a href="{{ route('configuracion.show') }}">Configúralo en Configuración → Email</a>
										antes de enviar campañas.
									</div>
								@endif

								<div class="row">
									@if ($plantillas->isNotEmpty())
										<div class="col-12">
											<label class="form-label" for="plantilla_email_id">Usar plantilla (opcional)</label>
											<select class="form-select" id="plantilla_email_id" name="plantilla_email_id">
												<option value="">— Sin plantilla —</option>
												@foreach ($plantillas as $plantilla)
													<option value="{{ $plantilla->id }}"
														data-asunto="{{ $plantilla->asunto }}"
														data-cuerpo="{{ $plantilla->cuerpo }}">
														{{ $plantilla->titulo }}
													</option>
												@endforeach
											</select>
											<small class="form-text text-muted">Precarga asunto y cuerpo; puedes editarlos antes de enviar.</small>
										</div>
									@endif

									<div class="col-12">
										<label class="form-label" for="asunto">Asunto</label>
										<input type="text" class="form-control" id="asunto" name="asunto" maxlength="255">
										<div class="invalid-feedback d-block" data-error-for="asunto"></div>
									</div>

									<div class="col-12">
										<label class="form-label" for="cuerpo">Cuerpo del correo</label>
										<textarea class="form-control" id="cuerpo" name="cuerpo" rows="12"
											placeholder="Escribe el contenido del correo. Puedes usar HTML."></textarea>
										<div class="invalid-feedback d-block" data-error-for="cuerpo"></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-lg-5">
						<div class="card">
							<div class="card-header border-0 flex-wrap">
								<h4 class="card-title mb-0">Destinatarios</h4>
								<div class="d-flex align-items-center gap-2">
									<input type="text" class="form-control" id="destinatarios-buscar" placeholder="Buscar cliente..." style="max-width: 180px;">
								</div>
							</div>
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-center mb-2">
									<div class="form-check">
										<input class="form-check-input" type="checkbox" id="destinatarios-todos">
										<label class="form-check-label" for="destinatarios-todos">Seleccionar todos</label>
									</div>
									<span class="text-muted small"><span id="destinatarios-contador">0</span> seleccionados</span>
								</div>
								<div class="invalid-feedback d-block mb-2" data-error-for="cliente_ids"></div>

								<div class="destinatarios-tabla-wrap">
									<table class="table table-sm mb-0" id="destinatarios-tabla">
										<thead>
											<tr>
												<th style="width: 2.5rem;"></th>
												<th>Cliente</th>
												<th>Email</th>
											</tr>
										</thead>
										<tbody>
											@forelse ($clientes as $cliente)
												<tr data-nombre="{{ Str::lower($cliente->razon_social ?: $cliente->nombre) }}" data-email="{{ Str::lower($cliente->email ?? '') }}">
													<td>
														<input class="form-check-input destinatario-check" type="checkbox"
															name="cliente_ids[]" value="{{ $cliente->id }}">
													</td>
													<td>{{ $cliente->razon_social ?: $cliente->nombre }}</td>
													<td>
														@if ($cliente->email)
															{{ $cliente->email }}
														@else
															<span class="destinatario-sin-email">sin email</span>
														@endif
													</td>
												</tr>
											@empty
												<tr>
													<td colspan="3" class="text-center text-muted py-3">No tienes clientes cargados todavía.</td>
												</tr>
											@endforelse
										</tbody>
									</table>
								</div>
							</div>
						</div>

					</div>
				</div>

				<div class="d-flex justify-content-end gap-2 mb-4">
					<a href="{{ route('campanas.index') }}" class="btn btn-danger light">Cancelar</a>
					<button type="submit" class="btn btn-primary" id="campana-enviar-btn" data-loading-text="Enviando...">
						Crear y enviar
					</button>
				</div>
			</form>
		</div>

		{{-- Modal de progreso de envío --}}
		<div class="modal fade" id="campanaEnvioModal" tabindex="-1" aria-hidden="true"
			data-bs-backdrop="static" data-bs-keyboard="false">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title d-flex align-items-center gap-2" id="campanaEnvioModalLabel">
							<span class="spinner-border spinner-border-sm text-primary" id="progreso-spinner" role="status" aria-hidden="true"></span>
							<span id="progreso-titulo">Enviando campaña…</span>
						</h5>
					</div>
					<div class="modal-body">
						<div class="progress mb-3" style="height: 1.25rem;">
							<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
								style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
						</div>
						<div class="d-flex justify-content-between">
							<span class="text-success"><strong id="progreso-enviados">0</strong> enviados</span>
							<span class="text-danger"><strong id="progreso-fallidos">0</strong> fallidos</span>
							<span class="text-muted"><span id="progreso-procesados">0</span> / <span id="progreso-total">0</span></span>
						</div>
					</div>
					<div class="modal-footer">
						<a href="#" id="progreso-ver-detalle" class="btn btn-primary" style="display: none;">Ver detalle</a>
						<a href="{{ route('campanas.index') }}" id="progreso-volver" class="btn btn-danger light" style="display: none;">Volver a campañas</a>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Nueva campaña')
@section('ayuda')
	@include('ayuda.campanas-crear')
@endsection

@push('scripts')
	<script>
		window.campanaFormState = {
			storeUrl: @json(route('campanas.store')),
			enviarTandaUrlTemplate: @json(route('campanas.enviar-tanda', ['campana' => '__ID__'])),
			tamanoTanda: 8,
		};
	</script>
	<script src="{{ asset('js/plugins-init/campanas-form.js') }}"></script>
@endpush
