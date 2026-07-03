<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Exige usuario autenticado con rol super_admin (tenant_id null) y host en central_domains
     * (contracts/http.md, research.md D4).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        abort_unless(
            $user->rol === UserRole::SuperAdmin
                && $user->tenant_id === null
                && in_array($request->getHost(), config('tenancy.central_domains'), true),
            403
        );

        return $next($request);
    }
}
