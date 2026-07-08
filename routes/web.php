<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\ArticuloController;
use App\Http\Controllers\AsignacionHorarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BancoController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CampanaController;
use App\Http\Controllers\CarpetaController;
use App\Http\Controllers\CategoriaArticuloController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CompraFacturaeController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CorreccionFichajeController;
use App\Http\Controllers\CuentaBancariaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\FacturaeController;
use App\Http\Controllers\FichajeController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\InformeJornadaController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadImportacionController;
use App\Http\Controllers\LocalidadController;
use App\Http\Controllers\LogActividadController;
use App\Http\Controllers\MiembroEquipoController;
use App\Http\Controllers\MiJornadaController;
use App\Http\Controllers\MovimientoStockController;
use App\Http\Controllers\OportunidadController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PlantillaEmailController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SuperAdmin\TenantController as SuperAdminTenantController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant.context', 'guest'])->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');

    Route::get('/registro', [RegisterController::class, 'create'])->name('register.create');
    Route::post('/registro', [RegisterController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');
});

Route::middleware(['tenant.context', 'auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('can:ver-clientes')->group(function () {
        Route::resource('clientes', ClienteController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/localidades', [LocalidadController::class, 'index'])->name('localidades.index');
    });

    Route::middleware('can:ver-articulos')->group(function () {
        Route::resource('articulos', ArticuloController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('unidades', UnidadController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('categorias', CategoriaArticuloController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('can:ver-facturas')->group(function () {
        Route::get('/facturas', [FacturaController::class, 'index'])->name('facturas.index');
        Route::get('/facturas/crear', [FacturaController::class, 'create'])->name('facturas.create');
        Route::post('/facturas', [FacturaController::class, 'store'])->name('facturas.store');
        Route::get('/facturas/{factura}/editar', [FacturaController::class, 'edit'])->name('facturas.edit');
        Route::match(['put', 'patch'], '/facturas/{factura}', [FacturaController::class, 'update'])->name('facturas.update');
        Route::delete('/facturas/{factura}', [FacturaController::class, 'destroy'])->name('facturas.destroy');
        Route::post('/facturas/{factura}/emitir', [FacturaController::class, 'emitir'])->name('facturas.emitir');
        Route::post('/facturas/{factura}/rectificar', [FacturaController::class, 'rectificar'])->name('facturas.rectificar');
        Route::get('/facturas/{factura}/pdf', [FacturaController::class, 'pdf'])->name('facturas.pdf');
        Route::post('/facturas/{factura}/enviar', [FacturaController::class, 'enviar'])->name('facturas.enviar');
        Route::get('/facturas/{factura}/facturae', [FacturaeController::class, 'descargar'])->name('facturas.facturae.descargar');
        Route::post('/facturas/{factura}/facturae', [FacturaeController::class, 'generarYEnviar'])->name('facturas.facturae.generar-enviar');
        Route::post('/facturas/{factura}/facturae/reenviar', [FacturaeController::class, 'reenviar'])->name('facturas.facturae.reenviar');
        Route::get('/facturas/{factura}/pagos', [PagoController::class, 'index'])->name('facturas.pagos.index');
        Route::post('/facturas/{factura}/pagos', [PagoController::class, 'store'])->name('facturas.pagos.store');
        Route::post('/pagos/{pago}/anular', [PagoController::class, 'anular'])->name('pagos.anular');
    });

    // CRM — leads, oportunidades, presupuestos (feature 028)
    Route::middleware('can:ver-leads')->group(function () {
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('/leads/importar', [LeadImportacionController::class, 'form'])->name('leads.importar.form');
        Route::post('/leads/importar', [LeadImportacionController::class, 'importar'])->name('leads.importar');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
        Route::post('/leads/{lead}/notas', [LeadController::class, 'storeNota'])->name('leads.notas.store');
        Route::post('/leads/{lead}/convertir', [LeadController::class, 'convertir'])->name('leads.convertir');
    });

    Route::middleware('can:ver-oportunidades')->group(function () {
        Route::get('/oportunidades', [OportunidadController::class, 'index'])->name('oportunidades.index');
        Route::post('/oportunidades', [OportunidadController::class, 'store'])->name('oportunidades.store');
        Route::get('/oportunidades/{oportunidad}', [OportunidadController::class, 'show'])->name('oportunidades.show');
        Route::put('/oportunidades/{oportunidad}', [OportunidadController::class, 'update'])->name('oportunidades.update');
        Route::put('/oportunidades/{oportunidad}/etapa', [OportunidadController::class, 'actualizarEtapa'])->name('oportunidades.etapa');
        Route::delete('/oportunidades/{oportunidad}', [OportunidadController::class, 'destroy'])->name('oportunidades.destroy');
    });

    Route::middleware('can:ver-presupuestos')->group(function () {
        Route::get('/presupuestos', [PresupuestoController::class, 'index'])->name('presupuestos.index');
        Route::get('/presupuestos/crear', [PresupuestoController::class, 'create'])->name('presupuestos.create');
        Route::post('/presupuestos', [PresupuestoController::class, 'store'])->name('presupuestos.store');
        Route::get('/presupuestos/{presupuesto}/editar', [PresupuestoController::class, 'edit'])->name('presupuestos.edit');
        Route::put('/presupuestos/{presupuesto}', [PresupuestoController::class, 'update'])->name('presupuestos.update');
        Route::delete('/presupuestos/{presupuesto}', [PresupuestoController::class, 'destroy'])->name('presupuestos.destroy');
        Route::put('/presupuestos/{presupuesto}/estado', [PresupuestoController::class, 'actualizarEstado'])->name('presupuestos.estado');
        Route::post('/presupuestos/{presupuesto}/convertir', [PresupuestoController::class, 'convertir'])->name('presupuestos.convertir');
        Route::get('/presupuestos/{presupuesto}/pdf', [PresupuestoController::class, 'pdf'])->name('presupuestos.pdf');
        Route::post('/presupuestos/{presupuesto}/enviar', [PresupuestoController::class, 'enviar'])->name('presupuestos.enviar');
    });

    // POS — facturas simplificadas (tickets)
    Route::middleware('can:ver-pos')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/crear', [PosController::class, 'create'])->name('pos.create');
        Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
        Route::get('/pos/{factura}/pdf', [PosController::class, 'pdf'])->name('pos.pdf');
    });

    // Perfil: sección personal, sin permiso (todo usuario autenticado del tenant).
    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/perfil/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');

    Route::middleware('can:ver-bancos')->group(function () {
        Route::resource('bancos', BancoController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('cuentas-bancarias', CuentaBancariaController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/cuentas-bancarias/{id}/restaurar', [CuentaBancariaController::class, 'restore'])->name('cuentas-bancarias.restore');
    });

    Route::middleware('can:ver-configuracion')->group(function () {
        Route::get('/configuracion', [ConfiguracionController::class, 'show'])->name('configuracion.show');
        Route::match(['put', 'patch'], '/configuracion/apariencia', [ConfiguracionController::class, 'update'])
            ->name('configuracion.apariencia.update');
        Route::match(['put', 'patch'], '/configuracion/facturacion', [ConfiguracionController::class, 'updateFacturacion'])
            ->name('configuracion.facturacion.update');
        Route::match(['put', 'patch'], '/configuracion/email', [ConfiguracionController::class, 'updateEmail'])
            ->name('configuracion.email.update');
        Route::post('/configuracion/email/prueba', [ConfiguracionController::class, 'enviarPrueba'])
            ->name('configuracion.email.prueba');
        Route::match(['put', 'patch'], '/configuracion/archivos', [ConfiguracionController::class, 'updateArchivos'])
            ->name('configuracion.archivos.update');
        Route::match(['put', 'patch'], '/configuracion/certificado', [ConfiguracionController::class, 'updateCertificado'])
            ->name('configuracion.certificado.update');
        Route::post('/configuracion/certificado/verificar-vies', [ConfiguracionController::class, 'verificarVies'])
            ->name('configuracion.certificado.verificar-vies');
        Route::match(['put', 'patch'], '/configuracion/fichajes', [ConfiguracionController::class, 'updateFichajes'])
            ->name('configuracion.fichajes.update');
        Route::match(['put', 'patch'], '/configuracion/general', [ConfiguracionController::class, 'updateGeneral'])
            ->name('configuracion.general.update');
        Route::match(['put', 'patch'], '/configuracion/crm', [ConfiguracionController::class, 'updateCrm'])
            ->name('configuracion.crm.update');
    });

    Route::middleware('can:ver-stock')->group(function () {
        Route::get('/stock', [MovimientoStockController::class, 'index'])->name('stock.index');
        Route::get('/stock/{articulo}', [MovimientoStockController::class, 'show'])->name('stock.show');
        Route::post('/stock/ajuste', [MovimientoStockController::class, 'ajuste'])->name('stock.ajuste');
    });

    Route::middleware('can:ver-proveedores')->group(function () {
        Route::resource('proveedores', ProveedorController::class)
            ->parameters(['proveedores' => 'proveedor'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('can:ver-compras')->group(function () {
        Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
        Route::get('/compras/crear', [CompraController::class, 'create'])->name('compras.create');
        Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
        Route::get('/compras/{compra}', [CompraController::class, 'show'])->name('compras.show');
        Route::get('/compras/{compra}/editar', [CompraController::class, 'edit'])->name('compras.edit');
        Route::match(['put', 'patch'], '/compras/{compra}', [CompraController::class, 'update'])->name('compras.update');
        Route::post('/compras/{compra}/confirmar', [CompraController::class, 'confirmar'])->name('compras.confirmar');
        Route::post('/compras/{compra}/anular', [CompraController::class, 'anular'])->name('compras.anular');
        Route::delete('/compras/{compra}', [CompraController::class, 'destroy'])->name('compras.destroy');
        Route::post('/compras/importar-facturae', [CompraFacturaeController::class, 'importar'])->name('compras.facturae.importar');
        Route::get('/compras/{compra}/facturae', [CompraFacturaeController::class, 'descargar'])->name('compras.facturae.descargar');
        Route::patch('/compras/{compra}/estado-b2b', [CompraFacturaeController::class, 'cambiarEstadoB2b'])->name('compras.estado-b2b.update');
    });

    // Email marketing — plantillas y campañas
    Route::middleware('can:ver-plantillas-email')->group(function () {
        Route::resource('plantillas-email', PlantillaEmailController::class)
            ->parameters(['plantillas-email' => 'plantilla'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('can:ver-campanas')->group(function () {
        Route::get('/campanas', [CampanaController::class, 'index'])->name('campanas.index');
        Route::get('/campanas/crear', [CampanaController::class, 'create'])->name('campanas.create');
        Route::post('/campanas', [CampanaController::class, 'store'])->name('campanas.store');
        Route::get('/campanas/{campana}', [CampanaController::class, 'show'])->name('campanas.show');
        Route::post('/campanas/{campana}/enviar-tanda', [CampanaController::class, 'enviarTanda'])->name('campanas.enviar-tanda');
        Route::post('/campanas/{campana}/reintentar', [CampanaController::class, 'reintentar'])->name('campanas.reintentar');
    });

    Route::middleware('can:ver-archivos')->group(function () {
        Route::get('/archivos', [ArchivoController::class, 'index'])->name('archivos.index');
        Route::post('/archivos', [ArchivoController::class, 'store'])->name('archivos.store');
        Route::match(['put', 'patch'], '/archivos/{archivo}', [ArchivoController::class, 'update'])->name('archivos.update');
        Route::delete('/archivos/{archivo}', [ArchivoController::class, 'destroy'])->name('archivos.destroy');
        Route::get('/archivos/{archivo}/descargar', [ArchivoController::class, 'descargar'])->name('archivos.descargar');
        Route::get('/archivos/{archivo}/preview', [ArchivoController::class, 'preview'])->name('archivos.preview');
        Route::post('/archivos/carpetas', [CarpetaController::class, 'store'])->name('carpetas.store');
        Route::match(['put', 'patch'], '/archivos/carpetas/{carpeta}', [CarpetaController::class, 'update'])->name('carpetas.update');
        Route::delete('/archivos/carpetas/{carpeta}', [CarpetaController::class, 'destroy'])->name('carpetas.destroy');
    });

    Route::middleware('can:ver-usuarios')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::patch('/usuarios/{usuario}/aprobar', [UsuarioController::class, 'aprobar'])->name('usuarios.aprobar');
        Route::patch('/usuarios/{usuario}/rechazar', [UsuarioController::class, 'rechazar'])->name('usuarios.rechazar');
        Route::patch('/usuarios/{usuario}/rol', [UsuarioController::class, 'actualizarRol'])->name('usuarios.rol.update');
    });

    Route::middleware('can:ver-roles')->group(function () {
        Route::get('/roles', [RolController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RolController::class, 'store'])->name('roles.store');
        Route::match(['put', 'patch'], '/roles/{rol}', [RolController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->name('roles.destroy');
        Route::patch('/roles/{rol}/defecto', [RolController::class, 'actualizarDefecto'])->name('roles.defecto.update');
    });

    Route::middleware('can:ver-logs')->group(function () {
        Route::get('/logs', [LogActividadController::class, 'index'])->name('logs.index');
    });

    Route::get('/fichajes', [FichajeController::class, 'index'])->name('fichajes.index');
    Route::post('/fichajes', [FichajeController::class, 'store'])->name('fichajes.store');

    Route::get('/mi-jornada', [MiJornadaController::class, 'index'])->name('mi-jornada.index');
    Route::get('/mi-jornada/exportar', [MiJornadaController::class, 'exportar'])->name('mi-jornada.exportar');

    Route::middleware('can:ver-jornada')->group(function () {
        Route::get('/jornada', [InformeJornadaController::class, 'index'])->name('jornada.index');
        Route::get('/jornada/exportar', [InformeJornadaController::class, 'exportar'])->name('jornada.exportar');

        Route::get('/miembros-equipo', [MiembroEquipoController::class, 'index'])->name('miembros-equipo.index');
        Route::post('/miembros-equipo', [MiembroEquipoController::class, 'store'])->name('miembros-equipo.store');
        Route::match(['put', 'patch'], '/miembros-equipo/{miembro}', [MiembroEquipoController::class, 'update'])->name('miembros-equipo.update');
        Route::delete('/miembros-equipo/{miembro}', [MiembroEquipoController::class, 'destroy'])->name('miembros-equipo.destroy');

        Route::resource('horarios', HorarioController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/miembros-equipo/{miembro}/horarios', [AsignacionHorarioController::class, 'index'])->name('asignaciones-horario.index');
        Route::post('/miembros-equipo/{miembro}/horarios', [AsignacionHorarioController::class, 'store'])->name('asignaciones-horario.store');
        Route::delete('/asignaciones-horario/{asignacion}', [AsignacionHorarioController::class, 'destroy'])->name('asignaciones-horario.destroy');

        Route::post('/fichajes/{fichaje}/corregir', [CorreccionFichajeController::class, 'store'])->name('fichajes.corregir');

        Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');
        Route::get('/calendario/eventos', [CalendarioController::class, 'eventos'])->name('calendario.eventos');
        Route::get('/calendario/resumen', [CalendarioController::class, 'resumen'])->name('calendario.resumen');

        Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
        Route::patch('/alertas/{alerta}', [AlertaController::class, 'update'])->name('alertas.update');
    });
});

Route::middleware(['tenant.context', 'auth', 'super_admin'])->prefix('super_admin')->name('super_admin.')->group(function () {
    Route::resource('tenants', SuperAdminTenantController::class)->only(['index', 'store', 'update', 'destroy']);
});
