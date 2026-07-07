<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoUsuario;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\RegistradorActividad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        // El registro se hace siempre sobre el tenant del dominio de la petición
        // (SetTenantContext ya lo inicializó). Sin contexto de tenant (dominio central)
        // no hay empresa a la que unirse: no se permite registrar. Principio I: nunca
        // asignar el usuario a un tenant que no sea el de su propio dominio.
        abort_unless(tenancy()->initialized, 404);

        $data = $request->validated();

        $usuario = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'tenant_id' => tenant('id'),
            'rol' => UserRole::Usuario,
            'estado' => EstadoUsuario::Pendiente,
            'activo' => false,
        ]);

        // Rol por defecto del tenant (feature 027, FR-014, RN-06). Sin rol por defecto
        // configurado, el usuario queda sin rol (solo secciones personales) — nunca un error.
        $rolPorDefecto = Role::where('tenant_id', tenant('id'))->where('es_defecto', true)->first();

        if ($rolPorDefecto) {
            $usuario->syncRoles([$rolPorDefecto]);
        }

        $this->registradorActividad->registrar(
            $usuario,
            AccionLogActividad::Alta,
            EntidadLogActividad::Usuario,
            $usuario->id,
            "Se registró {$usuario->name} (pendiente de aprobación)",
        );

        return redirect()->route('login')
            ->with('success', 'Solicitud registrada. Un administrador debe aprobar tu cuenta antes de que puedas iniciar sesión.');
    }
}
