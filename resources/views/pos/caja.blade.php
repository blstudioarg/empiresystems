@extends('layouts.app')

@section('title', 'POS · Caja')

@push('styles')
	<link href="@assetv('css/pos-caja.css')" rel="stylesheet">
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid caja" id="caja-app">

			{{-- ═══ Estado A — caja cerrada ═══════════════════════════════════════════════
			     Una sola idea en pantalla: abrir. "Abrir caja" despliega el panel en la MISMA
			     card (no navega ni abre modal): en la tablet, cuanto menos se mueva la vista,
			     mejor. --}}
			<section class="caja-vista" id="caja-cerrada" @if ($estado['abierta']) hidden @endif>
				<div class="card caja-cerrada-card">
					<div class="caja-hero" id="caja-hero">
						<div class="caja-hero-icono">
							<x-lordicon icon="wired-outline-2510-money-safety-hover-pinch" size="64" trigger="hover" target=".caja-hero" />
						</div>
						<h2>La caja está cerrada</h2>
						<p>Ábrela con el fondo de cambio para empezar a cobrar.</p>
						<button type="button" class="btn caja-cta caja-cta-xl caja-cta-dinero" id="caja-abrir-btn">
							<i class="fa-solid fa-lock-open" aria-hidden="true"></i> Abrir caja
						</button>
					</div>

					<div class="card-body" id="caja-apertura-wrap" hidden>
						@include('pos._caja-apertura', ['id' => 'caja-apertura', 'cancelable' => true])
					</div>

					<div class="caja-ultimo" id="caja-ultimo" @if (empty($estado['ultimo_cierre'])) hidden @endif>
						<div class="caja-ultimo-txt">
							<span class="eyebrow">Último cierre</span>
							<strong data-ultimo="titulo">—</strong>
							<span class="text-muted" data-ultimo="detalle"></span>
						</div>
						<div class="caja-ultimo-acciones">
							<button type="button" class="btn btn-outline-secondary caja-sec" id="caja-ultimo-ver">
								<i class="fa-solid fa-receipt" aria-hidden="true"></i> Ver informe
							</button>
							<a href="{{ route('pos.caja.cierres') }}" class="btn btn-outline-secondary caja-sec">
								<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Historial
							</a>
						</div>
					</div>
				</div>
			</section>

			{{-- ═══ Estado B — caja abierta (turno en curso, informe X) ═════════════════
			     Lo vendido y los movimientos. NUNCA el efectivo esperado: el arqueo es ciego
			     (FR-010) y esa cifra ni siquiera viaja al navegador hasta cerrar (D6). --}}
			<section class="caja-vista" id="caja-abierta" @unless ($estado['abierta']) hidden @endunless>
				<div class="card caja-estado" id="caja-estado">
					<div class="card-body">
						<div class="caja-estado-txt">
							<span class="caja-pulso" aria-hidden="true"></span>
							<div>
								<p class="caja-estado-titulo">Caja abierta</p>
								<p class="caja-estado-meta">
									Desde las <strong data-sesion="hora">—</strong>
									<span data-sesion="duracion"></span> · <span data-sesion="usuario">—</span>
								</p>
								<p class="caja-aviso-antigua">Abierta desde un día anterior: ciérrala antes de empezar el día.</p>
							</div>
						</div>
						<div class="caja-estado-acciones">
							<button type="button" class="btn btn-outline-secondary caja-sec" id="caja-actualizar" aria-label="Actualizar">
								<i class="fa-solid fa-rotate" aria-hidden="true"></i> <span class="d-none d-md-inline">Actualizar</span>
							</button>
							<a href="{{ route('pos.caja.cierres') }}" class="btn btn-outline-secondary caja-sec">
								<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> <span class="d-none d-md-inline">Historial</span>
							</a>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-xl-8">
						<div class="row">
							<div class="col-sm-4">
								<div class="card same-card">
									<div class="card-body">
										<div class="d-flex justify-content-between align-items-center">
											<div>
												<h6 class="mb-1">Vendido</h6>
												<h4 class="mb-0 text-success" data-metric="vendido" data-vivo="total_vendido">0,00 €</h4>
											</div>
											<div><x-lordicon icon="euro" size="38" trigger="hover" target=".card" /></div>
										</div>
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="card same-card">
									<div class="card-body">
										<div class="d-flex justify-content-between align-items-center">
											<div>
												<h6 class="mb-1">Tickets</h6>
												<h4 class="mb-0" data-metric="tickets" data-vivo="num_tickets">0</h4>
											</div>
											<div><x-lordicon icon="ticket" size="38" trigger="hover" target=".card" /></div>
										</div>
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="card same-card">
									<div class="card-body">
										<div class="d-flex justify-content-between align-items-center">
											<div>
												<h6 class="mb-1">Ticket medio</h6>
												<h4 class="mb-0" data-metric="ticket_medio" data-vivo="ticket_medio">0,00 €</h4>
											</div>
											<div><x-lordicon icon="wired-outline-2447-invoice-receipt-euro-hover-pinch" size="38" trigger="hover" target=".card" /></div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-header border-0 pb-0">
								<h4 class="card-title mb-0">Cómo te pagaron</h4>
							</div>
							<div class="card-body">
								<div class="caja-metodos-barra" id="caja-metodos-barra" role="img" aria-label="Reparto de lo vendido por método de pago"></div>
								<ul class="caja-metodos-lista" id="caja-metodos-lista"></ul>
							</div>
						</div>
					</div>

					<div class="col-xl-4">
						<div class="card caja-rail">
							<div class="card-body">
								<div class="caja-mov-botones">
									<button type="button" class="caja-mov-btn entrada" data-movimiento="entrada">
										<i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Entrada
									</button>
									<button type="button" class="caja-mov-btn salida" data-movimiento="salida">
										<i class="fa-solid fa-circle-minus" aria-hidden="true"></i> Salida
									</button>
								</div>

								<div>
									<div class="caja-mov-titulo">
										<h5>Movimientos del turno</h5>
										<span data-mov="conteo"></span>
									</div>
									<ul class="caja-mov-lista" id="caja-mov-lista"></ul>
									<p class="caja-mov-vacio" id="caja-mov-vacio">Sin entradas ni salidas de efectivo.</p>
								</div>

								<div class="caja-fondo-linea">
									<span>Fondo inicial</span>
									<strong data-sesion="fondo">0,00 €</strong>
								</div>

								<button type="button" class="btn caja-cta caja-cta-xl caja-cta-cerrar" id="caja-cerrar-btn">
									<i class="fa-solid fa-cash-register" aria-hidden="true"></i> Cerrar caja
								</button>
							</div>
						</div>
					</div>
				</div>
			</section>

			{{-- ═══ Estado C — cierre: 1 Contar · 2 Resultado ═══════════════════════════
			     Aquí la numeración sí es información: son dos pasos reales en secuencia. --}}
			<section class="caja-vista" id="caja-cierre" hidden>
				<div class="card caja-cierre">
					<div class="card-header border-0 flex-wrap gap-2">
						<div class="caja-pasos" aria-label="Pasos del cierre">
							<span class="caja-paso activo" data-paso="1"><span class="n">1</span> Contar</span>
							<span class="guion" aria-hidden="true"></span>
							<span class="caja-paso" data-paso="2"><span class="n">2</span> Resultado</span>
						</div>
						<span class="caja-ciego" id="caja-ciego">
							<i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
							Cuenta sin mirar lo esperado: lo verás al confirmar.
						</span>
					</div>

					<div class="card-body pt-2">
						{{-- Paso 1 — contar (arqueo ciego) --}}
						<div id="caja-paso-contar">
							@include('pos._caja-bandeja', [
								'id' => 'caja-bandeja-cierre',
								'modo' => 'conteo',
								'titulo' => 'Cuenta el efectivo del cajón',
								'subtitulo' => 'Toca cada billete o moneda y teclea cuántos hay. Doble toque suma uno.',
								'etiquetaTotal' => 'Total contado',
							])
							<div class="caja-acciones">
								<button type="button" class="btn btn-light caja-sec" id="caja-cierre-volver">
									<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver a la caja
								</button>
								<button type="button" class="btn caja-cta caja-cta-cerrar" id="caja-confirmar-conteo">
									<i class="fa-solid fa-check" aria-hidden="true"></i> Confirmar conteo
								</button>
							</div>
						</div>

						{{-- Paso 2 — resultado (el revelado) --}}
						<div id="caja-paso-resultado" hidden>
							<div class="caja-resultado" id="caja-resultado">
								<div>
									<ul class="caja-cifras">
										<li class="caja-revelar"><span class="lbl">Esperado en el cajón</span><span class="val" data-res="efectivo_esperado">0,00 €</span></li>
										<li class="caja-revelar"><span class="lbl">Contado</span><span class="val" data-res="efectivo_contado">0,00 €</span></li>
									</ul>

									<div class="caja-veredicto caja-revelar" id="caja-veredicto" data-estado="cuadra" role="status">
										<i class="fa-solid fa-circle-check" aria-hidden="true" data-veredicto="icono"></i>
										<div>
											<p class="titulo" data-veredicto="titulo">Cuadra</p>
											<p class="sub" data-veredicto="sub">El efectivo coincide al céntimo.</p>
										</div>
									</div>

									<div class="caja-observacion" id="caja-observacion" hidden>
										<label for="caja-observacion-txt">¿Qué pasó?</label>
										<textarea class="form-control" id="caja-observacion-txt" rows="3" maxlength="1000"
											placeholder="Por ejemplo: se pagó al repartidor del pan sin registrar la salida."></textarea>
										<small class="text-muted d-block mt-1" data-observacion="ayuda"></small>
									</div>

									<div class="caja-resultado-acciones" id="caja-acciones-provisional" hidden>
										<button type="button" class="btn btn-light caja-sec" id="caja-recontar">
											<i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Volver a contar
										</button>
										<button type="button" class="btn caja-cta caja-cta-cerrar" id="caja-cerrar-con-diferencia">
											Cerrar caja con esta diferencia
										</button>
									</div>

									<div class="caja-resultado-acciones" id="caja-acciones-final" hidden>
										<button type="button" class="btn caja-cta" id="caja-imprimir-ticket">
											<i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir 80 mm
										</button>
										<button type="button" class="btn btn-outline-secondary caja-sec" id="caja-ver-a4">
											<i class="fa-regular fa-file-lines" aria-hidden="true"></i> Ver en A4
										</button>
										<button type="button" class="btn btn-light caja-sec" id="caja-volver-inicio">
											Volver a la caja
										</button>
									</div>
								</div>

								<div class="caja-papel-wrap caja-revelar" id="caja-papel-wrap" hidden>
									<div class="caja-papel" id="caja-papel" aria-label="Informe Z"></div>
									<div class="caja-papel-borde" aria-hidden="true"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
		</div>
	</div>

	{{-- Movimiento de efectivo: modal pequeño, importe con el teclado propio dentro. --}}
	<div class="modal fade caja-modal" id="cajaMovimientoModal" tabindex="-1" aria-labelledby="cajaMovimientoTitulo" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content caja">
				<div class="modal-header">
					<h5 class="modal-title" id="cajaMovimientoTitulo">Movimiento de efectivo</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="btn-group filtro-segmentado w-100 caja-mov-tipo" role="group" aria-label="Tipo de movimiento">
						<button type="button" class="btn btn-outline-secondary" data-mov-tipo="entrada">Entrada</button>
						<button type="button" class="btn btn-outline-secondary" data-mov-tipo="salida">Salida</button>
					</div>

					<div class="caja-teclado">
						<div class="caja-teclado-display vacio" id="caja-mov-importe" aria-live="polite">0,00 €</div>
						<div class="caja-teclado-grid" id="caja-mov-teclado">
							@foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
								<button type="button" class="caja-key" data-key="{{ $n }}">{{ $n }}</button>
							@endforeach
							<button type="button" class="caja-key sec" data-key="," aria-label="Coma decimal">,</button>
							<button type="button" class="caja-key" data-key="0">0</button>
							<button type="button" class="caja-key sec" data-key="del" aria-label="Borrar"><i class="fa-solid fa-delete-left" aria-hidden="true"></i></button>
						</div>
					</div>

					<label class="form-label mt-3" for="caja-mov-motivo">Motivo</label>
					<input type="text" class="form-control caja-motivo" id="caja-mov-motivo" maxlength="160" autocomplete="off">
					<div class="caja-chips" id="caja-mov-chips"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light caja-sec" data-bs-dismiss="modal">Cancelar</button>
					<button type="button" class="btn caja-cta" id="caja-mov-guardar">Registrar salida</button>
				</div>
			</div>
		</div>
	</div>

	{{-- Vista previa del informe Z (docs/04 "Ver un documento: SIEMPRE en modal"). --}}
	<div class="modal fade" id="cajaInformeModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Informe de cierre de caja</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body p-0" style="height: 80vh;">
					<iframe id="cajaInformeFrame" src="" title="Informe de cierre de caja" style="width: 100%; height: 100%; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Caja')
@section('ayuda')
	@include('ayuda.pos-caja')
@endsection

@push('scripts')
	<script>
		window.cajaState = {
			estado: @json($estado),
			tenant: @json($tenantNombre),
			urls: {
				estado: @json(route('pos.caja')),
				abrir: @json(route('pos.caja.abrir')),
				movimiento: @json(route('pos.caja.movimientos.store')),
				cerrar: @json(route('pos.caja.cerrar')),
			},
			csrf: @json(csrf_token()),
			userId: @json(auth()->id()),
		};
	</script>
	<script src="@assetv('js/pos-teclado.js')"></script>
	<script src="@assetv('js/pos-caja-bandeja.js')"></script>
	<script src="@assetv('js/pos-caja-apertura.js')"></script>
	<script src="@assetv('js/plugins-init/pos-caja.init.js')"></script>
@endpush
