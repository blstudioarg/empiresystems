<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida nombre (único por tenant, ignorando el propio en edición) y tramos: `hora_fin >
 * hora_inicio` y sin solape dentro de un mismo `dia_semana` (FR-004). La validación de solape
 * se hace en `withValidator` porque cruza varios índices del array `tramos`.
 */
class HorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $horarioId = $this->route('horario');

        return [
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('horarios', 'nombre')
                    ->where('tenant_id', tenant()->getTenantKey())
                    ->ignore($horarioId),
            ],
            'activo' => ['sometimes', 'boolean'],
            'tramos' => ['array'],
            'tramos.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'tramos.*.hora_inicio' => ['required', 'date_format:H:i'],
            'tramos.*.hora_fin' => ['required', 'date_format:H:i', 'after:tramos.*.hora_inicio'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $tramos = $this->input('tramos', []);

            $porDia = [];
            foreach ($tramos as $indice => $tramo) {
                $dia = $tramo['dia_semana'] ?? null;
                $inicio = $tramo['hora_inicio'] ?? null;
                $fin = $tramo['hora_fin'] ?? null;

                if ($dia === null || $inicio === null || $fin === null) {
                    continue;
                }

                foreach ($porDia[$dia] ?? [] as $otro) {
                    if ($inicio < $otro['fin'] && $otro['inicio'] < $fin) {
                        $validator->errors()->add("tramos.{$indice}.hora_inicio", 'Este tramo se solapa con otro del mismo día.');
                    }
                }

                $porDia[$dia][] = ['inicio' => $inicio, 'fin' => $fin];
            }
        });
    }
}
