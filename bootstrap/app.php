<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
