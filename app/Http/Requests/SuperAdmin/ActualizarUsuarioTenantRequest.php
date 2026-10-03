<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarUsuarioTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Único dentro del tenant del propio usuario, no en toda la plataforma (el mismo
            // correo puede existir en otras empresas).
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query->where('tenant_id', $this->tenantIdDeLaRuta()))
                    ->ignore($this->route('usuario')),
            ],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    /**
     * El tenant al que pertenece el usuario que se edita, tomado del propio segmento `{tenant}`
     * de la ruta (`tenants/{tenant}/usuarios/{usuario}`). Es el ámbito dentro del cual el correo
     * debe ser único.
     */
    private function tenantIdDeLaRuta(): ?int
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof \App\Models\Tenant ? $tenant->id : (is_numeric($tenant) ? (int) $tenant : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no tiene un formato válido.',
            'email.unique' => 'Ese email ya está en uso por otro usuario.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
