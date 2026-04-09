<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Un grado es un concepto genérico (Primero, Segundo...)
     * Las secciones definen en qué año lectivo existe
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'order' => $this->grade_order,
            // school_year_id eliminado - el grado es independiente del año
            'sections' => $this->whenLoaded('sections', function () {
                return $this->sections->map(fn($section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'school_year_id' => $section->school_year_id,
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
