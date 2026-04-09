<?php

namespace App\Policies;

use App\Models\Entrega;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EntregaPolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any entregas for a tarea
     * Returns true for all, but index() method will filter data based on role
     */
    public function viewAny(User $user, Tarea $tarea)
    {
        return $this->canAccessTarea($user, $tarea);
    }

    /**
     * Determinate if user can view a specific entrega
     */
    public function view(User $user, Entrega $entrega)
    {
        // Admin puede ver cualquier entrega
        if ($this->isAdmin($user)) {
            return true;
        }

        // Profesor puede ver entregas de sus tareas
        if ($this->isProfesor($user)) {
            return $this->canAccessTarea($user, $entrega->tarea);
        }

        // Estudiante solo puede ver su propia entrega
        if ($this->isEstudiante($user)) {
            return $entrega->estudiante_id === $user->id;
        }

        return false;
    }

    /**
     * Determinate if user can create an entrega
     */
    public function create(User $user, Tarea $tarea)
    {
        // Solo estudiantes pueden crear entregas
        if (!$this->isEstudiante($user)) {
            return false;
        }

        // Y deben tener acceso a la tarea
        return $this->canAccessTarea($user, $tarea);
    }

    /**
     * Determinate if user can update (calificar) an entrega
     */
    public function update(User $user, Entrega $entrega)
    {
        // Solo profesor o admin pueden calificar
        if (!$this->isProfesor($user) && !$this->isAdmin($user)) {
            return false;
        }

        // Profesor solo puede calificar entregas de sus tareas
        if ($this->isProfesor($user)) {
            return $this->canAccessTarea($user, $entrega->tarea);
        }

        return true;
    }

    /**
     * Determinate if user can delete an entrega
     */
    public function delete(User $user, Entrega $entrega)
    {
        // Solo el estudiante que hizo la entrega puede eliminarla
        if (!$this->isEstudiante($user)) {
            return false;
        }

        return $entrega->estudiante_id === $user->id;
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

    /**
     * Helper: Check if user can access the tarea's modulo
     */
    private function canAccessTarea(User $user, Tarea $tarea): bool
    {
        // Cargar modulo si no está cargado
        if (!$tarea->relationLoaded('modulo')) {
            $tarea->load('modulo');
        }

        $modulo = $tarea->modulo;
        if (!$modulo) {
            // Si no hay módulo, solo admin puede acceder
            return $this->isAdmin($user);
        }

        // Verificar que el módulo tenga materia_id
        if (!$modulo->materia_id) {
            // Si el módulo no tiene materia asignada, solo admin puede acceder
            return $this->isAdmin($user);
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
        if ($this->isEstudiante($user)) {
            return \App\Models\ClassSchedule::where('section_id', $user->section_id)
                ->whereHas('subject', function ($query) use ($modulo) {
                    $query->where('id', $modulo->materia_id);
                })->exists();
        }

        return false;
    }
}
