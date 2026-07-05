@extends('layouts.guest')

@section('title', 'Crear cuenta')

@push('styles')
	<style>
		.login-form .form-control {
			height: calc(2.1rem + 2px) !important;
			padding: 0.35rem 0.75rem !important;
			font-size: 0.8rem !important;
		}

		.login-form .btn-primary {
			padding-top: 0.4rem !important;
			padding-bottom: 0.4rem !important;
			font-size: 0.85rem !important;
		}

		.login-eu-funding {
			margin-top: 0.5rem;
		}

		.login-eu-badges {
			display: flex;
			align-items: center;
			justify-content: center;
			flex-wrap: nowrap;
			gap: 1rem;
		}

		.login-eu-badges img {
			height: 44px;
			width: auto;
			flex: 0 0 auto;
		}

		.login-eu-quote {
			flex: 0 1 9rem;
			margin: 0;
			font-size: 0.72rem;
			line-height: 1.25;
			text-align: center;
		}

		@media (max-width: 575.98px) {
			.login-eu-badges {
				flex-wrap: wrap;
			}
		}

		.login-eu-disclaimer {
			margin: 1.25rem auto 0;
			max-width: 34rem;
			font-size: 0.75rem;
			line-height: 1.4;
			text-align: center;
			color: #6c757d;
		}
	</style>
@endpush

@section('content')
	<div class="authincation h-100">
		<div class="container-fluid h-100">
			<div class="row h-100">
				<div class="col-lg-6 col-md-12 col-sm-12 mx-auto align-self-center">
					<div class="login-form">
						<div class="text-center">
							<a href="{{ url('/') }}" class="brand-logo justify-content-center mb-1 d-flex align-items-center">
								@if (function_exists('tenant') && tenant() && tenant()->login_logo_path)
									<img src="{{ asset('storage/'.tenant()->login_logo_path) }}" alt="Logo" style="max-width: 360px;">
								@else
									<img src="{{ asset('images/logardo.png') }}" alt="Logo" style="max-width: 360px;">
								@endif
							</a>
							<h3 class="title">Crear cuenta</h3>
							<p>Registrá tu solicitud de acceso. Un administrador deberá aprobarla.</p>
						</div>
						<form method="POST" action="{{ route('register.store') }}">
							@csrf
							<div class="mb-4">
								<label class="mb-1">Nombre<span class="text-danger"> *</span></label>
								<input type="text" name="name" value="{{ old('name') }}"
									class="form-control @error('name') is-invalid @enderror" autofocus>
								@error('name')
									<span class="invalid-feedback d-block">{{ $message }}</span>
								@enderror
							</div>
							<div class="mb-4">
								<label class="mb-1">Email<span class="text-danger"> *</span></label>
								<input type="email" name="email" value="{{ old('email') }}"
									class="form-control @error('email') is-invalid @enderror">
								@error('email')
									<span class="invalid-feedback d-block">{{ $message }}</span>
								@enderror
							</div>
							<div class="mb-4">
								<label class="mb-1">Contraseña<span class="text-danger"> *</span></label>
								<div class="position-relative">
									<input type="password" name="password" id="dz-password"
										class="form-control @error('password') is-invalid @enderror">
									<span class="show-pass eye">
										<i class="fa fa-eye-slash"></i>
										<i class="fa fa-eye"></i>
									</span>
								</div>
								@error('password')
									<span class="invalid-feedback d-block">{{ $message }}</span>
								@enderror
							</div>
							<div class="mb-4">
								<label class="mb-1">Confirmar contraseña<span class="text-danger"> *</span></label>
								<input type="password" name="password_confirmation" class="form-control">
							</div>
							<div class="text-center mb-4 d-grid">
								<button type="submit" class="btn btn-primary">Solicitar acceso</button>
							</div>
						</form>
						<div class="text-center mb-4">
							<p class="mb-0">¿Ya tenés cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
						</div>

						<div class="login-eu-funding">
							<div class="login-eu-badges">
								<p class="login-eu-quote">«Financiado por la Unión Europea&nbsp;&ndash; NextGenerationEU.»</p>
								<img src="{{ asset('images/login/1.png') }}"
									alt="Plan de Recuperación, Transformación y Resiliencia">
								<img src="{{ asset('images/login/2.png') }}"
									alt="Financiado por la Unión Europea - NextGenerationEU">
							</div>
							<p class="login-eu-disclaimer">
								«Financiado por la Unión Europea&nbsp;&ndash; NextGenerationEU. Sin embargo, los puntos de
								vista y las opiniones expresadas son únicamente los del autor o autores y no reflejan
								necesariamente los de la Unión Europea o la Comisión Europea. Ni la Unión Europea ni la
								Comisión Europea pueden ser consideradas responsables de las mismas»
							</p>
						</div>
					</div>
				</div>
				<div class="col-xl-6 col-lg-6 d-none d-lg-block">
					@php
						$__tenantLogin = function_exists('tenant') ? tenant() : null;
						$__loginImagen = $__tenantLogin && $__tenantLogin->login_imagen_path
							? asset('storage/'.$__tenantLogin->login_imagen_path)
							: asset('images/login.png');
						$__extrasLogin = $__tenantLogin
							? \App\Support\AparienciaTenant::extrasEfectivos($__tenantLogin->getTenantKey())
							: ['titulo_login' => \App\Support\AparienciaTenant::DEFAULT_TITULO_LOGIN, 'facebook_url' => '', 'instagram_url' => ''];
					@endphp
					<div class="pages-left h-100 position-relative text-center"
						style="background-image: url('{{ $__loginImagen }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
						<div class="login-brand-band">
							<h2 class="login-brand-title">{{ $__extrasLogin['titulo_login'] }}</h2>
							@if ($__extrasLogin['facebook_url'] || $__extrasLogin['instagram_url'])
								<div class="login-brand-socials">
									@if ($__extrasLogin['facebook_url'])
										<a href="{{ $__extrasLogin['facebook_url'] }}" target="_blank" rel="noopener">
											<i class="fa-brands fa-facebook"></i>
										</a>
									@endif
									@if ($__extrasLogin['instagram_url'])
										<a href="{{ $__extrasLogin['instagram_url'] }}" target="_blank" rel="noopener">
											<i class="fa-brands fa-instagram"></i>
										</a>
									@endif
								</div>
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
