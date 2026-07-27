<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\SolicitarCambioEmailRequest;
use App\Http\Requests\Profile\UpdateNombreRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Mail\VerificarCambioEmail;
use App\Models\Fichaje;
use App\Models\LogActividad;
use App\Models\User;
use App\Services\RegistroFichajes;
use App\Support\AgenteUsuario;
use App\Support\GeolocalizadorIp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();

        return view('profile.show', [
            'user' => $user,
            ...$this->datosRolPermisos($user),
            'actividadReciente' => $this->actividadReciente($user),
            'empleadoFichaje' => $this->datosEmpleadoFichaje($user),
        ]);
    }

    /**
     * @return array{puesto: ?string, centroTrabajo: ?string, estado: string, estadoLabel: string, eventosRecientes: \Illuminate\Support\Collection<int, Fichaje>}|null
     */
    private function datosEmpleadoFichaje(User $user): ?array
    {
        $miembro = $user->miembroEquipo;

        if (! $miembro || ! $miembro->activo || $miembro->dado_baja_at !== null) {
            return null;
        }

        $estado = app(RegistroFichajes::class)->estadoActual($miembro->id);

        return [
            'puesto' => $miembro->puesto,
            'centroTrabajo' => $miembro->trabajo_direccion,
            'estado' => $estado,
            'estadoLabel' => [
                'cerrada' => 'Sin jornada abierta',
                'abierta' => 'Jornada abierta',
                'en_pausa' => 'En pausa',
            ][$estado],
            'eventosRecientes' => Fichaje::where('miembro_equipo_id', $miembro->id)
                ->whereNull('corrige_fichaje_id')
                ->orderByDesc('ocurrido_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{fecha: string, accion_label: string, resultado: string, resultado_label: string, navegador: ?string, ubicacion: ?string}>
     */
    private function actividadReciente(User $user): \Illuminate\Support\Collection
    {
        if (! $user->tenant_id) {
            return collect();
        }

        return LogActividad::where('usuario_id', $user->id)
            ->orderByDesc('ocurrido_at')
            ->limit(5)
            ->get()
            ->map(fn (LogActividad $log) => [
                'fecha' => $log->ocurrido_at->enZonaTenant()->format('d/m/Y H:i'),
                'accion_label' => $log->accion->label(),
                'resultado' => $log->resultado->value,
                'resultado_label' => $log->resultado->label(),
                'navegador' => AgenteUsuario::label($log->user_agent),
                'ubicacion' => GeolocalizadorIp::ubicacion($log->ip_origen),
            ]);
    }

    /**
     * @return array{esSuperAdmin: bool, rolReal: ?string}
     */
    private function datosRolPermisos($user): array
    {
        if ($user->isSuperAdmin()) {
            return ['esSuperAdmin' => true, 'rolReal' => null];
        }

        return [
            'esSuperAdmin' => false,
            'rolReal' => $user->getRoleNames()->first(),
        ];
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => Hash::make($request->string('password')->toString())]);

        // Invalida las sesiones de otros dispositivos: solo se borran filas de `sessions` del
        // propio usuario, nunca de otro (driver `database`, ver research.md #2).
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', session()->getId())
            ->delete();

        $mensaje = 'Contraseña actualizada correctamente. Se cerraron tus otras sesiones activas.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->route('profile.show')->with('success', $mensaje);
    }

    public function updateNombre(UpdateNombreRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $user->update(['name' => $request->string('name')->toString()]);

        $mensaje = 'Nombre actualizado correctamente.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje, 'name' => $user->name]);
        }

        return redirect()->route('profile.show')->with('success', $mensaje);
    }

    public function solicitarCambioEmail(SolicitarCambioEmailRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $user->update([
            'pending_email' => $request->string('nuevo_email')->toString(),
            'pending_email_token' => Str::random(64),
            'pending_email_expires_at' => now()->addHours(24),
        ]);

        $this->enviarVerificacionEmail($user);

        $mensaje = 'Revisá tu correo para confirmar el cambio de email.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->route('profile.show')->with('success', $mensaje);
    }

    public function confirmarCambioEmail(Request $request, int $user): RedirectResponse
    {
        $usuario = User::findOrFail($user);

        if (Auth::id() !== $usuario->id) {
            abort(403);
        }

        $tokenValido = $request->hasValidSignature()
            && $usuario->pending_email !== null
            && $usuario->pending_email_token === $request->query('token')
            && $usuario->pending_email_expires_at !== null
            && $usuario->pending_email_expires_at->isFuture();

        if (! $tokenValido) {
            return redirect()->route('profile.show')
                ->with('error', 'El enlace de confirmación no es válido o venció. Solicitá el cambio de nuevo.');
        }

        $usuario->update([
            'email' => $usuario->pending_email,
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_expires_at' => null,
        ]);

        return redirect()->route('profile.show')->with('success', 'Tu correo se actualizó correctamente.');
    }

    public function cancelarCambioEmail(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user->pending_email === null) {
            abort(404);
        }

        $user->update([
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_expires_at' => null,
        ]);

        $mensaje = 'Solicitud de cambio de email cancelada.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->route('profile.show')->with('success', $mensaje);
    }

    public function reenviarVerificacionEmail(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user->pending_email === null) {
            abort(404);
        }

        $user->update([
            'pending_email_token' => Str::random(64),
            'pending_email_expires_at' => now()->addHours(24),
        ]);

        $this->enviarVerificacionEmail($user);

        $mensaje = 'Enlace de verificación reenviado. El anterior ya no es válido.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->route('profile.show')->with('success', $mensaje);
    }

    private function enviarVerificacionEmail(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'profile.email.confirmar',
            now()->addHours(24),
            ['user' => $user->id, 'token' => $user->pending_email_token],
        );

        Mail::to($user->pending_email)->send(new VerificarCambioEmail($user, $url));
    }

    public function updateAvatar(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $ruta = $request->file('avatar')->store("avatars/{$user->id}", 'public');
        $user->update(['avatar_path' => $ruta]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Foto de perfil actualizada correctamente.',
                'avatar_url' => $user->avatarUrl(),
            ]);
        }

        return redirect()->route('profile.show')->with('success', 'Foto de perfil actualizada correctamente.');
    }
}
