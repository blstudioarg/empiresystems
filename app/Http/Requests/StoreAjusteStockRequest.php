<?php

namespace App\Http\Requests;

use App\Enums\TipoArticulo;
use App\Enums\TipoMovimientoStock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAjusteStockRequest extends FormRequest
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
            'articulo_id' => [
                'required',
                Rule::exists('articulos', 'id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', tenant()->getTenantKey())
                        ->where('tipo', TipoArticulo::Producto->value)
                        ->where('gestion_stock', true)
                        ->whereNull('deleted_at')),
            ],
            'tipo' => ['required', Rule::in([TipoMovimientoStock::Entrada->value, TipoMovimientoStock::Salida->value])],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
