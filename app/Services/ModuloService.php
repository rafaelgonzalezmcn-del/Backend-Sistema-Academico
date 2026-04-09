<?php

namespace App\Services;

use App\Models\Modulo;
use App\Models\Subject;
use App\Models\User;
use App\Traits\LogsActivity;

class ModuloService
{
    use LogsActivity;
    /**
     * Obtener módulos de una materia con autorización ya verificada
     */
    public function getByMateria(int $materiaId): array
    {
        $materia = Subject::findOrFail($materiaId);
        
        $modulos = Modulo::with(['materiales' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])->where('materia_id', $materiaId)
          ->orderBy('created_at', 'asc')
          ->get();

        return [
            'data' => $modulos,
            'materia' => $materia
        ];
    }

    /**
     * Crear un nuevo módulo
     */
    public function create(array $data): Modulo
    {
        $modulo = Modulo::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'materia_id' => $data['materia_id']
        ]);

        $this->logActivity($this->logEvent('modulo', 'created'), $modulo, null, true);

        return $modulo->load('materiales');
    }

    /**
     * Actualizar módulo
     */
    public function update(Modulo $modulo, array $data): Modulo
    {
        $oldData = [
            'nombre' => $modulo->nombre,
            'descripcion' => $modulo->descripcion
        ];
        
        $modulo->update([
            'nombre' => $data['nombre'] ?? $modulo->nombre,
            'descripcion' => $data['descripcion'] ?? $modulo->descripcion
        ]);

        $changes = $this->getChanges($oldData, $data);
        $this->logActivity($this->logEvent('modulo', 'updated'), $modulo, $changes);

        return $modulo;
    }

    /**
     * Eliminar módulo (y sus materiales)
     */
    public function delete(Modulo $modulo): void
    {
        // Eliminar archivos físicos de materiales
        foreach ($modulo->materiales as $material) {
            if ($material->ruta && \Illuminate\Support\Facades\Storage::disk('public')->exists($material->ruta)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($material->ruta);
            }
        }

        $modulo->delete();

        $this->logActivity($this->logEvent('modulo', 'deleted'), $modulo, null, true);
    }

    /**
     * Obtener un módulo específico
     */
    public function getById(int $moduloId): Modulo
    {
        return Modulo::with('materiales')->findOrFail($moduloId);
    }

    /**
     * Verificar que el usuario puede acceder a la materia (para autorización adicional)
     */
    public function canAccessMateria(User $user, int $materiaId): bool
    {
        // Admin tiene acceso total
        if ($user->role && $user->role->name === 'admin') {
            return true;
        }

        // Profesor debe enseñar la materia
        if ($user->role && $user->role->name === 'profesor') {
            return \App\Models\ClassSchedule::where('teacher_id', $user->id)
                ->whereHas('subject', function ($query) use ($materiaId) {
                    $query->where('id', $materiaId);
                })->exists();
        }

        // Estudiante debe estar inscrito en la materia
        if ($user->role && $user->role->name === 'estudiante' && $user->section_id) {
            return \App\Models\ClassSchedule::where('section_id', $user->section_id)
                ->whereHas('subject', function ($query) use ($materiaId) {
                    $query->where('id', $materiaId);
                })->exists();
        }

        return false;
    }
}
