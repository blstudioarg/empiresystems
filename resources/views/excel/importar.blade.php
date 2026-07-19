@extends('layouts.app')

@section('title', 'Importar '.$definicion->etiquetaModulo())

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-6">
					<div class="card">
						<div class="card-header">
							<h4 class="card-title mb-0">Importar {{ $definicion->etiquetaModulo() }} desde fichero</h4>
						</div>
						<div class="card-body">
							<form method="POST" action="{{ route($modulo.'.importar.previsualizar', ['modulo' => $modulo]) }}" enctype="multipart/form-data">
								@csrf
								<div class="mb-3">
									<label class="form-label" for="fichero">Fichero (.xlsx, .xls, .csv)</label>
									<input type="file" class="form-control @error('fichero') is-invalid @enderror" id="fichero" name="fichero" accept=".xlsx,.xls,.csv,.txt" required>
									@error('fichero')
										<div class="invalid-feedback">{{ $message }}</div>
									@enderror
									<small class="form-text text-muted">
										Columnas esperadas: <code>{{ collect($definicion->columnas())->pluck('etiqueta')->implode(', ') }}</code>.
										Máximo 2.000 filas por fichero.
									</small>
								</div>
								<button type="submit" class="btn btn-primary" data-loading-text="Analizando...">Previsualizar</button>
								<a href="{{ route($modulo.'.importar.plantilla', ['modulo' => $modulo]) }}" class="btn btn-outline-secondary">Descargar plantilla</a>
							</form>
						</div>
					</div>
				</div>

				<div class="col-lg-6">
					@if ($previsualizacion)
						@include('excel._resultado', ['tipo' => 'previsualizacion', 'datos' => $previsualizacion, 'modulo' => $modulo])
					@elseif (session('resumen_importacion'))
						@include('excel._resultado', ['tipo' => 'resumen', 'datos' => session('resumen_importacion'), 'modulo' => $modulo])
					@endif
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Importar y exportar')
@section('ayuda')
	@include('ayuda.importar-exportar')
@endsection
