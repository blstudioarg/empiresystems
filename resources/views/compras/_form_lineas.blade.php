<table class="table table-sm" id="lineas-table">
	<thead>
		<tr>
			<th>Artículo</th>
			<th>Concepto</th>
			<th>Unidad</th>
			<th>Cantidad</th>
			<th>Precio unitario</th>
			<th>% Impuesto</th>
			<th></th>
		</tr>
	</thead>
	<tbody id="lineas-body"></tbody>
</table>
<button type="button" class="btn btn-outline-primary" id="btn-add-linea">+ Añadir línea</button>

<template id="linea-template">
	<tr class="linea-row">
		<td style="min-width: 180px;">
			<select class="form-control linea-articulo">
				<option value="">Línea libre (sin artículo)</option>
				@foreach ($articulos as $articulo)
					<option value="{{ $articulo->id }}" data-nombre="{{ $articulo->nombre }}" data-precio="{{ $articulo->precio }}" data-tipo-impositivo="{{ $articulo->tipo_impositivo }}" data-unidad="{{ $articulo->unidad }}">{{ $articulo->nombre }}</option>
				@endforeach
			</select>
		</td>
		<td><input type="text" class="form-control linea-concepto"></td>
		<td style="width: 100px;"><input type="text" class="form-control linea-unidad"></td>
		<td style="width: 110px;"><input type="number" step="0.0001" min="0.0001" class="form-control linea-cantidad" value="1"></td>
		<td style="width: 130px;"><input type="number" step="0.0001" min="0" class="form-control linea-precio" value="0"></td>
		<td style="width: 100px;"><input type="number" step="0.01" min="0" class="form-control linea-impuesto" value="21"></td>
		<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-linea">&times;</button></td>
	</tr>
</template>
