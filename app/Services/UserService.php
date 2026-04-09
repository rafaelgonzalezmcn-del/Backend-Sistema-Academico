<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserService
{
    /**
     * Crear usuario
     * F9-H3: Normaliza datos antes de guardar.
     */
    public function create(array $data): User
    {
        // F14: Validar cupo si se asigna sección al crear
        if (isset($data['section_id']) && $data['section_id']) {
            $section = \App\Models\Section::find($data['section_id']);
            if ($section && !$section->hasAvailableSpace()) {
                $enrolled = $section->getEnrolledCount();
                throw new \Exception(
                    "La sección '{$section->name}' ha alcanzado su capacidad máxima ({$enrolled}/{$section->max_capacity} estudiantes)"
                );
            }
        }

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
            'password' => Hash::make($data['password']),
            'identification_number' => isset($data['identification_number'])
                ? preg_replace('/\D/', '', $data['identification_number'])
                : null,
            'phone' => $data['phone'] ?? null,
            'role_id' => $data['role_id'],
            'section_id' => $data['section_id'] ?? null,
            'activo' => true
        ]);

        Log::info('Usuario creado', ['user_id' => $user->id]);

        return $user->load(['role', 'section']);
    }

    /**
     * Actualizar usuario
     * F9-H3: Normaliza datos antes de guardar.
     */
    public function update(User $user, array $data): User
    {
        $updateData = [
            'first_name' => $data['first_name'] ?? $user->first_name,
            'last_name' => $data['last_name'] ?? $user->last_name,
            'email' => isset($data['email']) ? strtolower(trim($data['email'])) : $user->email,
            'identification_number' => isset($data['identification_number'])
                ? preg_replace('/\D/', '', $data['identification_number'])
                : $user->identification_number,
            'phone' => $data['phone'] ?? $user->phone,
            'role_id' => $data['role_id'] ?? $user->role_id,
            'section_id' => $data['section_id'] ?? $user->section_id,
            'activo' => $data['activo'] ?? $user->activo,
        ];

        // Si se proporciona nueva contraseña
        if (isset($data['password']) && $data['password']) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        Log::info('Usuario actualizado', ['user_id' => $user->id]);

        return $user->load(['role', 'section']);
    }

    /**
     * Desactivar usuario
     */
    public function deactivate(User $user): void
    {
        $user->update(['activo' => false]);

        Log::info('Usuario desactivado', ['user_id' => $user->id]);
    }

    /**
     * Asignar sección a estudiante
     * Verifica cupo disponible antes de asignar.
     *
     * @throws \Exception si la sección está llena
     */
    public function assignSection(User $user, int $sectionId, bool $force = false): User
    {
        if (!$user->isStudent()) {
            throw new \Exception('Solo los estudiantes pueden tener secciones asignadas');
        }

        // Cargar sección con info de capacidad
        $section = \App\Models\Section::find($sectionId);
        if (!$section) {
            throw new \Exception('La sección seleccionada no existe');
        }

        // Verificar cupo disponible (skip si force=true)
        if (!$force && !$section->hasAvailableSpace()) {
            $enrolled = $section->getEnrolledCount();
            throw new \Exception(
                "La sección '{$section->name}' ha alcanzado su capacidad máxima ({$enrolled}/{$section->max_capacity} estudiantes)"
            );
        }

        // Verificar si ya tiene una sección asignada
        if ($user->section_id && $user->section_id != $sectionId && !$force) {
            $user->load(['section', 'section.grade']);
            throw new \Exception('El estudiante ya está matriculado en una sección.');
        }

        $user->update(['section_id' => $sectionId]);

        \Illuminate\Support\Facades\Log::info('Sección asignada', ['user_id' => $user->id, 'section_id' => $sectionId]);

        return $user->load(['role', 'section']);
    }

    /**
     * Actualizar perfil del usuario autenticado
     */
    public function updateProfile(User $user, array $data): User
    {
        $updateData = [
            'first_name' => $data['first_name'] ?? $user->first_name,
            'last_name' => $data['last_name'] ?? $user->last_name,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['phone'] ?? $user->phone,
        ];

        // Cambiar contraseña si se proporciona
        if (isset($data['new_password']) && $data['new_password']) {
            $updateData['password'] = Hash::make($data['new_password']);
        }

        $user->update($updateData);

        Log::info('Perfil actualizado', ['user_id' => $user->id]);

        return $user->load(['role', 'section']);
    }
}
