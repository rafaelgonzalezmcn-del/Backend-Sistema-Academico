<?php

namespace App\Policies;

use App\Models\SchoolYear;
use App\Models\User;

class SchoolYearPolicy
{
    /**
     * Determinar si el usuario puede ver cualquier año lectivo
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determinar si el usuario puede ver el año lectivo
     */
    public function view(User $user, SchoolYear $schoolYear): bool
    {
        return true;
    }

    /**
     * Determinar si el usuario puede crear años lectivos
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determinar si el usuario puede actualizar el año lectivo
     */
    public function update(User $user, SchoolYear $schoolYear): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determinar si el usuario puede activar el año lectivo
     */
    public function activate(User $user, SchoolYear $schoolYear): bool
    {
        return $user->isAdmin();
    }
}
