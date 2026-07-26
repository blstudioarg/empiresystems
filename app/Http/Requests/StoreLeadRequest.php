<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:150'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'asignado_a' => ['nullable', 'integer'],
            // En el alta se exige que el canal esté activo (data-model.md §2); al editar un lead
            // se admite conservar uno ya desactivado (ver UpdateLeadRequest).
            'canal_captacion_id' => ['nullable', Rule::exists('canales_captacion', 'id')->where('tenant_id', tenant()->id)->where('activo', true)],
            'notas' => ['nullable', 'string'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $email = $this->input('email');
            $telefono = $this->input('telefono');

            if (! $email && ! $telefono) {
                $validator->errors()->add('email', 'Debes indicar al menos un email o un teléfono.');

                return;
            }

            if ($this->esDuplicado($email, $telefono)) {
                $validator->errors()->add('email', 'Ya existe un lead con ese email o teléfono (duplicado).');
            }
        });
    }

    protected function esDuplicado(?string $email, ?string $telefono): bool
    {
        return Lead::query()
            ->where(function ($query) use ($email, $telefono) {
                if ($email) {
                    $query->orWhere('email', $email);
                }
                if ($telefono) {
                    $query->orWhere('telefono', $telefono);
                }
            })
            ->exists();
    }
}
