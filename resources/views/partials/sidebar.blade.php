		<div class="deznav">
			<div class="deznav-scroll grid-menu">
				<div class="sidebar-user-card text-center">
					<form id="sidebar-avatar-form" action="{{ route('profile.avatar.update') }}" method="POST" enctype="multipart/form-data">
						@csrf
						<div class="sidebar-user-avatar">
							<img id="sidebar-avatar-preview" src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}">
							<label for="sidebar-avatar-input" class="sidebar-user-avatar-edit" title="Cambiar foto">
								<i class="fas fa-camera"></i>
							</label>
							<input type="file" id="sidebar-avatar-input" name="avatar" accept="image/*" class="d-none">
						</div>
					</form>
					<div class="sidebar-user-name">{{ auth()->user()->name }}</div>
					<div class="sidebar-user-role">{{ auth()->user()->rol->value }}</div>
				</div>
				<ul class="metismenu" id="menu">
					@if (auth()->user()->isSuperAdmin())
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="empresa" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Tenants</span>
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('super_admin.tenants.index') }}">Gestión de tenants</a></li>
							</ul>
						</li>
					@else
						<li><a href="{{ route('dashboard') }}">
								<div class="menu-icon">
									<x-lordicon icon="home" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Inicio</span>
							</a>
						</li>
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="wired-outline-1846-employee-working-hover-working" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Control de fichaje</span>
								@php
									$__alertasNuevas = auth()->user()->rol === \App\Enums\UserRole::Admin
										? \App\Models\Alerta::where('tenant_id', tenant()->getTenantKey())
											->where('estado', \App\Enums\EstadoAlerta::Nueva)
											->count()
										: 0;
								@endphp
								@if ($__alertasNuevas > 0)
									<span class="badge badge-danger ms-2">{{ $__alertasNuevas }}</span>
								@endif
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('fichajes.index') }}">Fichar</a></li>
								<li><a href="{{ route('mi-jornada.index') }}">Mi jornada</a></li>
								@if (auth()->user()->rol === \App\Enums\UserRole::Admin)
									<li><a href="{{ route('jornada.index') }}">Jornada</a></li>
									<li><a href="{{ route('calendario.index') }}">Calendario</a></li>
									<li><a href="{{ route('miembros-equipo.index') }}">Miembros</a></li>
									<li><a href="{{ route('horarios.index') }}">Horarios</a></li>
									<li><a href="{{ route('alertas.index') }}">
										Alertas
										@if ($__alertasNuevas > 0)
											<span class="badge badge-danger ms-1">{{ $__alertasNuevas }}</span>
										@endif
									</a></li>
								@endif
							</ul>
						</li>
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="empresa" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Clientes</span>
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('clientes.index') }}">Cartera de clientes</a></li>
							</ul>
						</li>
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="box" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Stock</span>
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('articulos.index') }}">Catálogo</a></li>
								<li><a href="{{ route('stock.index') }}">Kardex</a></li>
								<li><a href="{{ route('proveedores.index') }}">Proveedores</a></li>
								<li><a href="{{ route('compras.index') }}">Compras</a></li>
							</ul>
						</li>
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="invoice" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Facturas</span>
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('facturas.index') }}">Facturas</a></li>
									<li><a href="{{ route('facturas.create') }}">Crear factura</a></li>
							</ul>
						</li>
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="ticket" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">POS</span>
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('pos.index') }}">Facturas simplificadas</a></li>
								<li><a href="{{ route('pos.create') }}">Crear ticket</a></li>
							</ul>
						</li>
						<li><a href="{{ route('archivos.index') }}">
								<div class="menu-icon">
									<x-lordicon icon="system-regular-49-upload-file" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Archivos</span>
							</a>
						</li>
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="system-regular-1-share" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Marketing</span>
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('campanas.index') }}">Campañas</a></li>
								<li><a href="{{ route('campanas.create') }}">Nueva campaña</a></li>
								<li><a href="{{ route('plantillas-email.index') }}">Plantillas de email</a></li>
							</ul>
						</li>
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="person" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Usuarios</span>
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('usuarios.index') }}">Usuarios</a></li>
							</ul>
						</li>
					@endif
				</ul>
				<div class="help-desk">
					<a href="https://nexadash.dexignzone.com/laravel/doc/" target="_blank" class="btn btn-light">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path
								d="M7.30074 14.8016H12.7043C12.8884 14.8016 13.0428 14.7386 13.1673 14.6124C13.2919 14.4863 13.3542 14.33 13.3542 14.1436C13.3542 13.9586 13.2919 13.8045 13.1673 13.6814C13.0428 13.5584 12.8884 13.4968 12.7043 13.4968H7.29576C7.11162 13.4968 6.95727 13.5594 6.8327 13.6846C6.70813 13.8098 6.64584 13.9649 6.64584 14.1499C6.64584 14.335 6.70861 14.4899 6.83413 14.6146C6.95965 14.7393 7.11518 14.8016 7.30074 14.8016ZM7.30074 11.2599H12.7043C12.8884 11.2599 13.0428 11.1969 13.1673 11.0708C13.2919 10.9447 13.3542 10.7884 13.3542 10.6019C13.3542 10.4169 13.2919 10.2628 13.1673 10.1398C13.0428 10.0167 12.8884 9.95515 12.7043 9.95515H7.29576C7.11162 9.95515 6.95727 10.0177 6.8327 10.1429C6.70813 10.2681 6.64584 10.4232 6.64584 10.6083C6.64584 10.7933 6.70861 10.9482 6.83413 11.0729C6.95965 11.1976 7.11518 11.2599 7.30074 11.2599ZM4.62818 18.4579C4.24486 18.4579 3.91246 18.3171 3.63099 18.0357C3.34953 17.7542 3.2088 17.4218 3.2088 17.0385V2.96148C3.2088 2.5768 3.34953 2.24324 3.63099 1.9608C3.91246 1.67834 4.24486 1.53711 4.62818 1.53711H11.5634C11.7599 1.53711 11.9471 1.57682 12.1252 1.65623C12.3032 1.73564 12.4561 1.8392 12.5838 1.96692L16.3664 5.74953C16.4941 5.87725 16.5977 6.03013 16.6771 6.20817C16.7565 6.38621 16.7962 6.57346 16.7962 6.76992V17.0385C16.7962 17.4218 16.655 17.7542 16.3725 18.0357C16.0901 18.3171 15.7565 18.4579 15.3718 18.4579H4.62818ZM11.4343 6.1268V2.96148H4.62818V17.0385H15.3718V6.83648H12.144C11.9437 6.83648 11.7753 6.76829 11.6389 6.6319C11.5025 6.4955 11.4343 6.32713 11.4343 6.1268Z"
								fill="#6F767E" />
						</svg>
						<span>Live Documentation</span></a>
					<a href="javascript:void(0)" class="btn-link d-flex align-items-center mt-4 help-btn">
						<svg class="me-2" width="24" height="24" viewBox="0 0 24 24" fill="none"
							xmlns="http://www.w3.org/2000/svg">
							<path
								d="M12.0887 17.825C12.3629 17.825 12.5976 17.7271 12.7929 17.5313C12.9882 17.3356 13.0858 17.0994 13.0858 16.8226C13.0858 16.5459 12.988 16.3112 12.7922 16.1184C12.5964 15.9257 12.3605 15.8293 12.0843 15.8293C11.8082 15.8293 11.5734 15.9258 11.3801 16.1187C11.1868 16.3116 11.0902 16.5477 11.0902 16.827C11.0902 17.1018 11.1873 17.3368 11.3816 17.5321C11.5759 17.7274 11.8116 17.825 12.0887 17.825ZM12.0075 22.1494C10.6041 22.1494 9.28708 21.8838 8.05642 21.3525C6.82577 20.8212 5.74945 20.0945 4.82745 19.1725C3.90545 18.2505 3.17879 17.1738 2.64747 15.9425C2.11617 14.7112 1.85052 13.3931 1.85052 11.9883C1.85052 10.5835 2.11617 9.2652 2.64747 8.03332C3.17879 6.80142 3.90513 5.72853 4.8265 4.81464C5.74788 3.90078 6.82439 3.17729 8.05603 2.64419C9.28767 2.11109 10.606 1.84454 12.0111 1.84454C13.4163 1.84454 14.735 2.11094 15.9674 2.64374C17.1997 3.17653 18.2727 3.89959 19.1863 4.81294C20.0999 5.72629 20.8231 6.80032 21.356 8.03502C21.889 9.26974 22.1554 10.5892 22.1554 11.9933C22.1554 13.3973 21.8889 14.7145 21.3558 15.9451C20.8227 17.1757 20.0992 18.2502 19.1853 19.1686C18.2714 20.0871 17.1975 20.8135 15.9635 21.3479C14.7295 21.8823 13.4108 22.1494 12.0075 22.1494ZM12.0125 20.4462C14.3549 20.4462 16.3461 19.6233 17.9861 17.9774C19.6262 16.3315 20.4462 14.3349 20.4462 11.9875C20.4462 9.64511 19.6277 7.65389 17.9908 6.01384C16.3539 4.37381 14.3559 3.55379 11.997 3.55379C9.66092 3.55379 7.66972 4.37225 6.02335 6.00917C4.37697 7.64609 3.55377 9.64403 3.55377 12.003C3.55377 14.339 4.37671 16.3303 6.02257 17.9766C7.66844 19.623 9.66507 20.4462 12.0125 20.4462ZM12.0574 7.59564C12.537 7.59564 12.9595 7.74369 13.325 8.03979C13.6906 8.33588 13.8734 8.71178 13.8734 9.16749C13.8734 9.59036 13.7479 9.95941 13.4971 10.2746C13.2463 10.5899 12.9626 10.8824 12.6462 11.1522C12.2628 11.4728 11.9285 11.8294 11.6432 12.2219C11.3579 12.6144 11.2152 13.0535 11.2152 13.5391C11.2152 13.7326 11.2894 13.887 11.4377 14.0022C11.5861 14.1174 11.7592 14.175 11.957 14.175C12.169 14.175 12.3456 14.1043 12.4868 13.963C12.628 13.8217 12.7233 13.6471 12.7728 13.4391C12.8268 13.1011 12.957 12.7953 13.1636 12.5218C13.3701 12.2483 13.6131 12.0008 13.8927 11.7793C14.3015 11.4464 14.6373 11.0527 14.9 10.5982C15.1627 10.1438 15.294 9.65604 15.294 9.13502C15.294 8.29029 14.9835 7.58378 14.3625 7.01549C13.7415 6.44719 13.0049 6.16304 12.1527 6.16304C11.5452 6.16304 10.9639 6.29103 10.4088 6.54699C9.85362 6.80298 9.39507 7.17662 9.03312 7.66792C8.91247 7.84257 8.85719 8.03164 8.86727 8.23514C8.87734 8.43864 8.96335 8.60284 9.1253 8.72772C9.3275 8.86504 9.5375 8.90769 9.7553 8.85569C9.9731 8.80371 10.1539 8.68206 10.2978 8.49074C10.5023 8.20831 10.7588 7.98852 11.0673 7.83137C11.3758 7.67422 11.7058 7.59564 12.0574 7.59564Z"
								fill="#6F767E" />
						</svg>
						<span>Help & getting started</span></a>
				</div>
				<div class="mode-btn d-flex align-items-center justify-content-between">
					<div class="d-mode">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<g clip-path="url(#clip0_4_82)">
								<path
									d="M12.025 23.3407L8.62955 20.0479H3.95118V15.3728L0.584229 12L3.95208 8.62704V3.94519H8.6272L12.025 0.572266L15.3731 3.94497H20.055V8.62694L23.4277 12L20.0549 15.3704V20.0488H15.3728L12.025 23.3407ZM12.025 18.3445C13.7812 18.3445 15.2745 17.7251 16.5049 16.4863C17.7353 15.2474 18.3506 13.7439 18.3506 11.9757C18.3506 10.2214 17.7348 8.72844 16.5034 7.49684C15.2719 6.26524 13.7791 5.64944 12.025 5.64944V18.3445ZM12.025 20.9538L14.6609 18.347H18.3513V14.6568L21.0098 12L18.3493 9.33697V5.64874H14.6645L12.025 2.99022L9.34323 5.64874H5.65298V9.33547L2.9962 12L5.65545 14.6592V18.3445H9.31575L12.025 20.9538Z"
									fill="#6F767E" />
							</g>
							<defs>
								<clipPath id="clip0_4_82">
									<rect width="24" height="24" fill="white" />
								</clipPath>
							</defs>
						</svg>
						<span class="ms-2">Dark Mode</span>
					</div>
					<div class="dz-layout light">
						<i class="fas fa-sun sun"></i>
						<i class="fas fa-moon moon"></i>
					</div>
				</div>
			</div>
		</div>

		@push('scripts')
		<script>
			document.getElementById('sidebar-avatar-input').addEventListener('change', function (e) {
				if (!e.target.files.length) return;

				const form = document.getElementById('sidebar-avatar-form');
				const formData = new FormData(form);

				fetch(form.action, {
					method: 'POST',
					headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
					body: formData,
				})
					.then((response) => response.json().then((data) => ({ ok: response.ok, data })))
					.then(({ ok, data }) => {
						if (!ok) {
							const message = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'No se pudo actualizar la foto.');
							window.showToast('error', message);
							return;
						}

						document.getElementById('sidebar-avatar-preview').src = data.avatar_url;
						document.querySelectorAll('.header-media img, .products img.avatar-md').forEach((img) => {
							img.src = data.avatar_url;
						});
						window.showToast('success', data.message);
					})
					.catch(() => window.showToast('error', 'No se pudo actualizar la foto.'));
			});
		</script>
		@endpush