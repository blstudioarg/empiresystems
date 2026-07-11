<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Retención del registro de accesos (RGPD — minimización, docs/03-modelo-datos.md).
        // En hosting compartido (Principio V) basta con un único cron de cPanel que llame a
        // `php artisan schedule:run` cada minuto.
        $schedule->command('logs:purgar')->daily();

        // Retención de los XML Facturae emitidos/recibidos (RGPD — minimización, feature 022).
        $schedule->command('facturae:purgar')->daily();

        // Retención del dato de geo de fichajes y de los datos de casa de miembros dados de baja
        // (RGPD — minimización, feature 024).
        $schedule->command('fichajes:purgar-geo')->daily();
        $schedule->command('miembros:purgar-casa')->daily();

        // Alertas de incumplimiento de jornada (ausencia/retraso) del día anterior (feature 025).
        $schedule->command('jornada:evaluar-cumplimiento')->daily();

        // Retención de leads descartados/no convertidos (RGPD — minimización, feature 028).
        $schedule->command('leads:purgar')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás del edge proxy de Railway (TLS terminado en el borde): confiar en las cabeceras
        // X-Forwarded-* para que Laravel genere URLs https, respete el esquema y detecte la IP
        // real del cliente (relevante para el registro de accesos / fichajes con geo).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'tenant.context' => SetTenantContext::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);

        // SetTenantContext debe resolver el tenant por Host ANTES que auth/guest: si no, el
        // middlewarePriority global de Laravel (Authenticate tiene prioridad fija) ejecuta el
        // check de sesión primero y un host sin tenant nunca llega a devolver 404 (research.md D2).
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: SetTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
