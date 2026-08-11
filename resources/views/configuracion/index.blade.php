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
							<button class="nav-link active" id="tab-general-btn" data-bs-toggle="tab"
								data-bs-target="#tab-general" type="button" role="tab"
								aria-controls="tab-general" aria-selected="true">
								General
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-apariencia-btn" data-bs-toggle="tab"
								data-bs-target="#tab-apariencia" type="button" role="tab"
								aria-controls="tab-apariencia" aria-selected="false">
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
							<button class="nav-link" id="tab-email-btn" data-bs-toggle="tab"
								data-bs-target="#tab-email" type="button" role="tab"
								aria-controls="tab-email" aria-selected="false">
								Email
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-archivos-btn" data-bs-toggle="tab"
								data-bs-target="#tab-archivos" type="button" role="tab"
								aria-controls="tab-archivos" aria-selected="false">
								Archivos
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-certificado-btn" data-bs-toggle="tab"
								data-bs-target="#tab-certificado" type="button" role="tab"
								aria-controls="tab-certificado" aria-selected="false">
								Certificado
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-fichajes-btn" data-bs-toggle="tab"
								data-bs-target="#tab-fichajes" type="button" role="tab"
								aria-controls="tab-fichajes" aria-selected="false">
								Fichajes
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-pos-btn" data-bs-toggle="tab"
								data-bs-target="#tab-pos" type="button" role="tab"
								aria-controls="tab-pos" aria-selected="false">
								POS
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-ia-btn" data-bs-toggle="tab"
								data-bs-target="#tab-ia" type="button" role="tab"
								aria-controls="tab-ia" aria-selected="false">
								Asistente IA
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-crm-btn" data-bs-toggle="tab"
								data-bs-target="#tab-crm" type="button" role="tab"
								aria-controls="tab-crm" aria-selected="false">
								CRM
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-verifactu-btn" data-bs-toggle="tab"
								data-bs-target="#tab-verifactu" type="button" role="tab"
								aria-controls="tab-verifactu" aria-selected="false">
								Verifactu
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="tab-menu-btn" data-bs-toggle="tab"
								data-bs-target="#tab-menu" type="button" role="tab"
								aria-controls="tab-menu" aria-selected="false">
								Menú
							</button>
						</li>
					</ul>
					<div class="tab-content pt-4" id="configuracion-tabs-content">
						<div class="tab-pane fade show active" id="tab-general" role="tabpanel"
							aria-labelledby="tab-general-btn">
							@include('configuracion._tab_general')
						</div>
						<div class="tab-pane fade" id="tab-apariencia" role="tabpanel"
							aria-labelledby="tab-apariencia-btn">
							@include('configuracion._tab_apariencia')
						</div>
						<div class="tab-pane fade" id="tab-facturacion" role="tabpanel"
							aria-labelledby="tab-facturacion-btn">
							@include('configuracion._tab_facturacion')
						</div>
						<div class="tab-pane fade" id="tab-email" role="tabpanel"
							aria-labelledby="tab-email-btn">
							@include('configuracion._tab_email')
						</div>
						<div class="tab-pane fade" id="tab-archivos" role="tabpanel"
							aria-labelledby="tab-archivos-btn">
							@include('configuracion._tab_archivos')
						</div>
						<div class="tab-pane fade" id="tab-certificado" role="tabpanel"
							aria-labelledby="tab-certificado-btn">
							@include('configuracion._tab_certificado')
						</div>
						<div class="tab-pane fade" id="tab-fichajes" role="tabpanel"
							aria-labelledby="tab-fichajes-btn">
							@include('configuracion._tab_fichajes')
						</div>
						<div class="tab-pane fade" id="tab-pos" role="tabpanel"
							aria-labelledby="tab-pos-btn">
							@include('configuracion._tab_pos')
						</div>
						<div class="tab-pane fade" id="tab-ia" role="tabpanel"
							aria-labelledby="tab-ia-btn">
							@include('configuracion._tab_ia')
						</div>
						<div class="tab-pane fade" id="tab-crm" role="tabpanel"
							aria-labelledby="tab-crm-btn">
							@include('configuracion._tab_crm')
						</div>
						<div class="tab-pane fade" id="tab-verifactu" role="tabpanel"
							aria-labelledby="tab-verifactu-btn">
							@include('configuracion._tab_verifactu')
						</div>
						<div class="tab-pane fade" id="tab-menu" role="tabpanel"
							aria-labelledby="tab-menu-btn">
							@include('configuracion._tab_menu')
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Configuración')
@section('ayuda')
	@include('ayuda.configuracion')
@endsection
