<?php

namespace App\Http\Requests;

use App\Models\Carpeta;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarpetaRequest extends FormRequest
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
        $carpeta = Carpeta::findOrFail($this->route('carpeta'));

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => [
                'sometimes',
                'nullable',
                Rule::exists(Carpeta::class, 'id')->where('tenant_id', $tenantId),
                function (string $attribute, mixed $value, \Closure $fail) use ($carpeta) {
                    if ($value === null) {
                        return;
                    }

                    if ((int) $value === $carpeta->id) {
                        $fail('Una carpeta no puede moverse dentro de sí misma.');

                        return;
                    }

                    if (in_array((int) $value, $carpeta->descendientesIds(), true)) {
                        $fail('No se puede mover una carpeta dentro de una de sus propias subcarpetas.');
                    }
                },
            ],
        ];
    }

    /**
     * La unicidad de `nombre` depende de la combinación (nombre efectivo, parent_id efectivo):
     * cuando solo se envía `parent_id` (mover por drag&drop, sin renombrar) `nombre` está ausente
     * del payload y la regla `sometimes` de arriba no se ejecuta, así que el chequeo se hace acá
     * con los valores EFECTIVOS (los enviados o, si faltan, los actuales de la carpeta).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $carpeta = Carpeta::findOrFail($this->route('carpeta'));
            $tenantId = tenant()->getTenantKey();

            $nombreObjetivo = $this->input('nombre', $carpeta->nombre);
            $parentObjetivo = $this->has('parent_id') ? $this->input('parent_id') : $carpeta->parent_id;

            $duplicado = Carpeta::query()
                ->where('tenant_id', $tenantId)
                ->where('parent_id', $parentObjetivo)
                ->where('nombre', $nombreObjetivo)
                ->where('id', '!=', $carpeta->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicado) {
                $validator->errors()->add('nombre', 'Ya existe una carpeta con ese nombre en el nivel destino.');
            }
        });
    }
}
