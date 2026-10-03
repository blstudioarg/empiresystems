<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EstadoUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            Event::dispatch(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('Demasiados intentos. Inténtalo de nuevo en :seconds segundos.', [
                    'seconds' => $seconds,
                ]),
            ]);
        }

        $remember = $request->boolean('remember');

        // El tenant entra en las credenciales, no solo en el gate posterior: desde que el correo
        // es único POR TENANT, el mismo email puede existir en varias empresas, y un attempt que
        // busque solo por email resolvería un usuario cualquiera de entre los que lo comparten
        // (el primero que devuelva la BD). Filtrando por el tenant del dominio se autentica
        // siempre al usuario correcto. Super admin: `tenant_id` NULL en contexto central.
        $attempted = Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'activo' => true,
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
        ], $remember);

        if ($attempted && ! $this->tenantIsUsable(Auth::user())) {
            Auth::logout();
            $attempted = false;
        }

        if ($attempted && ! $this->dominioCoincideConUsuario(Auth::user())) {
            Auth::logout();
            $attempted = false;
        }

        if (! $attempted) {
            RateLimiter::hit($throttleKey, 60);

            // Mismo criterio que el attempt: el aviso de "cuenta pendiente/no habilitada" tiene
            // que mirar al usuario de ESTE tenant, no a otro que comparta el correo.
            $pendienteORechazado = User::where('email', $credentials['email'])
                ->where(fn ($query) => tenancy()->initialized
                    ? $query->where('tenant_id', tenant('id'))
                    : $query->whereNull('tenant_id'))
                ->whereIn('estado', [EstadoUsuario::Pendiente, EstadoUsuario::Rechazado])
                ->first();

            if ($pendienteORechazado && Hash::check($credentials['password'], $pendienteORechazado->password)) {
                throw ValidationException::withMessages([
                    'email' => $pendienteORechazado->estado === EstadoUsuario::Pendiente
                        ? __('Tu cuenta aún no está aprobada.')
                        : __('Tu cuenta no está habilitada.'),
                ]);
            }

            throw ValidationException::withMessages([
                'email' => __('Estas credenciales no coinciden con nuestros registros.'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function tenantIsUsable(User $user): bool
    {
        if (! $user->tenant_id) {
            return true;
        }

        return (bool) $user->tenant()->first()?->activo;
    }

    /**
     * Gate login<->dominio (research.md D3): el tenant resuelto por el host de la petición
     * (SetTenantContext, ya ejecutado en el grupo `guest`) debe coincidir con el tenant del
     * usuario. Super admin (tenant_id null) solo es válido en contexto central (sin tenant
     * resuelto por dominio).
     */
    private function dominioCoincideConUsuario(User $user): bool
    {
        if (! tenancy()->initialized) {
            return $user->tenant_id === null;
        }

        return $user->tenant_id === tenant('id');
    }
}
