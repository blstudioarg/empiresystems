<?php

namespace Database\Factories;

use App\Models\Banco;
use App\Models\CuentaBancaria;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CuentaBancaria>
 */
class CuentaBancariaFactory extends Factory
{
    protected $model = CuentaBancaria::class;

    /**
     * IBAN españoles válidos (estructura + mod-97) para pruebas.
     *
     * @var array<int, string>
     */
    private const IBANS_VALIDOS = [
        'ES9121000418450200051332',
        'ES7921000813610123456789',
        'ES6000491500051234567892',
        'ES1000492352082414205416',
        'ES9420805801101234567891',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'banco_id' => fn (array $attributes) => Banco::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'alias' => 'Cuenta '.fake()->word(),
            'iban' => fake()->randomElement(self::IBANS_VALIDOS),
            'titular' => fake()->name(),
            'activa' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => [
            'activa' => false,
        ]);
    }
}
