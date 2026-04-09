<?php

namespace App\Policies;

use App\Models\Modulo;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModuloPolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any modulos
     */
    public function viewAny(User $user)
    {
        return true; // Todos los autenticados pueden ver módulos
    }

    /**
     * Determinate if user can view the modulo
     */
    public function view(User $user, Modulo $modulo)
    {
        return $this->canAccessModulo($user, $modulo);
    }

    /**
     * Determinate if user can create modulos
     */
    public function create(User $user)
    {
        return $this->isProfesor($user) || $this->isAdmin($user);
    }

    /**
     * Determinate if user can update the modulo
     */
    public function update(User $user, Modulo $modulo)
    {
        return $this->isProfesor($user) || $this->isAdmin($user);
    }

    /**
     * Determinate if user can delete the modulo
     */
    public function delete(User $user, Modulo $modulo)
    {
        return $this->isAdmin($user);
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
    private function canAccessModulo(User $user, Modulo $modulo): bool
    {
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
