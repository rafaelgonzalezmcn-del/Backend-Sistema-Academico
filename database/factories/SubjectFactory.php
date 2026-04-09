<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Matemáticas',
                'Lenguaje',
                'Ciencias Naturales',
                'Ciencias Sociales',
                'Educación Física',
                'Arte',
                'Música',
                'Inglés',
                'Computación',
                'Ética',
            ]),
        ];
    }

    /**
     * Crea una materia con nombre específico.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
