<?php

namespace App\Policies;

use App\Models\Parcial;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ParcialPolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any parciales
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determinate if user can view the parcial
     */
    public function view(User $user, Parcial $parcial)
    {
        return $this->canAccessModulo($user, $parcial->modulo);
    }

    /**
     * Determinate if user can create parciales
     */
    public function create(User $user)
    {
        return $this->isProfesor($user) || $this->isAdmin($user);
    }

    /**
     * Determinate if user can update the parcial
     */
    public function update(User $user, Parcial $parcial)
    {
        return $this->isProfesor($user) || $this->isAdmin($user);
    }

    /**
     * Determinate if user can delete the parcial
     */
    public function delete(User $user, Parcial $parcial)
    {
        return $this->isAdmin($user);
    }

    /**
     * Determinate if user can view resumen de notas (profesor/admin)
     */
    public function viewResumen(User $user)
    {
        return $this->isProfesor($user) || $this->isAdmin($user);
    }

    /**
     * Determinate if user can view mis notas (estudiante)
     */
    public function viewMisNotas(User $user)
    {
        return $user->role && $user->role->name === 'estudiante';
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
     * Helper: Check if user can access the modulo
     */
    private function canAccessModulo(User $user, $modulo): bool
    {
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

        // Estudiante debe estar inscrito
        if ($user->role && $user->role->name === 'estudiante') {
            return \App\Models\ClassSchedule::where('section_id', $user->section_id)
                ->whereHas('subject', function ($query) use ($modulo) {
                    $query->where('id', $modulo->materia_id);
                })->exists();
        }

        return false;
    }
}
