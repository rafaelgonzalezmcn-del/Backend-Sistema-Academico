<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
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
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Sección cargada con whenLoaded (para show con datos de estudiante)
            // Solo se incluye si la relación está cargada
            'section' => $this->whenLoaded('section', function () {
                return [
                    'name' => $this->section['name'] ?? $this->section->name ?? null,
                    'grade' => $this->section['grade'] ?? $this->section->grade->name ?? null,
                ];
            }),
        ];
    }
}
