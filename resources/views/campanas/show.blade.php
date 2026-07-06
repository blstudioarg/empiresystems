@extends('layouts.app')

@section('title', 'Detalle de campaña')

@php
	$estadoBadge = [
		'borrador' => 'badge-light',
		'en_curso' => 'badge-warning',
		'finalizada' => 'badge-success',
	];
	$destinatarioBadge = [
		'pendiente' => 'badge-light',
		'enviado' => 'badge-success',
		'fallido' => 'badge-danger',
	];
	$hayFallidosConEmail = $campana->destinatarios
		->where('estado', \App\Enums\EstadoDestinatario::Fallido)
		->whereNotNull('email')
		->isNotEmpty();
@endphp

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header border-0 flex-wrap">
							<div>
								<h4 class="card-title mb-1">{{ $campana->asunto }}</h4>
								<span class="badge {{ $estadoBadge[$campana->estado->value] ?? 'badge-light' }}">
									{{ ucfirst(str_replace('_', ' ', $campana->estado->value)) }}
								</span>
								<span class="text-muted small ms-2">Creada el {{ $campana->created_at?->enZonaTenant()?->format('d/m/Y H:i') }}</span>
							</div>
							<div class="d-flex gap-2 align-items-center">
								<a href="{{ route('campanas.index') }}" class="btn btn-danger light btn-sm">Volver</a>
								@if ($hayFallidosConEmail)
									<button type="button" class="btn btn-primary btn-sm" id="campana-reintentar-btn"
										data-campana-id="{{ $campana->id }}"
										data-reintentar-url="{{ route('campanas.reintentar', $campana) }}"
										data-loading-text="Reintentando...">
										Reintentar fallidos
									</button>
								@endif
							</div>
						</div>
						<div class="card-body">
							<div class="row mb-4">
								<div class="col-sm-4">
									<div class="text-muted small">Total</div>
									<h4 class="mb-0">{{ $campana->total_destinatarios }}</h4>
								</div>
								<div class="col-sm-4">
									<div class="text-muted small">Enviados</div>
									<h4 class="mb-0 text-success">{{ $campana->enviados }}</h4>
								</div>
								<div class="col-sm-4">
									<div class="text-muted small">Fallidos</div>
									<h4 class="mb-0 text-danger">{{ $campana->fallidos }}</h4>
								</div>
							</div>

							<div class="table-responsive">
								<table class="table table-sm">
									<thead>
										<tr>
											<th>Cliente</th>
											<th>Email</th>
											<th>Estado</th>
											<th>Motivo / Enviado</th>
										</tr>
									</thead>
									<tbody>
										@foreach ($campana->destinatarios as $destinatario)
											<tr>
												<td>{{ $destinatario->cliente?->razon_social ?: $destinatario->cliente?->nombre }}</td>
												<td>{{ $destinatario->email ?: '—' }}</td>
												<td>
													<span class="badge {{ $destinatarioBadge[$destinatario->estado->value] ?? 'badge-light' }}">
														{{ ucfirst($destinatario->estado->value) }}
													</span>
												</td>
												<td class="small">
													@if ($destinatario->estado === \App\Enums\EstadoDestinatario::Enviado)
														{{ $destinatario->enviado_at?->enZonaTenant()?->format('d/m/Y H:i') }}
													@elseif ($destinatario->error)
														<span class="text-danger">{{ $destinatario->error }}</span>
													@else
														—
													@endif
												</td>
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script>
		window.campanaFormState = {
			enviarTandaUrlTemplate: @json(route('campanas.enviar-tanda', ['campana' => '__ID__'])),
			tamanoTanda: {{ $tamanoTanda }},
		};
	</script>
	<script src="{{ asset('js/plugins-init/campanas-form.js') }}"></script>
@endpush
