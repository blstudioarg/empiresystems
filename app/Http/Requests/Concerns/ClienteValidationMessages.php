<?php

namespace App\Http\Requests\Concerns;

trait ClienteValidationMessages
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de cliente es obligatorio.',
            'nombre.required' => 'El nombre es obligatorio.',
            'razon_social.required' => 'La razón social es obligatoria para clientes de tipo empresa.',
            'nif.required' => 'El NIF es obligatorio para clientes de tipo empresa.',
            'nif.unique' => 'Ya existe un cliente con este NIF en tu empresa.',
            'email.email' => 'El email no tiene un formato válido.',
            'pais.required' => 'El país es obligatorio.',
            'pais.size' => 'El país debe ser un código de 2 letras (por ejemplo, ES).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo de cliente',
            'nombre' => 'nombre',
            'razon_social' => 'razón social',
            'nif' => 'NIF',
            'direccion' => 'dirección',
            'cp' => 'código postal',
            'ciudad' => 'ciudad',
            'provincia' => 'provincia',
            'pais' => 'país',
            'email' => 'email',
            'telefono' => 'teléfono',
            'notas' => 'notas',
        ];
    }
}
