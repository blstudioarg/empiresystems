@php
    $__tenant = function_exists('tenant') ? tenant() : null;
    $__coloresTenant = $__tenant
        ? \App\Support\AparienciaTenant::coloresEfectivos($__tenant->getTenantKey())
        : null;
@endphp

@props([
    'icon',
    'trigger' => 'hover',
    'size' => 32,
    'colors' => null,
    'target' => null,
])

{{--
    Renderiza un ícono animado descargado con `php artisan lordicon:get` (public/icons/lordicon/*.json).
    Uso: <x-lordicon icon="system-regular-1-share" trigger="hover" size="24" />
    `target` (selector CSS del ancestro) permite disparar la animación al hacer hover sobre ese
    contenedor en vez de sobre el propio ícono, p. ej. target=".card".

    Colores: SIEMPRE color primario + secundario del tenant (ver docs/04-front-guidelines.md),
    salvo que se pase `colors` explícito para un caso puntual. Si no hay tenant en contexto
    (p. ej. páginas fuera del tenancy), cae a los defaults del template.
--}}
<lord-icon
    src="{{ asset('icons/lordicon/'.$icon.'.json') }}"
    trigger="{{ $trigger }}"
    colors="{{ $colors ?? 'primary:'.($__coloresTenant['color_primario'] ?? '#1D69D6').',secondary:'.($__coloresTenant['color_secundario'] ?? '#1F2025') }}"
    @if ($target) target="{{ $target }}" @endif
    style="width:{{ $size }}px;height:{{ $size }}px"
    {{ $attributes }}
></lord-icon>
