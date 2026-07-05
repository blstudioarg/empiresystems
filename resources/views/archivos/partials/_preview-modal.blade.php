<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="preview-modal-titulo">Vista previa</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body text-center p-0" id="preview-modal-body" style="min-height: 60vh;">
				{{-- El <iframe>/<img> se inyecta por JS al abrir, según el tipo de archivo. --}}
			</div>
			<div class="modal-footer">
				<a href="#" class="btn btn-primary" id="preview-modal-descargar" target="_blank">Descargar</a>
				<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>
