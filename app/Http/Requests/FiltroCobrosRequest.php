<?php

namespace App\Http\Requests;

use App\Enums\PresetRango;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado y del resumen de Cobros (feature 043). Un rango inválido (fin anterior a
 * inicio, fechas ausentes en `personalizado`) NO lanza: `App\Support\RangoFechas::desdePeticion()`
 * cae al mes en curso, tal como pide el edge case de la spec (research D5).
 */
class FiltroCobrosRequest extends FormRequest
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
            'estado_cobro' => ['nullable', Rule::in(['pendiente', 'parcial', 'cobrada'])],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')->where('tenant_id', auth()->user()?->tenant_id)],
            'serie_id' => ['nullable', 'integer', Rule::exists('series', 'id')->where('tenant_id', auth()->user()?->tenant_id)],
            'solo_vencidas' => ['nullable', 'boolean'],
            'preset' => ['nullable', Rule::in(array_column(PresetRango::cases(), 'value'))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
