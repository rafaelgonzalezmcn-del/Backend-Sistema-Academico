<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TareaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $esEstudiante = $user?->role?->name === 'estudiante';
        
        // Si es estudiante, buscar su entrega
        $miEntrega = null;
        if ($esEstudiante) {
            $entrega = \App\Models\Entrega::where('tarea_id', $this->id)
                ->where('estudiante_id', $user->id)
                ->first();
            if ($entrega) {
                $miEntrega = [
                    'id' => $entrega->id,
                    'nota' => $entrega->nota,
                    'observaciones' => $entrega->observaciones,
                    'fecha_entrega' => $entrega->fecha_entrega?->toIso8601String(),
                    'ha_entregado' => true,
                    'archivo' => $entrega->archivo,
                ];
            }
        }
        
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'fecha_limite' => $this->fecha_limite?->toIso8601String(),
            'modulo_id' => $this->modulo_id,
            'archivo_ruta' => $this->archivo_ruta,
            'archivo_nombre' => $this->archivo_nombre,
            'archivo_tamano' => $this->archivo_tamano,
            'puntaje_maximo' => $this->puntaje_maximo,
            'parcial_id' => $this->parcial_id,
            'parametro_id' => $this->parametro_id,
            'estado' => $this->estado ?? $this->getEstado(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Entrega del estudiante (si aplica)
            'ha_entregado' => $miEntrega ? true : false,
            'mi_entrega' => $miEntrega,
            
            // Relación con módulo
            'modulo' => $this->whenLoaded('modulo', function () {
                return [
                    'id' => $this->modulo->id,
                    'nombre' => $this->modulo->nombre,
                    'materia_id' => $this->modulo->materia_id,
                ];
            }),
            
            // Relación con parcial
            'parcial' => $this->whenLoaded('parcial', function () {
                return [
                    'id' => $this->parcial->id,
                    'nombre' => $this->parcial->nombre,
                    'numero' => $this->parcial->numero,
                ];
            }),
            
            // Relación con parámetro
            'parametro' => $this->whenLoaded('parametro', function () {
                return [
                    'id' => $this->parametro->id,
                    'nombre' => $this->parametro->nombre,
                    'tipo' => $this->parametro->tipo,
                    'porcentaje' => $this->parametro->porcentaje,
                ];
            }),
        ];
    }
}
