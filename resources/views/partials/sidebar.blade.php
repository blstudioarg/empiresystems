		<style>
			/* Badge de notificación "tipo esquina" para el menú lateral: un punto rojo con
			   contador, superpuesto al icono, en vez del pill grande junto al texto (leía
			   demasiado invasivo). El anillo blanco lo separa visualmente del icono debajo.
			   Nota: este parcial se incluye DESPUÉS de que el layout ya imprimió el stack de
			   estilos del head, así que un bloque apilado ahí nunca llegaría a tiempo — por
			   eso el <style> va inline aquí mismo, en el punto de inclusión. */
			.nav-icon-badge-wrap { position: relative; }
			.nav-icon-badge {
				position: absolute; top: -4px; right: -6px; z-index: 1;
				min-width: 16px; height: 16px; padding: 0 3px; border-radius: 999px;
				background: #e5534b; color: #fff; font-size: .62rem; font-weight: 700; line-height: 16px;
				text-align: center; box-shadow: 0 0 0 2px #fff;
			}
			.nav-link-badge-wrap { display: flex; align-items: center; gap: .4rem; }
			.nav-inline-badge {
				min-width: 16px; height: 16px; padding: 0 3px; border-radius: 999px;
				background: #e5534b; color: #fff; font-size: .62rem; font-weight: 700; line-height: 16px;
				text-align: center; display: inline-block;
			}
		</style>
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
					<div class="sidebar-user-role">{{ auth()->user()->isSuperAdmin() ? auth()->user()->rol->value : (auth()->user()->getRoleNames()->first() ?? 'Sin rol') }}</div>
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
						{{-- Inicio: solo si el rol tiene acceso al dashboard; el resto aterriza en Mi jornada (D11). --}}
						<li><a href="{{ auth()->user()->can('ver-dashboard') ? route('dashboard') : route('mi-jornada.index') }}">
								<div class="menu-icon">
									<x-lordicon icon="home" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Inicio</span>
							</a>
						</li>
						{{-- Control de fichaje: Fichar/Mi jornada son personales (siempre visibles); el bloque
						     de gestión va bajo can:ver-jornada. --}}
						@php
							$__alertasNuevas = auth()->user()->can('ver-jornada')
								? \App\Models\Alerta::where('tenant_id', tenant()->getTenantKey())
									->where('estado', \App\Enums\EstadoAlerta::Nueva)
									->count()
								: 0;
						@endphp
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon nav-icon-badge-wrap">
									<x-lordicon icon="wired-outline-1846-employee-working-hover-working" size="30" trigger="hover" />
									@if ($__alertasNuevas > 0)
										<span class="nav-icon-badge">{{ $__alertasNuevas > 9 ? '9+' : $__alertasNuevas }}</span>
									@endif
								</div>
								<span class="nav-text ms-2">Control de fichaje</span>
							</a>
							<ul aria-expanded="false">
								<li><a href="{{ route('fichajes.index') }}">Fichar</a></li>
								<li><a href="{{ route('mi-jornada.index') }}">Mi jornada</a></li>
								@can('ver-jornada')
									<li><a href="{{ route('jornada.index') }}">Jornada</a></li>
									<li><a href="{{ route('calendario.index') }}">Calendario</a></li>
									<li><a href="{{ route('miembros-equipo.index') }}">Miembros</a></li>
									<li><a href="{{ route('horarios.index') }}">Horarios</a></li>
									<li><a href="{{ route('alertas.index') }}" class="nav-link-badge-wrap">
										Alertas
										@if ($__alertasNuevas > 0)
											<span class="nav-inline-badge">{{ $__alertasNuevas > 9 ? '9+' : $__alertasNuevas }}</span>
										@endif
									</a></li>
								@endcan
							</ul>
						</li>
						@can('ver-clientes')
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
						@endcan
						@canany(['ver-leads', 'ver-oportunidades', 'ver-presupuestos'])
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="wired-outline-456-handshake-deal-hover-pinch" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">CRM</span>
							</a>
							<ul aria-expanded="false">
								@can('ver-leads')<li><a href="{{ route('leads.index') }}">Leads</a></li>@endcan
								@can('ver-oportunidades')<li><a href="{{ route('oportunidades.index') }}">Oportunidades</a></li>@endcan
								@can('ver-presupuestos')<li><a href="{{ route('presupuestos.index') }}">Presupuestos</a></li>@endcan
							</ul>
						</li>
						@endcanany
						@canany(['ver-articulos', 'ver-stock', 'ver-proveedores', 'ver-compras'])
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="box" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Stock</span>
							</a>
							<ul aria-expanded="false">
								@can('ver-articulos')<li><a href="{{ route('articulos.index') }}">Catálogo</a></li>@endcan
								@can('ver-stock')<li><a href="{{ route('stock.index') }}">Kardex</a></li>@endcan
								@can('ver-proveedores')<li><a href="{{ route('proveedores.index') }}">Proveedores</a></li>@endcan
								@can('ver-compras')<li><a href="{{ route('compras.index') }}">Compras</a></li>@endcan
							</ul>
						</li>
						@endcanany
						@can('ver-facturas')
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
						@endcan
						@can('ver-pos')
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
						@endcan
						@can('ver-archivos')
						<li><a href="{{ route('archivos.index') }}">
								<div class="menu-icon">
									<x-lordicon icon="system-regular-49-upload-file" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Archivos</span>
							</a>
						</li>
						@endcan
						@canany(['ver-campanas', 'ver-plantillas-email'])
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="system-regular-1-share" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Marketing</span>
							</a>
							<ul aria-expanded="false">
								@can('ver-campanas')
									<li><a href="{{ route('campanas.index') }}">Campañas</a></li>
									<li><a href="{{ route('campanas.create') }}">Nueva campaña</a></li>
								@endcan
								@can('ver-plantillas-email')<li><a href="{{ route('plantillas-email.index') }}">Plantillas de email</a></li>@endcan
							</ul>
						</li>
						@endcanany
						@canany(['ver-usuarios', 'ver-roles'])
						<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
								<div class="menu-icon">
									<x-lordicon icon="person" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Usuarios</span>
							</a>
							<ul aria-expanded="false">
								@can('ver-usuarios')<li><a href="{{ route('usuarios.index') }}">Usuarios</a></li>@endcan
								@can('ver-roles')@if (\Illuminate\Support\Facades\Route::has('roles.index'))<li><a href="{{ route('roles.index') }}">Roles</a></li>@endif @endcan
							</ul>
						</li>
						@endcanany
					@endif
				</ul>
				<div class="help-desk">
					<button type="button" class="ayuda-trigger" data-bs-toggle="modal" data-bs-target="#ayudaContextualModal">
						<span class="ayuda-trigger-icon">
							<x-lordicon icon="wired-outline-424-question-bubble-hover-wiggle" trigger="hover" size="22" target=".ayuda-trigger" />
						</span>
						<span class="ayuda-trigger-text">
							<span class="ayuda-trigger-title">Ayuda de esta pantalla</span>
							<span class="ayuda-trigger-sub">Guía rápida de lo que ves ahora</span>
						</span>
						<i class="fas fa-chevron-right ayuda-trigger-chevron"></i>
					</button>
				</div>
				{{-- Dark mode oculto a pedido explícito (no se usa por ahora): no se borra el
					mecanismo (dzSettingsOptions sigue funcionando si se reactiva), solo se
					esconde el toggle para que nadie pueda pasar a dark mode mientras tanto. --}}
				<div class="mode-btn d-flex align-items-center justify-content-between d-none">
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