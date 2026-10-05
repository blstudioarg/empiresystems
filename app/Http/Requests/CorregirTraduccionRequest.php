<?php

namespace App\Http\Requests;

use App\Models\Traduccion;
use App\Support\ConfigPos;
use App\Traduccion\MemoriaTraducciones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Corrección manual de una traducción del POS (feature 050, contracts/traducciones.md).
 *
 * - La corrección tiene que conservar todas las variables `:x` del texto original (FR-014).
 * - Se sanea (research D8): un texto con HTML (guías de ayuda) solo admite `strong`, `em` y `br`
 *   sin atributos; uno sin HTML no admite ninguna etiqueta, porque los JS del POS pintan la
 *   traducción como HTML. Un tenant no puede inyectar marcado en su propio POS.
 */
class CorregirTraduccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'traduccion' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'traduccion.required' => 'Escribe la traducción.',
            'traduccion.max' => 'La traducción es demasiado larga.',
        ];
    }

    /** La fila de `traducciones` que se corrige (idioma del tenant + hash de la URL), o null. */
    public function traduccionCorregida(): ?Traduccion
    {
        return Traduccion::query()
            ->where('idioma', ConfigPos::idioma((int) tenant()->getTenantKey()))
            ->where('hash', (string) $this->route('hash'))
            ->first();
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $original = $this->traduccionCorregida();

                if ($original === null || $validator->errors()->has('traduccion')) {
                    return;
                }

                if (! MemoriaTraducciones::conservaVariables($original->texto, $this->traduccionSaneada($original))) {
                    preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $original->texto, $variables);
                    $validator->errors()->add('traduccion', 'La traducción tiene que conservar estas partes tal cual: '.implode(', ', array_unique($variables[0])).'.');
                }
            },
        ];
    }

    public function traduccionSaneada(Traduccion $original): string
    {
        $texto = trim((string) $this->input('traduccion'));

        if (! $original->es_html) {
            return trim(strip_tags($texto));
        }

        $texto = strip_tags($texto, '<strong><em><br>');

        // Las etiquetas permitidas, siempre sin atributos (nada de `onclick`, `style`…).
        return trim(preg_replace('/<(\/?)(strong|em|br)\b[^>]*>/i', '<$1$2>', $texto));
    }
}
