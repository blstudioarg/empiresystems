<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimal-ui">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>@yield('title', 'Empire Systems')</title>
	@php
		$__tenantFavicon = function_exists('tenant') ? tenant() : null;
		$__favicon = $__tenantFavicon && $__tenantFavicon->favicon_path
			? asset('storage/'.$__tenantFavicon->favicon_path)
			: asset('images/fav.png');
	@endphp
	<link rel="shortcut icon" type="image/png" href="{{ $__favicon }}">

	{{-- Assets base del template NexaDash (siempre cargados) --}}
	<link href="{{ asset('vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">
	<link href="{{ asset('icons/fontawesome/css/all.min.css') }}" rel="stylesheet">
	<link href="{{ asset('icons/themify-icons/css/themify-icons.css') }}" rel="stylesheet">
	<link href="{{ asset('css/perfect-scrollbar.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/toastr/css/toastr.min.css') }}" rel="stylesheet">

	{{-- CSS de plugins específicos de cada vista (@push('styles')): DEBE cargar antes que
	     style.css. style.css ya trae sus propias reglas de theming para varios plugins del
	     banco (p. ej. .asColorPicker-trigger) y necesita ser la última hoja con esos selectores
	     para que gane en el cascade — si un plugin se carga después, su CSS base pisa el theming
	     del template aunque tenga la misma especificidad. --}}
	@stack('styles')

	<link href="{{ asset('css/style.css') }}" rel="stylesheet">
	{{-- ?v= con el filemtime: el hosting sirve los estáticos con Cache-Control de una semana,
	     así que sin esto un cambio de CSS no llega ni al usuario ni al cliente hasta que hagan
	     un refresco duro. Al versionar la URL, cada subida invalida el caché sola. --}}
	<link href="{{ asset('css/app-overrides.css') }}?v={{ @filemtime(public_path('css/app-overrides.css')) }}" rel="stylesheet">

	@include('partials.apariencia-tenant')
</head>

<body>
	@include('partials.preloader')

	<div id="main-wrapper">
		@include('partials.nav-header')
		@include('partials.header', ['CurrentPage' => ''])
		@include('partials.sidebar')

		@yield('content')
	</div>

	@include('partials.confirm-delete-modal')
	@include('partials.ayuda-modal')

	{{-- Assets base del template NexaDash (siempre cargados) --}}
	<script src="{{ asset('vendor/global/global.min.js') }}"></script>
	<script src="{{ asset('vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
	<script src="{{ asset('js/deznav-init.js') }}"></script>
	<script src="{{ asset('js/custom.js') }}"></script>
	<script src="{{ asset('js/theme-persist.js') }}"></script>
	<script src="{{ asset('vendor/toastr/js/toastr.min.js') }}"></script>
	<script src="{{ asset('js/toastr-config.js') }}"></script>
	<script src="{{ asset('js/button-loading.js') }}"></script>
	<script src="{{ asset('js/confirm-delete.js') }}"></script>
	<script src="{{ asset('js/metric-cards.js') }}?v={{ @filemtime(public_path('js/metric-cards.js')) }}"></script>
	<script src="{{ asset('js/plugins-init/password-toggle.init.js') }}"></script>

	{{-- Player de Lordicon (cuenta propia): renderiza <lord-icon> a partir de los JSON
	     descargados con `php artisan lordicon:get` y cacheados en public/icons/lordicon/. --}}
	<script src="https://cdn.lordicon.com/ritcuqlt.js"></script>

	@include('partials.flash-toastr')

	{{-- Oculto en la vista de fichar: el botón flotante tapa el hero de fichaje (mapa, reloj,
	     botón de fichar) en pantallas chicas, justo la pantalla que se usa a diario y rápido. --}}
	@unless (request()->routeIs('fichajes.index'))
		@include('partials.asistente-chat')
	@endunless

	@stack('scripts')
</body>

</html>
