{{--
	Informe Z de una sesión de caja (feature 048, FR-015). Contenido común a 80 mm y A4.
	Se pinta SIEMPRE desde lo congelado al cerrar (`CajaController::payloadInforme`), nunca
	recalculando: el informe de un día cerrado no puede cambiar (FR-017).
	Variables: $informe (array), $tenant (Tenant).
--}}
@php
	$m = fn ($v) => \App\Support\Formato::moneda($v);
	$veredicto = match ($informe['estado']) {
		'sobra' => __('SOBRANTE'),
		'falta' => __('FALTANTE'),
		default => __('CUADRA'),
	};
	$signo = (float) $informe['descuadre'] > 0 ? '+' : '';
@endphp

<div class="cab">
	<div class="bold grande">{{ $tenant->nombre_comercial ?: $tenant->razon_social }}</div>
	@if ($tenant->nif)<div class="muted">{{ __('NIF: :nif', ['nif' => $tenant->nif]) }}</div>@endif
	<div class="titulo">{{ __('CIERRE DE CAJA · Nº :numero', ['numero' => $informe['numero']]) }}</div>
	<div class="muted">{{ __('Informe Z · control interno, no es documento fiscal') }}</div>
</div>

<div class="sep"></div>
<table>
	<tr><td>{{ __('Apertura') }}</td><td class="right">{{ $informe['abierta_at'] }}</td></tr>
	<tr><td class="muted" colspan="2">{{ $informe['abierta_por'] }}</td></tr>
	<tr><td>{{ __('Cierre') }}</td><td class="right">{{ $informe['cerrada_at'] }}</td></tr>
	<tr><td class="muted" colspan="2">{{ $informe['cerrada_por'] }}</td></tr>
</table>

<div class="sep"></div>
<div class="sec">{{ __('VENTAS') }}</div>
<table>
	<tr><td>{{ __('Tickets') }}</td><td class="right">{{ $informe['num_tickets'] }}</td></tr>
	@if ($informe['primer_ticket'])
		<tr><td class="muted" colspan="2">{{ $informe['primer_ticket'] }} → {{ $informe['ultimo_ticket'] }}</td></tr>
	@endif
	<tr class="total"><td>{{ __('TOTAL FACTURADO') }}</td><td class="right">{{ $m($informe['total_facturado']) }} €</td></tr>
</table>

<div class="sep"></div>
<div class="sec">{{ __('POR MÉTODO DE PAGO') }}</div>
<table>
	@foreach ($informe['por_metodo'] as $metodo)
		<tr>
			<td>{{ $metodo['label'] }} <span class="muted">({{ $metodo['tickets'] }})</span></td>
			<td class="right">{{ $m($metodo['importe']) }} €</td>
		</tr>
	@endforeach
</table>

@if (count($informe['por_impuesto']))
	<div class="sep"></div>
	<div class="sec">{{ __('IMPUESTOS') }}</div>
	<table>
		<tr class="muted"><td>{{ __('Tipo') }}</td><td class="right">{{ __('Base') }}</td><td class="right">{{ __('Cuota') }}</td></tr>
		@foreach ($informe['por_impuesto'] as $imp)
			<tr>
				<td>{{ strtoupper($imp['tipo_impuesto']) }} {{ \App\Support\Formato::porcentaje($imp['porcentaje']) }}%</td>
				<td class="right">{{ $m($imp['base']) }}</td>
				<td class="right">{{ $m($imp['cuota']) }}</td>
			</tr>
		@endforeach
	</table>
@endif

@if (count($informe['anulados']))
	<div class="sep"></div>
	<div class="sec">{{ __('ANULADOS (no suman)') }}</div>
	<table>
		@foreach ($informe['anulados'] as $anulado)
			<tr><td>{{ $anulado['numero'] }}</td><td class="right">{{ $m($anulado['total']) }} €</td></tr>
		@endforeach
	</table>
@endif

@if (count($informe['movimientos']))
	<div class="sep"></div>
	<div class="sec">{{ __('MOVIMIENTOS DE EFECTIVO') }}</div>
	<table>
		@foreach ($informe['movimientos'] as $mov)
			<tr>
				<td>{{ $mov['hora'] }} {{ $mov['motivo'] }}</td>
				<td class="right">{{ $mov['tipo'] === 'entrada' ? '+' : '−' }}{{ $m($mov['importe']) }} €</td>
			</tr>
		@endforeach
	</table>
@endif

<div class="sep"></div>
<div class="sec">{{ __('ARQUEO DE EFECTIVO') }}</div>
<table>
	<tr><td>{{ __('Fondo inicial') }}</td><td class="right">{{ $m($informe['fondo_inicial']) }} €</td></tr>
	<tr><td>{{ __('+ Ventas en efectivo') }}</td><td class="right">{{ $m($informe['efectivo_ventas']) }} €</td></tr>
	<tr><td>{{ __('+ Entradas') }}</td><td class="right">{{ $m($informe['entradas']) }} €</td></tr>
	<tr><td>{{ __('− Salidas') }}</td><td class="right">{{ $m($informe['salidas']) }} €</td></tr>
	<tr class="bold"><td>{{ __('Esperado') }}</td><td class="right">{{ $m($informe['efectivo_esperado']) }} €</td></tr>
	<tr class="bold"><td>{{ __('Contado') }}</td><td class="right">{{ $m($informe['efectivo_contado']) }} €</td></tr>
	<tr class="total"><td>{{ $veredicto }}</td><td class="right">{{ $signo }}{{ $m($informe['descuadre']) }} €</td></tr>
</table>

@if (count($informe['conteo']))
	<div class="sep"></div>
	<div class="sec">{{ __('CONTEO') }}</div>
	<table>
		@foreach ($informe['conteo'] as $fila)
			<tr>
				<td>{{ $fila['cantidad'] }} × {{ $fila['centimos'] >= 100 ? $m($fila['centimos'] / 100).' €' : __(':n cént.', ['n' => $fila['centimos']]) }}</td>
				<td class="right">{{ $m($fila['subtotal']) }} €</td>
			</tr>
		@endforeach
	</table>
@endif

@if ($informe['observacion'])
	<div class="sep"></div>
	<div class="sec">{{ __('OBSERVACIÓN') }}</div>
	<div>{{ $informe['observacion'] }}</div>
@endif

<div class="sep"></div>
<div class="center muted">{{ __('Firma') }} ______________________</div>
