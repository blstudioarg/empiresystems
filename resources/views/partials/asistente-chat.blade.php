{{--
	Widget flotante del asistente IA (feature 030, D10). Render condicional en servidor:
	- Con clave configurada → widget completo para todos los usuarios del tenant.
	- Sin clave → solo usuarios con `ver-configuracion` ven el widget en modo "activar".
	- Resto → no se emite markup.
	Nunca en superadmin ni layouts fullwidth (este partial solo se incluye desde layouts/app).
--}}
@php
	$usuarioIa = auth()->user();
	$iaConfigurada = $usuarioIa && function_exists('tenant') && tenant() ? \App\Support\IaTenant::configurada() : false;
	$puedeConfigurar = $usuarioIa && $usuarioIa->can('ver-configuracion');
	$mostrarAsistente = $usuarioIa && ! $usuarioIa->isSuperAdmin() && ($iaConfigurada || $puedeConfigurar);
@endphp

@if ($mostrarAsistente)
	{{-- Estilos inline en el body a propósito: el partial se incluye después de que @stack('styles')
	     ya se imprimió en el <head>, así que un @push('styles') aquí llegaría tarde y no se vería. --}}
	<style>
			.asistente-chat { position: fixed; right: 24px; bottom: 24px; z-index: 1045; }
			.asistente-chat__toggle {
				width: 56px; height: 56px; border-radius: 50%; border: none;
				background: var(--primary, #4361ee); color: #fff; cursor: pointer;
				display: flex; align-items: center; justify-content: center;
				box-shadow: 0 6px 20px rgba(0, 0, 0, .22); transition: transform .15s ease, box-shadow .15s ease;
			}
			.asistente-chat__toggle:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(0, 0, 0, .28); }
			.asistente-chat__toggle:active { transform: scale(.94); transition-duration: .1s; }
			.asistente-chat__panel {
				position: absolute; right: 0; bottom: 72px; width: 380px; max-width: calc(100vw - 32px);
				height: 540px; max-height: calc(100vh - 120px); background: #fff; border-radius: 16px;
				box-shadow: 0 16px 48px rgba(0, 0, 0, .24); display: flex; flex-direction: column; overflow: hidden;
				/* Popover anclado al botón: escala desde su esquina inferior derecha, no desde el centro. */
				transform-origin: bottom right;
				opacity: 0; transform: scale(.96) translateY(10px); visibility: hidden; pointer-events: none;
				/* Estado cerrado = animación de SALIDA (más corta que la entrada). */
				transition: opacity .16s cubic-bezier(.23, 1, .32, 1),
				            transform .18s cubic-bezier(.23, 1, .32, 1),
				            visibility 0s linear .18s;
			}
			.asistente-chat.is-open .asistente-chat__panel {
				opacity: 1; transform: scale(1) translateY(0); visibility: visible; pointer-events: auto;
				/* Estado abierto = animación de ENTRADA, con un overshoot sutil que la hace sentir viva. */
				transition: opacity .2s cubic-bezier(.23, 1, .32, 1),
				            transform .26s cubic-bezier(.34, 1.28, .64, 1),
				            visibility 0s;
			}

			/* ---- Modo panel lateral (drawer desde la derecha), ideal para chats largos ---- */
			.asistente-chat__backdrop {
				position: fixed; inset: 0; z-index: 1044; background: rgba(15, 23, 42, .38);
				opacity: 0; visibility: hidden; pointer-events: none;
				transition: opacity .28s cubic-bezier(.32, .72, 0, 1), visibility 0s linear .28s;
			}
			.asistente-chat--drawer.is-open .asistente-chat__backdrop {
				opacity: 1; visibility: visible; pointer-events: auto;
				transition: opacity .3s cubic-bezier(.32, .72, 0, 1), visibility 0s;
			}
			.asistente-chat--drawer .asistente-chat__panel {
				position: fixed; top: 0; right: 0; bottom: 0; left: auto;
				width: 440px; max-width: 100vw; height: 100vh; height: 100dvh; max-height: none;
				border-radius: 16px 0 0 16px; z-index: 1046;
				transform-origin: right center;
				/* Cerrado: fuera de pantalla por la derecha (curva tipo iOS drawer). */
				opacity: 1; transform: translateX(100%); visibility: hidden; pointer-events: none;
				transition: transform .26s cubic-bezier(.32, .72, 0, 1), visibility 0s linear .26s;
			}
			.asistente-chat--drawer.is-open .asistente-chat__panel {
				transform: translateX(0); visibility: visible; pointer-events: auto;
				transition: transform .34s cubic-bezier(.32, .72, 0, 1), visibility 0s;
			}

			/* Iconos del switch de vista: cada modo muestra el icono del OTRO modo. */
			.asistente-chat__ico-flotante { display: none; }
			.asistente-chat--drawer .asistente-chat__ico-lateral { display: none; }
			.asistente-chat--drawer .asistente-chat__ico-flotante { display: inline; }

			@media (prefers-reduced-motion: reduce) {
				.asistente-chat__panel { transform: none; transition: opacity .15s ease, visibility 0s linear .15s; }
				.asistente-chat.is-open .asistente-chat__panel { transform: none; transition: opacity .15s ease, visibility 0s; }
				.asistente-chat--drawer .asistente-chat__panel,
				.asistente-chat--drawer.is-open .asistente-chat__panel { transition: opacity .15s ease, visibility 0s linear var(--vd, 0s); }
				.asistente-chat--drawer .asistente-chat__panel { opacity: 0; --vd: .15s; }
				.asistente-chat--drawer.is-open .asistente-chat__panel { opacity: 1; --vd: 0s; }
			}
			.asistente-chat__header {
				display: flex; align-items: center; justify-content: space-between;
				padding: 14px 16px; background: var(--primary, #4361ee); color: #fff;
			}
			.asistente-chat__title { display: flex; align-items: center; gap: 8px; font-weight: 600; }
			.asistente-chat__dot { width: 8px; height: 8px; border-radius: 50%; background: #2ecc71; box-shadow: 0 0 0 3px rgba(46, 204, 113, .3); }
			.asistente-chat__actions { display: flex; gap: 4px; }
			.asistente-chat__icon-btn { background: transparent; border: none; color: #fff; opacity: .85; cursor: pointer; padding: 4px; border-radius: 6px; }
			.asistente-chat__icon-btn:hover { opacity: 1; background: rgba(255, 255, 255, .15); }
			.asistente-chat__mensajes { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; background: #f7f8fc; }
			.asistente-chat__bienvenida { color: #6c757d; font-size: .9rem; text-align: center; margin: auto 0; }
			.asistente-msg { max-width: 85%; padding: 10px 13px; border-radius: 14px; font-size: .92rem; line-height: 1.45; white-space: pre-wrap; word-wrap: break-word; }
			.asistente-msg--user { align-self: flex-end; background: var(--primary, #4361ee); color: #fff; border-bottom-right-radius: 4px; }
			.asistente-msg--bot { align-self: flex-start; background: #fff; color: #212529; border: 1px solid #e9ecef; border-bottom-left-radius: 4px; }
			.asistente-msg--actividad { align-self: flex-start; font-size: .8rem; color: #6c757d; font-style: italic; }
			.asistente-chat__form { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #e9ecef; background: #fff; }
			.asistente-chat__input { flex: 1; resize: none; border: 1px solid #ced4da; border-radius: 10px; padding: 9px 12px; font-size: .92rem; max-height: 120px; }
			.asistente-chat__input:focus { outline: none; border-color: var(--primary, #4361ee); box-shadow: 0 0 0 3px rgba(67, 97, 238, .12); }
			.asistente-chat__enviar { border: none; background: var(--primary, #4361ee); color: #fff; border-radius: 10px; width: 42px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
			.asistente-chat__enviar:disabled { opacity: .5; cursor: not-allowed; }
			.asistente-chat__activar { padding: 24px; text-align: center; margin: auto 0; }
			.asistente-accion { align-self: flex-start; max-width: 92%; background: #fff; border: 1px solid #e9ecef; border-radius: 14px; padding: 12px; }
			.asistente-accion__resumen { font-size: .9rem; margin-bottom: 10px; }
			.asistente-accion__botones { display: flex; gap: 8px; }
		@media (max-width: 480px) { .asistente-chat__panel { width: calc(100vw - 32px); height: calc(100vh - 110px); } }
	</style>

	<div id="asistente-chat" class="asistente-chat" data-configurada="{{ $iaConfigurada ? '1' : '0' }}"
		data-url-mensaje="{{ route('asistente.mensaje') }}"
		data-url-reiniciar="{{ route('asistente.reiniciar') }}"
		data-url-confirmar="{{ url('asistente/accion') }}">

		{{-- Backdrop: solo visible en modo lateral con el panel abierto; click cierra. --}}
		<div class="asistente-chat__backdrop" id="asistente-backdrop" aria-hidden="true"></div>

		<button type="button" class="asistente-chat__toggle" id="asistente-toggle" aria-label="Abrir asistente IA" title="Asistente IA">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
				stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
			</svg>
		</button>

		<div class="asistente-chat__panel" id="asistente-panel" role="dialog" aria-label="Asistente IA">
			<header class="asistente-chat__header">
				<div class="asistente-chat__title">
					<span class="asistente-chat__dot"></span>
					Asistente IA
				</div>
				<div class="asistente-chat__actions">
					<button type="button" class="asistente-chat__icon-btn" id="asistente-modo" title="Cambiar entre vista flotante y panel lateral" aria-label="Cambiar vista">
						{{-- Icono "flotante": se muestra en modo lateral (para volver a flotante). --}}
						<svg class="asistente-chat__ico-flotante" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<rect x="10" y="11" width="11" height="9" rx="2"></rect><path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h2"></path>
						</svg>
						{{-- Icono "panel lateral": se muestra en modo flotante (para pasar a lateral). --}}
						<svg class="asistente-chat__ico-lateral" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M15 3v18"></path>
						</svg>
					</button>
					<button type="button" class="asistente-chat__icon-btn" id="asistente-nueva" title="Conversación nueva" aria-label="Conversación nueva">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path>
						</svg>
					</button>
					<button type="button" class="asistente-chat__icon-btn" id="asistente-cerrar" title="Cerrar" aria-label="Cerrar">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
						</svg>
					</button>
				</div>
			</header>

			@if ($iaConfigurada)
				<div class="asistente-chat__mensajes" id="asistente-mensajes" aria-live="polite">
					<div class="asistente-chat__bienvenida">
						¡Hola! Preguntame cómo funciona la app o pedime que consulte tus datos.
					</div>
				</div>
				<form class="asistente-chat__form" id="asistente-form">
					<textarea id="asistente-input" class="asistente-chat__input" rows="1" maxlength="4000"
						placeholder="Escribí tu mensaje…" autocomplete="off"></textarea>
					<button type="submit" class="asistente-chat__enviar" aria-label="Enviar">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M22 2 11 13"></path><path d="M22 2 15 22l-4-9-9-4z"></path>
						</svg>
					</button>
				</form>
			@else
				<div class="asistente-chat__activar">
					<p>El asistente todavía no está activado.</p>
					<p class="text-muted small">Configurá tu clave de API de OpenAI para empezar a usarlo.</p>
					<a href="{{ route('configuracion.show') }}#tab-ia" class="btn btn-primary btn-sm">Ir a Configuración</a>
				</div>
			@endif
		</div>
	</div>

	@push('scripts')
		<script src="{{ asset('js/asistente-chat.js') }}"></script>
	@endpush
@endif
