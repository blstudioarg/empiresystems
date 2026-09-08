{{--
	Importar compras desde PDF/imagen interpretados por IA (feature 044).

	Cuatro estados alternados con `d-none`, mismo esquema que `excel/_importar_modal.blade.php`:
	subir → interpretando → propuesta → resumen. No se vuelve a pedir el fichero: el token que
	devuelve la subida viaja hasta el final.

	La tabla de líneas NO es un DataTable a propósito: es una tabla de EDICIÓN, igual que
	`compras/_form_lineas.blade.php`. La guía exige DataTable para listados, no para esto.
--}}
<div class="modal fade" id="importarDocumentoModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Importar documento de compra</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>

			<div class="modal-body">

				{{-- ---------------------------------------------------------- 1. Subir --}}
				<div id="doc-paso-subir">
					<form id="importarDocumentoForm" enctype="multipart/form-data">
						@csrf
						<p class="mb-3">
							Subí facturas o albaranes de proveedor en PDF o foto. La IA propone los datos y
							vos los revisás antes de crear cada compra: <strong>nada se guarda hasta que
							confirmes</strong>.
						</p>
						<div class="mb-3">
							<label class="form-label" for="doc-archivos">Documentos</label>
							<input type="file" class="form-control" id="doc-archivos" name="archivos[]"
								accept=".pdf,.jpg,.jpeg,.png,.webp" multiple required>
							<small class="text-muted d-block mt-1">
								Hasta {{ config('compras.documentos.max_ficheros') }} documentos,
								{{ config('compras.documentos.max_mb') }} MB cada uno,
								{{ config('compras.documentos.max_paginas_pdf') }} páginas por PDF.
							</small>
						</div>
						<div id="doc-rechazados" class="d-none mb-3"></div>
					</form>
				</div>

				{{-- --------------------------------------------------- 2. Interpretando --}}
				<div id="doc-paso-interpretando" class="d-none text-center py-4">
					<div class="spinner-border text-primary mb-3" role="status">
						<span class="visually-hidden">Interpretando…</span>
					</div>
					<p class="mb-1">Interpretando <strong id="doc-interpretando-nombre"></strong>…</p>
					<p class="text-muted mb-0"><span id="doc-interpretando-contador"></span></p>
				</div>

				{{-- ------------------------------------------------------ 3. Propuesta --}}
				<div id="doc-paso-propuesta" class="d-none">
					<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
						<h6 class="mb-0">
							<span id="doc-propuesta-nombre"></span>
							<span class="text-muted fw-normal" id="doc-propuesta-contador"></span>
						</h6>
						<a href="#" id="doc-propuesta-descargar" class="d-none"></a>
					</div>

					<div id="doc-avisos"></div>

					<div class="row g-3 mb-3">
						<div class="col-md-6">
							<label class="form-label" for="doc-proveedor">Proveedor</label>
							<div id="doc-proveedor-bloque"></div>
						</div>
						<div class="col-md-3">
							<label class="form-label" for="doc-numero">Nº de documento</label>
							<input type="text" class="form-control" id="doc-numero">
						</div>
						<div class="col-md-3">
							<label class="form-label" for="doc-fecha">Fecha</label>
							<input type="date" class="form-control" id="doc-fecha">
						</div>
					</div>

					<div class="table-responsive">
						<table class="table table-sm align-middle" id="doc-lineas-tabla">
							<thead>
								<tr>
									<th style="min-width: 220px;">Concepto</th>
									<th style="min-width: 90px;">Unidad</th>
									<th style="min-width: 90px;">Cantidad</th>
									<th style="min-width: 110px;">Precio</th>
									<th style="min-width: 90px;">Imp. %</th>
									<th class="text-end" style="min-width: 100px;">Base</th>
									<th style="width: 40px;"></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>

					<button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="doc-anadir-linea">
						+ Añadir línea
					</button>

					<div class="d-flex justify-content-end">
						<table class="table table-sm w-auto mb-0">
							<tr><td class="text-muted pe-3">Base</td><td class="text-end" id="doc-total-base">0,00 €</td></tr>
							<tr><td class="text-muted pe-3">Impuestos</td><td class="text-end" id="doc-total-cuota">0,00 €</td></tr>
							<tr class="fw-bold"><td class="pe-3">Total</td><td class="text-end" id="doc-total-total">0,00 €</td></tr>
						</table>
					</div>
				</div>

				{{-- -------------------------------------------------------- 4. Resumen --}}
				<div id="doc-paso-resumen" class="d-none">
					<p class="mb-3" id="doc-resumen-titulo"></p>
					<ul class="list-unstyled mb-0" id="doc-resumen-detalle"></ul>
				</div>

			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal" id="doc-btn-cerrar">Cancelar</button>

				<button type="button" class="btn btn-outline-secondary d-none" id="doc-btn-descartar">
					Descartar
				</button>

				<button type="submit" form="importarDocumentoForm" class="btn btn-primary" id="doc-btn-subir"
					data-loading-text="Subiendo...">
					Interpretar
				</button>

				<button type="button" class="btn btn-primary d-none" id="doc-btn-crear" data-loading-text="Creando...">
					Crear compra
				</button>
			</div>
		</div>
	</div>
</div>
