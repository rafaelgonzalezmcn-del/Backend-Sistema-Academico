<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Cargar role si no está cargado para verificar permisos
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        $currentUser = $request->user();
        $esViewPropio = $this->id === $currentUser?->id;
        
        // Cargar rol del usuario actual si no está
        if ($currentUser && !$currentUser->relationLoaded('role')) {
            $currentUser->load('role');
        }
        
        $esAdmin = $currentUser?->role?->name === 'admin';
        $esProfesor = $currentUser?->role?->name === 'profesor';
        $esEstudiante = $currentUser?->role?->name === 'estudiante';
        
        // Determinar tipo de viewer
        $targetRole = $this->role?->name;
        
        // Estudiante viendo a otro estudiante O a un profesor de sus clases
        $puedeVerDatos = $esViewPropio || 
            ($esEstudiante && $targetRole === 'estudiante') ||
            ($esEstudiante && $targetRole === 'profesor' && $this->profesorEnseñaAEstudiante($currentUser, $this));

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'identification_number' => $this->identification_number,
            'phone' => $puedeVerDatos ? $this->phone : null,
            'role_id' => $this->role_id,
            'section_id' => $this->section_id,
            'activo' => $esAdmin ? $this->activo : null,
            'last_login_at' => $esAdmin ? $this->last_login_at?->toIso8601String() : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $esAdmin ? $this->updated_at?->toIso8601String() : null,
            
            // Relaciones
            'role' => $this->whenLoaded('role', function () {
                return [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                ];
            }),
            
            'section' => $this->whenLoaded('section', function () {
                $section = [
                    'id' => $this->section->id,
                    'name' => $this->section->name,
                    'grade_id' => $this->section->grade_id,
                ];
                
                if ($this->section->relationLoaded('grade')) {
                    $section['grade'] = [
                        'id' => $this->section->grade->id,
                        'name' => $this->section->grade->name,
                    ];
                }
                
                if ($this->section->relationLoaded('schoolYear')) {
                    $section['schoolYear'] = [
                        'id' => $this->section->schoolYear->id,
                        'name' => $this->section->schoolYear->name,
                    ];
                }
                
                return $section;
            }),
            
            // Selfie
            'has_selfie' => !empty($this->selfie),
        ];
    }
    
    /**
     * Verificar si el profesor enseña al estudiante
     */
    private function profesorEnseñaAEstudiante($estudiante, $profesor): bool
    {
        if (!$estudiante || !$estudiante->section_id) {
            return false;
        }
        
        return \App\Models\ClassSchedule::where('teacher_id', $profesor->id)
            ->where('section_id', $estudiante->section_id)
            ->exists();
    }
}
