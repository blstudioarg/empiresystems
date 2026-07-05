<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Illuminate\Auth\Events\{Login,Logout,Failed,Lockout} -> LogAuthenticationActivity ya
        // quedan enganchados por el auto-discovery de eventos de Laravel (los métodos handle*
        // están type-hinted con la clase del evento): registrarlos aquí también los disparaba
        // dos veces por request (detectado al añadir el registro en logs_actividad — feature 021).
    }
}
