<?php

namespace Database\Factories;

use App\Models\AsistenteConversacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AsistenteConversacion>
 */
class AsistenteConversacionFactory extends Factory
{
    protected $model = AsistenteConversacion::class;

    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(4),
            'resumen' => null,
            'resumido_hasta_mensaje_id' => null,
            'ultima_actividad_en' => now(),
        ];
    }

    /**
     * Conversación sin actividad reciente, para probar la purga.
     */
    public function inactivaDesdeHace(int $dias): static
    {
        return $this->state(fn () => ['ultima_actividad_en' => now()->subDays($dias)]);
    }

    public function compactada(string $resumen = 'Resumen de la parte anterior.'): static
    {
        return $this->state(fn () => ['resumen' => $resumen]);
    }
}
