<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParcialResource extends JsonResource
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
            'numero' => $this->numero,
            'modulo_id' => $this->modulo_id,
            'nota_maxima' => $this->nota_maxima,
            'fecha_inicio' => $this->fecha_inicio?->toIso8601String(),
            'fecha_fin' => $this->fecha_fin?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relación con módulo
            'modulo' => $this->whenLoaded('modulo', function () {
                return [
                    'id' => $this->modulo->id,
                    'nombre' => $this->modulo->nombre,
                    'materia_id' => $this->modulo->materia_id,
                ];
            }),
            
            // Relación con parámetros
            'parametros' => $this->whenLoaded('parametros', function () {
                return $this->parametros->map(fn($parametro) => [
                    'id' => $parametro->id,
                    'nombre' => $parametro->nombre,
                    'tipo' => $parametro->tipo,
                    'tipo_nombre' => $parametro->tipo_nombre,
                    'porcentaje' => $parametro->porcentaje,
                    'nota_maxima_default' => $parametro->nota_maxima_default,
                    'activo' => $parametro->activo,
                ]);
            }),
            
            // Relación con tareas
            'tareas' => $this->whenLoaded('tareas', function () {
                return $this->tareas->map(fn($tarea) => [
                    'id' => $tarea->id,
                    'titulo' => $tarea->titulo,
                    'puntaje_maximo' => $tarea->puntaje_maximo,
                    'fecha_limite' => $tarea->fecha_limite?->toIso8601String(),
                ]);
            }),
        ];
    }
}
