<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolYearResource extends JsonResource
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
            'name' => $this->name,
            'grade_order' => $this->grade_order,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Conteo de secciones
            'grades_count' => 0, // Los grados ya no tienen año lectivo
            'sections_count' => $this->whenCounted('sections', fn() => $this->sections_count ?? $this->sections->count()),
            
            // Relación con secciones
            'sections' => $this->whenLoaded('sections', function () {
                return $this->sections->map(fn($section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'grade_id' => $section->grade_id,
                ]);
            }),
        ];
    }
}
