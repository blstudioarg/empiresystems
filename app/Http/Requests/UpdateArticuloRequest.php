<?php

namespace App\Http\Requests;

use App\Enums\TipoArticulo;
use App\Http\Requests\Concerns\ArticuloValidationMessages;
use App\Rules\TipoImpositivoValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticuloRequest extends FormRequest
{
    use ArticuloValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esProducto = $this->input('tipo') === TipoArticulo::Producto->value;
        $gestionaStock = $esProducto && $this->boolean('gestion_stock');

        return [
            'tipo' => ['required', Rule::enum(TipoArticulo::class)],
            'sku' => ['nullable', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'imagen' => ['nullable', 'image', 'max:2048'],
            'quitar_imagen' => ['boolean'],
            'unidad' => ['nullable', 'string', 'max:20'],
            'categoria_id' => [
                'nullable',
                Rule::exists('categorias_articulo', 'id')->where('tenant_id', tenant()->id),
            ],
            'precio' => ['required', 'numeric', 'min:0'],
            'tipo_impositivo' => ['required', 'numeric', 'between:0,100', new TipoImpositivoValido],
            'gestion_stock' => ['boolean'],
            'stock_actual' => [$gestionaStock ? 'required' : 'nullable', 'numeric'],
            'stock_minimo' => ['nullable', 'numeric'],
            'aplica_recargo_equivalencia' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gestion_stock' => $this->boolean('gestion_stock'),
            'aplica_recargo_equivalencia' => $this->boolean('aplica_recargo_equivalencia'),
        ]);
    }
}
