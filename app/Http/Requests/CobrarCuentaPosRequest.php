<?php

namespace App\Http\Requests;

use App\Services\CobradorCuenta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cobrar una cuenta, entera o por partes (feature 038).
 *
 * Un solo endpoint para los dos casos porque el parcial es un total con menos unidades: `lineas`
 * ausente o vacío significa "todo lo pendiente". La pertenencia de cada línea a la cuenta y el
 * tope de unidades pendientes NO se validan aquí sino en {@see CobradorCuenta},
 * que es quien tiene la cuenta delante — validarlo en dos sitios sería duplicar la regla.
 */
class CobrarCuentaPosRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],

            'lineas' => ['nullable', 'array'],
            'lineas.*.cuenta_linea_id' => ['required', 'integer'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.01'],

            'pagos' => ['nullable', 'array'],
            'pagos.*.metodo' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia', 'domiciliacion'])],
            'pagos.*.importe' => ['required', 'numeric', 'min:0'],

            'receptor' => ['nullable', 'array'],
            'receptor.cliente_id' => ['nullable', 'integer'],
            'receptor.cliente_nif' => ['nullable', 'string', 'max:15'],
            'receptor.cliente_nombre' => ['nullable', 'string', 'max:255'],
            'receptor.cliente_razon_social' => ['nullable', 'string', 'max:255'],
            'receptor.cliente_direccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Selección normalizada a `cuenta_linea_id => unidades`.
     *
     * @return array<int, float>
     */
    public function seleccion(): array
    {
        $seleccion = [];

        foreach ($this->validated('lineas') ?? [] as $linea) {
            $id = (int) $linea['cuenta_linea_id'];
            $seleccion[$id] = round(($seleccion[$id] ?? 0) + (float) $linea['cantidad'], 2);
        }

        return $seleccion;
    }
}
