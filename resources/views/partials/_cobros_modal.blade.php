{{-- Modal de cobros de una factura (feature 043, research D6): extraído de facturas/index.blade.php
     a comportamiento constante para que también lo use cobros/index.blade.php. La lógica JS vive
     en public/js/plugins-init/cobros-modal.js (window.initCobrosModal). --}}
<div class="modal fade" id="cobrosModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Cobros de la factura</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div id="cobroContextoRectificada" class="alert alert-warning py-2 px-3 mb-3 d-none" role="alert"></div>
				<p class="mb-2">Saldo pendiente: <strong id="cobroSaldoPendiente">0,00</strong> €</p>

				<div class="table-responsive mb-3">
					<table id="cobros-table" class="table table-sm display responsive nowrap w-100">
						<thead>
							<tr>
								<th>Fecha</th>
								<th>Método</th>
								<th>Referencia</th>
								<th class="text-end">Importe</th>
								<th>Estado</th>
								<th></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>

				<form id="registrarCobroForm">
					@csrf
					<h6 class="mb-2">Registrar nuevo cobro</h6>
					<div class="mb-3">
						<label for="cobroFecha" class="form-label">Fecha</label>
						<input type="date" id="cobroFecha" name="fecha" class="form-control" required>
					</div>
					<div class="mb-3">
						<label for="cobroImporte" class="form-label">Importe</label>
						<div class="input-group">
							<input type="number" id="cobroImporte" name="importe" class="form-control" step="0.01" min="0.01" required>
							<button type="button" id="cobroPagarRestante" class="btn btn-outline-secondary">Pagar restante</button>
						</div>
						<div class="invalid-feedback d-block" data-error-for="importe"></div>
					</div>
					<div class="mb-3">
						<label for="cobroMetodo" class="form-label">Método</label>
						<select id="cobroMetodo" name="metodo" class="form-control" required>
							<option value="transferencia">Transferencia</option>
							<option value="tarjeta">Tarjeta</option>
							<option value="efectivo">Efectivo</option>
							<option value="domiciliacion">Domiciliación</option>
						</select>
					</div>
					<div class="mb-3">
						<label for="cobroReferencia" class="form-label">Referencia</label>
						<input type="text" id="cobroReferencia" name="referencia" class="form-control" maxlength="100">
					</div>
					<button type="submit" class="btn btn-primary">Registrar cobro</button>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>
