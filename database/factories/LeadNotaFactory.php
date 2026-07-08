<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadNota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadNota>
 */
class LeadNotaFactory extends Factory
{
    protected $model = LeadNota::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'user_id' => null,
            'tipo' => 'nota',
            'contenido' => fake()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (LeadNota $nota) {
            if ($nota->lead_id && ! $nota->tenant_id) {
                $nota->tenant_id = Lead::withoutGlobalScopes()->find($nota->lead_id)?->tenant_id;
            }
        });
    }
}
