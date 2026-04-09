<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // school_year_id se deriva desde section (columna eliminada de class_schedules)
        $schoolYearId = null;
        if ($this->relationLoaded('section') && $this->section) {
            $schoolYearId = $this->section->school_year_id;
        } elseif ($this->relationLoaded('schoolYear') && $this->schoolYear) {
            $schoolYearId = $this->schoolYear->id;
        }

        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'subject_id' => $this->subject_id,
            'section_id' => $this->section_id,
            'school_year_id' => $schoolYearId, // Derivado desde section
            'day' => $this->day,
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'duration_minutes' => $this->duration_in_minutes ?? $this->calculateDuration(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relación con profesor
            'teacher' => $this->whenLoaded('teacher', function () {
                return [
                    'id' => $this->teacher->id,
                    'first_name' => $this->teacher->first_name,
                    'last_name' => $this->teacher->last_name,
                    'full_name' => $this->teacher->first_name . ' ' . $this->teacher->last_name,
                ];
            }),
            
            // Relación con materia
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                ];
            }),
            
            // Relación con sección
            'section' => $this->whenLoaded('section', function () {
                $section = [
                    'id' => $this->section->id,
                    'name' => $this->section->name,
                    'grade_id' => $this->section->grade_id,
                ];
                
                // Incluir grade si está cargado
                if ($this->section->relationLoaded('grade')) {
                    $section['grade'] = [
                        'id' => $this->section->grade->id,
                        'name' => $this->section->grade->name,
                    ];
                }
                
                return $section;
            }),
            
            // Relación con año lectivo
            'schoolYear' => $this->whenLoaded('schoolYear', function () {
                return [
                    'id' => $this->schoolYear->id,
                    'name' => $this->schoolYear->name,
                    'active' => $this->schoolYear->active,
                ];
            }),
        ];
    }
    
    /**
     * Calcular duración si no está disponible
     */
    private function calculateDuration(): ?int
    {
        if (!$this->start_time || !$this->end_time) {
            return null;
        }
        
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        
        return $start->diffInMinutes($end);
    }
    
    /**
     * Formatear hora correctamente (solo HH:MM)
     */
    private function formatTime($time): ?string
    {
        if ($time === null) {
            return null;
        }
        
        // Si es un string, intentar parsear
        if (is_string($time)) {
            $parsed = \Carbon\Carbon::parse($time);
            return $parsed->format('H:i');
        }
        
        // Si es un objeto Carbon
        if ($time instanceof \Carbon\Carbon) {
            return $time->format('H:i');
        }
        
        return $time;
    }
}
