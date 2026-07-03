@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
	<div class="authincation h-100">
		<div class="container-fluid h-100">
			<div class="row h-100">
				<div class="col-lg-6 col-md-12 col-sm-12 mx-auto align-self-center">
					<div class="login-form">
						<div class="text-center">
							<a href="{{ url('/') }}" class="brand-logo justify-content-center mb-3 d-flex align-items-center">
								@if (function_exists('tenant') && tenant() && tenant()->login_logo_path)
									<img src="{{ asset('storage/'.tenant()->login_logo_path) }}" alt="Logo" style="max-width: 250px;">
								@else
									<img src="{{ asset('images/logardo.png') }}" alt="Logo" style="max-width: 250px;">
								@endif
							</a>
							<h3 class="title">Iniciar sesión</h3>
							<p>Ingresá con tu email y contraseña para acceder al panel</p>
						</div>
						<form method="POST" action="{{ route('login.attempt') }}">
							@csrf
							<div class="mb-4">
								<label class="mb-1">Email<span class="text-danger"> *</span></label>
								<input type="email" name="email" value="{{ old('email') }}"
									class="form-control @error('email') is-invalid @enderror" autofocus>
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
							<div class="form-row d-flex justify-content-between mt-4 mb-2">
								<div class="mb-4">
									<div class="form-check custom-checkbox mb-3">
										<input type="checkbox" name="remember" class="form-check-input" id="customCheckBox1">
										<label class="form-check-label mt-1" for="customCheckBox1">Recordarme</label>
									</div>
								</div>
							</div>
							<div class="text-center mb-4 d-grid">
								<button type="submit" class="btn btn-primary">Iniciar sesión</button>
							</div>
						</form>
						<div class="text-center">
							<p>¿No tenés cuenta? <a href="{{ route('register.create') }}">Crear cuenta</a></p>
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
