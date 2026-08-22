{{--
	Barra de filtros del módulo de Cobros (feature 043, FR-011). Estado de cobro como grupo de
	botones (patrón del filtro por tipo de facturas/index.blade.php), "solo vencidas" como control
	aparte que se combina con AND (research D10), y el selector de rango de fechas clonado de
	informes-comerciales/index.blade.php (research D5).
--}}
<div id="cobros-filtro-barra" class="d-flex flex-wrap align-items-center gap-2">
	<div class="btn-group" role="group" aria-label="Filtrar por estado de cobro">
		<button type="button" class="btn btn-outline-secondary btn-filtro-cobro active" data-estado-cobro="">Todas</button>
		<button type="button" class="btn btn-outline-secondary btn-filtro-cobro" data-estado-cobro="pendiente">Pendiente</button>
		<button type="button" class="btn btn-outline-secondary btn-filtro-cobro" data-estado-cobro="parcial">Parcial</button>
		<button type="button" class="btn btn-outline-secondary btn-filtro-cobro" data-estado-cobro="cobrada">Cobrada</button>
	</div>

	<div class="form-check form-switch ms-1">
		<input class="form-check-input" type="checkbox" id="cobros-filtro-vencidas">
		<label class="form-check-label" for="cobros-filtro-vencidas">Solo vencidas</label>
	</div>

	<select id="cobros-filtro-cliente" class="form-select form-select-sm" style="max-width: 220px;">
		<option value="">Todos los clientes</option>
		@foreach ($clientes as $cliente)
			<option value="{{ $cliente->id }}">{{ $cliente->razon_social ?: $cliente->nombre }}</option>
		@endforeach
	</select>

	<select id="cobros-filtro-serie" class="form-select form-select-sm" style="max-width: 160px;">
		<option value="">Todas las series</option>
		@foreach ($series as $serie)
			<option value="{{ $serie->id }}">{{ $serie->codigo }}</option>
		@endforeach
	</select>

	<div class="btn-group ms-auto" role="group" aria-label="Rango de fechas">
		<input type="radio" class="btn-check" name="cobros-preset" id="cobros-preset-mes" value="mes" autocomplete="off" checked>
		<label class="btn btn-outline-primary" for="cobros-preset-mes">Mes</label>

		<input type="radio" class="btn-check" name="cobros-preset" id="cobros-preset-trimestre" value="trimestre" autocomplete="off">
		<label class="btn btn-outline-primary" for="cobros-preset-trimestre">Trimestre</label>

		<input type="radio" class="btn-check" name="cobros-preset" id="cobros-preset-anio" value="anio" autocomplete="off">
		<label class="btn btn-outline-primary" for="cobros-preset-anio">Año</label>

		<input type="radio" class="btn-check" name="cobros-preset" id="cobros-preset-personalizado" value="personalizado" autocomplete="off">
		<label class="btn btn-outline-primary" for="cobros-preset-personalizado">Personalizado</label>
	</div>

	<div id="cobros-rango-personalizado" class="d-none">
		<input type="text" id="cobros-rango-input" class="form-control form-control-sm" style="min-width: 200px;" readonly>
		<input type="hidden" id="cobros-rango-desde">
		<input type="hidden" id="cobros-rango-hasta">
	</div>
</div>
