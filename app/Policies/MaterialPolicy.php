<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaterialPolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any materials
     */
    public function viewAny(User $user)
    {
        return true; // Todos los autenticados pueden ver materiales
    }

    /**
     * Determinate if user can view the material
     */
    public function view(User $user, Material $material)
    {
        return $this->canAccessMaterial($user, $material);
    }

    /**
     * Determinate if user can create materials
     */
    public function create(User $user)
    {
        // Solo profesores pueden crear materiales
        return $this->isProfesor($user) || $this->isAdmin($user);
    }

    /**
     * Determinate if user can update the material
     */
    public function update(User $user, Material $material)
    {
        // Solo profesores pueden actualizar materiales
        if (!$this->isProfesor($user) && !$this->isAdmin($user)) {
            return false;
        }
        return $this->canAccessMaterial($user, $material);
    }

    /**
     * Determinate if user can delete the material
     */
    public function delete(User $user, Material $material)
    {
        // Solo profesores pueden eliminar materiales
        if (!$this->isProfesor($user) && !$this->isAdmin($user)) {
            return false;
        }
        return $this->canAccessMaterial($user, $material);
    }

    /**
     * Helper: Check if user is profesor
     */
    private function isProfesor(User $user): bool
    {
        return $user->role && $user->role->name === 'profesor';
    }

    /**
     * Helper: Check if user is admin
     */
    private function isAdmin(User $user): bool
    {
        return $user->role && $user->role->name === 'admin';
    }

    /**
     * Helper: Check if user can access the material's modulo/materia
     */
    private function canAccessMaterial(User $user, Material $material): bool
    {
        // Cargar relacion modulo si no está cargada
        if (!$material->relationLoaded('modulo')) {
            $material->load('modulo');
        }

        $modulo = $material->modulo;
        if (!$modulo) {
            return false;
        }

        // Admin tiene acceso total
        if ($this->isAdmin($user)) {
            return true;
        }

        // Profesor debe enseñar la materia del módulo
        if ($this->isProfesor($user)) {
            return \App\Models\ClassSchedule::where('teacher_id', $user->id)
                ->whereHas('subject', function ($query) use ($modulo) {
                    $query->where('id', $modulo->materia_id);
                })->exists();
        }

        // Estudiante debe estar inscrito en la materia del módulo
        if ($user->role && $user->role->name === 'estudiante' && $user->section_id) {
            return \App\Models\ClassSchedule::where('section_id', $user->section_id)
                ->whereHas('subject', function ($query) use ($modulo) {
                    $query->where('id', $modulo->materia_id);
                })->exists();
        }

        return false;
    }
}
