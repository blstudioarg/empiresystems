{{--
	Bandeja de conteo de efectivo (feature 048) — elemento firma de la caja.

	Un solo componente para contar el fondo al abrir y el cajón al cerrar: mismo gesto, mismo
	teclado, mismas reglas (docs/04 "Entrada numérica en pantallas táctiles"). Lo controla
	`public/js/pos-caja-bandeja.js` (`PosCajaBandeja.crear(el)`).

	Dos modos, alternados con el control segmentado:
	  · conteo  → fichas de billetes y monedas; tocar una la selecciona y el teclado teclea SU
	              cantidad (entera; la tecla C pone a cero). Doble toque suma 1.
	  · importe → un único display grande con teclado decimal.

	Nunca pinta el efectivo esperado: el arqueo es ciego (FR-010).

	Props: $id, $modo ('conteo'|'importe'), $titulo, $subtitulo, $etiquetaTotal.
--}}
@php
	$modo = $modo ?? 'conteo';
	$billetes = \App\Support\DenominacionesEuro::billetes();
	$monedas = \App\Support\DenominacionesEuro::monedas();
	$etiquetaTotal = $etiquetaTotal ?? 'Total contado';
@endphp
<div class="caja-bandeja-comp" id="{{ $id }}" data-bandeja data-modo-inicial="{{ $modo }}">
	<div class="caja-apertura-cab">
		<div>
			<h3>{{ $titulo }}</h3>
			@if (! empty($subtitulo))<p>{{ $subtitulo }}</p>@endif
		</div>
		<div class="btn-group filtro-segmentado" role="group" aria-label="Cómo contar">
			<button type="button" class="btn btn-outline-secondary" data-bandeja-modo="conteo">Billetes y monedas</button>
			<button type="button" class="btn btn-outline-secondary" data-bandeja-modo="importe">Importe total</button>
		</div>
	</div>

	<div class="caja-bandeja">
		<div>
			<div class="caja-bandeja-tray" data-bandeja-panel="conteo">
				<p class="caja-tray-eyebrow">Billetes</p>
				<div class="caja-tray-billetes">
					@foreach ($billetes as $d)
						<button type="button" class="caja-ficha caja-billete" data-centimos="{{ $d['centimos'] }}" data-tono="{{ $d['tono'] }}"
							data-etiqueta="{{ $d['etiqueta'] }}" aria-label="Billete de {{ $d['etiqueta'] }}, 0 unidades">
							<span class="caja-ficha-valor">{{ intdiv($d['centimos'], 100) }}<small>€</small></span>
							<span class="caja-ficha-pie">
								<span class="caja-ficha-cant" data-cant>×0</span>
								<span class="caja-ficha-sub" data-sub>0,00</span>
							</span>
						</button>
					@endforeach
				</div>
				<p class="caja-tray-eyebrow">Monedas</p>
				<div class="caja-tray-monedas">
					@foreach ($monedas as $d)
						<button type="button" class="caja-ficha caja-moneda" data-centimos="{{ $d['centimos'] }}" data-tono="{{ $d['tono'] }}"
							data-etiqueta="{{ $d['etiqueta'] }}" aria-label="Moneda de {{ $d['etiqueta'] }}, 0 unidades">
							<span class="caja-moneda-disco">{{ $d['centimos'] >= 100 ? intdiv($d['centimos'], 100).'€' : $d['centimos'] }}</span>
							<span class="caja-ficha-pie">
								<span class="caja-ficha-cant" data-cant>×0</span>
								<span class="caja-ficha-sub" data-sub>0,00</span>
							</span>
						</button>
					@endforeach
				</div>
			</div>

			<div class="caja-importe-total" data-bandeja-panel="importe" hidden>
				<div class="lbl">{{ $etiquetaTotal }}</div>
				<div class="val" data-bandeja-importe aria-live="polite">0,00 €</div>
				<p class="hint">Teclea el total con el teclado de la derecha.</p>
			</div>
		</div>

		<div class="caja-teclado">
			<div class="caja-teclado-sel" data-bandeja-solo="conteo">
				<span class="lbl">Cantidad de</span>
				<span class="den" data-bandeja-sel>—</span>
			</div>
			<div class="caja-teclado-display vacio" data-bandeja-solo="conteo" data-bandeja-display>0</div>
			<div class="caja-teclado-grid">
				@foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
					<button type="button" class="caja-key" data-key="{{ $n }}">{{ $n }}</button>
				@endforeach
				<button type="button" class="caja-key sec" data-key="," data-bandeja-coma aria-label="Coma decimal">,</button>
				<button type="button" class="caja-key" data-key="0">0</button>
				<button type="button" class="caja-key sec" data-key="del" aria-label="Borrar"><i class="fa-solid fa-delete-left" aria-hidden="true"></i></button>
			</div>
			<div class="caja-total-vivo" data-bandeja-solo="conteo">
				<span class="lbl">{{ $etiquetaTotal }}</span>
				<span class="val" data-bandeja-total aria-live="polite">0,00 €</span>
			</div>
		</div>
	</div>
</div>
