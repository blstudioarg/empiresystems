<?php

namespace Database\Factories;

use App\Enums\FormaPago;
use App\Models\Factura;
use App\Models\TicketPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketPago>
 */
class TicketPagoFactory extends Factory
{
    protected $model = TicketPago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'factura_id' => Factura::factory(),
            'metodo' => FormaPago::Efectivo,
            'importe' => 10,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TicketPago $pago) {
            if ($pago->factura_id && ! $pago->tenant_id) {
                $pago->tenant_id = Factura::withoutGlobalScopes()->find($pago->factura_id)?->tenant_id;
            }
        });
    }
}
