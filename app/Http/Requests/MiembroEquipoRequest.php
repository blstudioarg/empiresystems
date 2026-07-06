<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MiembroEquipoRequest extends FormRequest
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
        $tenantId = tenant()->getTenantKey();
        $miembroId = $this->route('miembro');

        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                Rule::unique('miembros_equipo', 'user_id')->ignore($miembroId),
            ],
            'puesto' => ['nullable', 'string', 'max:120'],
            'trabajo_direccion' => ['nullable', 'string', 'max:255'],
            'trabajo_latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'trabajo_longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'distancia_max_metros' => ['required', 'integer', 'min:1'],
            'casa_direccion' => ['nullable', 'string', 'max:255'],
            'casa_latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'casa_longitud' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
