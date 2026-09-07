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
					<x-avatar-editable />
					<div class="sidebar-user-name">{{ auth()->user()->name }}</div>
					<div class="sidebar-user-role">{{ auth()->user()->isSuperAdmin() ? auth()->user()->rol->value : (auth()->user()->getRoleNames()->first() ?? 'Sin rol') }}</div>
				</div>
				<ul class="metismenu" id="menu">
					@if (auth()->user()->isSuperAdmin())
						<li><a href="{{ route('super_admin.home') }}">
								<div class="menu-icon">
									<x-lordicon icon="home" size="30" trigger="hover" />
								</div>
								<span class="nav-text ms-2">Inicio</span>
							</a>
						</li>
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
						{{-- Menú dirigido por catálogo (feature 036): sidebar.blade.php ya no declara el menú
						     del tenant a mano. Una entrada nueva se añade a App\Support\CatalogoMenu, no acá —
						     ver docs/04-front-guidelines.md, "Nueva entrada de menú ⇒ nuevo permiso". La
						     personalización (nombre/orden) la resuelve MenuTenant::estructura(); la
						     visibilidad por permiso se sigue evaluando acá, igual que antes. --}}
						@php
							$__alertasNuevas = auth()->user()->can('ver-alertas')
								? \App\Models\Alerta::where('tenant_id', tenant()->getTenantKey())
									->where('estado', \App\Enums\EstadoAlerta::Nueva)
									->count()
								: 0;
						@endphp
						@foreach (\App\Support\MenuTenant::estructura(tenant()->getTenantKey()) as $__grupo)
							@if (empty($__grupo['hijos']))
								{{-- Grupo sin hijos: enlace directo (caso "Inicio"/"Archivos", D6.1) --}}
								@if ($__grupo['ruta'] && \Illuminate\Support\Facades\Route::has($__grupo['ruta']) && (! $__grupo['permiso'] || auth()->user()->can($__grupo['permiso'])))
									<li><a href="{{ route($__grupo['ruta']) }}">
											<div class="menu-icon">
												<x-lordicon icon="{{ $__grupo['icono'] }}" size="30" trigger="hover" />
											</div>
											<span class="nav-text ms-2">{{ $__grupo['etiqueta'] }}</span>
										</a>
									</li>
								@endif
							@else
								@php
									$__hijosVisibles = collect($__grupo['hijos'])->filter(function ($hijo) {
										return $hijo['ruta']
											&& \Illuminate\Support\Facades\Route::has($hijo['ruta'])
											&& (! $hijo['permiso'] || auth()->user()->can($hijo['permiso']));
									})->values();
								@endphp
								{{-- Grupo con hijos: visible si al menos un hijo sobrevive al filtro de permiso
								     (equivalente al @canany de antes, derivado del catálogo — research.md D2) --}}
								@if ($__hijosVisibles->isNotEmpty())
									<li><a class="has-arrow " href="javascript:void(0);" aria-expanded="false">
											<div class="menu-icon @if ($__grupo['clave'] === 'control-fichaje') nav-icon-badge-wrap @endif">
												<x-lordicon icon="{{ $__grupo['icono'] }}" size="30" trigger="hover" />
												@if ($__grupo['clave'] === 'control-fichaje' && $__alertasNuevas > 0)
													<span class="nav-icon-badge">{{ $__alertasNuevas > 9 ? '9+' : $__alertasNuevas }}</span>
												@endif
											</div>
											<span class="nav-text ms-2">{{ $__grupo['etiqueta'] }}</span>
										</a>
										<ul aria-expanded="false">
											@foreach ($__hijosVisibles as $__hijo)
												{{-- Badge de alertas condicionado a la CLAVE, nunca a la etiqueta
												     personalizada (D6.2): renombrar "Alertas" no debe ocultarlo. --}}
												@if ($__hijo['clave'] === 'alertas')
													<li><a href="{{ route($__hijo['ruta']) }}" class="nav-link-badge-wrap">
															{{ $__hijo['etiqueta'] }}
															@if ($__alertasNuevas > 0)
																<span class="nav-inline-badge">{{ $__alertasNuevas > 9 ? '9+' : $__alertasNuevas }}</span>
															@endif
														</a></li>
												@else
													<li><a href="{{ route($__hijo['ruta']) }}">{{ $__hijo['etiqueta'] }}</a></li>
												@endif
											@endforeach
										</ul>
									</li>
								@endif
							@endif
						@endforeach
					@endif
				</ul>
				<div class="help-desk pb-3">
					<button type="button" class="ayuda-trigger" data-bs-toggle="modal" data-bs-target="#ayudaContextualModal"
						title="Ayuda de esta pantalla" aria-label="Ayuda de esta pantalla">
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
