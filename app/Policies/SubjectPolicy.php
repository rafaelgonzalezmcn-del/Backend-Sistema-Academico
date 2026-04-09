<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubjectPolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any subjects
     */
    public function viewAny(User $user)
    {
        return true; // Todos pueden ver lista de materias
    }

    /**
     * Determinate if user can view the subject
     */
    public function view(User $user, Subject $subject)
    {
        return $this->canAccessSubject($user, $subject);
    }

    /**
     * Determinate if user can create subjects
     */
    public function create(User $user)
    {
        return $this->isAdmin($user);
    }

    /**
     * Determinate if user can update the subject
     */
    public function update(User $user, Subject $subject)
    {
        return $this->isAdmin($user);
    }

    /**
     * Determinate if user can delete the subject
     */
    public function delete(User $user, Subject $subject)
    {
        return $this->isAdmin($user);
    }

    /**
     * Determinate if user can view participantes
     */
    public function viewParticipantes(User $user, Subject $subject)
    {
        return $this->canAccessSubject($user, $subject);
    }

    /**
     * Helper: Check if user is admin
     */
    private function isAdmin(User $user): bool
    {
        return $user->role && $user->role->name === 'admin';
    }

    /**
     * Helper: Check if user can access the subject
     */
    private function canAccessSubject(User $user, Subject $subject): bool
    {
        // Admin tiene acceso total
        if ($this->isAdmin($user)) {
            return true;
        }

        $roleName = $user->role?->name;

        // Estudiante debe estar inscrito en la materia
        if ($roleName === 'estudiante' && $user->section_id) {
            return \App\Models\ClassSchedule::where('section_id', $user->section_id)
                ->where('subject_id', $subject->id)
                ->exists();
        }

        // Profesor debe enseñar la materia
        if ($roleName === 'profesor') {
            return \App\Models\ClassSchedule::where('teacher_id', $user->id)
                ->where('subject_id', $subject->id)
                ->exists();
        }

        return false;
    }
}
