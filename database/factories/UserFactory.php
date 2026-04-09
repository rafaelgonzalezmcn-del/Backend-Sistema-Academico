<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'identification_number' => fake()->unique()->numerify('###########'),
            'phone' => fake()->phoneNumber(),
            'role_id' => 2, // Por defecto estudiante
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indica que el email no está verificado.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indica que el usuario está inactivo.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /**
     * Crea un usuario con rol de admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => 1,
        ]);
    }

    /**
     * Crea un usuario con rol de estudiante.
     */
    public function estudiante(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => 2,
        ]);
    }

    /**
     * Crea un usuario con rol de profesor.
     */
    public function profesor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => 3,
        ]);
    }
}
