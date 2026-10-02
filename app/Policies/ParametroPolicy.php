<?php

namespace App\Policies;

use App\Models\Parametro;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ParametroPolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any parametros
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determinate if user can view the parametro
     */
    public function view(User $user, Parametro $parametro)
    {
        return $this->canAccessModulo($user, $parametro->parcial?->modulo);
    }

    /**
     * Determinate if user can create parametros
     */
    public function create(User $user)
    {
        return $this->isProfesor($user) || $this->isAdmin($user);
    }

    /**
     * Determinate if user can update the parametro
     */
    public function update(User $user, Parametro $parametro)
    {
        // Solo admin o el profesor que dicta la materia de este parámetro
        return $this->isAdmin($user)
            || ($this->isProfesor($user) && $this->canAccessModulo($user, $parametro->parcial?->modulo));
    }

    /**
     * Determinate if user can delete the parametro
     */
    public function delete(User $user, Parametro $parametro)
    {
        // Solo admin o el profesor que dicta la materia de este parámetro
        return $this->isAdmin($user)
            || ($this->isProfesor($user) && $this->canAccessModulo($user, $parametro->parcial?->modulo));
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
