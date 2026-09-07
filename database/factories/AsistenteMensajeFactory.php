<?php

namespace Database\Factories;

use App\Models\AsistenteMensaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AsistenteMensaje>
 */
class AsistenteMensajeFactory extends Factory
{
    protected $model = AsistenteMensaje::class;

    public function definition(): array
    {
        return [
            'rol' => AsistenteMensaje::ROL_USER,
            'contenido' => $this->faker->sentence(),
            'metadatos' => null,
        ];
    }

    public function delAsistente(): static
    {
        return $this->state(fn () => [
            'rol' => AsistenteMensaje::ROL_ASSISTANT,
            'contenido' => $this->faker->sentence(),
        ]);
    }

    /**
     * Turno del asistente que solo pide una herramienta: sin texto, con `tool_calls`.
     */
    public function conToolCall(string $id = 'call_1', string $tool = 'buscar_clientes'): static
    {
        return $this->state(fn () => [
            'rol' => AsistenteMensaje::ROL_ASSISTANT,
            'contenido' => null,
            'metadatos' => ['tool_calls' => [[
                'id' => $id,
                'type' => 'function',
                'function' => ['name' => $tool, 'arguments' => '{}'],
            ]]],
        ]);
    }

    public function resultadoDeTool(string $id = 'call_1'): static
    {
        return $this->state(fn () => [
            'rol' => AsistenteMensaje::ROL_TOOL,
            'contenido' => '{"resultados":[]}',
            'metadatos' => ['tool_call_id' => $id],
        ]);
    }
}
