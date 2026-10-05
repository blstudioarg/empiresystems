<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Traduccion;
use App\Models\TraduccionCorreccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TraduccionCorreccion> */
class TraduccionCorreccionFactory extends Factory
{
    protected $model = TraduccionCorreccion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'idioma' => 'zh',
            'hash' => fn (array $atributos) => Traduccion::hashDe($atributos['texto']),
            'texto' => fake()->unique()->sentence(3),
            'traduccion' => '修正',
            'corregida_por' => null,
        ];
    }
}
