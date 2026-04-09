<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    protected $model = SchoolYear::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = now()->year;
        $uniqueSuffix = fake()->unique()->numerify('###');
        return [
            'name' => "Año Lectivo {$year}-{$uniqueSuffix}",
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'active' => false,
        ];
    }


    /**
     * Indica que el año lectivo está activo.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Crea un año lectivo para un año específico.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => "Año Lectivo {$year}",
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
        ]);
    }
}
