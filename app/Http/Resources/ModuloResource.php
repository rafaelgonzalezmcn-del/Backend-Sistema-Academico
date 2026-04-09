<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuloResource extends JsonResource
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
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'materia_id' => $this->materia_id,
            'num_materiales' => $this->num_materiales ?? $this->materiales()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relación con materia
            'materia' => $this->whenLoaded('materia', function () {
                return [
                    'id' => $this->materia->id,
                    'name' => $this->materia->name,
                ];
            }),
            
            // Relación con materiales
            'materiales' => $this->whenLoaded('materiales', function () {
                return MaterialResource::collection($this->materiales);
            }),
        ];
    }
}
