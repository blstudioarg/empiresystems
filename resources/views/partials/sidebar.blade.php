<div class="deznav">
	<div class="deznav-scroll grid-menu">
		<ul class="metismenu" id="menu">
			<li class="{{ request()->routeIs('dashboard') ? 'mm-active' : '' }}">
				<a href="{{ url('/') }}" class="{{ request()->routeIs('dashboard') ? 'mm-active' : '' }}">
					<div class="menu-icon ai-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M5.55286 19.446H9.14743V13.1482H14.8507V19.446H18.4453V9.77373L11.9991 4.94055L5.55286 9.77571V19.446ZM5.55286 21.1493C5.08446 21.1493 4.68348 20.9825 4.34993 20.6489C4.01638 20.3154 3.84961 19.9144 3.84961 19.446V9.77373C3.84961 9.50478 3.90971 9.25 4.02991 9.00938C4.15011 8.76876 4.31791 8.56974 4.53331 8.4123L10.9735 3.57915C11.1317 3.46719 11.2973 3.38222 11.4704 3.32426C11.6434 3.26629 11.8215 3.2373 12.0045 3.2373C12.1875 3.2373 12.3642 3.26629 12.5347 3.32426C12.7052 3.38222 12.8685 3.46719 13.0246 3.57915L19.4648 8.4123C19.6798 8.57115 19.8486 8.77066 19.9709 9.01083C20.0933 9.251 20.1545 9.5053 20.1545 9.77373V19.446C20.1545 19.9144 19.9871 20.3154 19.6524 20.6489C19.3177 20.9825 18.9153 21.1493 18.4453 21.1493H13.2132V14.7857H10.7849V21.1493H5.55286Z" fill="#6F767E" />
						</svg>
					</div>
					<span class="nav-text">Dashboard</span>
				</a>
			</li>
		</ul>
		{{-- El resto del menú (Clientes, Facturas, Series, Configuración...) se agrega a
		     medida que cada feature entra por /speckit-specify. --}}
		<div class="mode-btn d-flex align-items-center justify-content-between">
			<div class="d-mode">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<g clip-path="url(#clip0_4_82)">
						<path d="M12.025 23.3407L8.62955 20.0479H3.95118V15.3728L0.584229 12L3.95208 8.62704V3.94519H8.6272L12.025 0.572266L15.3731 3.94497H20.055V8.62694L23.4277 12L20.0549 15.3704V20.0488H15.3728L12.025 23.3407ZM12.025 18.3445C13.7812 18.3445 15.2745 17.7251 16.5049 16.4863C17.7353 15.2474 18.3506 13.7439 18.3506 11.9757C18.3506 10.2214 17.7348 8.72844 16.5034 7.49684C15.2719 6.26524 13.7791 5.64944 12.025 5.64944V18.3445ZM12.025 20.9538L14.6609 18.347H18.3513V14.6568L21.0098 12L18.3493 9.33697V5.64874H14.6645L12.025 2.99022L9.34323 5.64874H5.65298V9.33547L2.9962 12L5.65545 14.6592V18.3445H9.31575L12.025 20.9538Z" fill="#6F767E" />
					</g>
					<defs>
						<clipPath id="clip0_4_82">
							<rect width="24" height="24" fill="white" />
						</clipPath>
					</defs>
				</svg>
				<span class="ms-2">Modo oscuro</span>
			</div>
			<div class="dz-layout light">
				<i class="fas fa-sun sun"></i>
				<i class="fas fa-moon moon"></i>
			</div>
		</div>
	</div>
</div>
