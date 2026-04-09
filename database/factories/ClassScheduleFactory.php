<?php

namespace Database\Factories;

use App\Models\ClassSchedule;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassScheduleFactory extends Factory
{
    protected $model = ClassSchedule::class;

    /**
     * Define el estado por defecto del modelo.
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->profesor(),
            'subject_id' => Subject::factory(),
            'section_id' => Section::factory(),
            'school_year_id' => SchoolYear::factory(),
            'day' => fake()->randomElement(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes']),
            'start_time' => fake()->time('H:i'),
            'end_time' => fake()->time('H:i'),
        ];
    }

    /**
     * Crea un horario para un día específico.
     */
    public function forDay(string $day): static
    {
        return $this->state(fn (array $attributes) => [
            'day' => $day,
        ]);
    }

    /**
     * Crea un horario con horas específicas.
     */
    public function withTime(string $start, string $end): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /**
     * Crea un horario para un profesor específico.
     */
    public function forTeacher(User $teacher): static
    {
        return $this->state(fn (array $attributes) => [
            'teacher_id' => $teacher->id,
        ]);
    }

    /**
     * Crea un horario para una sección específica.
     */
    public function forSection(Section $section): static
    {
        return $this->state(fn (array $attributes) => [
            'section_id' => $section->id,
            'school_year_id' => $section->school_year_id,
        ]);
    }

    /**
     * Crea un horario para un año lectivo específico.
     */
    public function forSchoolYear(SchoolYear $schoolYear): static
    {
        return $this->state(fn (array $attributes) => [
            'school_year_id' => $schoolYear->id,
        ]);
    }
}
