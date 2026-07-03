{{--
    Modal genérico de confirmación de eliminación. Se incluye una sola vez en layouts/app.blade.php
    y lo dispara `window.confirmDelete(mensaje, onConfirm)` (definido en public/js/confirm-delete.js).
    Ver docs/04-front-guidelines.md ("Confirmación de eliminación") — nunca usar `confirm()` nativo
    del navegador para esto.
--}}
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content text-center">
			<div class="modal-body pt-4 pb-0">
				<x-lordicon icon="wired-outline-185-trash-bin-hover-empty" trigger="loop" size="150" />
				<p id="confirmDeleteMessage" class="mb-0 mt-2"></p>
			</div>
			<div class="modal-footer border-0 justify-content-center pb-4">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
				<button type="button" id="confirmDeleteButton" class="btn btn-danger">Eliminar</button>
			</div>
		</div>
	</div>
</div>
