{{--
	Panel "Abrir caja" (feature 048, US1). Se usa en la pantalla de caja y en el modal del TPV
	(apertura inline al cobrar con la caja cerrada, FR-020). Lo controla
	`public/js/pos-caja-apertura.js`.

	Arranca en "Importe total" porque abrir es la acción rápida de cada mañana; quien quiera
	contar el fondo billete a billete cambia de modo con un toque.

	Props: $id, $cancelable (bool: muestra "Cancelar").
--}}
<div class="caja-apertura" id="{{ $id }}" data-caja-apertura>
	@include('pos._caja-bandeja', [
		'id' => $id.'-bandeja',
		'modo' => 'importe',
		'titulo' => 'Abrir caja',
		'subtitulo' => 'Con cuánto efectivo empieza el cajón (el fondo de cambio).',
		'etiquetaTotal' => 'Fondo de cambio',
	])

	<div class="caja-acciones">
		@if ($cancelable ?? true)
			<button type="button" class="btn btn-light caja-sec" data-apertura-cancelar>Cancelar</button>
		@endif
		<button type="button" class="btn caja-cta caja-cta-dinero" data-apertura-confirmar>
			<i class="fa-solid fa-lock-open" aria-hidden="true"></i>
			<span data-apertura-texto>Abrir caja con 0,00 €</span>
		</button>
	</div>
</div>
