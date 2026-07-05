<div class="modal fade" id="ajusteStockModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<form id="ajuste-stock-form" method="POST" action="{{ route('stock.ajuste') }}">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title">Ajuste manual de stock</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-md-12">
							<label for="ajuste_articulo_id" class="form-label">Artículo</label>
							<select name="articulo_id" id="ajuste_articulo_id" class="form-control">
								<option value="">Selecciona un artículo</option>
							</select>
							<div class="invalid-feedback" data-error-for="articulo_id"></div>
						</div>
						<div class="col-md-6">
							<label for="ajuste_tipo" class="form-label">Tipo</label>
							<select name="tipo" id="ajuste_tipo" class="form-control">
								<option value="entrada">Entrada</option>
								<option value="salida">Salida</option>
							</select>
							<div class="invalid-feedback" data-error-for="tipo"></div>
						</div>
						<div class="col-md-6">
							<label for="ajuste_cantidad" class="form-label">Cantidad</label>
							<input type="number" step="0.0001" min="0.0001" name="cantidad" id="ajuste_cantidad" class="form-control">
							<div class="invalid-feedback" data-error-for="cantidad"></div>
						</div>
						<div class="col-md-12">
							<label for="ajuste_motivo" class="form-label">Motivo</label>
							<input type="text" name="motivo" id="ajuste_motivo" class="form-control" placeholder="Ej. recuento de inventario, rotura...">
							<div class="invalid-feedback" data-error-for="motivo"></div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary">Registrar ajuste</button>
				</div>
			</form>
		</div>
	</div>
</div>
