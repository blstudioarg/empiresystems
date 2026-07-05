@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#cuentas-bancarias-table_wrapper .dataTables_paginate .paginate_button.previous,
		#cuentas-bancarias-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

<form id="facturacion-form" method="POST" action="{{ route('configuracion.apariencia.update') }}" enctype="multipart/form-data">
	@csrf
	@method('PUT')

	<p class="text-muted small mb-3">Los cambios se guardan automáticamente.</p>

	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="logo_facturacion">Logo de facturación (PDF)</label>
			<img id="logo-facturacion-preview" src="{{ $logoFacturacionPath ? asset('storage/'.$logoFacturacionPath) : '' }}"
				alt="Logo de facturación" class="d-block mb-2" style="max-height: 80px; {{ $logoFacturacionPath ? '' : 'display:none;' }}">
			<input type="file" class="form-control" id="logo_facturacion" name="logo_facturacion" accept="image/png,image/jpeg,image/webp">
			<small class="form-text text-muted">PNG, JPG o WEBP, máximo 1 MB. Si no se configura, se usa el logo por defecto del PDF de factura. Se sube al seleccionarlo.</small>
			@error('logo_facturacion')
				<div class="text-danger small mt-1">{{ $message }}</div>
			@enderror
		</div>
	</div>
</form>

<hr class="my-4">

<div class="mb-3">
	<h5 class="mb-1">Facturas simplificadas (POS)</h5>
	<p class="text-muted small mb-3">El tope de una factura simplificada es 400 € (IVA incl.). Si tu actividad está en un sector con tope ampliado (venta al por menor, hostelería/restauración, transporte de personas, peluquerías, aparcamiento, etc.), el tope pasa a 3.000 €.</p>
	<div class="form-check form-switch">
		<input class="form-check-input" type="checkbox" role="switch" id="simplificada_tope_ampliado"
			{{ ($simplificadaTopeAmpliado ?? false) ? 'checked' : '' }}>
		<label class="form-check-label" for="simplificada_tope_ampliado">
			Sector con tope ampliado (3.000 €)
		</label>
	</div>
</div>

<hr class="my-4">

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
	<div>
		<h5 class="mb-1">Cuentas bancarias</h5>
		<p class="text-muted small mb-0">Cuentas del negocio para cobros por transferencia. Las desactivadas dejan de ofrecerse al crear facturas, pero se conservan.</p>
	</div>
	<button type="button" class="btn btn-primary btn-add-cuenta" data-bs-toggle="modal" data-bs-target="#cuentaBancariaModal">
		+ Añadir cuenta
	</button>
</div>

<div class="table-responsive">
	<table id="cuentas-bancarias-table" class="display responsive nowrap w-100">
		<thead>
			<tr>
				<th>Alias</th>
				<th>Banco</th>
				<th>IBAN</th>
				<th>Titular</th>
				<th>Estado</th>
				<th>Acciones</th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>
</div>

<div class="modal fade" id="cuentaBancariaModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<form id="cuenta-bancaria-form" method="POST" action="{{ route('cuentas-bancarias.store') }}">
				@csrf
				<input type="hidden" name="_method" id="cuenta_method" value="POST">
				<div class="modal-header">
					<h5 class="modal-title" id="cuentaBancariaModalLabel">Añadir cuenta bancaria</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label" for="cuenta_banco_id">Banco</label>
						<x-banco-select name="banco_id" id="cuenta_banco_id" />
					</div>
					<div class="mb-3">
						<label class="form-label" for="cuenta_alias">Alias</label>
						<input type="text" class="form-control" id="cuenta_alias" name="alias" maxlength="255" placeholder="Ej. Cuenta principal">
						<div class="invalid-feedback" data-error-for="alias"></div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="cuenta_iban">IBAN</label>
						<input type="text" class="form-control font-monospace" id="cuenta_iban" name="iban" maxlength="34" placeholder="ES00 0000 0000 0000 0000 0000">
						<div class="invalid-feedback" data-error-for="iban"></div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="cuenta_titular">Titular</label>
						<input type="text" class="form-control" id="cuenta_titular" name="titular" maxlength="255">
						<div class="invalid-feedback" data-error-for="titular"></div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary">Guardar</button>
				</div>
			</form>
		</div>
	</div>
</div>

@push('scripts')
	<script>
		window.cuentaBancariaState = {
			indexUrl: @json(route('cuentas-bancarias.index')),
			storeUrl: @json(route('cuentas-bancarias.store')),
		};
	</script>
	<script>
		document.getElementById('simplificada_tope_ampliado').addEventListener('change', function () {
			var ampliado = this.checked;

			fetch(@json(route('configuracion.facturacion.update')), {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'X-HTTP-Method-Override': 'PUT',
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify({ simplificada_tope_ampliado: ampliado ? 1 : 0 }),
			})
				.then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
				.then(function (res) {
					if (!res.ok) {
						window.showToast('error', res.data.message || 'No se pudo guardar.');
						return;
					}
					window.showToast('success', res.data.message);
				})
				.catch(function () { window.showToast('error', 'No se pudo guardar la configuración.'); });
		});
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/cuentas-bancarias-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/cuentas-bancarias-modal.init.js') }}"></script>
@endpush
