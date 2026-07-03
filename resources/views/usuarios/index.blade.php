@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de usuarios</h6>
									<h3 class="mb-0">{{ $totales['total'] }}</h3>
								</div>
								<div>
									<x-lordicon icon="people" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Pendientes de aprobación</h6>
									<h3 class="mb-0">{{ $totales['pendientes'] }}</h3>
								</div>
								<div>
									<x-lordicon icon="person" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Usuarios activos</h6>
									<h3 class="mb-0">{{ $totales['activos'] }}</h3>
								</div>
								<div>
									<x-lordicon icon="box" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header">
					<h4 class="card-title">Usuarios del tenant</h4>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-responsive-md">
							<thead>
								<tr>
									<th>Nombre</th>
									<th>Email</th>
									<th>Rol</th>
									<th>Estado</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody>
								@foreach ($usuarios as $usuario)
									<tr>
										<td>{{ $usuario->name }}</td>
										<td>{{ $usuario->email }}</td>
										<td>{{ $usuario->rol->value }}</td>
										<td>
											@if ($usuario->estado === \App\Enums\EstadoUsuario::Pendiente)
												<span class="badge badge-warning">Pendiente</span>
											@elseif ($usuario->estado === \App\Enums\EstadoUsuario::Aprobado)
												<span class="badge badge-success">Aprobado</span>
											@else
												<span class="badge badge-danger">Rechazado</span>
											@endif
										</td>
										<td>
											@unless ($usuario->id === auth()->id())
												@if ($usuario->estado !== \App\Enums\EstadoUsuario::Aprobado)
													<form action="{{ route('usuarios.aprobar', $usuario) }}" method="POST" class="d-inline">
														@csrf
														@method('PATCH')
														<button type="submit" class="btn btn-sm btn-success">Aprobar</button>
													</form>
												@endif
												@if ($usuario->estado !== \App\Enums\EstadoUsuario::Rechazado)
													<form action="{{ route('usuarios.rechazar', $usuario) }}" method="POST" class="d-inline">
														@csrf
														@method('PATCH')
														<button type="submit" class="btn btn-sm btn-danger">Rechazar</button>
													</form>
												@endif
											@endunless
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
@endsection
