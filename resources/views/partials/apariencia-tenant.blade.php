@php
	$__tenant = function_exists('tenant') ? tenant() : null;
	$__variablesCss = $__tenant ? \App\Support\AparienciaTenant::variablesCss($__tenant->getTenantKey()) : '';
@endphp

{{--
	La regla del topbar se emite SIEMPRE (aunque el tenant no haya configurado nada), para que la
	variable --topbar-bg exista en el DOM desde el primer render. Así, cuando el guardado por AJAX
	de configuracion-apariencia.init.js hace document.documentElement.style.setProperty(...) tras el
	primer cambio de color, la regla ya está ahí para reaccionar sin necesitar recargar la página.
--}}
<style>
	{!! $__variablesCss !!}

	.header.header, .nav-header.nav-header {
		background: var(--topbar-bg, inherit);
	}
</style>
