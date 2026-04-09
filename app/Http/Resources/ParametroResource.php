<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParametroResource extends JsonResource
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
            'tipo' => $this->tipo,
            'tipo_nombre' => $this->tipo_nombre,
            'parcial_id' => $this->parcial_id,
            'porcentaje' => $this->porcentaje,
            'nota_maxima_default' => $this->nota_maxima_default,
            'activo' => $this->activo,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relación con parcial
            'parcial' => $this->whenLoaded('parcial', function () {
                return [
                    'id' => $this->parcial->id,
                    'nombre' => $this->parcial->nombre,
                    'numero' => $this->parcial->numero,
                ];
            }),
        ];
    }
}
