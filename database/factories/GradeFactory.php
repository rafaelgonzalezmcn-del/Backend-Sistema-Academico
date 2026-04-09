<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['1er Grado', '2do Grado', '3er Grado', '4to Grado', '5to Grado', '6to Grado']),
            'school_year_id' => SchoolYear::factory(),
        ];
    }

    /**
     * Crea un grado con un año lectivo específico.
     */
    public function forSchoolYear(SchoolYear $schoolYear): static
    {
        return $this->state(fn (array $attributes) => [
            'school_year_id' => $schoolYear->id,
        ]);
    }

    /**
     * Crea un grado de nombre específico.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
