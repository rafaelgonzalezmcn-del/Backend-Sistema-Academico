<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade_id' => $this->grade_id,
            'school_year_id' => $this->school_year_id,
            'name' => $this->name,
            'max_capacity' => $this->max_capacity,
            'enrolled_count' => $this->enrolled_count ?? 0,
            'is_full' => $this->max_capacity !== null && ($this->enrolled_count ?? 0) >= $this->max_capacity,
            'full_name' => $this->full_name ?? "{$this->grade?->name} - {$this->name}",
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relación con grado
            'grade' => $this->whenLoaded('grade', function () {
                return [
                    'id' => $this->grade->id,
                    'name' => $this->grade->name,
                ];
            }),
            
            // Relación con año lectivo
            'schoolYear' => $this->whenLoaded('schoolYear', function () {
                return [
                    'id' => $this->schoolYear->id,
                    'name' => $this->schoolYear->name,
                    'active' => $this->schoolYear->active,
                ];
            }),
            
            // Estudiantes (solo si está cargado)
            'students' => $this->whenLoaded('students', function () {
                return $this->students->map(fn($student) => [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                ]);
            }),
            
            // Horarios (solo si está cargado)
            'classSchedules' => $this->whenLoaded('classSchedules', function () {
                return $this->classSchedules->map(fn($schedule) => [
                    'id' => $schedule->id,
                    'day' => $schedule->day,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ]);
            }),
        ];
    }
}
