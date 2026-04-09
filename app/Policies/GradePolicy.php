<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    /**
     * Determinar si el usuario puede ver cualquier grado
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determinar si el usuario puede ver el grado
     */
    public function view(User $user, Grade $grade): bool
    {
        return true;
    }

    /**
     * Determinar si el usuario puede crear grados
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determinar si el usuario puede actualizar el grado
     */
    public function update(User $user, Grade $grade): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determinar si el usuario puede eliminar el grado
     */
    public function delete(User $user, Grade $grade): bool
    {
        return $user->isAdmin();
    }
}
