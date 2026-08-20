@extends('layouts.app')

@section('title', 'POS · Módulo de hostelería desactivado')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row justify-content-center">
				<div class="col-xl-7 col-lg-9">
					<div class="card" data-pos-modulo-inactivo>
						<div class="card-body text-center py-5">
							<div class="mb-4">
								<i class="fa-solid fa-utensils fa-3x text-primary opacity-50"></i>
							</div>
							<h4 class="mb-3">El módulo de hostelería está desactivado</h4>
							<p class="text-muted mb-4">
								{{ $mensaje }}
							</p>
							@can('ver-configuracion')
								<a href="{{ route('configuracion.show') }}" class="btn btn-primary">
									Ir a Configuración → POS
								</a>
							@else
								<p class="small text-muted mb-0">
									Pide a un administrador de tu empresa que lo active desde Configuración → POS.
								</p>
							@endcan
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
