<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntregaResource extends JsonResource
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
            'tarea_id' => $this->tarea_id,
            'estudiante_id' => $this->estudiante_id,
            'archivo' => $this->archivo,
            'archivo_url' => $this->archivo_url ?? \App\Support\ArchivoPrivado::url($this->archivo),
            'fecha_entrega' => $this->fecha_entrega?->toIso8601String(),
            'nota' => $this->nota,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relación con tarea
            'tarea' => $this->whenLoaded('tarea', function () {
                return [
                    'id' => $this->tarea->id,
                    'titulo' => $this->tarea->titulo,
                    'puntaje_maximo' => $this->tarea->puntaje_maximo,
                    'fecha_limite' => $this->tarea->fecha_limite?->toIso8601String(),
                ];
            }),
            
            // Relación con estudiante
            'estudiante' => $this->whenLoaded('estudiante', function () {
                return [
                    'id' => $this->estudiante->id,
                    'first_name' => $this->estudiante->first_name,
                    'last_name' => $this->estudiante->last_name,
                    'full_name' => $this->estudiante->first_name . ' ' . $this->estudiante->last_name,
                    'email' => $this->estudiante->email,
                ];
            }),
        ];
    }
}
