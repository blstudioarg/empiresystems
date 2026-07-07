<?php

namespace App\Http\Requests;

use App\Support\CatalogoPermisos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRolRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('tenant_id', $this->user()->tenant_id),
            ],
            'permisos' => ['required', 'array', 'min:1'],
            'permisos.*' => [Rule::in(CatalogoPermisos::claves())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique' => 'Ya existe un rol con ese nombre.',
            'permisos.required' => 'Seleccioná al menos un permiso.',
            'permisos.min' => 'Seleccioná al menos un permiso.',
            'permisos.*.in' => 'Uno de los permisos seleccionados no es válido.',
        ];
    }
}
