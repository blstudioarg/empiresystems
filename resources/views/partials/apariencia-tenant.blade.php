@php
	$__tenant = function_exists('tenant') ? tenant() : null;
	$__variablesCss = $__tenant ? \App\Support\AparienciaTenant::variablesCss($__tenant->getTenantKey()) : '';
	$__topbarFallback = $__tenant
		? \App\Support\AparienciaTenant::DEFAULT_TOPBAR
		: \App\Support\AparienciaTenant::DEFAULT_TOPBAR_CENTRAL;
@endphp

{{--
	La regla del topbar se emite SIEMPRE (aunque el tenant no haya configurado nada, o no haya
	tenant en absoluto — p. ej. super_admin en el panel central), para que la variable
	--topbar-bg exista en el DOM desde el primer render y el header nunca se quede sin color de
	fondo (fallback a un color por defecto, no `inherit`: sin ancestro con background propio,
	`inherit` deja el header transparente). Sin tenant (panel super_admin) el fallback es un gris
	neutro (DEFAULT_TOPBAR_CENTRAL), distinto del blanco de negocio (DEFAULT_TOPBAR), para marcar
	visualmente que no se está en el contexto de ningún tenant. Así, cuando el guardado por AJAX de
	configuracion-apariencia.init.js hace document.documentElement.style.setProperty(...) tras el
	primer cambio de color, la regla ya está ahí para reaccionar sin necesitar recargar la página.
--}}
<style>
	{!! $__variablesCss !!}

	.header.header, .nav-header.nav-header {
		background: var(--topbar-bg, {{ $__topbarFallback }});
	}
</style>
