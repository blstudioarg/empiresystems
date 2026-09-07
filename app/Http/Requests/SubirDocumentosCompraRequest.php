<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubirDocumentosCompraRequest extends FormRequest
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
        $tipos = implode(',', (array) config('compras.documentos.tipos'));

        return [
            'archivos' => ['required', 'array', 'min:1', 'max:'.(int) config('compras.documentos.max_ficheros')],
            'archivos.*' => [
                'required',
                'file',
                'mimes:'.$tipos,
                'max:'.((int) config('compras.documentos.max_mb') * 1024),
            ],
        ];
    }

    /**
     * FR-004: el mensaje nombra el límite concreto, no un "archivo inválido" genérico.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxFicheros = (int) config('compras.documentos.max_ficheros');
        $maxMb = (int) config('compras.documentos.max_mb');
        $tipos = strtoupper(implode(', ', (array) config('compras.documentos.tipos')));

        return [
            'archivos.required' => 'Elegí al menos un documento.',
            'archivos.max' => "Se pueden subir como mucho {$maxFicheros} documentos por lote.",
            'archivos.*.mimes' => "Solo se admiten documentos {$tipos}.",
            'archivos.*.max' => "Cada documento debe pesar como mucho {$maxMb} MB.",
        ];
    }
}
