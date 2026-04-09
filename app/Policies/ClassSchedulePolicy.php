<?php

namespace App\Policies;

use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClassSchedulePolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any schedules
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determinate if user can view the schedule
     */
    public function view(User $user, ClassSchedule $schedule)
    {
        // Admin tiene acceso total
        if ($this->isAdmin($user)) {
            return true;
        }

        // Profesor: solo sus propios horarios
        if ($this->isProfesor($user)) {
            return $schedule->teacher_id === $user->id;
        }

        // Estudiante: solo horarios de su sección
        if ($this->isEstudiante($user) && $user->section_id) {
            return $schedule->section_id === $user->section_id;
        }

        return false;
    }

    /**
     * Determinate if user can create schedules
     */
    public function create(User $user)
    {
        return $this->isAdmin($user);
    }

    /**
     * Determinate if user can update schedules
     */
    public function update(User $user, ClassSchedule $schedule)
    {
        return $this->isAdmin($user);
    }

    /**
     * Determinate if user can delete schedules
     */
    public function delete(User $user, ClassSchedule $schedule)
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
     * Helper: Check if user is estudiante
     */
    private function isEstudiante(User $user): bool
    {
        return $user->role && $user->role->name === 'estudiante';
    }
}
