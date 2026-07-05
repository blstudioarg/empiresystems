<?php

namespace Database\Factories;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\ResultadoLogActividad;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogActividad>
 */
class LogActividadFactory extends Factory
{
    protected $model = LogActividad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();
        $accion = $this->faker->randomElement(AccionLogActividad::cases());
        $entidadTipo = in_array($accion, [AccionLogActividad::Login, AccionLogActividad::Logout], true)
            ? null
            : $this->faker->randomElement(EntidadLogActividad::cases());

        return [
            'tenant_id' => $tenant,
            'usuario_id' => User::factory()->for($tenant, 'tenant'),
            'usuario_nombre' => $this->faker->name(),
            'accion' => $accion,
            'resultado' => ResultadoLogActividad::Exito,
            'ip_origen' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadTipo ? $this->faker->numberBetween(1, 1000) : null,
            'descripcion' => $this->faker->sentence(),
            'ocurrido_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function fallo(): static
    {
        return $this->state(fn (array $attributes) => [
            'usuario_id' => null,
            'accion' => AccionLogActividad::Login,
            'resultado' => ResultadoLogActividad::Fallo,
            'entidad_tipo' => null,
            'entidad_id' => null,
        ]);
    }
}
