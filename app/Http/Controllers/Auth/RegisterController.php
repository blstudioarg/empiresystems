<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EstadoUsuario;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'tenant_id' => Tenant::query()->first()->id,
            'rol' => UserRole::Usuario,
            'estado' => EstadoUsuario::Pendiente,
            'activo' => false,
        ]);

        return redirect()->route('login')
            ->with('success', 'Solicitud registrada. Un administrador debe aprobar tu cuenta antes de que puedas iniciar sesión.');
    }
}
