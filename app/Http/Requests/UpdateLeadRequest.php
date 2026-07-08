<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
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
            'estado' => ['nullable', 'string'],
            'motivo_descarte' => ['nullable', 'string', 'max:255'],
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
        $leadId = $this->route('lead');

        return Lead::query()
            ->when($leadId, fn ($query) => $query->where('id', '!=', $leadId))
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
