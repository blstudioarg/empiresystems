{{--
	Notificaciones de la app: SIEMPRE via toastr (vendor/toastr), nunca alerts Bootstrap ad-hoc.
	Este partial se incluye una vez en layouts/app.blade.php y dispara un toast por cada flash
	de sesión presente. Las respuestas AJAX deben usar window.showToast(type, message) en JS
	(ver public/js/toastr-config.js) en vez de construir su propio markup de alerta.
--}}
@php
	$__flashes = [
		'success' => session('success'),
		'error' => session('error'),
		'warning' => session('warning'),
		'info' => session('info'),
	];
@endphp

@if (collect($__flashes)->filter()->isNotEmpty())
	<script>
		(function () {
			if (typeof toastr === "undefined") {
				return;
			}

			@foreach ($__flashes as $__tipo => $__mensaje)
				@if ($__mensaje)
					toastr.{{ $__tipo === 'error' ? 'error' : $__tipo }}(@json($__mensaje));
				@endif
			@endforeach
		})();
	</script>
@endif
