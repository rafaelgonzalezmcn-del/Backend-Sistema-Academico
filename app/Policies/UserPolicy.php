<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determinate if user can view any users
     */
    public function viewAny(User $user)
    {
        return $this->isAdmin($user);
    }

    /**
     * Determinate if user can view the user
     */
    public function view(User $user, User $targetUser)
    {
        // Admin puede ver cualquier usuario
        if ($this->isAdmin($user)) {
            return true;
        }

        // Usuarios pueden ver su propio perfil
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Profesor puede ver estudiantes de sus materias
        if ($this->isProfesor($user)) {
            return $this->userIsStudentInMateriaProfesor($user, $targetUser);
        }

        // Estudiantes pueden ver otros estudiantes (solo datos públicos)
        if ($this->isEstudiante($user)) {
            return $this->userIsStudentInSameSection($user, $targetUser) ||
                   $this->userIsProfesorInMateriaEstudiante($user, $targetUser);
        }

        return false;
    }

    /**
     * Helper: Check if user is estudiante
     */
    private function isEstudiante(User $user): bool
    {
        return $user->role && $user->role->name === 'estudiante';
    }

    /**
     * Helper: Check if target user is in the same section (for students to see each other)
     */
    private function userIsStudentInSameSection(User $user, User $targetUser): bool
    {
        // No puede verse a sí mismo (ya cubierto arriba)
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Verificar que el usuario objetivo sea estudiante
        if (!$targetUser->relationLoaded('role')) {
            $targetUser->load('role');
        }

        if (!$targetUser->role || $targetUser->role->name !== 'estudiante') {
            return false;
        }

        // Verificar que estén en la misma sección
        return $user->section_id && $targetUser->section_id && 
               $user->section_id === $targetUser->section_id;
    }

    /**
     * Helper: Check if target user is a professor that teaches the student
     */
    private function userIsProfesorInMateriaEstudiante(User $estudiante, User $profesor): bool
    {
        // Verificar que el usuario objetivo sea profesor
        if (!$profesor->relationLoaded('role')) {
            $profesor->load('role');
        }

        if (!$profesor->role || $profesor->role->name !== 'profesor') {
            return false;
        }

        // Obtener las secciones del estudiante
        $estudianteSectionId = $estudiante->section_id;
        if (!$estudianteSectionId) {
            return false;
        }

        // Verificar si el profesor enseña alguna materia a esa sección
        return \App\Models\ClassSchedule::where('teacher_id', $profesor->id)
            ->where('section_id', $estudianteSectionId)
            ->exists();
    }

    /**
     * Helper: Check if user is profesor
     */
    private function isProfesor(User $user): bool
    {
        return $user->role && $user->role->name === 'profesor';
    }

    /**
     * Helper: Check if target user is a student in any materia where user is profesor
     */
    private function userIsStudentInMateriaProfesor(User $profesor, User $student): bool
    {
        // Cargar el rol si no está cargado
        if (!$student->relationLoaded('role')) {
            $student->load('role');
        }

        // Verificar que el usuario objetivo sea estudiante
        if (!$student->role || $student->role->name !== 'estudiante') {
            return false;
        }

        // Obtener las materias que dicta el profesor (vía ClassSchedule)
        $subjectIds = \App\Models\ClassSchedule::where('teacher_id', $profesor->id)
            ->pluck('subject_id')
            ->unique()
            ->toArray();

        if (empty($subjectIds)) {
            return false;
        }

        // Obtener las secciones que tienen esas materias
        $sectionIds = \App\Models\ClassSchedule::whereIn('subject_id', $subjectIds)
            ->pluck('section_id')
            ->unique()
            ->toArray();

        if (empty($sectionIds)) {
            return false;
        }

        // Verificar si el estudiante está en alguna de esas secciones
        return in_array($student->section_id, $sectionIds);
    }

    /**
     * Determinate if user can create users
     */
    public function create(User $user)
    {
        return $this->isAdmin($user);
    }

    /**
     * Determinate if user can update users
     */
    public function update(User $user, User $targetUser)
    {
        // Admin puede actualizar cualquier usuario
        if ($this->isAdmin($user)) {
            return true;
        }

        // Usuarios pueden actualizar su propio perfil
        return $user->id === $targetUser->id;
    }

    /**
     * Determinate if user can delete users
     */
    public function delete(User $user, User $targetUser)
    {
        // Admin puede desactivar usuarios
        if ($this->isAdmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determinate if user can assign section
     */
    public function assignSection(User $user)
    {
        return $this->isAdmin($user);
    }

    /**
     * Helper: Check if user is admin
     */
    private function isAdmin(User $user): bool
    {
        // Cargar rol si no está cargado
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        return $user->role && $user->role->name === 'admin';
    }
}
