<?php

use App\Http\Controllers\ArticuloController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BancoController;
use App\Http\Controllers\CarpetaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CampanaController;
use App\Http\Controllers\CuentaBancariaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\LocalidadController;
use App\Http\Controllers\LogActividadController;
use App\Http\Controllers\MovimientoStockController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PlantillaEmailController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProveedorController;
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

    Route::resource('clientes', ClienteController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/localidades', [LocalidadController::class, 'index'])->name('localidades.index');
    Route::resource('articulos', ArticuloController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('unidades', UnidadController::class)->only(['index', 'store', 'update', 'destroy']);

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
    Route::get('/facturas/{factura}/pagos', [PagoController::class, 'index'])->name('facturas.pagos.index');
    Route::post('/facturas/{factura}/pagos', [PagoController::class, 'store'])->name('facturas.pagos.store');
    Route::post('/pagos/{pago}/anular', [PagoController::class, 'anular'])->name('pagos.anular');

    // POS — facturas simplificadas (tickets)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/crear', [PosController::class, 'create'])->name('pos.create');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    Route::get('/pos/{factura}/pdf', [PosController::class, 'pdf'])->name('pos.pdf');

    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/perfil/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');

    Route::resource('bancos', BancoController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('cuentas-bancarias', CuentaBancariaController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/cuentas-bancarias/{id}/restaurar', [CuentaBancariaController::class, 'restore'])->name('cuentas-bancarias.restore');

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

    Route::get('/stock', [MovimientoStockController::class, 'index'])->name('stock.index');
    Route::get('/stock/{articulo}', [MovimientoStockController::class, 'show'])->name('stock.show');
    Route::post('/stock/ajuste', [MovimientoStockController::class, 'ajuste'])->name('stock.ajuste');

    Route::resource('proveedores', ProveedorController::class)
        ->parameters(['proveedores' => 'proveedor'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::get('/compras/crear', [CompraController::class, 'create'])->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
    Route::get('/compras/{compra}', [CompraController::class, 'show'])->name('compras.show');
    Route::get('/compras/{compra}/editar', [CompraController::class, 'edit'])->name('compras.edit');
    Route::match(['put', 'patch'], '/compras/{compra}', [CompraController::class, 'update'])->name('compras.update');
    Route::post('/compras/{compra}/confirmar', [CompraController::class, 'confirmar'])->name('compras.confirmar');
    Route::post('/compras/{compra}/anular', [CompraController::class, 'anular'])->name('compras.anular');
    Route::delete('/compras/{compra}', [CompraController::class, 'destroy'])->name('compras.destroy');

    // Email marketing — plantillas y campañas
    Route::resource('plantillas-email', PlantillaEmailController::class)
        ->parameters(['plantillas-email' => 'plantilla'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/campanas', [CampanaController::class, 'index'])->name('campanas.index');
    Route::get('/campanas/crear', [CampanaController::class, 'create'])->name('campanas.create');
    Route::post('/campanas', [CampanaController::class, 'store'])->name('campanas.store');
    Route::get('/campanas/{campana}', [CampanaController::class, 'show'])->name('campanas.show');
    Route::post('/campanas/{campana}/enviar-tanda', [CampanaController::class, 'enviarTanda'])->name('campanas.enviar-tanda');
    Route::post('/campanas/{campana}/reintentar', [CampanaController::class, 'reintentar'])->name('campanas.reintentar');

    Route::get('/archivos', [ArchivoController::class, 'index'])->name('archivos.index');
    Route::post('/archivos', [ArchivoController::class, 'store'])->name('archivos.store');
    Route::match(['put', 'patch'], '/archivos/{archivo}', [ArchivoController::class, 'update'])->name('archivos.update');
    Route::delete('/archivos/{archivo}', [ArchivoController::class, 'destroy'])->name('archivos.destroy');
    Route::get('/archivos/{archivo}/descargar', [ArchivoController::class, 'descargar'])->name('archivos.descargar');
    Route::get('/archivos/{archivo}/preview', [ArchivoController::class, 'preview'])->name('archivos.preview');
    Route::post('/archivos/carpetas', [CarpetaController::class, 'store'])->name('carpetas.store');
    Route::match(['put', 'patch'], '/archivos/carpetas/{carpeta}', [CarpetaController::class, 'update'])->name('carpetas.update');
    Route::delete('/archivos/carpetas/{carpeta}', [CarpetaController::class, 'destroy'])->name('carpetas.destroy');

    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::patch('/usuarios/{usuario}/aprobar', [UsuarioController::class, 'aprobar'])->name('usuarios.aprobar');
    Route::patch('/usuarios/{usuario}/rechazar', [UsuarioController::class, 'rechazar'])->name('usuarios.rechazar');

    Route::get('/logs', [LogActividadController::class, 'index'])->name('logs.index');
});

Route::middleware(['tenant.context', 'auth', 'super_admin'])->prefix('super_admin')->name('super_admin.')->group(function () {
    Route::resource('tenants', SuperAdminTenantController::class)->only(['index', 'store', 'update', 'destroy']);
});
