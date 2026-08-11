<?php

namespace Database\Factories;

use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosOpcion> */
class PosOpcionFactory extends Factory
{
    protected $model = PosOpcion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'grupo_id' => PosOpcionGrupo::factory(),
            'nombre' => fake()->unique()->words(2, true),
            'precio_defecto' => 0,
            'articulo_vinculado_id' => null,
            'orden' => 0,
        ];
    }
}
