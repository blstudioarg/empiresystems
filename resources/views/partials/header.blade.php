<div class="header">
	<div class="header-content">
		<nav class="navbar navbar-expand">
			<div class="collapse navbar-collapse justify-content-between">
				<div class="header-left">
					<form>
						<div class="input-group search-area">
							<span class="input-group-text"><button type="button">
								<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M9.25 14.25C12.5637 14.25 15.25 11.5637 15.25 8.25C15.25 4.93629 12.5637 2.25 9.25 2.25C5.93629 2.25 3.25 4.93629 3.25 8.25C3.25 11.5637 5.93629 14.25 9.25 14.25Z" stroke="#6F767E" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M16.75 15.75L13.4875 12.4875" stroke="#6F767E" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</button></span>
							<input type="text" class="form-control" placeholder="Buscar">
						</div>
					</form>
				</div>
				<ul class="navbar-nav header-right">
					<li class="nav-item dropdown notification_dropdown">
						<a class="nav-link" href="javascript:void(0);" role="button" data-bs-toggle="dropdown">
							<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 25 25" fill="none">
								<path d="M5.05384 11.75C4.58544 11.75 4.18446 11.5832 3.85091 11.2495C3.51736 10.9159 3.35059 10.5129 3.35059 10.0407V5.05379C3.35059 4.58376 3.51736 4.18138 3.85091 3.84664C4.18446 3.51191 4.58544 3.34454 5.05384 3.34454H10.0468C10.5152 3.34454 10.9161 3.51191 11.2497 3.84664C11.5833 4.18138 11.75 4.58376 11.75 5.05379V10.0407C11.75 10.5129 11.5833 10.9159 11.2497 11.2495C10.9161 11.5832 10.5152 11.75 10.0468 11.75H5.05384ZM5.05384 21.6494C4.58544 21.6494 4.18446 21.4827 3.85091 21.1491C3.51736 20.8156 3.35059 20.4146 3.35059 19.9462V14.9533C3.35059 14.4849 3.51736 14.0839 3.85091 13.7503C4.18446 13.4168 4.58544 13.25 5.05384 13.25H10.0468C10.5152 13.25 10.9161 13.4168 11.2497 13.7503C11.5833 14.0839 11.75 14.4849 11.75 14.9533V19.9462C11.75 20.4146 11.5833 20.8156 11.2497 21.1491C10.9161 21.4827 10.5152 21.6494 10.0468 21.6494H5.05384ZM14.9593 11.75C14.4871 11.75 14.0842 11.5832 13.7505 11.2495C13.4169 10.9159 13.25 10.5129 13.25 10.0407V5.05379C13.25 4.58376 13.4169 4.18138 13.7505 3.84664C14.0842 3.51191 14.4871 3.34454 14.9593 3.34454H19.9462C20.4163 3.34454 20.8186 3.51191 21.1534 3.84664C21.4881 4.18138 21.6555 4.58376 21.6555 5.05379V10.0407C21.6555 10.5129 21.4881 10.9159 21.1534 11.2495C20.8186 11.5832 20.4163 11.75 19.9462 11.75H14.9593ZM14.9593 21.6494C14.4871 21.6494 14.0842 21.4827 13.7505 21.1491C13.4169 20.8156 13.25 20.4146 13.25 19.9462V14.9533C13.25 14.4849 13.4169 14.0839 13.7505 13.7503C14.0842 13.4168 14.4871 13.25 14.9593 13.25H19.9462C20.4163 13.25 20.8186 13.4168 21.1534 13.7503C21.4881 14.0839 21.6555 14.4849 21.6555 14.9533V19.9462C21.6555 20.4146 21.4881 20.8156 21.1534 21.1491C20.8186 21.4827 20.4163 21.6494 19.9462 21.6494H14.9593ZM5.05384 10.0407H10.0468V5.05379H5.05384V10.0407ZM14.9593 10.0407H19.9462V5.05379H14.9593V10.0407ZM14.9593 19.9462H19.9462V14.9533H14.9593V19.9462ZM5.05384 19.9462H10.0468V14.9533H5.05384V19.9462Z" fill="black"/>
							</svg>
						</a>
						<div class="dropdown-menu dropdown-menu-end">
							<div class="widget-media dz-scroll p-3" style="height:200px;">
								<p class="text-muted mb-0">Sin notificaciones por ahora.</p>
							</div>
						</div>
					</li>
					<li class="nav-item ps-3">
						<div class="dropdown header-profile2">
							<a class="nav-link p-0" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false">
								<div class="header-info2 d-flex align-items-center">
									<div class="header-media">
										<img src="{{ asset('images/user1.jpg') }}" alt="">
									</div>
								</div>
							</a>
							<div class="dropdown-menu dropdown-menu-end">
								<div class="card border-0 mb-0">
									<div class="card-header py-2">
										<div class="products">
											<img src="{{ asset('images/user1.jpg') }}" class="avatar avatar-md" alt="">
											<div>
												<h6>{{ auth()->user()->name ?? 'Usuario' }}</h6>
												<span>{{ auth()->user()->email ?? '' }}</span>
											</div>
										</div>
									</div>
									<div class="card-footer px-0 py-2">
										<a href="javascript:void(0);" class="dropdown-item ai-icon text-danger">
											<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E55555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
											<span class="ms-2">Cerrar sesión</span>
										</a>
									</div>
								</div>
							</div>
						</div>
					</li>
				</ul>
			</div>
		</nav>
	</div>
</div>
