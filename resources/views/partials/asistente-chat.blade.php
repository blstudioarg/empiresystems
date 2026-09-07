{{--
	Panel lateral del asistente IA (feature 030, D10). Render condicional en servidor:
	- Con clave configurada → panel completo para todos los usuarios del tenant.
	- Sin clave → solo usuarios con `ver-configuracion` ven el panel en modo "activar".
	- Resto → no se emite markup.
	Nunca en superadmin ni layouts fullwidth (este partial solo se incluye desde layouts/app).
	Se abre desde el botón de la topbar (`partials/header.blade.php`, id `asistente-toggle`),
	no hay botón flotante: decisión explícita del 2026-07-26 porque el flotante tapaba contenido
	y molestaba en pantallas chicas.
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
			.asistente-chat__backdrop {
				position: fixed; inset: 0; z-index: 1044; background: rgba(15, 23, 42, .38);
				opacity: 0; visibility: hidden; pointer-events: none;
				transition: opacity .28s cubic-bezier(.32, .72, 0, 1), visibility 0s linear .28s;
			}
			.asistente-chat.is-open .asistente-chat__backdrop {
				opacity: 1; visibility: visible; pointer-events: auto;
				transition: opacity .3s cubic-bezier(.32, .72, 0, 1), visibility 0s;
			}
			.asistente-chat__panel {
				position: fixed; top: 0; right: 0; bottom: 0; left: auto;
				width: 440px; max-width: 100vw; height: 100vh; height: 100dvh;
				background: #fff; border-radius: 16px 0 0 16px; z-index: 1046;
				box-shadow: 0 16px 48px rgba(0, 0, 0, .24); display: flex; flex-direction: column; overflow: hidden;
				transform-origin: right center;
				/* Cerrado: fuera de pantalla por la derecha (curva tipo iOS drawer). */
				transform: translateX(100%); visibility: hidden; pointer-events: none;
				transition: transform .26s cubic-bezier(.32, .72, 0, 1), visibility 0s linear .26s;
			}
			.asistente-chat.is-open .asistente-chat__panel {
				transform: translateX(0); visibility: visible; pointer-events: auto;
				transition: transform .34s cubic-bezier(.32, .72, 0, 1), visibility 0s;
			}
			@media (prefers-reduced-motion: reduce) {
				.asistente-chat__panel { transition: opacity .15s ease, visibility 0s linear .15s; opacity: 0; }
				.asistente-chat.is-open .asistente-chat__panel { transition: opacity .15s ease, visibility 0s; opacity: 1; transform: translateX(0); }
			}
			@media (max-width: 480px) { .asistente-chat__panel { width: 100vw; border-radius: 0; } }

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
			/* Indicador de progreso (feature 030): cubre el hueco entre que se envía el mensaje y
			   llega el primer texto del modelo, donde antes no había ningún feedback en pantalla. */
			.asistente-chat__estado {
				align-self: flex-start; display: flex; align-items: center; gap: 8px;
				background: #fff; border: 1px solid #e9ecef; border-radius: 14px; border-bottom-left-radius: 4px;
				padding: 10px 13px; font-size: .85rem; color: #6c757d;
			}
			.asistente-chat__puntos { display: inline-flex; gap: 4px; }
			.asistente-chat__puntos span {
				width: 6px; height: 6px; border-radius: 50%; background: var(--primary, #4361ee);
				animation: asistente-pensando 1.3s infinite ease-in-out both;
			}
			.asistente-chat__puntos span:nth-child(2) { animation-delay: .16s; }
			.asistente-chat__puntos span:nth-child(3) { animation-delay: .32s; }
			@keyframes asistente-pensando {
				0%, 70%, 100% { opacity: .25; transform: translateY(0); }
				35% { opacity: 1; transform: translateY(-3px); }
			}
			@media (prefers-reduced-motion: reduce) {
				.asistente-chat__puntos span { animation: none; opacity: .6; }
			}
			.asistente-chat__form { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #e9ecef; background: #fff; }
			.asistente-chat__input { flex: 1; resize: none; border: 1px solid #ced4da; border-radius: 10px; padding: 9px 12px; font-size: .92rem; max-height: 120px; }
			.asistente-chat__input:focus { outline: none; border-color: var(--primary, #4361ee); box-shadow: 0 0 0 3px rgba(67, 97, 238, .12); }
			.asistente-chat__enviar { border: none; background: var(--primary, #4361ee); color: #fff; border-radius: 10px; width: 42px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
			.asistente-chat__enviar:disabled { opacity: .5; cursor: not-allowed; }
			.asistente-chat__activar { padding: 24px; text-align: center; margin: auto 0; }
			.asistente-accion { align-self: flex-start; max-width: 92%; background: #fff; border: 1px solid #e9ecef; border-radius: 14px; padding: 12px; }
			.asistente-accion__resumen { font-size: .9rem; margin-bottom: 10px; }
			.asistente-accion__botones { display: flex; gap: 8px; }
		</style>

	<div id="asistente-chat" class="asistente-chat" data-configurada="{{ $iaConfigurada ? '1' : '0' }}"
		data-url-mensaje="{{ route('asistente.mensaje') }}"
		data-url-reiniciar="{{ route('asistente.reiniciar') }}"
		data-url-confirmar="{{ url('asistente/accion') }}">

		{{-- Backdrop: click cierra el panel. --}}
		<div class="asistente-chat__backdrop" id="asistente-backdrop" aria-hidden="true"></div>

		<div class="asistente-chat__panel" id="asistente-panel" role="dialog" aria-label="Asistente IA">
			<header class="asistente-chat__header">
				<div class="asistente-chat__title">
					<span class="asistente-chat__dot"></span>
					Asistente IA
				</div>
				<div class="asistente-chat__actions">
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
					<button type="submit" id="asistente-enviar" class="asistente-chat__enviar" aria-label="Enviar">
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
		<script src="@assetv('js/asistente-chat.js')"></script>
	@endpush
@endif
