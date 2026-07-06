<?php

namespace App\Http\Requests;

use App\Enums\TipoEventoFichaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorregirFichajeRequest extends FormRequest
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
            'tipo' => ['required', Rule::in(array_map(fn (TipoEventoFichaje $tipo) => $tipo->value, TipoEventoFichaje::cases()))],
            'ocurrido_at' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
