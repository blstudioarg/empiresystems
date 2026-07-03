@extends('layouts.app')

@section('title', 'Configuración')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header">
					<h4 class="card-title">Configuración</h4>
				</div>
				<div class="card-body">
					<ul class="nav nav-tabs" id="configuracion-tabs" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="tab-apariencia-btn" data-bs-toggle="tab"
								data-bs-target="#tab-apariencia" type="button" role="tab"
								aria-controls="tab-apariencia" aria-selected="true">
								Apariencia / Marca
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-facturacion-btn" data-bs-toggle="tab"
								data-bs-target="#tab-facturacion" type="button" role="tab"
								aria-controls="tab-facturacion" aria-selected="false">
								Facturación
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link disabled" type="button" role="tab" disabled>
								Verifactu <span class="badge badge-sm badge-secondary light ms-1">Próximamente</span>
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link disabled" type="button" role="tab" disabled>
								Email <span class="badge badge-sm badge-secondary light ms-1">Próximamente</span>
							</button>
						</li>
					</ul>
					<div class="tab-content pt-4" id="configuracion-tabs-content">
						<div class="tab-pane fade show active" id="tab-apariencia" role="tabpanel"
							aria-labelledby="tab-apariencia-btn">
							@include('configuracion._tab_apariencia')
						</div>
						<div class="tab-pane fade" id="tab-facturacion" role="tabpanel"
							aria-labelledby="tab-facturacion-btn">
							@include('configuracion._tab_facturacion')
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
