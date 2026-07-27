@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="card profile-overview">
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
								<i class="las la-user-tag me-1 fs-18"></i>{{ $esSuperAdmin ? 'Super Admin' : ($rolReal ?? 'Sin rol') }}
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

			<div class="card">
				<div class="card-body">
					<ul class="nav nav-tabs" id="perfil-tabs" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active d-flex align-items-center" id="tab-seguridad-btn" data-bs-toggle="tab"
								data-bs-target="#tab-seguridad" type="button" role="tab"
								aria-controls="tab-seguridad" aria-selected="true">
								<x-lordicon icon="wired-outline-457-shield-security-hover-pinch" size="22" trigger="hover" target=".nav-link" />
								<span class="ms-2">Seguridad</span>
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link d-flex align-items-center" id="tab-datos-btn" data-bs-toggle="tab"
								data-bs-target="#tab-datos" type="button" role="tab"
								aria-controls="tab-datos" aria-selected="false">
								<x-lordicon icon="person" size="22" trigger="hover" target=".nav-link" />
								<span class="ms-2">Datos de la cuenta</span>
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link d-flex align-items-center" id="tab-actividad-btn" data-bs-toggle="tab"
								data-bs-target="#tab-actividad" type="button" role="tab"
								aria-controls="tab-actividad" aria-selected="false">
								<x-lordicon icon="wired-outline-153-bar-chart" size="22" trigger="hover" target=".nav-link" />
								<span class="ms-2">Actividad reciente</span>
							</button>
						</li>
						@if ($empleadoFichaje)
							<li class="nav-item" role="presentation">
								<button class="nav-link d-flex align-items-center" id="tab-fichaje-btn" data-bs-toggle="tab"
									data-bs-target="#tab-fichaje" type="button" role="tab"
									aria-controls="tab-fichaje" aria-selected="false">
									<x-lordicon icon="wired-outline-1846-employee-working-hover-working" size="22" trigger="hover" target=".nav-link" />
									<span class="ms-2">Mi puesto y fichaje</span>
								</button>
							</li>
						@endif
					</ul>

					<div class="tab-content pt-4" id="perfil-tabs-content">
						{{-- Cambio de contraseña (autoservicio) --}}
						<div class="tab-pane fade show active" id="tab-seguridad" role="tabpanel" aria-labelledby="tab-seguridad-btn">
							<form id="form-password" style="max-width: 26rem;">
								<div class="mb-3">
									<label class="form-label" for="contrasena_actual">Contraseña actual</label>
									<input type="password" class="form-control" id="contrasena_actual" name="contrasena_actual" required>
									<div class="text-danger small mt-1" data-error-for="contrasena_actual"></div>
								</div>
								<div class="mb-3">
									<label class="form-label" for="password">Contraseña nueva</label>
									<input type="password" class="form-control" id="password" name="password" minlength="8" required>
									<div class="text-danger small mt-1" data-error-for="password"></div>
								</div>
								<div class="mb-3">
									<label class="form-label" for="password_confirmation">Confirmar contraseña nueva</label>
									<input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required>
								</div>
								<small class="form-text text-muted d-block mb-2">
									Mínimo 8 caracteres. Al cambiarla se cerrarán tus sesiones activas en otros dispositivos.
								</small>
								<button type="submit" class="btn btn-primary" id="btn-guardar-password">Cambiar contraseña</button>
							</form>
						</div>

						{{-- Editar nombre + cambiar email --}}
						<div class="tab-pane fade" id="tab-datos" role="tabpanel" aria-labelledby="tab-datos-btn">
							<h5 class="fw-semibold mb-3">Editar nombre</h5>
							<form id="form-nombre" class="mb-4" style="max-width: 26rem;">
								<div class="mb-3">
									<label class="form-label" for="name">Nombre</label>
									<input type="text" class="form-control" id="name" name="name" value="{{ $user->name }}" maxlength="255" required>
									<div class="text-danger small mt-1" data-error-for="name"></div>
								</div>
								<button type="submit" class="btn btn-primary" id="btn-guardar-nombre">Guardar nombre</button>
							</form>

							<h5 class="fw-semibold mb-3">Cambiar email</h5>
							@if ($user->pending_email)
								<div class="alert alert-warning" role="alert">
									Tenés una solicitud de cambio de email pendiente a
									<strong>{{ $user->pending_email }}</strong>. Revisá tu correo para confirmarla
									(vence el {{ $user->pending_email_expires_at?->enZonaTenant()?->translatedFormat('d/m/Y H:i') }}).
								</div>
								<button type="button" class="btn btn-outline-secondary me-2" id="btn-reenviar-email">Reenviar enlace</button>
								<button type="button" class="btn btn-outline-danger" id="btn-cancelar-email">Cancelar solicitud</button>
							@else
								<form id="form-email" style="max-width: 26rem;">
									<div class="mb-3">
										<label class="form-label" for="contrasena_actual_email">Contraseña actual</label>
										<input type="password" class="form-control" id="contrasena_actual_email" name="contrasena_actual" required>
										<div class="text-danger small mt-1" data-error-for="contrasena_actual"></div>
									</div>
									<div class="mb-3">
										<label class="form-label" for="nuevo_email">Nuevo email</label>
										<input type="email" class="form-control" id="nuevo_email" name="nuevo_email" maxlength="255" required>
										<div class="text-danger small mt-1" data-error-for="nuevo_email"></div>
									</div>
									<small class="form-text text-muted d-block mb-2">
										Te enviaremos un enlace de confirmación a la nueva dirección, válido por 24 horas.
										Tu email actual sigue funcionando hasta que confirmes.
									</small>
									<button type="submit" class="btn btn-primary" id="btn-solicitar-email">Solicitar cambio</button>
								</form>
							@endif
						</div>

						{{-- Actividad reciente propia --}}
						<div class="tab-pane fade" id="tab-actividad" role="tabpanel" aria-labelledby="tab-actividad-btn">
							@can('ver-logs')
								<div class="d-flex justify-content-end mb-3">
									<a href="{{ route('logs.index') }}" class="btn btn-sm btn-outline-primary">Ver más</a>
								</div>
							@endcan
							@if ($actividadReciente->isEmpty())
								<p class="mb-0 text-muted">Sin actividad reciente.</p>
							@else
								<div class="table-responsive">
									<table class="table table-sm mb-0">
										<thead>
											<tr>
												<th>Fecha</th>
												<th>Acción</th>
												<th>Resultado</th>
												<th>Navegador</th>
												<th>Ubicación</th>
											</tr>
										</thead>
										<tbody>
											@foreach ($actividadReciente as $evento)
												<tr>
													<td>{{ $evento['fecha'] }}</td>
													<td>{{ $evento['accion_label'] }}</td>
													<td>
														<span class="badge {{ $evento['resultado'] === 'exito' ? 'badge-success' : 'badge-danger' }} light">
															{{ $evento['resultado_label'] }}
														</span>
													</td>
													<td>{{ $evento['navegador'] ?? '—' }}</td>
													<td>{{ $evento['ubicacion'] ?? '—' }}</td>
												</tr>
											@endforeach
										</tbody>
									</table>
								</div>
							@endif
						</div>

						@if ($empleadoFichaje)
							{{-- Datos de empleado y estado de fichaje en vivo --}}
							<div class="tab-pane fade" id="tab-fichaje" role="tabpanel" aria-labelledby="tab-fichaje-btn">
								<ul class="d-flex flex-wrap fs-6 align-items-center mb-3">
									@if ($empleadoFichaje['puesto'])
										<li class="me-3 d-inline-flex align-items-center">
											<i class="las la-briefcase me-1 fs-18"></i>{{ $empleadoFichaje['puesto'] }}
										</li>
									@endif
									@if ($empleadoFichaje['centroTrabajo'])
										<li class="me-3 d-inline-flex align-items-center">
											<i class="las la-map-marker me-1 fs-18"></i>{{ $empleadoFichaje['centroTrabajo'] }}
										</li>
									@endif
									<li class="me-3 d-inline-flex align-items-center">
										<span class="badge {{ ['cerrada' => 'bg-secondary', 'abierta' => 'bg-primary', 'en_pausa' => 'bg-warning text-dark'][$empleadoFichaje['estado']] }}">
											{{ $empleadoFichaje['estadoLabel'] }}
										</span>
									</li>
								</ul>

								@if ($empleadoFichaje['eventosRecientes']->isEmpty())
									<p class="mb-0 text-muted">Sin fichajes registrados todavía.</p>
								@else
									<div class="table-responsive">
										<table class="table table-sm mb-0">
											<thead>
												<tr>
													<th>Fecha</th>
													<th>Tipo</th>
													<th>Distancia</th>
												</tr>
											</thead>
											<tbody>
												@foreach ($empleadoFichaje['eventosRecientes'] as $evento)
													<tr>
														<td>{{ $evento->ocurrido_at->enZonaTenant()->format('d/m/Y H:i') }}</td>
														<td>{{ $evento->tipo->label() }}</td>
														<td>
															@if ($evento->distancia_metros !== null)
																{{ \App\Support\Formato::cantidad($evento->distancia_metros) }} m
															@else
																—
															@endif
														</td>
													</tr>
												@endforeach
											</tbody>
										</table>
									</div>
								@endif
							</div>
						@endif
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Mi perfil')
@section('ayuda')
	@include('ayuda.profile')
@endsection

@push('scripts')
	<script>
		document.getElementById('avatar').addEventListener('change', function (e) {
			const file = e.target.files[0];
			if (!file) return;
			document.getElementById('avatar-preview').src = URL.createObjectURL(file);
		});

		document.getElementById('form-password').addEventListener('submit', function (e) {
			e.preventDefault();
			var $form = this;
			var $btn = document.getElementById('btn-guardar-password');
			$form.querySelectorAll('[data-error-for]').forEach(function (el) { el.textContent = ''; });

			window.withButtonLoading($btn, function () {
				return fetch(@json(route('profile.password.update')), {
					method: 'PUT',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({
						contrasena_actual: $form.contrasena_actual.value,
						password: $form.password.value,
						password_confirmation: $form.password_confirmation.value,
					}),
				})
					.then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
					.then(function (res) {
						if (!res.ok) {
							if (res.data.errors) {
								Object.keys(res.data.errors).forEach(function (campo) {
									var $el = $form.querySelector('[data-error-for="' + campo + '"]');
									if ($el) $el.textContent = res.data.errors[campo][0];
								});
							}
							window.showToast('error', res.data.message || 'No se pudo cambiar la contraseña.');
							return;
						}
						window.showToast('success', res.data.message);
						$form.reset();
					})
					.catch(function () { window.showToast('error', 'No se pudo cambiar la contraseña.'); });
			});
		});

		function enviarJson(url, method, btn, body) {
			return window.withButtonLoading(btn, function () {
				return fetch(url, {
					method: method,
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: body ? JSON.stringify(body) : undefined,
				}).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); });
			});
		}

		var formNombre = document.getElementById('form-nombre');
		if (formNombre) {
			formNombre.addEventListener('submit', function (e) {
				e.preventDefault();
				formNombre.querySelectorAll('[data-error-for]').forEach(function (el) { el.textContent = ''; });

				enviarJson(@json(route('profile.nombre.update')), 'PUT', document.getElementById('btn-guardar-nombre'), {
					name: formNombre.name.value,
				})
					.then(function (res) {
						if (!res.ok) {
							if (res.data.errors) {
								Object.keys(res.data.errors).forEach(function (campo) {
									var $el = formNombre.querySelector('[data-error-for="' + campo + '"]');
									if ($el) $el.textContent = res.data.errors[campo][0];
								});
							}
							window.showToast('error', res.data.message || 'No se pudo actualizar el nombre.');
							return;
						}
						window.showToast('success', res.data.message);
					})
					.catch(function () { window.showToast('error', 'No se pudo actualizar el nombre.'); });
			});
		}

		var formEmail = document.getElementById('form-email');
		if (formEmail) {
			formEmail.addEventListener('submit', function (e) {
				e.preventDefault();
				formEmail.querySelectorAll('[data-error-for]').forEach(function (el) { el.textContent = ''; });

				enviarJson(@json(route('profile.email.solicitar')), 'POST', document.getElementById('btn-solicitar-email'), {
					contrasena_actual: formEmail.contrasena_actual.value,
					nuevo_email: formEmail.nuevo_email.value,
				})
					.then(function (res) {
						if (!res.ok) {
							if (res.data.errors) {
								Object.keys(res.data.errors).forEach(function (campo) {
									var $el = formEmail.querySelector('[data-error-for="' + campo + '"]');
									if ($el) $el.textContent = res.data.errors[campo][0];
								});
							}
							window.showToast('error', res.data.message || 'No se pudo solicitar el cambio de email.');
							return;
						}
						window.showToast('success', res.data.message);
						window.location.reload();
					})
					.catch(function () { window.showToast('error', 'No se pudo solicitar el cambio de email.'); });
			});
		}

		var btnReenviar = document.getElementById('btn-reenviar-email');
		if (btnReenviar) {
			btnReenviar.addEventListener('click', function () {
				enviarJson(@json(route('profile.email.reenviar')), 'POST', btnReenviar)
					.then(function (res) {
						window.showToast(res.ok ? 'success' : 'error', res.data.message || 'No se pudo reenviar el enlace.');
					})
					.catch(function () { window.showToast('error', 'No se pudo reenviar el enlace.'); });
			});
		}

		var btnCancelar = document.getElementById('btn-cancelar-email');
		if (btnCancelar) {
			btnCancelar.addEventListener('click', function () {
				enviarJson(@json(route('profile.email.cancelar')), 'DELETE', btnCancelar)
					.then(function (res) {
						if (!res.ok) {
							window.showToast('error', res.data.message || 'No se pudo cancelar la solicitud.');
							return;
						}
						window.showToast('success', res.data.message);
						window.location.reload();
					})
					.catch(function () { window.showToast('error', 'No se pudo cancelar la solicitud.'); });
			});
		}
	</script>
@endpush
