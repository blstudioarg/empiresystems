<?php

namespace Database\Factories;

use App\Models\Traduccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Traduccion> */
class TraduccionFactory extends Factory
{
    protected $model = Traduccion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'idioma' => 'zh',
            'hash' => fn (array $atributos) => Traduccion::hashDe($atributos['texto']),
            'texto' => fake()->unique()->sentence(3),
            'ambito' => 'pos',
            'es_html' => false,
            'traduccion' => null,
            'estado' => Traduccion::ESTADO_PENDIENTE,
            'intentos' => 0,
            'ultimo_error' => null,
            'traducida_en' => null,
            'vista_en' => now(),
        ];
    }

    public function traducida(string $traduccion): static
    {
        return $this->state([
            'traduccion' => $traduccion,
            'estado' => Traduccion::ESTADO_TRADUCIDA,
            'traducida_en' => now(),
        ]);
    }
}
