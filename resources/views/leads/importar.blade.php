@extends('layouts.app')

@section('title', 'Importar leads')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header">
							<h4 class="card-title mb-0">Importar leads desde fichero</h4>
						</div>
						<div class="card-body">
							<form method="POST" action="{{ route('leads.importar') }}" enctype="multipart/form-data">
								@csrf
								<div class="mb-3">
									<label class="form-label" for="fichero">Fichero CSV o Excel (.xlsx)</label>
									<input type="file" class="form-control @error('fichero') is-invalid @enderror" id="fichero" name="fichero" accept=".csv,.txt,.xlsx" required>
									@error('fichero')
										<div class="invalid-feedback">{{ $message }}</div>
									@enderror
									<small class="form-text text-muted">
										Columnas esperadas: <code>nombre, empresa, email, telefono</code>. Cada fila necesita
										nombre y al menos un email o teléfono. Las filas duplicadas (mismo email/teléfono
										que un lead ya existente) se rechazan.
									</small>
								</div>
								<button type="submit" class="btn btn-primary" data-loading-text="Importando...">Importar</button>
								<a href="{{ route('leads.index') }}" class="btn btn-outline-secondary">Volver a leads</a>
							</form>
						</div>
					</div>
				</div>

				@if (session('resumen_importacion'))
					@php $resumen = session('resumen_importacion'); @endphp
					<div class="col-lg-6">
						<div class="card">
							<div class="card-header">
								<h5 class="card-title mb-0">Resultado de la importación</h5>
							</div>
							<div class="card-body">
								<p class="mb-3"><strong>{{ $resumen['importados'] }}</strong> leads importados correctamente.</p>

								@if (count($resumen['rechazadas']) > 0)
									<p class="text-danger mb-2"><strong>{{ count($resumen['rechazadas']) }}</strong> filas rechazadas:</p>
									<ul class="list-group list-group-flush">
										@foreach ($resumen['rechazadas'] as $rechazo)
											<li class="list-group-item px-0">Fila {{ $rechazo['fila'] }}: {{ $rechazo['motivo'] }}</li>
										@endforeach
									</ul>
								@else
									<p class="text-success mb-0">Sin filas rechazadas.</p>
								@endif
							</div>
						</div>
					</div>
				@endif
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Leads')
@section('ayuda')
	@include('ayuda.leads')
@endsection
