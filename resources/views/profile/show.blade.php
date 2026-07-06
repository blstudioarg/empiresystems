@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="card profile-overview profile-overview-wide">
				<div class="card-header">
					<h4 class="card-title">Mi perfil</h4>
				</div>
				<div class="card-body d-lg-flex align-items-start">
					{{-- Avatar + estado decorativo --}}
					<div class="clearfix">
						<div class="d-inline-block position-relative me-sm-4 me-3 mb-3 mb-lg-0">
							<img id="avatar-preview" src="{{ $user->avatarUrl() }}"
								alt="Foto de {{ $user->name }}" class="rounded-4 profile-avatar">
							@if ($user->estado)
								<span
									class="fa fa-circle border border-3 border-white position-absolute bottom-0 end-0 rounded-circle
									@class([
										'text-success' => $user->estado === \App\Enums\EstadoUsuario::Aprobado,
										'text-warning' => $user->estado === \App\Enums\EstadoUsuario::Pendiente,
										'text-danger' => $user->estado === \App\Enums\EstadoUsuario::Rechazado,
									])"></span>
							@endif
						</div>
					</div>

					{{-- Datos del usuario --}}
					<div class="clearfix flex-grow-1">
						<div class="d-flex align-items-center flex-wrap mb-1">
							<h3 class="fw-semibold mb-0 me-2">{{ $user->name }}</h3>
							@if ($user->estado)
								<span class="badge {{ $user->estado->badgeClass() }} light">{{ $user->estado->label() }}</span>
							@endif
						</div>

						<ul class="d-flex flex-wrap fs-6 align-items-center mb-3">
							<li class="me-3 d-inline-flex align-items-center">
								<i class="las la-user-tag me-1 fs-18"></i>{{ $user->rol?->label() ?? 'Usuario' }}
							</li>
							<li class="me-3 d-inline-flex align-items-center">
								<i class="las la-envelope me-1 fs-18"></i>{{ $user->email }}
							</li>
							<li class="me-3 d-inline-flex align-items-center">
								<i class="las la-building me-1 fs-18"></i>{{ $user->tenant?->name ?? 'Sin empresa' }}
							</li>
							<li class="me-3 d-inline-flex align-items-center">
								<i class="las la-calendar me-1 fs-18"></i>Miembro desde
								{{ $user->created_at?->enZonaTenant()?->translatedFormat('d/m/Y') ?? '—' }}
							</li>
						</ul>

						{{-- Cambio de foto de perfil (preview pegada encima del input) --}}
						<form method="POST" action="{{ route('profile.avatar.update') }}"
							enctype="multipart/form-data" class="mt-2" style="max-width: 22rem;">
							@csrf
							<label class="form-label" for="avatar">Cambiar foto de perfil</label>
							<input type="file" class="form-control @error('avatar') is-invalid @enderror"
								id="avatar" name="avatar" accept="image/png,image/jpeg,image/webp" required>
							@error('avatar')
								<div class="text-danger small mt-1">{{ $message }}</div>
							@enderror
							<small class="form-text text-muted d-block mb-2">PNG, JPG o WEBP, máximo 2 MB.</small>
							<button type="submit" class="btn btn-primary">Guardar foto</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script>
		document.getElementById('avatar').addEventListener('change', function (e) {
			const file = e.target.files[0];
			if (!file) return;
			document.getElementById('avatar-preview').src = URL.createObjectURL(file);
		});
	</script>
@endpush
