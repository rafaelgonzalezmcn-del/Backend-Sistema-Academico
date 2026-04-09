<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Crea el rol de administrador.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'admin',
            'description' => 'Administrador del sistema',
        ]);
    }

    /**
     * Crea el rol de estudiante.
     */
    public function estudiante(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'estudiante',
            'description' => 'Usuario estudiante',
        ]);
    }

    /**
     * Crea el rol de profesor.
     */
    public function profesor(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'profesor',
            'description' => 'Usuario profesor',
        ]);
    }
}
