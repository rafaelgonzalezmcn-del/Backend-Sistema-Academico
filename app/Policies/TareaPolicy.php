<?php

namespace App\Policies;

use App\Models\Tarea;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TareaPolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any tareas
     */
    public function viewAny(User $user)
    {
        return true; // Todos los autenticados pueden ver tareas
    }

    /**
     * Determinate if user can view the tarea
     */
    public function view(User $user, Tarea $tarea)
    {
        return $this->canAccessModulo($user, $tarea->modulo);
    }

    /**
     * Determinate if user can create tareas
     */
    public function create(User $user)
    {
        return $this->isProfesor($user) || $this->isAdmin($user);
    }

    /**
     * Determinate if user can update the tarea
     */
    public function update(User $user, Tarea $tarea)
    {
        if (!$this->isProfesor($user) && !$this->isAdmin($user)) {
            return false;
        }
        return $this->canAccessModulo($user, $tarea->modulo);
    }

    /**
     * Determinate if user can delete the tarea
     */
    public function delete(User $user, Tarea $tarea)
    {
        if (!$this->isProfesor($user) && !$this->isAdmin($user)) {
            return false;
        }
        return $this->canAccessModulo($user, $tarea->modulo);
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

        // Estudiante debe estar inscrito en la materia del módulo
        if ($user->role && $user->role->name === 'estudiante') {
            return \App\Models\ClassSchedule::where('section_id', $user->section_id)
                ->whereHas('subject', function ($query) use ($modulo) {
                    $query->where('id', $modulo->materia_id);
                })->exists();
        }

        return false;
    }
}
