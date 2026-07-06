<?php

namespace App\Http\Requests;

use App\Enums\TipoEventoFichaje;
use App\Support\ConfigFichajes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FicharRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->miembroEquipo !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tiposPermitidos = ConfigFichajes::registrarPausas(tenant()->getTenantKey())
            ? TipoEventoFichaje::cases()
            : [TipoEventoFichaje::Entrada, TipoEventoFichaje::Salida];

        return [
            'tipo' => ['required', Rule::in(array_map(fn (TipoEventoFichaje $tipo) => $tipo->value, $tiposPermitidos))],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'precision' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
