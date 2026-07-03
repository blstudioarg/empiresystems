<?php

namespace Database\Factories;

use App\Enums\EstadoUsuario;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'tenant_id' => Tenant::factory(),
            'rol' => UserRole::Usuario,
            'activo' => true,
            'estado' => EstadoUsuario::Aprobado,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Super admin global: sin tenant.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'rol' => UserRole::SuperAdmin,
        ]);
    }

    /**
     * Admin de un tenant.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => UserRole::Admin,
        ]);
    }

    /**
     * Usuario inactivo (no puede autenticarse).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /**
     * Solicitante pendiente de aprobación.
     */
    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoUsuario::Pendiente,
            'activo' => false,
        ]);
    }

    /**
     * Solicitud rechazada.
     */
    public function rechazado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoUsuario::Rechazado,
            'activo' => false,
        ]);
    }
}
