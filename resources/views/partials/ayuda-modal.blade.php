{{--
    Modal de ayuda contextual (mini-tutorial), único y global. Se incluye una sola vez en
    layouts/app.blade.php. El contenido lo aporta cada vista con @section('ayuda') (+ el título
    con @section('ayuda-titulo')); si la vista no define ayuda, muestra un estado vacío.
    Se dispara desde el botón "Ayuda" del sidebar. Ver docs/04-front-guidelines.md.
--}}
<div class="modal fade ayuda-modal" id="ayudaContextualModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content ayuda-content">
			<button type="button" class="ayuda-close" data-bs-dismiss="modal" aria-label="Cerrar">
				<i class="fas fa-times"></i>
			</button>

			<div class="ayuda-head">
				<div class="ayuda-head-icon">
					<x-lordicon icon="wired-outline-424-question-bubble-hover-wiggle" trigger="loop" size="34" />
				</div>
				<div class="ayuda-head-text">
					<span class="ayuda-eyebrow">Guía rápida</span>
					<h5 class="ayuda-title">@yield('ayuda-titulo', 'Ayuda')</h5>
				</div>
			</div>

			<div class="ayuda-body">
				@hasSection('ayuda')
					@yield('ayuda')
				@else
					<div class="ayuda-empty">
						<p class="mb-0">Todavía no hay una guía para esta pantalla. La iremos
							completando poco a poco.</p>
					</div>
				@endif
			</div>
		</div>
	</div>
</div>
