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
								<svg width="30" height="33" viewBox="0 0 30 33" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path fill-rule="evenodd" clip-rule="evenodd"
										d="M17.9757 0.803847C16.1193 -0.267949 13.8322 -0.267949 11.9757 0.803847L3 5.986C1.14359 7.05779 0 9.03856 0 11.1822V21.5464C0 23.69 1.14359 25.6708 3 26.7426L11.9757 31.9247C13.8322 32.9965 16.1193 32.9965 17.9757 31.9247L26.9515 26.7426C28.8079 25.6708 29.9515 23.69 29.9515 21.5464V11.1821C29.9515 9.03855 28.8079 7.05779 26.9515 5.986L17.9757 0.803847ZM16.4757 6.08629C15.5475 5.5504 14.4039 5.5504 13.4757 6.0863L6.8247 9.92627C5.8965 10.4622 5.3247 11.4526 5.3247 12.5243V20.2043C5.3247 21.2761 5.8965 22.2665 6.82471 22.8024L13.4757 26.6423C14.4039 27.1782 15.5475 27.1782 16.4757 26.6423L23.1268 22.8024C24.055 22.2665 24.6268 21.2761 24.6268 20.2043V12.5243C24.6268 11.4525 24.055 10.4622 23.1268 9.92627L16.4757 6.08629Z"
										fill="var(--primary)" />
								</svg>
								<span class="ms-2 fw-bold fs-4">Empire Systems</span>
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
							<div class="mb-4 position-relative">
								<label class="mb-1">Contraseña<span class="text-danger"> *</span></label>
								<input type="password" name="password" id="dz-password"
									class="form-control @error('password') is-invalid @enderror">
								<span class="show-pass eye">
									<i class="fa fa-eye-slash"></i>
									<i class="fa fa-eye"></i>
								</span>
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
				<div class="col-xl-6 col-lg-6">
					<div class="pages-left h-100">
						<div class="login-content">
							<p>Sistema de facturación para España. Tu verdadero valor se mide en cuánto más
								das que lo que tomás a cambio.</p>
						</div>
						<div class="login-media text-center">
							<img src="{{ asset('images/login.png') }}" alt="">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
