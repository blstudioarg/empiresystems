<div class="row">
	<div class="col-md-6">
		<label for="nombre" class="form-label">Nombre</label>
		<input type="text" name="nombre" id="nombre" class="form-control">
		<div class="invalid-feedback" data-error-for="nombre"></div>
	</div>
	<div class="col-md-6">
		<label for="razon_social" class="form-label">Razón social</label>
		<input type="text" name="razon_social" id="razon_social" class="form-control">
		<div class="invalid-feedback" data-error-for="razon_social"></div>
	</div>

	<div class="col-md-6">
		<label for="nif" class="form-label">NIF / CIF</label>
		<input type="text" name="nif" id="nif" class="form-control">
		<div class="invalid-feedback" data-error-for="nif"></div>
	</div>
	<div class="col-md-6">
		<label for="email" class="form-label">Email</label>
		<input type="email" name="email" id="email" class="form-control">
		<div class="invalid-feedback" data-error-for="email"></div>
	</div>

	<div class="col-md-6">
		<label for="telefono" class="form-label">Teléfono</label>
		<input type="text" name="telefono" id="telefono" class="form-control">
		<div class="invalid-feedback" data-error-for="telefono"></div>
	</div>
	<div class="col-md-6">
		<label for="direccion" class="form-label">Dirección</label>
		<input type="text" name="direccion" id="direccion" class="form-control">
		<div class="invalid-feedback" data-error-for="direccion"></div>
	</div>

	<div class="col-md-4">
		<label for="cp" class="form-label">Código postal</label>
		<input type="text" name="cp" id="cp" class="form-control">
		<div class="invalid-feedback" data-error-for="cp"></div>
	</div>
	<div class="col-md-4">
		<label for="provincia" class="form-label">Provincia</label>
		<select name="provincia" id="provincia" class="form-control">
			<option value="">Selecciona una provincia</option>
			@foreach ($provincias as $provincia)
				<option value="{{ $provincia->nombre }}" data-provincia-id="{{ $provincia->id }}">{{ $provincia->nombre }}</option>
			@endforeach
		</select>
		<div class="invalid-feedback" data-error-for="provincia"></div>
	</div>
	<div class="col-md-4">
		<label for="ciudad" class="form-label">Ciudad</label>
		<select name="ciudad" id="ciudad" class="form-control">
			<option value="">Selecciona una provincia primero</option>
		</select>
		<div class="invalid-feedback" data-error-for="ciudad"></div>
	</div>

	<div class="col-md-4">
		<label for="pais" class="form-label">País</label>
		<input type="text" name="pais" id="pais" value="ES" maxlength="2" class="form-control">
		<div class="invalid-feedback" data-error-for="pais"></div>
	</div>

	<div class="col-md-12">
		<label for="notas" class="form-label">Notas</label>
		<textarea name="notas" id="notas" rows="3" class="form-control"></textarea>
		<div class="invalid-feedback" data-error-for="notas"></div>
	</div>
</div>
