		<div class="header">
            <div class="header-content">
                <nav class="navbar navbar-expand">
                    <div class="collapse navbar-collapse justify-content-between">
                        <div class="header-left">
							@php
    $menuConfig = config('dz.pagelevel.'.$CurrentPage.'.front-menu');
@endphp
							@if(isset($menuConfig))
							<div class="front-menu">
								<a href="{{ url('chat') }}" class="active">Apps</a>
								<a href="{{ url('page-login') }}">Pages</a>
								<a href="{{ url('uc-lightgallery') }}">Plugins</a>
							</div>
				@endif
                        </div>
                        <ul class="navbar-nav header-right">
						
							@php
								$__usuarioIa = auth()->user();
								$__iaConfigurada = $__usuarioIa && function_exists('tenant') && tenant() ? \App\Support\IaTenant::configurada() : false;
								$__puedeConfigurarIa = $__usuarioIa && $__usuarioIa->can('ver-configuracion');
								$__mostrarAsistente = $__usuarioIa && ! $__usuarioIa->isSuperAdmin() && ($__iaConfigurada || $__puedeConfigurarIa) && ! request()->routeIs('fichajes.index');
							@endphp
							@if ($__mostrarAsistente)
								<li class="nav-item dropdown notification_dropdown">
									<a class="nav-link" href="javascript:void(0);" id="asistente-toggle" title="Asistente IA" aria-label="Abrir asistente IA">
										{{-- Destello/"sparkles": el símbolo con el que hoy se reconoce la IA generativa en
											cualquier producto. El bocadillo de chat anterior no decía "IA", decía "mensajes"
											— y encima competía visualmente con las notificaciones. --}}
										<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
										stroke="#ffffff"	 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
											<path d="M12 3.2 13.65 8.1a4 4 0 0 0 2.5 2.5l4.9 1.65-4.9 1.65a4 4 0 0 0-2.5 2.5L12 21.3l-1.65-4.9a4 4 0 0 0-2.5-2.5L2.95 12.25l4.9-1.65a4 4 0 0 0 2.5-2.5z"></path>
											<path d="M18.6 3v3.4"></path>
											<path d="M20.3 4.7h-3.4"></path>
										</svg>
									</a>
								</li>
							@endif
							 
							<li class="nav-item dropdown notification_dropdown">
                                <a class="nav-link dz-fullscreen" href="javascript:void(0);">
									<svg width="22" height="22" viewBox="0 0 19 19" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
									<path d="M1.56896 18.5C1.26722 18.5 1.01362 18.3973 0.808174 18.1918C0.602725 17.9864 0.5 17.7328 0.5 17.431V13.1751C0.5 12.8734 0.603248 12.6198 0.809743 12.4143C1.01624 12.2089 1.27114 12.1062 1.57445 12.1062C1.87774 12.1062 2.1308 12.2089 2.33364 12.4143C2.53647 12.6198 2.63789 12.8734 2.63789 13.1751V16.3621H5.8249C6.12664 16.3621 6.38024 16.4654 6.58569 16.6719C6.79111 16.8784 6.89383 17.1333 6.89383 17.4366C6.89383 17.7399 6.79111 17.9929 6.58569 18.1957C6.38024 18.3986 6.12664 18.5 5.8249 18.5H1.56896ZM1.56344 6.90133C1.26015 6.90133 1.00709 6.79861 0.804251 6.59319C0.601417 6.38774 0.5 6.13414 0.5 5.8324V1.57646C0.5 1.27472 0.602725 1.01987 0.808174 0.811908C1.01362 0.603969 1.26722 0.5 1.56896 0.5H5.8249C6.12664 0.5 6.38024 0.604504 6.58569 0.813509C6.79111 1.02249 6.89383 1.27864 6.89383 1.58195C6.89383 1.88524 6.79111 2.1383 6.58569 2.34114C6.38024 2.54397 6.12664 2.64539 5.8249 2.64539H2.63789V5.8324C2.63789 6.13414 2.53464 6.38774 2.32814 6.59319C2.12165 6.79861 1.86675 6.90133 1.56344 6.90133ZM13.1676 18.5C12.8659 18.5 12.6123 18.3968 12.4068 18.1903C12.2014 17.9838 12.0987 17.7289 12.0987 17.4256C12.0987 17.1223 12.2014 16.8692 12.4068 16.6664C12.6123 16.4635 12.8659 16.3621 13.1676 16.3621H16.3546V13.1751C16.3546 12.8734 16.4579 12.6198 16.6644 12.4143C16.8709 12.2089 17.1258 12.1062 17.4291 12.1062C17.7324 12.1062 17.9867 12.2089 18.192 12.4143C18.3973 12.6198 18.5 12.8734 18.5 13.1751V17.431C18.5 17.7328 18.396 17.9864 18.1881 18.1918C17.9801 18.3973 17.7253 18.5 17.4235 18.5H13.1676ZM17.4181 6.90133C17.1148 6.90133 16.8617 6.79861 16.6589 6.59319C16.456 6.38774 16.3546 6.13414 16.3546 5.8324V2.64539H13.1676C12.8659 2.64539 12.6123 2.54214 12.4068 2.33564C12.2014 2.12915 12.0987 1.87424 12.0987 1.57093C12.0987 1.26765 12.2014 1.01333 12.4068 0.807986C12.6123 0.602662 12.8659 0.5 13.1676 0.5H17.4235C17.7253 0.5 17.9801 0.603969 18.1881 0.811908C18.396 1.01987 18.5 1.27472 18.5 1.57646V5.8324C18.5 6.13414 18.3955 6.38774 18.1865 6.59319C17.9775 6.79861 17.7214 6.90133 17.4181 6.90133Z" fill="currentColor"/>
									</svg>
                                </a>
							</li>
							<li class="nav-item dropdown notification_dropdown">
								<a class="nav-link" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false">
									<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 25 25" fill="none" aria-hidden="true">
										<path d="M12.5 12.5C14.9853 12.5 17 10.4853 17 8C17 5.51472 14.9853 3.5 12.5 3.5C10.0147 3.5 8 5.51472 8 8C8 10.4853 10.0147 12.5 12.5 12.5Z" fill="currentColor"/>
										<path d="M12.5 14.75C7.94365 14.75 4.25 17.3505 4.25 20.5625C4.25 20.9767 4.58579 21.3125 5 21.3125H20C20.4142 21.3125 20.75 20.9767 20.75 20.5625C20.75 17.3505 17.0563 14.75 12.5 14.75Z" fill="currentColor"/>
									</svg>
								</a>
									<div class="dropdown-menu dropdown-menu-end">
										<div class="card border-0 mb-0">
											<div class="card-header py-2">
												<div class="products">
													<img src="{{ auth()->user()->avatarUrl() }}" class="avatar avatar-md" alt="">
													<div>
														<h6>{{ auth()->user()->name }}</h6>
														<span>{{ auth()->user()->rol->value }}</span>
													</div>	
												</div>
											</div>
											<div class="card-body px-0 py-2">
												<a href="{{ route('profile.show') }}" class="dropdown-item ai-icon ">
													<svg  width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path fill-rule="evenodd" clip-rule="evenodd" d="M11.9848 15.3462C8.11714 15.3462 4.81429 15.931 4.81429 18.2729C4.81429 20.6148 8.09619 21.2205 11.9848 21.2205C15.8524 21.2205 19.1543 20.6348 19.1543 18.2938C19.1543 15.9529 15.8733 15.3462 11.9848 15.3462Z" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
													<path fill-rule="evenodd" clip-rule="evenodd" d="M11.9848 12.0059C14.5229 12.0059 16.58 9.94779 16.58 7.40969C16.58 4.8716 14.5229 2.81445 11.9848 2.81445C9.44667 2.81445 7.38857 4.8716 7.38857 7.40969C7.38 9.93922 9.42381 11.9973 11.9524 12.0059H11.9848Z" stroke="var(--primary)" stroke-width="1.42857" stroke-linecap="round" stroke-linejoin="round"/>
													</svg>

													<span class="ms-2">Mi perfil</span>
												</a>
											</div>
											<div class="card-footer px-0 py-2">
												@can('ver-configuracion')
												<a href="{{ route('configuracion.show') }}" class="dropdown-item ai-icon ">
													<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
														<path fill-rule="evenodd" clip-rule="evenodd" d="M20.8066 7.62355L20.1842 6.54346C19.6576 5.62954 18.4907 5.31426 17.5755 5.83866V5.83866C17.1399 6.09528 16.6201 6.16809 16.1307 6.04103C15.6413 5.91396 15.2226 5.59746 14.9668 5.16131C14.8023 4.88409 14.7139 4.56833 14.7105 4.24598V4.24598C14.7254 3.72916 14.5304 3.22834 14.17 2.85761C13.8096 2.48688 13.3145 2.2778 12.7975 2.27802H11.5435C11.0369 2.27801 10.5513 2.47985 10.194 2.83888C9.83666 3.19791 9.63714 3.68453 9.63958 4.19106V4.19106C9.62457 5.23686 8.77245 6.07675 7.72654 6.07664C7.40418 6.07329 7.08843 5.98488 6.8112 5.82035V5.82035C5.89603 5.29595 4.72908 5.61123 4.20251 6.52516L3.53432 7.62355C3.00838 8.53633 3.31937 9.70255 4.22997 10.2322V10.2322C4.82187 10.574 5.1865 11.2055 5.1865 11.889C5.1865 12.5725 4.82187 13.204 4.22997 13.5457V13.5457C3.32053 14.0719 3.0092 15.2353 3.53432 16.1453V16.1453L4.16589 17.2345C4.41262 17.6797 4.82657 18.0082 5.31616 18.1474C5.80575 18.2865 6.33061 18.2248 6.77459 17.976V17.976C7.21105 17.7213 7.73116 17.6515 8.21931 17.7821C8.70746 17.9128 9.12321 18.233 9.37413 18.6716C9.53867 18.9488 9.62708 19.2646 9.63043 19.5869V19.5869C9.63043 20.6435 10.4869 21.5 11.5435 21.5H12.7975C13.8505 21.5 14.7055 20.6491 14.7105 19.5961V19.5961C14.7081 19.088 14.9088 18.6 15.2681 18.2407C15.6274 17.8814 16.1154 17.6806 16.6236 17.6831C16.9451 17.6917 17.2596 17.7797 17.5389 17.9393V17.9393C18.4517 18.4653 19.6179 18.1543 20.1476 17.2437V17.2437L20.8066 16.1453C21.0617 15.7074 21.1317 15.1859 21.0012 14.6963C20.8706 14.2067 20.5502 13.7893 20.111 13.5366V13.5366C19.6717 13.2839 19.3514 12.8665 19.2208 12.3769C19.0902 11.8872 19.1602 11.3658 19.4153 10.9279C19.5812 10.6383 19.8213 10.3981 20.111 10.2322V10.2322C21.0161 9.70283 21.3264 8.54343 20.8066 7.63271V7.63271V7.62355Z" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
														<circle cx="12.175" cy="11.889" r="2.63616" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
														</svg>

													<span class="ms-2">Configuración </span>
												</a>
												@endcan
												@can('ver-logs')
												<a href="{{ route('logs.index') }}" class="dropdown-item ai-icon ">
													<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
														<path d="M8 6H21" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
														<path d="M8 12H21" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
														<path d="M8 18H21" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
														<path d="M3 6H3.01" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
														<path d="M3 12H3.01" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
														<path d="M3 18H3.01" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
													</svg>

													<span class="ms-2">Logs</span>
												</a>
												@endcan
												<a href="javascript:void(0);" class="dropdown-item ai-icon text-danger"
													onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
													<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E55555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
													<span class="ms-2">Cerrar sesión</span>
												</a>
												<form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
													@csrf
												</form>
											</div>
										</div>

									</div>
							</li>
							@php
    $icon = config('dz.pagelevel.'.$CurrentPage.'.sidebar-add-icon');
@endphp
							@if ($icon == true) 
								<li class="nav-item dropdown notification_dropdown sidebar-close">
								<a class="nav-link" href="javascript:void(0);">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
										fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
										stroke-linejoin="round" class="feather feather-chevron-right">
										<polyline points="9 18 15 12 9 6"></polyline>
									</svg>
								</a>
							</li> 
						@endif
                        </ul>
                    </div>
				</nav>
			</div>
		</div>