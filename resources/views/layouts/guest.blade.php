<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimal-ui">
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
	<link href="{{ asset('css/style.css') }}" rel="stylesheet">
	<link href="{{ asset('css/app-overrides.css') }}" rel="stylesheet">
	@include('partials.apariencia-tenant')

	@stack('styles')
</head>

<body class="vh-100">

	@yield('content')

	{{-- Assets base del template NexaDash (siempre cargados) --}}
	<script src="{{ asset('vendor/global/global.min.js') }}"></script>
	<script src="{{ asset('vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
	<script src="{{ asset('js/custom.js') }}"></script>

	@stack('scripts')
</body>

</html>
