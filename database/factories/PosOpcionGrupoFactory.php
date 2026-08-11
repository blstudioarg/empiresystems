<?php

namespace Database\Factories;

use App\Models\PosOpcionGrupo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosOpcionGrupo> */
class PosOpcionGrupoFactory extends Factory
{
    protected $model = PosOpcionGrupo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'nombre' => fake()->unique()->words(2, true),
            'min_selecciones' => 0,
            'max_selecciones' => null,
            'obligatorio' => false,
            'orden' => 0,
        ];
    }

    public function obligatorio(int $min = 1, ?int $max = 1): static
    {
        return $this->state(fn () => [
            'obligatorio' => true,
            'min_selecciones' => $min,
            'max_selecciones' => $max,
        ]);
    }
}
