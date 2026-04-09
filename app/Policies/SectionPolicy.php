<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    /**
     * Determinar si el usuario puede ver cualquier sección
     */
    public function viewAny(User $user): bool
    {
        return true; // Público para authenticated
    }

    /**
     * Determinar si el usuario puede ver la sección
     */
    public function view(User $user, Section $section): bool
    {
        return true;
    }

    /**
     * Determinar si el usuario puede crear secciones
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determinar si el usuario puede actualizar la sección
     */
    public function update(User $user, Section $section): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determinar si el usuario puede eliminar la sección
     */
    public function delete(User $user, Section $section): bool
    {
        return $user->isAdmin();
    }
}
