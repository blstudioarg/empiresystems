<?php

namespace App\Http\Requests;

use App\Support\MenuTenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la tab "Menú" de Configuración (feature 036, data-model.md §4). El servidor no
 * confía en que `etiquetas`/`orden` estén completas ni bien formadas — {@see MenuTenant}
 * descarta cualquier clave que no exista en el catálogo (research.md D4); esta clase solo valida
 * la forma y el contenido de lo que sí llega.
 */
class ActualizarMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ver-configuracion') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'etiquetas' => ['sometimes', 'array'],
            'etiquetas.*' => ['required', 'string', 'max:40', 'regex:/\S/'],
            'orden' => ['sometimes', 'array'],
            'orden.*' => ['array'],
            'orden.*.*' => ['string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'etiquetas.*.required' => 'El nombre no puede quedar vacío.',
            'etiquetas.*.string' => 'El nombre debe ser texto.',
            'etiquetas.*.max' => 'El nombre no puede superar los 40 caracteres.',
            'etiquetas.*.regex' => 'El nombre no puede quedar vacío.',
            'orden.*.array' => 'El orden enviado no es válido.',
            'orden.*.*.string' => 'El orden enviado no es válido.',
        ];
    }
}
