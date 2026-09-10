<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\StudentCourse;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentCourse>
 */
class StudentCourseFactory extends Factory
{
    protected $model = StudentCourse::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory()->estudiante(),
            'subject_id' => Subject::factory(),
            'section_id' => Section::factory(),
            'school_year_id' => SchoolYear::factory(),
            'status' => fake()->randomElement(['cursando', 'aprobado', 'reprobado', 'concluido']),
            'final_grade' => fake()->randomFloat(2, 0, 10),
            'closed_at' => now(),
        ];
    }

    /**
     * Crea un curso aprobado.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'aprobado',
            'final_grade' => fake()->randomFloat(2, 7, 10),
            'closed_at' => now(),
        ]);
    }

    /**
     * Crea un curso reprobado.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reprobado',
            'final_grade' => fake()->randomFloat(2, 0, 6.9),
            'closed_at' => now(),
        ]);
    }

    /**
     * Crea un curso sin cerrar (cursando).
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cursando',
            'final_grade' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Crea un curso para un estudiante específico.
     */
    public function forStudent(User $student): static
    {
        return $this->state(fn (array $attributes) => [
            'student_id' => $student->id,
        ]);
    }

    /**
     * Crea un curso para una materia específica.
     */
    public function forSubject(Subject $subject): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_id' => $subject->id,
        ]);
    }

    /**
     * Crea un curso para una sección específica.
     */
    public function forSection(Section $section): static
    {
        return $this->state(fn (array $attributes) => [
            'section_id' => $section->id,
            'school_year_id' => $section->school_year_id,
        ]);
    }

    /**
     * Crea un curso para un año lectivo específico.
     */
    public function forSchoolYear(SchoolYear $schoolYear): static
    {
        return $this->state(fn (array $attributes) => [
            'school_year_id' => $schoolYear->id,
        ]);
    }
}