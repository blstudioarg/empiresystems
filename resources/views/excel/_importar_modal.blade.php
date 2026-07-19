{{--
	Modal reutilizable de importación (subir → previsualizar → confirmar), todo por AJAX — nunca
	navega a otra página. Un solo `@include('excel._importar_modal', ['modulo' => 'clientes'])`
	por vista; el JS que lo maneja se inicializa aparte con `window.initImportacionModal({...})`
	(ver docs/04-front-guidelines.md, "Botón Importar en modal").
--}}
<div class="modal fade" id="importarModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Importar {{ $etiqueta ?? $modulo }}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div id="importar-paso-subir">
					<form id="importar-form">
						<div class="mb-3">
							<label class="form-label" for="importar-fichero">Fichero (.xlsx, .xls, .csv)</label>
							<input type="file" class="form-control" id="importar-fichero" name="fichero" accept=".xlsx,.xls,.csv,.txt">
							<div class="invalid-feedback d-block" data-error-for="fichero"></div>
							<small class="form-text text-muted">Máximo 2.000 filas por fichero.</small>
						</div>
					</form>
					<a href="{{ route($modulo.'.importar.plantilla', ['modulo' => $modulo]) }}" class="btn btn-outline-secondary btn-sm">
						Descargar plantilla
					</a>
				</div>

				<div id="importar-paso-previsualizacion" class="d-none">
					<p class="mb-3">
						<strong data-campo="total_filas"></strong> filas leídas,
						<strong class="text-success" data-campo="validas"></strong> válidas,
						<strong class="text-danger" data-campo="rechazadas-count"></strong> rechazadas.
					</p>

					<div data-seccion="rechazadas" class="d-none">
						<p class="text-danger mb-2">Filas rechazadas:</p>
						<ul class="list-group list-group-flush mb-3" style="max-height: 200px; overflow-y: auto;" data-lista="rechazadas"></ul>
					</div>

					<div data-seccion="muestra" class="d-none">
						<p class="mb-2">Muestra de filas válidas:</p>
						<div class="table-responsive mb-3" style="max-height: 240px; overflow-y: auto;">
							<table class="table table-sm" data-tabla="muestra">
								<thead><tr></tr></thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>

				<div id="importar-paso-resultado" class="d-none">
					<p class="mb-3"><strong data-campo="importados"></strong> registros importados correctamente.</p>

					<div data-seccion="resultado-rechazadas" class="d-none">
						<p class="text-danger mb-2"><strong data-campo="rechazadas-final-count"></strong> filas rechazadas:</p>
						<ul class="list-group list-group-flush mb-3" style="max-height: 200px; overflow-y: auto;" data-lista="rechazadas-final"></ul>
						<a href="#" class="btn btn-outline-danger btn-sm" data-link="rechazos-descarga" target="_blank" rel="noopener">
							Descargar detalle de rechazos
						</a>
					</div>

					<p class="text-success mb-0 d-none" data-seccion="resultado-sin-rechazos">Sin filas rechazadas.</p>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-primary" id="importar-btn-previsualizar" data-loading-text="Analizando...">Previsualizar</button>
				<button type="button" class="btn btn-success d-none" id="importar-btn-confirmar" data-loading-text="Importando...">Confirmar</button>
			</div>
		</div>
	</div>
</div>
