<?php

namespace App\Services;

use App\Models\User;
use App\Models\Section;
use App\Models\SchoolYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EnrollmentService
{
    /**
     * Matricular estudiante en una sección de forma transaccional.
     *
     * Soporta dos modos:
     * 1. Estudiante nuevo: crea usuario + asigna sección en transacción
     * 2. Estudiante existente: solo asigna sección (re-matrícula)
     *
     * Valida:
     * - Cupo disponible en la sección
     * - No duplicar cédula (estudiante nuevo)
     * - No duplicar email (estudiante nuevo)
     * - Rol de estudiante
     *
     * @param array $studentData Datos del estudiante (para nuevo) o null (existente)
     * @param int $sectionId ID de la sección
     * @param User|null $existingUser Usuario existente (para re-matrícula)
     * @return User
     * @throws \Exception
     */
    public function enroll(?array $studentData, int $sectionId, ?User $existingUser = null): User
    {
        // Validar sección
        $section = Section::with(['grade', 'schoolYear'])->find($sectionId);
        if (!$section) {
            throw new \Exception('La sección seleccionada no existe');
        }

        // Validar cupo
        if (!$section->hasAvailableSpace()) {
            $enrolled = $section->getEnrolledCount();
            throw new \Exception(
                "La sección '{$section->grade?->name} — {$section->name}' ha alcanzado su capacidad máxima ({$enrolled}/{$section->max_capacity} estudiantes)"
            );
        }

        $isNewStudent = $existingUser === null;

        return DB::transaction(function () use ($studentData, $sectionId, $existingUser, $section, $isNewStudent) {
            if ($isNewStudent) {
                // ─── MODO: Estudiante nuevo ───
                // Validar datos requeridos
                if (empty($studentData['first_name'])) {
                    throw new \Exception('El nombre es requerido');
                }
                if (empty($studentData['email'])) {
                    throw new \Exception('El correo electrónico es requerido');
                }
                if (empty($studentData['password'])) {
                    throw new \Exception('La contraseña es requerida');
                }

                // Validar cédula duplicada (si se proporciona)
                if (!empty($studentData['identification_number'])) {
                    $normalizedId = preg_replace('/\D/', '', $studentData['identification_number']);
                    $existing = User::where('identification_number', $normalizedId)->first();
                    if ($existing) {
                        throw new \Exception(
                            "Ya existe un usuario con la cédula {$normalizedId}: {$existing->full_name}"
                        );
                    }
                }

                // Validar email duplicado
                $email = strtolower(trim($studentData['email']));
                $existingEmail = User::where('email', $email)->first();
                if ($existingEmail) {
                    throw new \Exception(
                        "Ya existe un usuario con el correo {$email}: {$existingEmail->full_name}"
                    );
                }

                // Obtener rol de estudiante
                $studentRole = \App\Models\Role::where('name', 'estudiante')->first();
                if (!$studentRole) {
                    throw new \Exception('No se encontró el rol de estudiante en el sistema');
                }

                // Crear usuario + asignar sección en transacción
                $user = User::create([
                    'first_name' => $studentData['first_name'],
                    'last_name' => $studentData['last_name'] ?? null,
                    'email' => $email,
                    'password' => Hash::make($studentData['password']),
                    'identification_number' => !empty($studentData['identification_number'])
                        ? preg_replace('/\D/', '', $studentData['identification_number'])
                        : null,
                    'phone' => $studentData['phone'] ?? null,
                    'role_id' => $studentRole->id,
                    'section_id' => $sectionId,
                    'activo' => true,
                ]);

                Log::info('Estudiante matriculado (nuevo)', [
                    'user_id' => $user->id,
                    'section_id' => $sectionId,
                    'section' => $section->full_name,
                ]);

                return $user->load(['role', 'section.grade', 'section.schoolYear']);
            } else {
                // ─── MODO: Estudiante existente (re-matrícula) ───
                if (!$existingUser->isStudent()) {
                    throw new \Exception('El usuario seleccionado no es un estudiante');
                }

                // Validar que el estudiante tenga sección actual
                if (!$existingUser->section_id) {
                    // Estudiante sin sección: permitir asignar (primera matrícula)
                    $oldSection = null;
                    $existingUser->update(['section_id' => $sectionId]);

                    Log::info('Estudiante sin sección — primera matrícula', [
                        'user_id' => $existingUser->id,
                        'new_section_id' => $sectionId,
                        'new_section' => $section->full_name,
                    ]);

                    return $existingUser->load(['role', 'section.grade', 'section.schoolYear']);
                }

                $oldSection = Section::with(['grade', 'schoolYear'])->find($existingUser->section_id);

                // Verificar si ya está en esta sección
                if ($existingUser->section_id == $sectionId) {
                    throw new \Exception(
                        "El estudiante ya está matriculado en esta sección: {$section->full_name}"
                    );
                }

                // ─── POLÍTICA DE RE-MATRÍCULA ───
                // La re-matrícula de estudiante existente SOLO permite cambio de paralelo
                // dentro del MISMO grado y MISMO año lectivo.
                // Para cambios de grado o año lectivo, el flujo correcto es PROMOCIONES.
                // Esto evita que Matrícula se use como puerta trasera para promover/retroceder.

                if ($oldSection && $oldSection->grade_id !== $section->grade_id) {
                    throw new \Exception(
                        "No se puede cambiar de grado por matrícula. "
                        . "El estudiante está en '{$oldSection->grade?->name}' y la sección destino es '{$section->grade?->name}'. "
                        . "Para cambiar de grado, use el módulo de Promociones."
                    );
                }

                if ($oldSection && $oldSection->school_year_id !== $section->school_year_id) {
                    throw new \Exception(
                        "No se puede cambiar de año lectivo por matrícula. "
                        . "El estudiante está en '{$oldSection->schoolYear?->name}' y la sección destino es '{$section->schoolYear?->name}'. "
                        . "Para cambiar de año lectivo, use el módulo de Promociones."
                    );
                }

                $existingUser->update(['section_id' => $sectionId]);

                Log::info('Estudiante re-matriculado (cambio de paralelo)', [
                    'user_id' => $existingUser->id,
                    'old_section_id' => $oldSection?->id,
                    'old_section' => $oldSection?->full_name,
                    'new_section_id' => $sectionId,
                    'new_section' => $section->full_name,
                    'grade' => $section->grade?->name,
                    'school_year' => $section->schoolYear?->name,
                ]);

                return $existingUser->load(['role', 'section.grade', 'section.schoolYear']);
            }
        });
    }

    /**
     * Obtener secciones con cupo disponible para el wizard.
     * Retorna secciones ordenadas por grado y nombre.
     */
    public function getAvailableSections(?int $schoolYearId = null, ?int $gradeId = null): array
    {
        $query = Section::with(['grade', 'schoolYear']);

        if ($schoolYearId) {
            $query->where('school_year_id', $schoolYearId);
        }

        if ($gradeId) {
            $query->where('grade_id', $gradeId);
        }

        $sections = $query->orderBy('grade_id')->orderBy('name')->get();

        $result = [];
        foreach ($sections as $section) {
            $enrolled = $section->getEnrolledCount();
            $maxCap = $section->max_capacity;
            $isFull = $maxCap !== null && $enrolled >= $maxCap;
            $available = $maxCap === null ? null : max(0, $maxCap - $enrolled);

            $result[] = [
                'id' => $section->id,
                'name' => $section->name,
                'grade_id' => $section->grade_id,
                'grade_name' => $section->grade?->name,
                'grade_order' => $section->grade?->grade_order,
                'school_year_id' => $section->school_year_id,
                'school_year_name' => $section->schoolYear?->name,
                'max_capacity' => $maxCap,
                'enrolled' => $enrolled,
                'available' => $available,
                'is_full' => $isFull,
                'label' => "{$section->grade?->name} — {$section->name}" .
                    ($section->schoolYear?->name ? " ({$section->schoolYear->name})" : ''),
            ];
        }

        return $result;
    }

    /**
     * Verificar si un estudiante ya tiene matrícula activa en un año lectivo.
     * (Un estudiante tiene matrícula activa si tiene section_id y su sección
     * pertenece al año lectivo indicado)
     */
    public function checkExistingEnrollment(int $studentId, int $schoolYearId): ?array
    {
        $user = User::with(['section.grade', 'section.schoolYear'])->find($studentId);

        if (!$user || !$user->section_id) {
            return null;
        }

        if ($user->section->school_year_id == $schoolYearId) {
            return [
                'user_id' => $user->id,
                'name' => $user->full_name,
                'section' => $user->section->full_name,
                'grade' => $user->section->grade?->name,
                'school_year' => $user->section->schoolYear?->name,
            ];
        }

        return null;
    }
}
