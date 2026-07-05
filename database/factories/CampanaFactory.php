<?php

namespace Database\Factories;

use App\Enums\EstadoCampana;
use App\Models\Campana;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campana>
 */
class CampanaFactory extends Factory
{
    protected $model = Campana::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'plantilla_email_id' => null,
            'asunto' => fake()->sentence(5),
            'cuerpo' => '<p>'.fake()->paragraph().'</p>',
            'estado' => EstadoCampana::Borrador,
            'total_destinatarios' => 0,
            'enviados' => 0,
            'fallidos' => 0,
            'enviada_at' => null,
        ];
    }
}
