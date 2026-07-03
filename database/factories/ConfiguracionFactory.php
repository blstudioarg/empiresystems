<?php

namespace Database\Factories;

use App\Models\Configuracion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Configuracion>
 */
class ConfiguracionFactory extends Factory
{
    protected $model = Configuracion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'clave' => 'apariencia.color_primario',
            'valor' => '#1D69D6',
            'tipo' => 'string',
            'grupo' => 'apariencia',
            'descripcion' => null,
        ];
    }
}
