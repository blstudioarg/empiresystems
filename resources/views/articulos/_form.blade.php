@php
	$tiposImpositivosValidos = \App\Support\TiposImpositivos::validosPara(tenant()->regimen_impositivo);
@endphp
<div class="row">
	<div class="col-md-6">
		<label for="tipo" class="form-label">Tipo</label>
		<select name="tipo" id="tipo" class="form-control">
			<option value="producto">Producto</option>
			<option value="servicio">Servicio</option>
		</select>
		<div class="invalid-feedback" data-error-for="tipo"></div>
	</div>
	<div class="col-md-6">
		<label for="nombre" class="form-label">Nombre</label>
		<input type="text" name="nombre" id="nombre" class="form-control">
		<div class="invalid-feedback" data-error-for="nombre"></div>
	</div>

	<div class="col-md-4">
		<label for="sku" class="form-label">SKU</label>
		<input type="text" name="sku" id="sku" class="form-control">
		<div class="invalid-feedback" data-error-for="sku"></div>
	</div>
	<div class="col-md-4">
		<label for="unidad" class="form-label">Unidad</label>
		<x-unidad-select name="unidad" id="unidad" />
	</div>
	<div class="col-md-4">
		<label for="precio" class="form-label">Precio</label>
		<input type="number" step="0.0001" min="0" name="precio" id="precio" class="form-control">
		<div class="invalid-feedback" data-error-for="precio"></div>
	</div>

	<div class="col-md-6">
		<label for="categoria_id" class="form-label">Categoría</label>
		<x-categoria-select name="categoria_id" id="categoria_id" />
	</div>

	<div class="col-md-6">
		<label for="tipo_impositivo" class="form-label">Tipo impositivo (%)</label>
		@if ($tiposImpositivosValidos === null)
			<input type="number" step="0.01" min="0" max="100" name="tipo_impositivo" id="tipo_impositivo" class="form-control">
		@else
			<select name="tipo_impositivo" id="tipo_impositivo" class="form-control">
				@foreach ($tiposImpositivosValidos as $tipo)
					<option value="{{ $tipo }}">{{ rtrim(rtrim(number_format($tipo, 2), '0'), '.') }}%</option>
				@endforeach
			</select>
		@endif
		<div class="invalid-feedback" data-error-for="tipo_impositivo"></div>
	</div>
	<div class="col-md-12">
		<label for="descripcion" class="form-label">Descripción</label>
		<textarea name="descripcion" id="descripcion" rows="2" class="form-control"></textarea>
		<div class="invalid-feedback" data-error-for="descripcion"></div>
	</div>

	<div class="col-md-6">
		<label class="form-label" for="imagen">Imagen</label>
		<img id="imagen-preview" class="d-block mb-2" style="max-height: 80px;" hidden>
		<input type="file" accept="image/*" class="form-control" id="imagen" name="imagen">
		<div class="invalid-feedback" data-error-for="imagen"></div>
		<div class="form-check mt-1 campo-quitar-imagen" hidden>
			<input type="checkbox" name="quitar_imagen" value="1" class="form-check-input" id="quitar_imagen">
			<label class="form-check-label" for="quitar_imagen">Quitar imagen actual</label>
		</div>
	</div>

	<div class="col-md-4 d-flex align-items-end campos-producto" hidden>
		<div class="form-check">
			<input type="checkbox" name="gestion_stock" id="gestion_stock" value="1" class="form-check-input">
			<label class="form-check-label" for="gestion_stock">Gestionar stock</label>
		</div>
	</div>
	<div class="col-md-4 campos-stock" hidden>
		<label for="stock_actual" class="form-label">Stock actual</label>
		<input type="number" step="0.0001" name="stock_actual" id="stock_actual" class="form-control">
		<div class="invalid-feedback" data-error-for="stock_actual"></div>
	</div>
	<div class="col-md-4 campos-stock" hidden>
		<label for="stock_minimo" class="form-label">Stock mínimo</label>
		<input type="number" step="0.0001" name="stock_minimo" id="stock_minimo" class="form-control">
		<div class="invalid-feedback" data-error-for="stock_minimo"></div>
	</div>

	@if (tenant()->regimen_impositivo->value === 'iva')
		<div class="col-md-4 d-flex align-items-end">
			<div class="form-check">
				<input type="checkbox" name="aplica_recargo_equivalencia" id="aplica_recargo_equivalencia" value="1" class="form-check-input">
				<label class="form-check-label" for="aplica_recargo_equivalencia">Aplica recargo de equivalencia</label>
			</div>
		</div>
	@endif
</div>
