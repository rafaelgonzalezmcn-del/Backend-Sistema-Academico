<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\SchoolYear;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grade_id' => Grade::factory(),
            'school_year_id' => SchoolYear::factory(),
            'name' => fake()->randomElement(['A', 'B', 'C', 'D']),
        ];
    }

    /**
     * Crea una sección para un grado específico.
     */
    public function forGrade(Grade $grade): static
    {
        return $this->state(fn (array $attributes) => [
            'grade_id' => $grade->id,
        ]);
    }

    /**
     * Crea una sección para un año lectivo específico.
     */
    public function forSchoolYear(SchoolYear $schoolYear): static
    {
        return $this->state(fn (array $attributes) => [
            'school_year_id' => $schoolYear->id,
        ]);
    }

    /**
     * Crea una sección con nombre específico.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Crea una sección con nombre único basado en timestamp.
     */
    public function uniqueName(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'T' . time() . rand(1, 99),
        ]);
    }
}
