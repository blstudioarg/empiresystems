{{--
	Par de selects encadenados Provincia → Localidad.

	Renderiza LAS DOS columnas (provincia y ciudad) para poder colocarse dentro de un
	`.row` existente. Los valores que se envían son los NOMBRES, no los ids (así lo
	guardan clientes, proveedores, tenants y los campos congelados de factura).

	Uso:
		<x-provincia-localidad
			name-provincia="cliente_provincia"
			name-ciudad="cliente_ciudad"
			:valor-provincia="old('cliente_provincia', $factura?->cliente_provincia)"
			:valor-ciudad="old('cliente_ciudad', $factura?->cliente_ciudad)"
			col="col-md-2" />
--}}
@props([
	'nameProvincia' => 'provincia',
	'nameCiudad' => 'ciudad',
	'idProvincia' => null,
	'idCiudad' => null,
	'valorProvincia' => null,
	'valorCiudad' => null,
	'labelProvincia' => 'Provincia',
	'labelCiudad' => 'Ciudad',
	'col' => 'col-md-4',
	'labelClass' => 'form-label',
	'provincias' => null,
])

@php
	$idProv = $idProvincia ?? $nameProvincia;
	$idCiu = $idCiudad ?? $nameCiudad;
	// Memoizado por render: varias instancias en la misma página no repiten la query.
	$listaProvincias = $provincias ?? once(fn () => \App\Models\Provincia::orderBy('nombre')->get(['id', 'nombre']));
	$provinciaEnCatalogo = $valorProvincia && $listaProvincias->contains('nombre', $valorProvincia);
@endphp

<div class="{{ $col }}">
	<label for="{{ $idProv }}" class="{{ $labelClass }}">{{ $labelProvincia }}</label>
	<select name="{{ $nameProvincia }}" id="{{ $idProv }}" class="form-control"
		data-provincia-select data-localidad-target="{{ $idCiu }}"
		data-localidades-url="{{ route('localidades.index') }}">
		<option value="">Selecciona una provincia</option>
		@foreach ($listaProvincias as $provincia)
			<option value="{{ $provincia->nombre }}" data-provincia-id="{{ $provincia->id }}"
				@selected($valorProvincia === $provincia->nombre)>{{ $provincia->nombre }}</option>
		@endforeach
		{{-- Dato heredado fuera del catálogo (importaciones, registros antiguos): no se pierde. --}}
		@unless ($provinciaEnCatalogo || blank($valorProvincia))
			<option value="{{ $valorProvincia }}" selected>{{ $valorProvincia }}</option>
		@endunless
	</select>
	<div class="invalid-feedback" data-error-for="{{ $nameProvincia }}"></div>
</div>

<div class="{{ $col }}">
	<label for="{{ $idCiu }}" class="{{ $labelClass }}">{{ $labelCiudad }}</label>
	<select name="{{ $nameCiudad }}" id="{{ $idCiu }}" class="form-control"
		data-valor-inicial="{{ $valorCiudad }}">
		<option value="">Selecciona una provincia primero</option>
	</select>
	<div class="invalid-feedback" data-error-for="{{ $nameCiudad }}"></div>
</div>

@once
	@push('scripts')
		<script src="{{ asset('js/components/provincia-localidad.js') }}"></script>
	@endpush
@endonce
