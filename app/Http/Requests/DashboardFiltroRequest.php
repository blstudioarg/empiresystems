<?php

namespace App\Http\Requests;

use App\Enums\PresetRango;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el filtro de rango del dashboard sin romper la página ante un rango inválido: no lanza
 * la excepción de validación por defecto (ver `failedValidation()`), el controller decide el
 * fallback a mes en curso + aviso (contrato: nunca 422, siempre 200).
 */
class DashboardFiltroRequest extends FormRequest
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
            'preset' => ['nullable', 'string', Rule::enum(PresetRango::class)],
            'desde' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,personalizado'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,personalizado', 'after_or_equal:desde'],
        ];
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        // Intencionalmente no lanza: un rango inválido cae a mes en curso + aviso en el
        // controller (huboRangoInvalido()), nunca en un 422.
    }

    public function huboRangoInvalido(): bool
    {
        return $this->getValidatorInstance()->fails();
    }
}
