<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
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
            'nombre_archivo' => $this->nombre_archivo,
            'ruta' => $this->ruta,
            'tipo_archivo' => $this->tipo_archivo,
            'tamano' => $this->tamano,
            'tamano_formateado' => $this->tamano_formateado ?? $this->formatSize($this->tamano),
            'descripcion' => $this->descripcion,
            'modulo_id' => $this->modulo_id,
            'user_id' => $this->user_id,
            'url' => $this->url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Usuario que subió el material
            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => $this->usuario->id,
                    'nombre' => $this->usuario->first_name . ' ' . $this->usuario->last_name,
                ];
            }),
        ];
    }
    
    /**
     * Formatear tamaño si no existe el accessor
     */
    private function formatSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
