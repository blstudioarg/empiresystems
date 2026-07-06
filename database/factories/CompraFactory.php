<?php

namespace Database\Factories;

use App\Enums\EstadoB2b;
use App\Enums\EstadoCompra;
use App\Enums\OrigenCompra;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    protected $model = Compra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'proveedor_id' => Proveedor::factory(),
            'numero_documento' => fake()->bothify('FAC-####'),
            'fecha' => now()->toDateString(),
            'estado' => EstadoCompra::Borrador,
            'origen' => OrigenCompra::Manual,
            'formato_recepcion' => null,
            'archivo_recibido_path' => null,
            'estado_b2b' => null,
            'estado_b2b_fecha' => null,
            'base_total' => 0,
            'cuota_impuesto_total' => 0,
            'total' => 0,
            'notas' => null,
        ];
    }

    public function confirmada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoCompra::Confirmada,
            'confirmada_at' => now(),
        ]);
    }

    /**
     * Compra creada a partir de un Facturae recibido (origen=facturae), con estado B2B inicial.
     */
    public function facturae(): static
    {
        return $this->state(fn (array $attributes) => [
            'origen' => OrigenCompra::Facturae,
            'formato_recepcion' => 'facturae',
            'archivo_recibido_path' => 'tenants/0/documentos/'.fake()->uuid().'.xml',
            'estado_b2b' => EstadoB2b::Recibida,
            'estado_b2b_fecha' => now(),
        ]);
    }
}
