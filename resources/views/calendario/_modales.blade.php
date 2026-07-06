{{-- Modal de detalle de día (US4/FR-011): fichajes y cumplimiento desde extendedProps del feed. --}}
<div class="modal fade" id="calendarioDiaModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="calendario-dia-titulo">Detalle del día</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div id="calendario-dia-resumen" class="mb-3"></div>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead>
							<tr>
								<th>Hora</th>
								<th>Tipo</th>
								<th>Ubicación</th>
								<th>Corrección</th>
								<th></th>
							</tr>
						</thead>
						<tbody id="calendario-dia-fichajes"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-primary" id="calendario-asignar-horario-btn">Asignar horario</button>
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

{{-- Modal de equipo (US3/FR-010): miembros afectados de un día, con salto al calendario individual. --}}
<div class="modal fade" id="calendarioEquipoModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="calendario-equipo-titulo">Incumplimientos del día</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<ul class="list-group list-group-flush" id="calendario-equipo-miembros"></ul>
			</div>
		</div>
	</div>
</div>

{{-- Modal de corrección (US4/FR-012): mismos campos y endpoint que la vista de jornada. --}}
<div class="modal fade" id="corregirFichajeModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form id="corregirFichajeForm" method="POST" action="">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title">Corregir fichaje</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-2">
						<label class="form-label" for="corregir-tipo">Tipo</label>
						<select name="tipo" id="corregir-tipo" class="form-select">
							<option value="entrada">Entrada</option>
							<option value="salida">Salida</option>
							<option value="inicio_pausa">Inicio de pausa</option>
							<option value="fin_pausa">Fin de pausa</option>
						</select>
					</div>
					<div class="mb-2">
						<label class="form-label" for="corregir-ocurrido-at">Fecha y hora correcta</label>
						<input type="datetime-local" name="ocurrido_at" id="corregir-ocurrido-at" class="form-control" required>
					</div>
					<div class="mb-2">
						<label class="form-label" for="corregir-motivo">Motivo</label>
						<textarea name="motivo" id="corregir-motivo" class="form-control" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" data-loading-text="Guardando...">Guardar corrección</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Modal de asignación de horario (US4/FR-013): mismo endpoint y validaciones que miembros-equipo. --}}
<div class="modal fade" id="asignarHorarioModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form id="asignarHorarioForm" method="POST" action="">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title">Asignar horario</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label" for="asignar-horario-id">Horario</label>
						<select name="horario_id" id="asignar-horario-id" class="form-select" required></select>
					</div>
					<div class="mb-3">
						<label class="form-label" for="asignar-vigente-desde">Vigente desde</label>
						<input type="date" name="vigente_desde" id="asignar-vigente-desde" class="form-control" required>
					</div>
					<div>
						<h6 class="mb-2">Histórico de asignaciones</h6>
						<ul class="list-group list-group-flush" id="asignar-historico">
							<li class="list-group-item text-muted px-0">Cargando…</li>
						</ul>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" data-loading-text="Asignando...">Asignar</button>
				</div>
			</form>
		</div>
	</div>
</div>
