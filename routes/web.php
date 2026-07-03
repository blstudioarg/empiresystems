<?php

use App\Http\Controllers\ArticuloController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\LocalidadController;
use App\Http\Controllers\ProfileController;
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
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

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

    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/perfil/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');

    Route::get('/configuracion', [ConfiguracionController::class, 'show'])->name('configuracion.show');
    Route::match(['put', 'patch'], '/configuracion/apariencia', [ConfiguracionController::class, 'update'])
        ->name('configuracion.apariencia.update');

    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::patch('/usuarios/{usuario}/aprobar', [UsuarioController::class, 'aprobar'])->name('usuarios.aprobar');
    Route::patch('/usuarios/{usuario}/rechazar', [UsuarioController::class, 'rechazar'])->name('usuarios.rechazar');
});

Route::middleware(['tenant.context', 'auth', 'super_admin'])->prefix('super_admin')->name('super_admin.')->group(function () {
    Route::resource('tenants', SuperAdminTenantController::class)->only(['index', 'store', 'update', 'destroy']);
});
